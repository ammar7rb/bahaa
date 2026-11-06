<?php

namespace App\Http\Middleware;

use App\Models\Product;
use App\Models\VendorProductWorkspace;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Translate private product references only at the authenticated vendor boundary. */
class VendorProductWorkspaceBoundary
{
    public function handle(Request $request, Closure $next, int $sellerId): mixed
    {
        if (!config('vendor_product_workspace.enabled')) return $next($request);
        $request->attributes->set('vendor_workspace_seller_id', $sellerId);
        try {
            $states = VendorProductWorkspace::where('seller_id', $sellerId)->get();
            $out = $states->pluck('private_reference', 'product_id')->all();
            $in = array_flip($out);
            $action = $request->route()?->getAction('controller') ?? $request->route()?->getAction('uses') ?? '';
            $action = is_string($action) ? $action : '';
            $productController = str_contains($action, '\ProductController@');
            $method = substr($action, strrpos($action, '@') + 1);
            // Presentation must never alter the authoritative prices or stock used by order/POS calculations.
            $financialContext = str_contains($action, '\POS')
                || (!$request->isMethod('GET') && str_contains($action, 'Order'));
            $request->attributes->set('vendor_workspace_financial_context', $financialContext);
            if ($financialContext && $request->filled('cart')) {
                $cart = is_string($request->input('cart')) ? json_decode($request->input('cart'), true) : $request->input('cart');
                if (is_array($cart)) {
                    foreach ($cart as &$line) if (isset($line['id'])) $line['id'] = $this->resolve($line['id'], $in);
                    unset($line);
                    $request->merge(['cart' => is_string($request->input('cart')) ? json_encode($cart) : $cart]);
                }
            }
            if (str_contains($action, '\POS\CartController@') && $request->filled('id')) $request->merge(['id' => $this->resolve($request->input('id'), $in)]);
            // Galleries and raw barcodes disclose the public catalog. Export only vendor fields.
            if ($productController && in_array($method, ['getBarcodeView', 'barcode_generate', 'getProductGalleryView'], true)) {
                abort(410, 'هذا الإجراء غير متاح؛ يمكنك إدارة إعلانك من قائمة المنتجات.');
            }
            if (($productController && in_array($method, ['exportList', 'exportRestockList'], true)) || in_array($method, ['allProductExportExcel', 'productStockExport'], true)) {
                return response()->streamDownload(function () use ($states) {
                    $stream = fopen('php://output', 'w'); fwrite($stream, "\xEF\xBB\xBF");
                    fputcsv($stream, ['مرجع الإعلان', 'اسم المنتج', 'سعر التاجر', 'حالة الإعلان']);
                    foreach ($states as $state) {
                        $name = (string) ($state->seller_content['name'] ?? '');
                        if (preg_match('/^[=+@\-]/', $name)) $name = "'".$name;
                        fputcsv($stream, [$state->private_reference, $name, $state->seller_content['unit_price'] ?? '', app(\App\Services\VendorProductWorkspaceService::class)->vendorActive($state) ? 'نشط' : 'غير نشط']);
                    }
                    fclose($stream);
                }, 'vendor-products.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
            }
            foreach (['product_id', 'productId'] as $key) {
                if ($request->filled($key)) $request->merge([$key => $this->resolve($request->input($key), $in)]);
            }
            if ($productController) {
                foreach (in_array($method, ['deleteRestockRequest', 'deleteRestock'], true) ? ['product_id'] : ['id', 'product', 'product_id'] as $key) {
                    $value = $request->route()?->parameter($key);
                    if ($value !== null) $request->route()->setParameter($key, $this->resolve($value, $in));
                }
                if (!in_array($method, ['deleteRestockRequest', 'deleteRestock'], true) && $request->filled('id')) $request->merge(['id' => $this->resolve($request->input('id'), $in)]);
            }
            $reservations = app(\App\Services\VendorSlotReservationService::class);
            $create = $productController && in_array($method, ['add', 'add_new'], true) && !$request->isMethod('GET');
            if ($reservations->enabled() && $productController && in_array($method, ['getBulkImportView', 'importBulkProduct'], true)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['advertising_slot' => 'أضف كل منتج من مساحة متاحة؛ الاستيراد الجماعي لا يخصص مساحات.']);
            }
            if ($reservations->enabled() && $productController && in_array($method, ['updateStatus', 'status_update'], true) && (int) $request->input('status') === 1
                && !VendorProductWorkspace::where('seller_id', $sellerId)->where('product_id', $request->input('id') ?? $request->input('product_id'))->where('legacy_exempt', true)->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['advertising_slot' => 'اختر الإعلان من صفحة المساحات لتفعيله.']);
            }
            if ($reservations->enabled() && $create && config('vendor_advertising.extra_publish_enabled') && $request->boolean('extra_ad_publish')) {
                $data = $request->validate(['extra_ad_subscription_id' => 'required|integer|min:1', 'extra_ad_request_key' => 'required|uuid', 'extra_ad_expected_price' => 'required|numeric|gt:0']);
                try {
                    $response = \Illuminate\Support\Facades\DB::transaction(function () use ($sellerId, $request, $next, $reservations, $data) {
                        $seller = \App\Models\Seller::whereKey($sellerId)->lockForUpdate()->firstOrFail();
                        try {
                            $purchase = app(\App\Services\VendorExtraAdService::class)->purchase($seller, [
                                'subscription_id' => (int) $data['extra_ad_subscription_id'], 'quantity' => 1,
                                'request_key' => $data['extra_ad_request_key'], 'expected_unit_price' => number_format((float) $data['extra_ad_expected_price'], 2, '.', ''),
                                'payment_method' => 'operating_balance',
                            ]);
                        } catch (\DomainException $error) {
                            throw \Illuminate\Validation\ValidationException::withMessages(['extra_ad_payment' => $error->getMessage()]);
                        }
                        $slot = \App\Models\VendorAdvertisingSlot::where('seller_id', $sellerId)->where('extra_ad_purchase_id', $purchase->id)->lockForUpdate()->firstOrFail();
                        $token = $data['extra_ad_request_key'];
                        if ($reservations->receipt($sellerId, $slot->id, $token)) {
                            return $request->is('api/*') || $request->expectsJson()
                                ? response()->json(['message' => translate('product_added_successfully'), 'already_saved' => true, 'saved' => true, 'redirect_url' => route('vendor.products.list', ['type' => 'all'])])
                                : redirect()->route('vendor.products.list', ['type' => 'all']);
                        }
                        $reservations->reserve($seller, $slot->id, $token);
                        $request->merge(['slot_id' => $slot->id, 'slot_reservation_token' => $token]);
                        $result = $next($request);
                        if (!$reservations->receipt($sellerId, $slot->id, $token)) throw new \App\Exceptions\VendorPublicationRollback($result);
                        return $result;
                    });
                } catch (\App\Exceptions\VendorPublicationRollback $rollback) {
                    $response = $rollback->response;
                }
            } elseif ($reservations->enabled() && $create) {
                $request->validate(['slot_id' => 'required|integer|min:1', 'slot_reservation_token' => 'required|uuid']);
                $response = \Illuminate\Support\Facades\DB::transaction(function () use ($sellerId, $request, $next, $reservations) {
                    \App\Models\Seller::whereKey($sellerId)->lockForUpdate()->firstOrFail();
                    if ($reservations->receipt($sellerId, $request->integer('slot_id'), $request->string('slot_reservation_token')->toString())) {
                        return $request->is('api/*') || $request->expectsJson()
                            ? response()->json(['message' => translate('product_added_successfully'), 'already_saved' => true, 'saved' => true, 'redirect_url' => route('vendor.products.list', ['type' => 'all'])])
                            : redirect()->route('vendor.packages.slots');
                    }
                    $reservations->assertCreation($sellerId);
                    return $next($request);
                });
            } else {
                $response = $next($request);
            }
            // Include products created by this request, without leaking their public identity.
            $states = VendorProductWorkspace::where('seller_id', $sellerId)->get();
            $out = $states->pluck('private_reference', 'product_id')->all();
            if ($response instanceof JsonResponse) {
                $response->setData($this->sanitize($response->getData(true), $out, $productController));
            } elseif (str_contains($response->headers->get('Content-Type', ''), 'text/html')) {
                $response->setContent($this->sanitizeHtml($response->getContent(), $out));
            }
            if ($response->isRedirection()) {
                $location = $response->headers->get('Location', '');
                foreach ($out as $public => $private) $location = preg_replace('~(/vendor/products/(?:view|update)/)'.$public.'(?=\?|$)~', '${1}'.$private, $location);
                $response->headers->set('Location', $location);
            }
            $response->headers->set('Cache-Control', 'private, no-store');
            return $response;
        } catch (\Illuminate\Validation\ValidationException $exception) {
            if (isset($exception->errors()['extra_ad_payment'])) {
                $message = $exception->errors()['extra_ad_payment'][0];
                if ($request->expectsJson() || $request->is('api/*')) return response()->json([
                    'message' => $message, 'errors' => [['code' => 'extra_ad_payment', 'message' => $message]],
                    'action' => ['type' => 'wallet', 'url' => route('vendor.balance.payments')],
                ], 422);
                return back()->withErrors(['extra_ad_payment' => $message])->withInput();
            }
            if (isset($exception->errors()['advertising_slot']) || ($create ?? false) && (isset($exception->errors()['slot_id']) || isset($exception->errors()['slot_reservation_token']))) {
                $message = $exception->errors()['advertising_slot'][0] ?? 'اختَر مساحة متاحة وافتح نموذج الإضافة منها.';
                if ($request->expectsJson() || $request->is('api/*')) return response()->json([
                    'message' => $message, 'errors' => [['code' => 'advertising_slot_required', 'message' => $message]],
                    'action' => ['type' => 'advertising_slots', 'url' => route('vendor.packages.slots')],
                ], 422);
                \Devrabiul\ToastMagic\Facades\ToastMagic::warning($message);
                return redirect()->route('vendor.packages.slots');
            }
            if (!isset($exception->errors()['subscription'])) throw $exception;
            $message = $exception->errors()['subscription'][0];
            if ($request->expectsJson() || $request->is('api/*')) return response()->json([
                'message' => $message, 'errors' => [['code' => 'active_vendor_package_required', 'message' => $message]],
                'action' => ['type' => 'subscriptions', 'url' => route('vendor.packages.index')],
            ], 422);
            \Devrabiul\ToastMagic\Facades\ToastMagic::warning($message);
            return redirect()->route('vendor.packages.index');
        } finally {
            $request->attributes->remove('vendor_workspace_seller_id');
            $request->attributes->remove('vendor_workspace_financial_context');
        }
    }

    private function resolve(mixed $reference, array $map): int
    {
        abort_unless(is_scalar($reference) && isset($map[(string) $reference]), 404);
        return (int) $map[(string) $reference];
    }

    public function sanitizeHtml(string $content, array $map): string
    {
        $content = preg_replace_callback('~<select\b[^>]*name=["\']product_id["\'][^>]*>.*?</select>~s', function ($select) use ($map) {
            return preg_replace_callback('~(<option\b[^>]*value=["\'])(\d+)(["\'])~', fn ($option) => $option[1].($map[$option[2]] ?? '').$option[3], $select[0]);
        }, $content);
        foreach ($map as $public => $private) {
            $content = preg_replace('~(product-status|product-)'.$public.'(?=["\'\s-])~', '${1}'.$private, $content);
            $content = preg_replace('~(/vendor/products/(?:view|update|delete|barcode|update-product-images)/)'.$public.'(?=[?"\'\s/<])~', '${1}'.$private, $content);
            $content = preg_replace('~(name=["\'](?:product_id|id)["\'][^>]*value=["\'])'.$public.'(["\'])~', '${1}'.$private.'$2', $content);
            $content = preg_replace('~((?:product_id|productId|data-id|data-product)["\']?\s*[:=]\s*["\']?)'.$public.'(?=["\'\s,;}])~', '${1}'.$private, $content);
            $content = preg_replace('~(/vendor/products/(?:delete-image|get-variations)[^"\'<>]*[?&](?:amp;)?id=)'.$public.'(?=[&"\'\s<])~', '${1}'.$private, $content);
        }
        return preg_replace('~href=["\'][^"\']*/product/[^"\']*["\']~', 'href="#" aria-disabled="true"', $content);
    }

    public function sanitize(mixed $data, array $map, bool $productContext = false): mixed
    {
        if (!is_array($data)) return $data;
        $isProduct = isset($data['id']) && (isset($data['product_type']) || isset($data['unit_price']) || ($productContext && (isset($data['name']) || count($data) <= 3)));
        if ($isProduct) $data = app(\App\Services\VendorProductWorkspaceService::class)->privatePayload((int) $data['id'], $data);
        foreach ($data as $key => $value) {
            if (in_array($key, ['review_state', 'review_status', 'seller_review_status', 'seller_review_reason', 'seller_review_changes', 'seller_reviewed_at', 'seller_reviewed_by', 'denied_note', 'editorial_content', 'approved_seller_content', 'digitalVariation', 'digital_variation', 'tags'], true)) {
                unset($data[$key]);
                continue;
            }
            if (($key === 'id' && $isProduct) || in_array($key, ['product_id', 'productId'], true)) {
                $data[$key] = $map[$value] ?? null;
            } elseif ($key === 'product_details' && is_string($value)) {
                $decoded = json_decode($value, true);
                $data[$key] = is_array($decoded) ? json_encode($this->sanitize($decoded, $map, true), JSON_UNESCAPED_UNICODE) : null;
            } elseif (in_array($key, ['slug', 'product_url', 'share_url', 'public_url', 'seo_info', 'seoInfo', 'translations', 'meta_title', 'meta_description', 'meta_image', 'meta_image_full_url'], true) && ($isProduct || $productContext)) {
                unset($data[$key]);
            } elseif (in_array($key, ['html', 'htmlView', 'view', 'html_view', 'rendered_view'], true) && is_string($value)) {
                $data[$key] = $this->sanitizeHtml($value, $map);
            } elseif (is_array($value)) {
                $data[$key] = $this->sanitize($value, $map, in_array($key, ['products', 'product', 'product_details'], true) || (is_int($key) && $productContext));
            }
        }
        return $data;
    }
}

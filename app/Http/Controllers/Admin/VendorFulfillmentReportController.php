<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\VendorFulfillmentReportService;
use App\Services\VendorFulfillmentDecisionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VendorFulfillmentReportController extends Controller
{
    private function filters(Request $request): array
    {
        $filters = $request->validate(['seller_id' => 'nullable|integer|min:1', 'order_id' => 'nullable|integer|min:1',
            'status' => ['nullable', Rule::in(array_keys(VendorFulfillmentDecisionService::LABELS))],
            'portion' => 'nullable|in:advertising,outside_slot', 'restriction' => 'nullable|in:restricted,partial',
            'from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d',
            'page' => 'nullable|integer|min:1', 'dataset' => 'nullable|in:requests,charges,deposits']);
        if (! empty($filters['from']) && ! empty($filters['to'])) {
            validator($filters, ['to' => 'after_or_equal:from'])->validate();
        }

        return $filters;
    }

    public function index(Request $request, VendorFulfillmentReportService $reports)
    {
        return response()->view('admin-views.order.fulfillment-report', $reports->workspace($this->filters($request)))
            ->header('Cache-Control', 'private, no-store');
    }

    public static function csvCell(mixed $value): string
    {
        $text = (string) ($value ?? '');

        return preg_match('/^[\s\x{FEFF}]*[=+\-@]/u', $text) ? "'".$text : $text;
    }

    public function export(Request $request, VendorFulfillmentReportService $reports)
    {
        $filters = $this->filters($request);
        $dataset = $filters['dataset'] ?? 'requests';

        return response()->streamDownload(function () use ($reports, $filters, $dataset) {
            $stream = fopen('php://output', 'wb');
            fwrite($stream, "\xEF\xBB\xBF");
            foreach ($reports->exportRows($dataset, $filters) as $row) {
                fputcsv($stream, array_map([self::class, 'csvCell'], $row), ',', '"', '');
            }
            fclose($stream);
        }, 'vendor-fulfillment-'.$dataset.'-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}

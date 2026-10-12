<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{User, Seller, Product, Order};
use App\Packages\AdvanceSearch\AdvanceSearch;
use App\Services\{AdminWorkspaceService, EgyptPhoneService, AdministrativeReferenceService};
use App\Utils\Helpers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminWorkspaceController extends Controller
{
    public function snapshot(AdminWorkspaceService $workspace)
    {
        return response()->json($workspace->snapshot())->header('Cache-Control', 'private, no-store');
    }

    public function reviews(string $kind, AdminWorkspaceService $workspace)
    {
        abort_unless(in_array($kind, ['orders', 'payments'], true), 404);
        if ($kind === 'orders') {
            abort_unless(Helpers::module_permission_check('orders'), 403);
            $records = $workspace->pendingOrders()->latest('id')->paginate(25);
            $records->through(fn ($o) => ['title' => translate('Order_id').' #'.$o->id, 'detail' => translate($o->order_status), 'url' => route('admin.orders.details', $o->id)]);
        } else {
            abort_unless(Helpers::module_permission_check('orders') || Helpers::module_permission_check('people') || Helpers::module_permission_check('reports'), 403);
            $query = $workspace->pendingPaymentRows();
            abort_unless($query, 404);
            $records = $query->orderByDesc('updated_at')->paginate(25)->through(fn ($r) => $workspace->paymentItem($r));
        }
        $title = translate($kind === 'orders' ? 'pending_Orders' : 'payments_waiting_for_review');
        return view('admin-views.system.workspace-reviews', compact('records', 'title'));
    }

    public function search(Request $request, AdminWorkspaceService $workspace, EgyptPhoneService $phones)
    {
        $data = $request->validate(['q' => 'nullable|string|max:100']);
        $keyword = trim($data['q'] ?? '');
        if (mb_strlen($keyword) < 2) return response()->json(['groups' => []]);
        $keyword = strtr($keyword, array_combine(preg_split('//u', '٠١٢٣٤٥٦٧٨٩', -1, PREG_SPLIT_NO_EMPTY), str_split('0123456789')));
        $like = '%'.addcslashes($keyword, '%_\\').'%';
        $groups = [];
        $accountReference = AdministrativeReferenceService::parse($keyword);
        $depositReference = AdministrativeReferenceService::parseDeposit($keyword);
        $add = function ($type, $rows) use (&$groups) {
            if ($rows->isNotEmpty()) $groups[] = ['label' => translate($type), 'items' => $rows->values()];
        };
        if (Helpers::module_permission_check('people')) {
            foreach (['customers' => User::class, 'vendors' => Seller::class] as $type => $model) {
                $q = $model::query()->where(function ($q) use ($like, $keyword, $phones, $accountReference, $type) {
                    $q->where('f_name', 'like', $like)->orWhere('l_name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like);
                    $fullName = DB::connection()->getDriverName() === 'sqlite' ? "COALESCE(f_name, '') || ' ' || COALESCE(l_name, '')" : "CONCAT(COALESCE(f_name, ''), ' ', COALESCE(l_name, ''))";
                    $q->orWhereRaw($fullName.' LIKE ?', [$like]);
                    if ($accountReference && $accountReference['type'] === ($type === 'customers' ? 'users' : 'sellers')) $q->orWhere('id', $accountReference['id']);
                    if ($phones->isValid($keyword)) {
                        $canonical = $phones->normalize($keyword);
                        $q->orWhereIn('phone', [$canonical, substr($canonical, 1), '0'.substr($canonical, 3), '00'.substr($canonical, 1)]);
                        // Existing imports may retain separators; compare their canonical digits too.
                        $q->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', ''), '+', '') IN (?, ?, ?)", [substr($canonical, 1), '0'.substr($canonical, 3), '00'.substr($canonical, 1)]);
                    }
                });
                if ($type === 'customers') $q->where(fn ($q) => $q->whereNull('email')->orWhere('email', '!=', 'walking@customer.com'));
                $add($type, $q->latest('id')->limit(6)->get(['id', 'f_name', 'l_name', 'email', 'phone'])->map(fn ($r) => ['title' => trim($r->f_name.' '.$r->l_name).' #'.$r->id, 'detail' => $r->phone.' · '.$r->email, 'url' => route($type === 'customers' ? 'admin.customer.view' : 'admin.vendors.view', $r->id)]));
            }
            $conversations = $workspace->conversations()->where(function ($q) use ($like) {
                $q->where('sender', 'like', $like)->orWhere('subject', 'like', $like)->orWhere('preview', 'like', $like)
                    ->orWhereExists(fn ($m) => $m->selectRaw('1')->from('contact_conversation_messages')->whereColumn('contact_id', 'conversations.id')->where('conversations.kind', 'contact')->where('body', 'like', $like))
                    ->orWhereExists(fn ($m) => $m->selectRaw('1')->from('vendor_support_messages')->whereColumn('vendor_support_thread_id', 'conversations.id')->where('conversations.kind', 'vendor')->where('body', 'like', $like))
                    ->orWhereExists(fn ($m) => $m->selectRaw('1')->from('support_ticket_convs')->whereColumn('support_ticket_id', 'conversations.id')->where('conversations.kind', 'ticket')->where(fn ($m) => $m->where('customer_message', 'like', $like)->orWhere('admin_message', 'like', $like)))
                    ->orWhereExists(fn ($m) => $m->selectRaw('1')->from('chattings')->whereColumn('user_id', 'conversations.id')->where('admin_id', 0)->where('conversations.kind', 'chat')->where('message', 'like', $like));
            });
            $add('messages', $conversations->orderByDesc('updated_at')->limit(6)->get()->map(fn ($r) => ['title' => $r->subject ?: $r->sender, 'detail' => translate($r->audience).' · '.$r->sender, 'url' => $workspace->conversationUrl($r)]));
            if (app(\App\Services\WorkflowFeatureService::class)->activationCenterEnabled() && \Illuminate\Support\Facades\Schema::hasTable('account_activation_cases')) {
                $add('account_activation_center', \App\Models\AccountActivationCase::where('id', $keyword)->orWhere('subject_id', $keyword)->limit(4)->get()->map(fn ($r) => ['title' => translate('account_activation_center').' #'.$r->id, 'detail' => translate($r->subject_type).' #'.$r->subject_id.' · '.translate($r->status), 'url' => route('admin.activation-center.show', $r)]));
            }
        }
        if (Helpers::module_permission_check('catalog')) {
            $add('products', Product::withoutGlobalScope('translate')->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('code', 'like', $like)->orWhere('id', $keyword)->orWhereHas('translations', fn ($t) => $t->where('value', 'like', $like)))->latest('id')->limit(6)->get(['id', 'name', 'code', 'added_by'])->map(fn ($p) => ['title' => $p->name, 'detail' => '#'.$p->id.' · '.$p->code, 'url' => route('admin.products.view', ['addedBy' => $p->added_by === 'seller' ? 'vendor' : 'in-house', 'id' => $p->id])]));
        }
        if (Helpers::module_permission_check('orders')) {
            $add('orders', Order::where(fn ($q) => $q->where('id', $keyword)->orWhere('transaction_ref', 'like', $like))->latest('id')->limit(6)->get()->map(fn ($o) => ['title' => translate('order').' #'.$o->id, 'detail' => translate($o->order_status).' · '.translate($o->payment_status), 'url' => route('admin.orders.details', $o->id)]));
        }
        $payments = collect();
        foreach ($workspace->paymentSources() as [$table, $module, $amount]) {
            if (!Helpers::module_permission_check($module) || !\Illuminate\Support\Facades\Schema::hasTable($table)) continue;
            $q = DB::table($table)->where('id', $keyword);
            if ($depositReference && $depositReference['table'] === $table) {
                $q->orWhere(function ($q) use ($depositReference, $table) {
                    $q->where('id', $depositReference['id']);
                    if ($table === 'customer_balance_deposits' && $depositReference['wallet'] === 'insurance') $q->where('wallet_type', 'insurance');
                    else if ($table === 'customer_balance_deposits') $q->where(fn ($q) => $q->whereNull('wallet_type')->orWhere('wallet_type', '!=', 'insurance'));
                    else if ($depositReference['wallet'] === 'insurance') $q->where('metadata->wallet_target', 'insurance');
                    else $q->where(fn ($q) => $q->whereNull('metadata->wallet_target')->orWhere('metadata->wallet_target', '!=', 'insurance'));
                });
            }
            foreach (['transaction_id', 'payment_reference', 'transaction_reference', 'invoice_no'] as $column) {
                if (\Illuminate\Support\Facades\Schema::hasColumn($table, $column)) $q->orWhere($column, 'like', $like);
            }
            foreach ($q->latest('id')->limit(2)->get() as $row) $payments->push(['title' => $workspace->paymentLabel($table).' #'.$row->id, 'detail' => $row->transaction_reference ?? $row->payment_reference ?? $row->transaction_id ?? '', 'url' => $workspace->paymentUrl($table, $row)]);
        }
        $add('transactions', $payments->take(8));
        if (Helpers::module_permission_check('reports') && \Illuminate\Support\Facades\Schema::hasTable('order_transactions')) {
            $add('order_Transactions', DB::table('order_transactions')->where(fn ($q) => $q->where('id', $keyword)->orWhere('order_id', $keyword)->orWhere('transaction_id', 'like', $like))->latest('id')->limit(6)->get()->map(fn ($r) => ['title' => translate('transaction').' #'.$r->id, 'detail' => '#'.$r->order_id.' · '.$r->transaction_id, 'url' => route('admin.transaction.order-transaction-list', ['record_id' => $r->id, 'date_type' => 'all'])]));
        }
        // The menu provider filters module permissions before returning entries.
        $arabicMenus = require base_path('resources/lang/ar/messages.php');
        $entries = collect((new AdvanceSearch('admin', $keyword))->adminMenuWithRoutes());
        foreach ([
            ['workspace_communication_center', 'admin.workspace.communication', 'people', 'communication support messages مركز الاتصالات رسائل دعم', []],
            ['account_activation_center', 'admin.activation-center.index', 'people', 'activation accounts تفعيل حسابات', []],
            ['payments_waiting_for_review', 'admin.workspace.reviews', '', 'payment review مدفوعات مراجعة الدفع', ['kind'=>'payments']],
            ['pending_Orders', 'admin.workspace.reviews', 'orders', 'order review مراجعة الطلبات', ['kind'=>'orders']],
            ['insurance_center', 'admin.insurance-center.index', 'reports', 'insurance تأمين ضمان', []],
            ['vendor_support_conversations', 'admin.vendor-support.index', 'people', 'vendor support دعم التجار', []],
            ['pending_Orders', 'admin.orders.admin-gate.index', 'orders', 'order gate approval بوابة الطلبات اعتماد', []],
        ] as [$title, $route, $module, $keywords, $parameters]) {
            if ($title === 'account_activation_center' && !app(\App\Services\WorkflowFeatureService::class)->activationCenterEnabled()) continue;
            if ($module === '' && !Helpers::module_permission_check('people') && !Helpers::module_permission_check('orders') && !Helpers::module_permission_check('reports')) continue;
            $entries->push(['page_title'=>$title, 'module'=>$module, 'keywords'=>$keywords, 'uri'=>route($route, $parameters), 'method'=>'GET']);
        }
        $menus = $entries->filter(function ($m) use ($keyword, $arabicMenus) {
            return (($m['module'] ?? '') === '' || Helpers::module_permission_check($m['module'])) && ($m['method'] ?? 'GET') === 'GET'
                && str_contains(mb_strtolower(($m['keywords'] ?? '').' '.($m['page_title_value'] ?? '').' '.translate($m['page_title']).' '.($arabicMenus[$m['page_title']] ?? '')), mb_strtolower($keyword));
        })->unique('uri')->take(8)->map(fn ($m) => ['title' => translate($m['page_title']), 'detail' => translate('page'), 'url' => url($m['uri'])]);
        $add('pages', $menus);
        return response()->json(['groups' => $groups])->header('Cache-Control', 'private, no-store');
    }

    public function communication(Request $request, AdminWorkspaceService $workspace)
    {
        abort_unless(Helpers::module_permission_check('people'), 403);
        $request->validate(['q' => 'nullable|string|max:100']);
        $q = $workspace->conversations();
        $unread = (int) (clone $q)->sum('unread');
        if ($request->filled('q')) {
            $like = '%'.addcslashes($request->query('q'), '%_\\').'%';
            $q->where(fn ($q) => $q->where('subject', 'like', $like)->orWhere('sender', 'like', $like));
        }
        $conversations = $q->orderByDesc('updated_at')->paginate(25)->withQueryString();
        return view('admin-views.system.communication', compact('conversations', 'unread', 'workspace'));
    }

    public function conversation(string $kind, int $id, AdminWorkspaceService $workspace)
    {
        abort_unless(Helpers::module_permission_check('people'), 403);
        abort_unless($kind === 'chat', 404);
        // The legacy chat page selects its first customer; place the requested one first.
        return redirect()->route('admin.messages.index', ['type' => 'customer', 'customer_id' => $id]);
    }

    public function read(Request $request, AdminWorkspaceService $workspace)
    {
        abort_unless(Helpers::module_permission_check('people'), 403);
        $data = $request->validate(['kind' => 'required|in:contact,ticket', 'id' => 'required|integer|min:1']);
        $row = $workspace->conversations()->where('kind', $data['kind'])->where('id', $data['id'])->first();
        abort_unless($row, 404);
        DB::table($data['kind'] === 'contact' ? 'contacts' : 'support_tickets')->where('id', $data['id'])->update($data['kind'] === 'contact' ? ['seen' => 1] : ['admin_read_at' => now(), 'admin_read_message_id' => DB::table('support_ticket_convs')->where('support_ticket_id', $data['id'])->max('id') ?? 0]);
        return back();
    }
}

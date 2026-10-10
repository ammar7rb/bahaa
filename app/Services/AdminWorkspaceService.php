<?php

namespace App\Services;

use App\Models\{Order, Product, User, Seller, AccountActivationCase};
use App\Utils\Helpers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Read models shared by the header, dashboard and communication center. */
class AdminWorkspaceService
{
    public function totals(): array
    {
        return [
            'orders' => Helpers::module_permission_check('orders') ? Order::count() : null,
            'customers' => Helpers::module_permission_check('people') ? User::where(fn ($q) => $q->whereNull('email')->orWhere('email', '!=', 'walking@customer.com'))->count() : null,
            'vendors' => Helpers::module_permission_check('people') ? Seller::count() : null,
            'products' => Helpers::module_permission_check('catalog') ? Product::count() : null,
        ];
    }

    public function pendingOrders()
    {
        return Order::query()->where(function ($q) {
            if (Schema::hasColumn('orders', 'commerce_flow_status')) {
                $q->where('commerce_flow_status', 'pending_admin_review')
                    ->orWhere(fn ($legacy) => $legacy->whereNull('commerce_flow_status')->where('order_status', 'pending'));
            } else {
                $q->where('order_status', 'pending');
            }
        });
    }

    public function activation(): array
    {
        if (!app(WorkflowFeatureService::class)->activationCenterEnabled()) {
            return ['activation_enabled' => false, 'pending_activation_count' => 0, 'recent_activation_cases' => collect()];
        }
        $query = AccountActivationCase::with(['customer', 'seller'])->whereNotIn('status', [AccountActivationCase::STATUS_APPROVED, AccountActivationCase::STATUS_REJECTED]);
        return ['activation_enabled' => true, 'pending_activation_count' => $query->count(), 'recent_activation_cases' => $query->latest('updated_at')->limit(6)->get()];
    }

    /** Each specification is also used for transaction search, avoiding divergent status rules. */
    public function paymentSources(): array
    {
        return [
            ['orders', 'orders', 'order_amount', ['payment_method' => 'offline_payment', 'payment_status' => 'unpaid'], 'admin.orders.details'],
            ['seller_balance_deposits', 'people', 'amount', ['source' => 'offline', 'status' => 'pending'], 'admin.payment-reviews.index', ['audience' => 'vendor', 'kind' => 'balance_deposit']],
            ['seller_package_subscriptions', 'people', 'paid_package_price', ['payment_method' => 'offline_payment', 'status' => 'pending_review'], 'admin.payment-reviews.index', ['audience' => 'vendor', 'kind' => 'package']],
            ['seller_insurances', 'people', 'amount', ['payment_method' => 'offline_payment', 'status' => 'pending_review'], 'admin.payment-reviews.index', ['audience' => 'vendor', 'kind' => 'insurance']],
            ['seller_order_insurances', 'people', 'amount', ['payment_method' => 'offline_payment', 'status' => 'pending_review'], 'admin.payment-reviews.index', ['audience' => 'vendor', 'kind' => 'insurance']],
            ['customer_balance_deposits', 'people', 'amount', ['status' => 'pending'], 'admin.customer.wallet.deposits'],
            ['post_purchase_invoices', 'orders', 'total_amount', ['status' => 'awaiting_review'], 'admin.orders.details'],
            ['order_insurances', 'reports', 'amount', ['status' => 'pending_review'], 'admin.insurance-center.customers.show'],
        ];
    }

    public function payments(): array
    {
        $query = $this->pendingPaymentRows();
        if (!$query) return ['count' => 0, 'items' => []];
        return ['count' => (clone $query)->count(), 'items' => $query->orderByDesc('updated_at')->limit(6)->get()->map(fn ($row) => $this->paymentItem($row))->all()];
    }

    public function pendingPaymentRows(): ?\Illuminate\Database\Query\Builder
    {
        $union = null;
        foreach ($this->paymentSources() as [$table, $module, $amount, $filters, $route]) {
            if (!Helpers::module_permission_check($module) || !Schema::hasTable($table)) continue;
            $q = DB::table($table)->where(collect($filters)->mapWithKeys(fn ($value, $column) => [$table.'.'.$column => $value])->all());
            if ($table === 'orders') $q->where(fn ($q) => $q->whereNull('orders.activation_status')->orWhere('orders.activation_status', '!=', 'offline_payment_rejected'));
            $actorType = str_starts_with($table, 'seller_') ? 'vendor' : 'customer';
            $foreign = $actorType === 'vendor' ? 'seller_id' : 'customer_id';
            $q->selectRaw("'".$table."' as source, ".$table.".id, ".$table.".updated_at, ".($table === 'post_purchase_invoices' ? $table.'.order_id' : 'NULL')." as order_id, '".$actorType."' as audience");
            if (Schema::hasColumn($table, $foreign)) {
                $q->leftJoin(($actorType === 'vendor' ? 'sellers' : 'users').' as actor', 'actor.id', '=', $table.'.'.$foreign)
                    ->addSelect('actor.f_name as actor_first', 'actor.l_name as actor_last');
            } else {
                $q->selectRaw("'' as actor_first, '' as actor_last");
            }
            $union = $union ? $union->unionAll($q) : $q;
        }
        return $union ? DB::query()->fromSub($union, 'payment_reviews') : null;
    }

    public function paymentItem(object $row): array
    {
        return ['title' => $this->paymentLabel($row->source).' #'.$row->id, 'detail' => translate($row->audience).' · '.trim($row->actor_first.' '.$row->actor_last), 'url' => $this->paymentUrl($row->source, $row), 'time' => $row->updated_at];
    }

    public function paymentLabel(string $table): string
    {
        return translate(match ($table) {
            'orders' => 'purchase_payment',
            'seller_balance_deposits', 'customer_balance_deposits' => 'purchase_balance_deposit',
            'seller_package_subscriptions' => 'seller_package',
            'seller_insurances' => 'seller_insurance',
            'seller_order_insurances' => 'seller_order_insurance',
            'post_purchase_invoices' => 'post_purchase_invoice_payment',
            'order_insurances' => 'insurance_payment',
        });
    }

    public function paymentUrl(string $table, object $row): string
    {
        foreach ($this->paymentSources() as $source) {
            if ($source[0] !== $table) continue;
            $params = $source[5] ?? [];
            if (in_array($table, ['orders', 'order_insurances'])) return route($source[4], $row->id);
            if ($table === 'post_purchase_invoices') return route($source[4], $row->order_id);
            if ($table === 'customer_balance_deposits') return route($source[4], ['record_id' => $row->id]);
            $recordType = match ($table) {
                'seller_balance_deposits' => 'vendor_balance_deposit',
                'seller_package_subscriptions' => 'vendor_package',
                'seller_insurances' => 'vendor_insurance',
                'seller_order_insurances' => 'vendor_order_insurance',
            };
            return route($source[4], $params + ['record_type' => $recordType, 'record_id' => $row->id]);
        }
        abort(404);
    }

    /** Unified SQL projection: pagination and text search happen in the database. */
    public function conversations()
    {
        $concat = DB::getDriverName() === 'sqlite' ? "COALESCE(%s.f_name,'') || ' ' || COALESCE(%s.l_name,'')" : "CONCAT(COALESCE(%s.f_name,''),' ',COALESCE(%s.l_name,''))";
        $contacts = DB::table('contacts')->selectRaw("'contact' as kind, id, name as sender, COALESCE(sender_type, CASE WHEN subject LIKE '%تاجر%' OR EXISTS (SELECT 1 FROM sellers s WHERE s.email = contacts.email AND s.email <> '') THEN 'vendor' ELSE 'customer' END) as audience, subject, COALESCE((SELECT body FROM contact_conversation_messages m WHERE m.contact_id=contacts.id AND m.sender='requester' ORDER BY m.id DESC LIMIT 1),message) as preview, updated_at, CASE WHEN seen = 0 THEN 1 ELSE 0 END as unread");
        $tickets = DB::table('support_tickets as t')->leftJoin('users as u', 'u.id', '=', 't.customer_id')
            ->selectRaw("'ticket' as kind, t.id, ".sprintf($concat, 'u', 'u')." as sender, 'customer' as audience, t.subject, COALESCE((SELECT customer_message FROM support_ticket_convs m WHERE m.support_ticket_id=t.id AND m.customer_message IS NOT NULL ORDER BY m.id DESC LIMIT 1),t.description) as preview, t.updated_at, CASE WHEN t.admin_read_at IS NULL OR EXISTS (SELECT 1 FROM support_ticket_convs c WHERE c.support_ticket_id=t.id AND c.customer_message IS NOT NULL AND c.id > COALESCE(t.admin_read_message_id,0)) THEN 1 ELSE 0 END as unread")
            ->where(fn ($q) => $q->whereNull('t.purpose')->orWhere('t.purpose', '!=', 'account_activation'));
        $vendors = DB::table('vendor_support_threads as t')->leftJoin('sellers as s', 's.id', '=', 't.seller_id')
            ->selectRaw("'vendor' as kind, t.id, ".sprintf($concat, 's', 's')." as sender, 'vendor' as audience, t.subject, COALESCE((SELECT body FROM vendor_support_messages m WHERE m.vendor_support_thread_id=t.id ORDER BY m.id DESC LIMIT 1),'') as preview, t.updated_at, 0 as unread");
        $chats = DB::table('chattings as c')->leftJoin('users as u', 'u.id', '=', 'c.user_id')
            ->selectRaw("'chat' as kind, c.user_id as id, MAX(".sprintf($concat, 'u', 'u').") as sender, 'customer' as audience, '' as subject, COALESCE((SELECT message FROM chattings m WHERE m.user_id=c.user_id AND m.admin_id=0 ORDER BY m.id DESC LIMIT 1),'') as preview, MAX(c.created_at) as updated_at, SUM(CASE WHEN c.sent_by_customer = 1 AND c.seen_by_admin = 0 THEN 1 ELSE 0 END) as unread")
            ->where('c.admin_id', 0)->whereNotNull('c.user_id')->groupBy('c.user_id');
        return DB::query()->fromSub($contacts->unionAll($tickets)->unionAll($vendors)->unionAll($chats), 'conversations');
    }

    public function conversationUrl(object $row): string
    {
        return match ($row->kind) {
            'contact' => route('admin.contact.view', $row->id),
            'ticket' => route('admin.support-ticket.singleTicket', $row->id),
            'vendor' => route('admin.vendor-support.show', $row->id),
            'chat' => route('admin.workspace.conversation', ['kind' => 'chat', 'id' => $row->id]),
        };
    }

    public function snapshot(): array
    {
        $categories = [];
        if (Helpers::module_permission_check('orders')) {
            $q = $this->pendingOrders();
            $categories['orders'] = ['count' => (clone $q)->count(), 'items' => $q->latest('id')->limit(6)->get()->map(fn ($o) => ['title' => translate('order').' #'.$o->id, 'detail' => translate($o->order_status), 'url' => route('admin.orders.details', $o->id)])->all()];
        }
        if (Helpers::module_permission_check('catalog')) {
            $q = Product::withoutGlobalScope('translate')->where('added_by', 'seller')->where('request_status', 0);
            $categories['products'] = ['count' => (clone $q)->count(), 'items' => $q->setEagerLoads([])->latest('updated_at')->limit(6)->get(['id', 'name', 'user_id'])->map(fn ($p) => ['title' => $p->name, 'detail' => translate('vendor').' #'.$p->user_id.' · #'.$p->id, 'url' => route('admin.products.view', ['addedBy' => 'vendor', 'id' => $p->id])])->all()];
        }
        $categories['payments'] = $this->payments();
        if (Helpers::module_permission_check('people')) {
            $q = $this->conversations();
            $categories['messages'] = ['count' => (int) (clone $q)->sum('unread'), 'items' => $q->orderByDesc('unread')->orderByDesc('updated_at')->limit(6)->get()->map(fn ($r) => ['title' => $r->subject ?: translate('messages'), 'detail' => $r->sender.' · '.translate($r->audience).' · '.\Illuminate\Support\Str::limit(strip_tags($r->preview), 75), 'url' => $this->conversationUrl($r), 'unread' => $r->unread])->all()];
        }
        return ['totals' => $this->totals(), 'categories' => $categories];
    }
}

<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use App\Models\Seller;
use App\Models\User;
use App\Services\CustomerTaxRuleService;
use App\Services\SellerCommissionService;
use App\Utils\Helpers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BusinessRatesController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(Helpers::module_permission_check('tax_manage') || Helpers::module_permission_check('commission_manage'), 403);
        $request->validate(['q' => 'nullable|string|max:100', 'audience' => 'nullable|in:customer,vendor']);
        $search = trim((string) $request->query('q', ''));
        $customers = $search !== '' ? User::query()->where(function ($query) use ($search) {
            $query->where('f_name', 'like', "%{$search}%")->orWhere('l_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%");
        })->limit(20)->get(['id', 'f_name', 'l_name', 'email', 'phone']) : collect();
        $vendors = $search !== '' ? Seller::query()->where(function ($query) use ($search) {
            $query->where('f_name', 'like', "%{$search}%")->orWhere('l_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%");
        })->limit(20)->get(['id', 'f_name', 'l_name', 'email', 'phone', 'sales_commission_percentage']) : collect();
        $auditableKinds = array_values(array_filter([
            Helpers::module_permission_check('tax_manage') ? 'tax' : null,
            Helpers::module_permission_check('commission_manage') ? 'commission' : null,
        ]));
        $groups = DB::table('business_rate_groups')->whereIn('kind', $auditableKinds)->orderBy('kind')->orderBy('name')->get();
        $groupMembers = DB::table('business_rate_group_accounts')->whereIn('group_id', $groups->pluck('id'))
            ->get()->groupBy('group_id')->map(fn ($rows) => $rows->pluck('account_id')->all());
        return view('admin-views.business-settings.rates', [
            'customers' => $customers, 'vendors' => $vendors, 'groups' => $groups,
            'groupMembers' => $groupMembers, 'search' => $search,
            'auditRows' => DB::table('business_setting_audits')->whereIn('kind', $auditableKinds)->orderByDesc('id')->limit(30)->get(),
            'taxGlobal' => (bool) getWebConfig(name: 'customer_second_invoice_tax_enabled'),
            'commissionGlobal' => app(SellerCommissionService::class)->getSummary(null)['default_rate'],
            'taxRules' => $customers->mapWithKeys(fn ($customer) => [$customer->id => app(CustomerTaxRuleService::class)->effective($customer->id)]),
            'commissionRules' => $vendors->mapWithKeys(fn ($vendor) => [$vendor->id => app(SellerCommissionService::class)->getSummary($vendor)]),
        ]);
    }

    public function globalTax(Request $request)
    {
        abort_unless(Helpers::module_permission_check('tax_manage'), 403);
        $data = $request->validate(['enabled' => 'required|boolean']);
        DB::transaction(function () use ($data) {
            $old = (bool) getWebConfig(name: 'customer_second_invoice_tax_enabled');
            BusinessSetting::query()->updateOrCreate(['type' => 'customer_second_invoice_tax_enabled'], ['value' => $data['enabled']]);
            $this->audit('tax', null, null, ['enabled' => $old], $data);
        });
        return back()->with('success', 'حُفظت قاعدة ضريبة الطلبات الجديدة؛ الفواتير السابقة لم تتغير.');
    }

    public function customerTax(Request $request)
    {
        abort_unless(Helpers::module_permission_check('tax_manage'), 403);
        $data = $request->validate(['customer_id' => 'required|integer|exists:users,id', 'mode' => 'required|in:on,off,inherit']);
        DB::transaction(function () use ($data) {
            $id = (int) $data['customer_id'];
            $old = DB::table('customer_tax_overrides')->where('customer_id', $id)->first();
            if ($data['mode'] === 'inherit') DB::table('customer_tax_overrides')->where('customer_id', $id)->delete();
            else DB::table('customer_tax_overrides')->updateOrInsert(['customer_id' => $id], [
                'enabled' => $data['mode'] === 'on', 'updated_by_admin_id' => auth('admin')->id(),
                'created_at' => $old?->created_at ?? now(), 'updated_at' => now(),
            ]);
            $this->audit('tax', 'customer', $id, ['enabled' => $old?->enabled], ['mode' => $data['mode']]);
        });
        return back()->with('success', 'حُفظ استثناء العميل للطلبات الجديدة.');
    }

    public function globalCommission(Request $request)
    {
        abort_unless(Helpers::module_permission_check('commission_manage'), 403);
        $data = $request->validate(['rate' => 'required|numeric|between:0,100|decimal:0,3']);
        DB::transaction(function () use ($data) {
            $old = (float) getWebConfig(name: 'sales_commission');
            BusinessSetting::query()->updateOrCreate(['type' => 'sales_commission'], ['value' => $data['rate']]);
            $this->audit('commission', null, null, ['rate' => $old], $data);
        });
        return back()->with('success', 'حُفظت نسبة الشركة للطلبات الجديدة.');
    }

    public function vendorCommission(Request $request)
    {
        abort_unless(Helpers::module_permission_check('commission_manage'), 403);
        $data = $request->validate(['vendor_id' => 'required|integer|exists:sellers,id',
            'mode' => 'required|in:custom,inherit', 'rate' => 'required_if:mode,custom|nullable|numeric|between:0,100|decimal:0,3']);
        DB::transaction(function () use ($data) {
            $vendor = Seller::query()->lockForUpdate()->findOrFail($data['vendor_id']);
            app(SellerCommissionService::class)->updateOverride($vendor, $data['mode'] === 'custom', $data['mode'] === 'custom' ? (float) $data['rate'] : null);
        });
        return back()->with('success', 'حُفظ استثناء التاجر للطلبات الجديدة.');
    }

    public function group(Request $request)
    {
        $rawIds = trim((string) $request->input('account_ids_text', ''));
        abort_unless((bool) preg_match('/^\d+(\s*,\s*\d+)*$/D', $rawIds), 422);
        $request->merge(['account_ids' => array_map('intval', preg_split('/\s*,\s*/', $rawIds))]);
        $data = $request->validate(['kind' => 'required|in:tax,commission', 'name' => 'required|string|max:150',
            'group_id' => 'nullable|integer|exists:business_rate_groups,id',
            'account_ids' => 'required|array|min:1|max:500', 'account_ids.*' => 'required|integer|distinct',
            'rate' => 'required_if:kind,commission|nullable|numeric|between:0,100|decimal:0,3',
            'enabled' => 'required_if:kind,tax|nullable|boolean']);
        $permission = $data['kind'] === 'tax' ? 'tax_manage' : 'commission_manage';
        abort_unless(Helpers::module_permission_check($permission), 403);
        $table = $data['kind'] === 'tax' ? 'users' : 'sellers';
        $ids = array_map('intval', $data['account_ids']);
        abort_unless(DB::table($table)->whereIn('id', $ids)->count() === count($ids), 422);
        DB::transaction(function () use ($data, $ids) {
            $kind = $data['kind'];
            $group = isset($data['group_id']) ? DB::table('business_rate_groups')->where('id', $data['group_id'])->lockForUpdate()->first() : null;
            abort_if($group && $group->kind !== $kind, 422);
            $values = ['kind' => $kind, 'name' => $data['name'], 'rate' => $kind === 'commission' ? $data['rate'] : null,
                'enabled' => $kind === 'tax' ? $data['enabled'] : null,
                'updated_by_admin_id' => auth('admin')->id(), 'updated_at' => now()];
            if ($group) {
                $oldMembers = DB::table('business_rate_group_accounts')->where('group_id', $group->id)->pluck('account_id')->all();
                DB::table('business_rate_groups')->where('id', $group->id)->update($values);
                $id = $group->id;
                DB::table('business_rate_group_accounts')->where('group_id', $id)->delete();
            } else { $id = DB::table('business_rate_groups')->insertGetId($values + ['created_at' => now()]); $oldMembers = []; }
            DB::table('business_rate_group_accounts')->where('kind', $kind)->whereIn('account_id', $ids)->delete();
            DB::table('business_rate_group_accounts')->insert(array_map(fn ($accountId) => [
                'kind' => $kind, 'group_id' => $id, 'account_id' => $accountId, 'created_at' => now(), 'updated_at' => now(),
            ], $ids));
            $this->audit($kind, 'group', $id, $group ? (array) $group + ['account_ids' => $oldMembers] : null, $values + ['account_ids' => $ids]);
        });
        return back()->with('success', 'حُفظت المجموعة وأعضاؤها للطلبات الجديدة.');
    }

    private function audit(string $kind, ?string $subjectType, ?int $subjectId, ?array $before, array $after): void
    {
        DB::table('business_setting_audits')->insert(['kind' => $kind, 'subject_type' => $subjectType,
            'subject_id' => $subjectId, 'before' => json_encode($before, JSON_UNESCAPED_UNICODE),
            'after' => json_encode($after, JSON_UNESCAPED_UNICODE), 'admin_id' => auth('admin')->id(), 'created_at' => now()]);
    }
}

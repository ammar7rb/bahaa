@extends('layouts.admin.app')

@section('title', translate('Insurance_Rule_Center'))

@section('content')
<div class="content container-fluid">
    <div class="mb-3"><h1 class="mb-1">{{ translate('Insurance_Rule_Center') }}</h1><p class="text-muted mb-0">{{ translate('customer_and_seller_insurance_rules_are_independent') }}</p></div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="card mb-3">
        <div class="card-body p-2">
            <ul class="nav nav-pills gap-2" role="tablist">
                <li class="nav-item"><a class="nav-link {{ $activeTab === 'rules' ? 'active' : '' }}" href="{{ route('admin.insurance-center.index',['tab'=>'rules']) }}"><i class="fi fi-sr-settings-sliders me-1"></i>{{ translate('Insurance_Rules_And_Settings') }}</a></li>
                <li class="nav-item"><a class="nav-link {{ $activeTab === 'customers' ? 'active' : '' }}" href="{{ route('admin.insurance-center.index',['tab'=>'customers']) }}"><i class="fi fi-sr-user me-1"></i>{{ translate('Customer_Insurances') }} <span class="badge badge-light ms-1">{{ $insuranceStats['customer_total'] }}</span></a></li>
                <li class="nav-item"><a class="nav-link {{ $activeTab === 'sellers' ? 'active' : '' }}" href="{{ route('admin.insurance-center.index',['tab'=>'sellers']) }}"><i class="fi fi-sr-store-alt me-1"></i>{{ translate('Seller_Insurances') }} <span class="badge badge-light ms-1">{{ $insuranceStats['seller_total'] }}</span></a></li>
            </ul>
        </div>
    </div>

    @if($activeTab === 'rules')
    <div class="row g-3 mb-3">
        @foreach([
            ['Customer_Insurances',$insuranceStats['customer_total'],'primary'],
            ['Paid_Customer_Insurances',$insuranceStats['customer_paid'],'success'],
            ['Seller_Insurances',$insuranceStats['seller_total'],'info'],
            ['Paid_Seller_Insurances',$insuranceStats['seller_paid'],'success'],
        ] as [$label,$value,$color])
            <div class="col-md-3"><div class="card h-100 border-start border-{{ $color }} border-3"><div class="card-body"><small class="text-muted">{{ translate($label) }}</small><h2 class="mb-0">{{ $value }}</h2></div></div></div>
        @endforeach
    </div>

    <div class="alert alert-info mb-3">
        <h3 class="fs-16 mb-2">{{ translate('how_insurance_rule_center_works') }}</h3>
        <ul class="mb-0 ps-3">
            <li>{{ translate('customer_and_seller_insurance_are_calculated_by_separate_rule_sets') }}</li>
            <li>{{ translate('insurance_rule_type_help') }}</li>
            <li>{{ translate('insurance_account_age_help') }}</li>
            <li>{{ translate('insurance_priority_help') }}</li>
            <li>{{ translate('insurance_first_matching_rule_help') }}</li>
        </ul>
    </div>

    <div class="card mb-3"><div class="card-header"><h3 class="mb-1">{{ translate('Insurance_Master_Settings') }}</h3><p class="text-muted mb-0">{{ translate('insurance_master_settings_help') }}</p></div><div class="card-body">
        <form method="post" action="{{ route('admin.insurance-center.master') }}">@csrf
            <div class="row g-3 align-items-end">
                <div class="col-md-2"><label class="form-label">{{ translate('Customer_Insurance') }}</label><div><input type="checkbox" name="customer_enabled" value="1" @checked((int)getWebConfig(name:'customer_order_insurance_status')===1)> {{ translate('Active') }}</div></div>
                <div class="col-md-2"><label class="form-label">{{ translate('Insurance_Recovery_Days') }}</label><input class="form-control" type="number" name="customer_maturity_days" min="1" max="3650" value="{{ getWebConfig(name:'customer_order_insurance_maturity_days') ?? 90 }}" required></div>
                <div class="col-md-2"><label class="form-label">{{ translate('Customer_Insurance_Payment_Deadline_Hours') }}</label><input class="form-control" type="number" name="customer_payment_deadline_hours" min="1" max="720" value="{{ getWebConfig(name:'customer_order_insurance_payment_deadline_hours') ?? 72 }}" required><small class="text-muted">{{ translate('deadline_starts_after_purchase_payment') }}</small></div>
                <div class="col-md-2"><label class="form-label">{{ translate('Purchase_Refund_Delay_Days') }}</label><input class="form-control" type="number" name="customer_purchase_refund_delay_days" min="0" max="365" value="{{ getWebConfig(name:'customer_purchase_refund_delay_days') ?? 7 }}" required><small class="text-muted">{{ translate('refund_goes_to_purchase_wallet_only') }}</small></div>
                <div class="col-md-2"><label class="form-label">{{ translate('Seller_Order_Insurance') }}</label><div><input type="checkbox" name="seller_enabled" value="1" @checked((int)getWebConfig(name:'seller_order_insurance_status')===1)> {{ translate('Active') }}</div></div>
                <div class="col-md-2"><label class="form-label">{{ translate('Payment_Deadline_Days') }}</label><input class="form-control" type="number" name="seller_pending_days" min="1" max="90" value="{{ getWebConfig(name:'seller_order_insurance_pending_days') ?? 3 }}" required></div>
                <div class="col-md-2"><label class="form-label">{{ translate('Reusable_After_Days') }}</label><input class="form-control" type="number" name="seller_reuse_after_days" min="1" max="3650" value="{{ getWebConfig(name:'seller_order_insurance_reuse_after_days') ?? 90 }}" required></div>
                <div class="col-md-2"><button class="btn btn-primary w-100">{{ translate('save_changes') }}</button></div>
            </div>
        </form>
    </div></div>

    <div class="row g-3 mb-3">
        <div class="col-md-3"><div class="card h-100"><div class="card-body"><small class="text-muted">{{ translate('Welcome_Insurance_Credit') }}</small><h3>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $report['welcome_credit'])) }}</h3><span>{{ $report['welcome_redemptions'] }} {{ translate('operations') }}</span></div></div></div>
        <div class="col-md-3"><div class="card h-100"><div class="card-body"><small class="text-muted">{{ translate('Promotional_Insurance_Discounts') }}</small><h3>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $report['premium_discount'])) }}</h3><span>{{ $report['discount_redemptions'] }} {{ translate('operations') }}</span></div></div></div>
        <div class="col-md-6"><div class="alert alert-info h-100 mb-0">{{ translate('welcome_credit_and_premium_discount_are_separate_non_withdrawable_financial_movements') }}</div></div>
    </div>

    <div class="card mb-3"><div class="card-header"><h3 class="mb-1">{{ translate('Add_Insurance_Rule') }}</h3><p class="text-muted mb-0">{{ translate('add_insurance_rule_section_help') }}</p></div><div class="card-body">
        <form method="post" action="{{ route('admin.insurance-center.rules.store') }}">@csrf
            <div class="row g-3">
                <div class="col-md-2"><label class="form-label">{{ translate('Applies_To') }}</label><select class="form-select" name="subject_type"><option value="customer">{{ translate('Customer') }}</option><option value="seller">{{ translate('Seller') }}</option></select><small class="text-muted">{{ translate('customer_and_seller_rules_never_share_rates') }}</small></div>
                <div class="col-md-3"><label class="form-label">{{ translate('Name') }}</label><input class="form-control" name="name" required></div>
                <div class="col-md-2"><label class="form-label">{{ translate('Code') }}</label><input class="form-control" name="code"></div>
                <div class="col-md-2"><label class="form-label">{{ translate('Rule_Type') }}</label><select class="form-select" name="rule_type"><option value="tier">{{ translate('Amount_Tier') }}</option><option value="account_age">{{ translate('Account_Age') }}</option><option value="default">{{ translate('Default') }}</option></select><small class="text-muted">{{ translate('rule_type_is_for_admin_classification_and_conditions_control_matching') }}</small></div>
                <div class="col-md-1"><label class="form-label">{{ translate('Priority') }}</label><input class="form-control" type="number" name="priority" value="100" min="1" required><small class="text-muted">{{ translate('smaller_priority_number_runs_first') }}</small></div>
                <div class="col-md-2"><label class="form-label">{{ translate('Status') }}</label><div><input type="checkbox" name="is_active" value="1" checked> {{ translate('Active') }}</div></div>
                <div class="col-md-2"><label class="form-label">{{ translate('Minimum_Order_Amount') }}</label><input class="form-control" type="number" step="0.01" min="0" name="minimum_order_amount" value="0" required></div>
                <div class="col-md-2"><label class="form-label">{{ translate('Maximum_Order_Amount') }}</label><input class="form-control" type="number" step="0.01" min="0" name="maximum_order_amount"></div>
                <div class="col-md-2"><label class="form-label">{{ translate('Minimum_Account_Age_Days') }}</label><input class="form-control" type="number" min="0" name="minimum_account_age_days" value="0"><small class="text-muted">{{ translate('zero_means_from_account_creation') }}</small></div>
                <div class="col-md-2"><label class="form-label">{{ translate('Maximum_Account_Age_Days') }}</label><input class="form-control" type="number" min="0" name="maximum_account_age_days"><small class="text-muted">{{ translate('leave_blank_for_no_maximum_age') }}</small></div>
                <div class="col-md-2"><label class="form-label">{{ translate('Calculation_Type') }}</label><select class="form-select" name="calculation_type"><option value="percentage">{{ translate('percentage') }}</option><option value="fixed">{{ translate('fixed_amount') }}</option></select></div>
                <div class="col-md-2"><label class="form-label">{{ translate('Calculation_Value') }}</label><input class="form-control" type="number" step="0.01" min="0.01" name="calculation_value" required></div>
                <div class="col-md-2"><label class="form-label">{{ translate('Starts_At') }}</label><input class="form-control" type="datetime-local" name="starts_at"></div>
                <div class="col-md-2"><label class="form-label">{{ translate('Ends_At') }}</label><input class="form-control" type="datetime-local" name="ends_at"></div>
                <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100">{{ translate('Add') }}</button></div>
            </div>
        </form>
    </div></div>

    @foreach(['customer' => $customerRules, 'seller' => $sellerRules] as $subject => $rules)
        <div class="card mb-3"><div class="card-header"><h3 class="mb-0">{{ translate(ucfirst($subject).'_Insurance_Rules') }}</h3></div><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>{{ translate('Priority') }}</th><th>{{ translate('Name') }}</th><th>{{ translate('Rule_Type') }}</th><th>{{ translate('Order_Range') }}</th><th>{{ translate('Account_Age') }}</th><th>{{ translate('Calculation') }}</th><th>{{ translate('Status') }}</th><th>{{ translate('Action') }}</th></tr></thead><tbody>
        @forelse($rules as $rule)<tr><td>{{ $rule->priority }}</td><td>{{ $rule->name }}<div class="small text-muted">{{ $rule->code }}</div></td><td>{{ translate($rule->rule_type) }}</td><td>{{ usdToDefaultCurrency(amount:$rule->minimum_order_amount) }} – {{ $rule->maximum_order_amount === null ? '∞' : usdToDefaultCurrency(amount:$rule->maximum_order_amount) }}</td><td>{{ $rule->minimum_account_age_days }} – {{ $rule->maximum_account_age_days ?? '∞' }}</td><td>{{ $rule->calculation_type === 'percentage' ? $rule->calculation_value.'%' : setCurrencySymbol(amount:usdToDefaultCurrency(amount:$rule->calculation_value)) }}</td><td><span class="badge badge-soft-{{ $rule->is_active ? 'success':'danger' }}">{{ translate($rule->is_active ? 'Active':'Inactive') }}</span></td><td>@if($rule->rule_type !== 'default')<div class="d-flex gap-2"><form method="post" action="{{ route('admin.insurance-center.rules.toggle',$rule) }}">@csrf<button class="btn btn-outline-primary btn-sm">{{ translate($rule->is_active?'Deactivate':'Activate') }}</button></form><form method="post" action="{{ route('admin.insurance-center.rules.delete',$rule) }}">@csrf @method('delete')<button class="btn btn-outline-danger btn-sm">{{ translate('Delete') }}</button></form></div>@else<span class="text-muted small">{{ translate('Managed_From_Default_Settings') }}</span>@endif</td></tr>
        @empty<tr><td colspan="8" class="text-center text-muted">{{ translate('no_data_found') }}</td></tr>@endforelse
        </tbody></table></div></div>
    @endforeach

    <div class="card mb-3" id="insurance-subject-overrides">
        <div class="card-header">
            <h3 class="mb-1">استثناءات التأمين للحسابات المحددة</h3>
            <p class="text-muted mb-0">أعفِ عميلاً أو تاجراً من التأمين، أو افرض عليه قيمة خاصة. يسري القرار على الطلبات الجديدة فقط ولا يغيّر السجلات المالية السابقة.</p>
        </div>
        <div class="card-body border-bottom">
            <form method="post" action="{{ route('admin.insurance-center.subject-overrides.store') }}">@csrf
                <div class="row g-3 align-items-end">
                    <div class="col-md-2"><label class="form-label">نوع الحساب</label><select class="form-select" name="subject_type" required><option value="customer">عميل</option><option value="seller">تاجر</option></select></div>
                    <div class="col-md-2"><label class="form-label">رقم الحساب</label><input class="form-control" type="number" min="1" name="subject_id" value="{{ old('subject_id') }}" required><small class="text-muted">المعرّف الظاهر في قائمة العملاء أو التجار.</small></div>
                    <div class="col-md-2"><label class="form-label">القرار</label><select class="form-select js-insurance-override-mode" name="mode" required><option value="exempt">إعفاء من التأمين</option><option value="required">تأمين إلزامي خاص</option></select></div>
                    <div class="col-md-2 js-insurance-override-value d-none"><label class="form-label">طريقة الاحتساب</label><select class="form-select" name="calculation_type"><option value="percentage">نسبة مئوية</option><option value="fixed">قيمة ثابتة</option></select></div>
                    <div class="col-md-2 js-insurance-override-value d-none"><label class="form-label">القيمة</label><input class="form-control" type="number" min="0.01" step="0.01" name="calculation_value"></div>
                    <div class="col-md-2"><label class="form-label">سبب القرار</label><input class="form-control" name="reason" maxlength="500" value="{{ old('reason') }}" required></div>
                    <div class="col-md-2"><button class="btn btn-primary w-100">حفظ الاستثناء</button></div>
                </div>
            </form>
        </div>
        <div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>الحساب</th><th>القرار</th><th>القيمة الخاصة</th><th>السبب</th><th>تاريخ الحفظ</th><th>إجراء</th></tr></thead><tbody>
            @forelse($subjectOverrides as $override)
                <tr><td>{{ $override->subject_type === 'customer' ? 'عميل' : 'تاجر' }} #{{ $override->subject_id }}</td><td>{{ $override->mode === 'exempt' ? 'معفى من التأمين' : 'تأمين إلزامي خاص' }}</td><td>@if($override->mode === 'required'){{ $override->calculation_type === 'percentage' ? $override->calculation_value.'%' : setCurrencySymbol(amount:usdToDefaultCurrency(amount:$override->calculation_value)) }}@else—@endif</td><td>{{ $override->reason }}</td><td>{{ $override->created_at?->format('Y-m-d H:i') }}</td><td><form method="post" action="{{ route('admin.insurance-center.subject-overrides.delete',$override) }}">@csrf @method('delete')<button class="btn btn-outline-danger btn-sm">حذف</button></form></td></tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted">لا توجد استثناءات للحسابات حتى الآن.</td></tr>
            @endforelse
        </tbody></table></div>
    </div>

    <div class="card mb-3"><div class="card-header"><h3 class="mb-0">{{ translate('Insurance_Incentives') }}</h3><p class="text-muted mb-0 mt-1">{{ translate('insurance_incentives_explanation') }}</p></div><div class="card-body">
        <form method="post" action="{{ route('admin.insurance-center.incentives.store') }}">@csrf<div class="row g-3">
            <div class="col-md-3"><label class="form-label">{{ translate('Name') }}</label><input class="form-control" name="name" required></div>
            <div class="col-md-2"><label class="form-label">{{ translate('Type') }}</label><select class="form-select" name="incentive_type"><option value="welcome_credit">{{ translate('Welcome_Insurance_Credit') }}</option><option value="premium_discount">{{ translate('Premium_Discount') }}</option></select></div>
            <div class="col-md-2"><label class="form-label">{{ translate('Value_Type') }}</label><select class="form-select" name="value_type"><option value="fixed">{{ translate('fixed_amount') }}</option><option value="percentage">{{ translate('percentage') }}</option></select></div>
            <div class="col-md-1"><label class="form-label">{{ translate('Value') }}</label><input class="form-control" type="number" step="0.01" min="0.01" name="value" required></div>
            <div class="col-md-1"><label class="form-label">{{ translate('Max') }}</label><input class="form-control" type="number" step="0.01" name="maximum_amount"></div>
            <div class="col-md-1"><label class="form-label">{{ translate('Priority') }}</label><input class="form-control" type="number" name="priority" value="100" required></div>
            <div class="col-md-1"><label class="form-label">{{ translate('Uses') }}</label><input class="form-control" type="number" name="usage_limit_per_customer" value="1" min="1" required></div>
            <div class="col-md-1"><label class="form-label">{{ translate('Active') }}</label><input type="checkbox" name="is_active" value="1" checked></div>
            <div class="col-md-2"><label class="form-label">{{ translate('Minimum_Account_Age_Days') }}</label><input class="form-control" type="number" name="minimum_account_age_days" value="0"></div>
            <div class="col-md-2"><label class="form-label">{{ translate('Maximum_Account_Age_Days') }}</label><input class="form-control" type="number" name="maximum_account_age_days"></div>
            <div class="col-md-2"><label class="form-label">{{ translate('Starts_At') }}</label><input class="form-control" type="datetime-local" name="starts_at"></div>
            <div class="col-md-2"><label class="form-label">{{ translate('Ends_At') }}</label><input class="form-control" type="datetime-local" name="ends_at"></div>
            <div class="col-md-2"><label class="form-label">{{ translate('New_Accounts_Only') }}</label><input type="checkbox" name="new_accounts_only" value="1"></div>
            <div class="col-md-2"><button class="btn btn-primary w-100">{{ translate('Add') }}</button></div>
        </div></form>
    </div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>{{ translate('Name') }}</th><th>{{ translate('Type') }}</th><th>{{ translate('Value') }}</th><th>{{ translate('Uses') }}</th><th>{{ translate('Status') }}</th><th>{{ translate('Action') }}</th></tr></thead><tbody>@forelse($incentives as $incentive)<tr><td>{{ $incentive->name }}</td><td>{{ translate($incentive->incentive_type) }}</td><td>{{ $incentive->value_type === 'percentage' ? $incentive->value.'%' : setCurrencySymbol(amount:usdToDefaultCurrency(amount:$incentive->value)) }}</td><td>{{ $incentive->usage_limit_per_customer }}</td><td>{{ translate($incentive->is_active?'Active':'Inactive') }}</td><td class="d-flex gap-2"><form method="post" action="{{ route('admin.insurance-center.incentives.toggle',$incentive) }}">@csrf<button class="btn btn-outline-primary btn-sm">{{ translate($incentive->is_active?'Deactivate':'Activate') }}</button></form><form method="post" action="{{ route('admin.insurance-center.incentives.delete',$incentive) }}">@csrf @method('delete')<button class="btn btn-outline-danger btn-sm">{{ translate('Delete') }}</button></form></td></tr>@empty<tr><td colspan="6" class="text-center text-muted">{{ translate('no_data_found') }}</td></tr>@endforelse</tbody></table></div></div>

    <div class="card mb-3" id="insurance-balance-governance">
        <div class="card-header"><h3 class="mb-1">{{ translate('Insurance_Balance_Governance') }}</h3><p class="text-muted mb-0">{{ translate('insurance_balance_governance_help') }}</p></div>
        <div class="card-body border-bottom">
            <form method="post" action="{{ route('admin.insurance-center.balance-actions.store') }}">@csrf
                <input type="hidden" name="request_token" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                <div class="row g-3 align-items-end">
                    <div class="col-md-2"><label class="form-label">{{ translate('Account_Type') }}</label><select class="form-select" name="subject_type" required><option value="customer">{{ translate('Customer') }}</option><option value="seller">{{ translate('Seller') }}</option></select></div>
                    <div class="col-md-2"><label class="form-label">{{ translate('Account_ID') }}</label><input class="form-control" type="number" min="1" name="subject_id" required></div>
                    <div class="col-md-2"><label class="form-label">{{ translate('Action') }}</label><select class="form-select" name="action" required><option value="hold">{{ translate('Temporary_Hold') }}</option><option value="confiscate">{{ translate('Final_Insurance_Confiscation') }}</option></select></div>
                    <div class="col-md-2"><label class="form-label">{{ translate('Amount') }}</label><input class="form-control" type="number" min="0.01" step="0.01" name="amount" required></div>
                    <div class="col-md-2"><label class="form-label">{{ translate('Order_ID') }}</label><input class="form-control" type="number" min="1" name="order_id"></div>
                    <div class="col-md-4"><label class="form-label">{{ translate('Reason') }}</label><textarea class="form-control" name="reason" rows="2" required></textarea></div>
                    <div class="col-md-4"><label class="form-label">{{ translate('Evidence_Note') }}</label><textarea class="form-control" name="evidence_note" rows="2"></textarea></div>
                    <div class="col-md-2"><button class="btn btn-danger w-100">{{ translate('Record_Insurance_Action') }}</button></div>
                </div>
            </form>
            <div class="alert alert-warning mt-3 mb-0">{{ translate('insurance_confiscation_affects_insurance_only_not_purchase_value') }}</div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0"><thead><tr><th>#</th><th>{{ translate('Account') }}</th><th>{{ translate('Action') }}</th><th>{{ translate('Amount') }}</th><th>{{ translate('Reason') }}</th><th>{{ translate('Reference') }}</th><th>{{ translate('Created_By') }}</th><th>{{ translate('Follow_Up') }}</th></tr></thead><tbody>
            @forelse($balanceActions as $balanceAction)
                <tr><td>{{ $balanceAction->id }}</td><td>{{ translate(ucfirst($balanceAction->subject_type)) }} #{{ $balanceAction->subject_id }}</td><td>{{ translate('insurance_action_'.$balanceAction->action) }}</td><td>{{ setCurrencySymbol(amount:usdToDefaultCurrency(amount:$balanceAction->amount)) }}</td><td>{{ $balanceAction->reason }}</td><td class="small">{{ $balanceAction->reference }}</td><td>{{ $balanceAction->admin_id ?: translate('System') }}</td><td>
                    @if(in_array($balanceAction->action, ['hold','confiscate'], true))
                        <form method="post" action="{{ route('admin.insurance-center.balance-actions.follow-up',$balanceAction) }}" class="d-flex flex-wrap gap-2">@csrf
                            <input type="hidden" name="request_token" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                            <select class="form-select form-select-sm" name="action" required>
                                @if($balanceAction->action === 'hold')<option value="release">{{ translate('Release_Hold') }}</option><option value="confiscate">{{ translate('Confiscate_From_Hold') }}</option>@endif
                                @if($balanceAction->action === 'confiscate')<option value="reverse_confiscation">{{ translate('Reverse_Confiscation') }}</option>@endif
                            </select>
                            <input class="form-control form-control-sm" style="max-width:110px" type="number" min="0.01" step="0.01" max="{{ $balanceAction->amount }}" name="amount" placeholder="{{ translate('Amount') }}" required>
                            <input class="form-control form-control-sm" style="min-width:160px" name="reason" placeholder="{{ translate('Reason') }}" required>
                            <button class="btn btn-outline-primary btn-sm">{{ translate('Apply') }}</button>
                        </form>
                    @else<span class="text-muted">—</span>@endif
                </td></tr>
            @empty<tr><td colspan="8" class="text-center text-muted">{{ translate('no_data_found') }}</td></tr>@endforelse
            </tbody></table>
        </div>
    </div>
    @elseif($activeTab === 'customers')
        @include('admin-views.insurance.partials.insurance-list', ['partyType' => 'customer', 'insurances' => $customerInsurances])
    @elseif($activeTab === 'sellers')
        @include('admin-views.insurance.partials.insurance-list', ['partyType' => 'seller', 'insurances' => $sellerInsurances])
    @endif
</div>
@endsection

@push('script')
<script>
    document.querySelectorAll('.js-insurance-override-mode').forEach(function (select) {
        var fields = select.closest('form').querySelectorAll('.js-insurance-override-value');
        var refresh = function () { fields.forEach(function (field) { field.classList.toggle('d-none', select.value !== 'required'); }); };
        select.addEventListener('change', refresh); refresh();
    });
</script>
@endpush

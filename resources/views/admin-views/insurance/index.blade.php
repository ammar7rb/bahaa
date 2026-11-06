@extends('layouts.admin.app')

@section('title', translate($activeTab === 'customers' ? 'Customer_Insurances' : 'Seller_Insurances'))

@push('css_or_js')
<style>
    .insurance-center { --insurance-ink:#173153; --insurance-muted:#64748b; --insurance-border:#e2e8f0; min-width:0; overflow-x:hidden; }
    .insurance-center .insurance-hero { padding:24px 28px; border-radius:20px; background:linear-gradient(120deg,#123d83,#2563a9); color:#fff; }
    .insurance-center .insurance-hero h1,.insurance-center .insurance-hero p { color:#fff; }
    .insurance-center .insurance-hero p { opacity:.85; }
    .insurance-center .insurance-primary-nav,.insurance-center .insurance-section-nav { display:flex; flex-wrap:wrap; gap:8px; }
    .insurance-center .insurance-primary-nav a,.insurance-center .insurance-section-nav a { display:inline-flex; align-items:center; gap:8px; padding:10px 14px; border:1px solid var(--insurance-border); border-radius:11px; background:#fff; color:var(--insurance-ink); font-weight:700; text-decoration:none; }
    .insurance-center .insurance-primary-nav a.active { background:#eaf2ff; border-color:#a9c5f2; color:#174b9a; }
    .insurance-center .insurance-section-nav a.active { background:#174b9a; border-color:#174b9a; color:#fff; }
    .insurance-center .insurance-primary-nav .badge { color:inherit; background:rgba(37,99,235,.1); }
    .insurance-center .insurance-panel { border:1px solid var(--insurance-border); border-radius:16px; box-shadow:0 6px 22px rgba(15,23,42,.04); overflow:hidden; }
    .insurance-center .insurance-panel .card-header { background:#fff; border-bottom:1px solid var(--insurance-border); padding:18px 22px; }
    .insurance-center .insurance-panel .card-body { padding:22px; }
    .insurance-center .insurance-stat { border:1px solid var(--insurance-border); border-radius:15px; box-shadow:none; }
    .insurance-center .insurance-stat .card-body { padding:18px 20px; }
    .insurance-center .insurance-stat small { color:var(--insurance-muted); font-weight:600; }
    .insurance-center .insurance-stat h2,.insurance-center .insurance-stat h3 { margin:8px 0 0; color:var(--insurance-ink); }
    .insurance-center .insurance-metrics { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:16px; }
    .insurance-center .insurance-metrics > * { min-width:0; }
    .insurance-center .insurance-metrics h2 { overflow-wrap:anywhere; }
    .insurance-center .insurance-guidance { border:1px solid var(--insurance-border); border-radius:14px; background:#f8fbff; }
    .insurance-center .insurance-guidance summary { cursor:pointer; padding:15px 18px; font-weight:700; color:var(--insurance-ink); }
    .insurance-center .insurance-guidance ul { padding:0 35px 15px; margin:0; color:var(--insurance-muted); }
    .insurance-center .insurance-form-grid > [class*="col-"] { flex:0 0 100%; max-width:100%; }
    .insurance-center .insurance-form-grid > .insurance-form-heading.col-12 { flex:0 0 100%; max-width:100%; padding-top:8px; border-top:1px solid var(--insurance-border); }
    .insurance-center .insurance-form-grid > .insurance-form-heading:first-child { border-top:0; padding-top:0; }
    .insurance-center .form-label { font-weight:700; color:var(--insurance-ink); margin-bottom:7px; }
    .insurance-center .table th { white-space:nowrap; color:var(--insurance-muted); font-size:12px; font-weight:700; background:#f8fafc; }
    .insurance-center .table td { vertical-align:middle; }
    .insurance-center .insurance-table { min-width:850px; }
    [data-ba-admin-theme="dark"] .insurance-center { --insurance-ink:#e6eefb; --insurance-muted:#b3c2d8; --insurance-border:#304563; }
    [data-ba-admin-theme="dark"] .insurance-center .insurance-primary-nav a,[data-ba-admin-theme="dark"] .insurance-center .insurance-section-nav a,[data-ba-admin-theme="dark"] .insurance-center .insurance-panel,[data-ba-admin-theme="dark"] .insurance-center .insurance-stat,[data-ba-admin-theme="dark"] .insurance-center .insurance-panel .card-header { background:#1a2a43; color:#e6eefb; }
    [data-ba-admin-theme="dark"] .insurance-center .insurance-primary-nav a.active,[data-ba-admin-theme="dark"] .insurance-center .insurance-section-nav a.active { background:#275fa7; border-color:#5b8bc8; color:#fff; }
    [data-ba-admin-theme="dark"] .insurance-center .insurance-guidance,[data-ba-admin-theme="dark"] .insurance-center .table th { background:#20334f; color:#e6eefb; }
    @media(min-width:768px) { .insurance-center .insurance-form-grid > [class*="col-"] { flex:0 0 50%; max-width:50%; } }
    @media(min-width:1200px) { .insurance-center .insurance-form-grid > [class*="col-"] { flex:0 0 25%; max-width:25%; } }
    @media(max-width:1199px) { .insurance-center .insurance-metrics { grid-template-columns:repeat(2,minmax(0,1fr)); } }
    @media(max-width:575px) { .insurance-center .insurance-metrics { grid-template-columns:1fr; } }
    @media(max-width:767px) { .insurance-center .insurance-hero { padding:20px; } .insurance-center .insurance-primary-nav a,.insurance-center .insurance-section-nav a { flex:1 1 100%; justify-content:space-between; } }
</style>
@endpush

@section('content')
<div class="content container-fluid insurance-center">
    <header class="insurance-hero mb-4"><h1 class="mb-2">{{ translate($activeTab === 'customers' ? 'Customer_Insurances' : 'Seller_Insurances') }}</h1><p class="mb-0">{{ $activeTab === 'customers' ? 'إعدادات وقواعد وسجلات تأمين العملاء في مكان واحد.' : 'إعدادات وقواعد وسجلات تأمين التجار في مكان واحد.' }}</p></header>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <nav class="insurance-primary-nav mb-3" aria-label="{{ translate('Insurance_Rule_Center') }}">
        <a class="{{ $activeTab === 'customers' ? 'active' : '' }}" href="{{ route('admin.insurance-center.index',['tab'=>'customers']) }}" @if($activeTab === 'customers') aria-current="page" @endif><i class="fi fi-sr-user"></i>{{ translate('Customer_Insurances') }} <span class="badge">{{ $insuranceStats['customer_total'] }}</span></a>
        <a class="{{ $activeTab === 'sellers' ? 'active' : '' }}" href="{{ route('admin.insurance-center.index',['tab'=>'sellers']) }}" @if($activeTab === 'sellers') aria-current="page" @endif><i class="fi fi-sr-store-alt"></i>{{ translate('Seller_Insurances') }} <span class="badge">{{ $insuranceStats['seller_total'] }}</span></a>
    </nav>

    <nav class="insurance-section-nav mb-4" aria-label="{{ translate($activeTab === 'customers' ? 'Customer_Insurances' : 'Seller_Insurances') }}">
        @foreach([
            'overview' => ['Insurance_Overview', 'fi fi-sr-home'],
            'rules' => ['Advanced_Insurance_Rules', 'fi fi-sr-list-check'],
            'exceptions' => ['Insurance_Selected_Accounts', 'fi fi-sr-user-pen'],
            'records' => ['All_Insurance_Records', 'fi fi-sr-document'],
            ...($activeTab === 'customers' ? ['incentives' => ['Insurance_Incentives', 'fi fi-sr-gift']] : []),
            'balances' => ['Insurance_Balance_Governance', 'fi fi-sr-wallet'],
        ] as $section => [$label, $icon])
            <a class="{{ $activeSection === $section ? 'active' : '' }}" href="{{ route('admin.insurance-center.index',['tab'=>$activeTab,'section'=>$section]) }}" @if($activeSection === $section) aria-current="page" @endif><i class="{{ $icon }}"></i>{{ translate($label) }}</a>
        @endforeach
    </nav>
    @if($activeSection === 'overview')
    @php($prefix = $subjectType)
    <div class="insurance-metrics mb-3">
        @foreach([
            ['All_Insurance_Records',$insuranceStats[$prefix.'_total'],'primary'],
            ['Paid_Insurances',$insuranceStats[$prefix.'_paid'],'success'],
            ['Paid_Insurance_Amount',setCurrencySymbol(amount:usdToDefaultCurrency(amount:$insuranceStats[$prefix.'_paid_amount'])),'info'],
            ['Confiscated_Insurance_Amount',setCurrencySymbol(amount:usdToDefaultCurrency(amount:$insuranceStats[$prefix.'_confiscated'])),'warning'],
        ] as [$label,$value,$color])
            <div class="card insurance-stat h-100 border-start border-{{ $color }} border-3"><div class="card-body"><small>{{ translate($label) }}</small><h2>{{ $value }}</h2></div></div>
        @endforeach
    </div>

    <details class="insurance-guidance mb-3">
        <summary>{{ translate('how_insurance_rule_center_works') }}</summary>
        <ul class="mb-0 ps-3">
            <li>{{ $activeTab === 'customers' ? 'إعدادات العملاء لا تغيّر تأمين التجار.' : 'إعدادات التجار لا تغيّر تأمين العملاء.' }}</li>
            <li>{{ translate('insurance_rule_type_help') }}</li>
            <li>{{ translate('insurance_account_age_help') }}</li>
            <li>{{ translate('insurance_priority_help') }}</li>
            <li>{{ translate('insurance_first_matching_rule_help') }}</li>
        </ul>
    </details>

    <div class="card insurance-panel mb-3"><div class="card-header"><h3 class="mb-1">{{ translate('Insurance_Master_Settings') }}</h3><p class="text-muted mb-0">حدد من يشمله التأمين. الحساب الجديد لا يُؤمَّن بمجرد التسجيل؛ يبدأ تطبيق التأمين على الطلبات الجديدة فقط حسب النطاق الذي تختاره.</p></div><div class="card-body">
        <form method="post" action="{{ route('admin.insurance-center.master') }}">@csrf
            <input type="hidden" name="subject_type" value="{{ $subjectType }}">
            <div class="row g-3 align-items-end insurance-form-grid">
                @if($subjectType === 'customer')
                <div class="col-12 insurance-form-heading"><h4 class="h6 mb-0">تأمين العملاء</h4></div>
                <div class="col-md-2"><label class="form-label">{{ translate('Customer_Insurance') }}</label><div><input type="checkbox" name="customer_enabled" value="1" @checked((int)getWebConfig(name:'customer_order_insurance_status')===1)> {{ translate('Active') }}</div></div>
                <div class="col-md-2"><label class="form-label">ضريبة العملاء على المنتجات 14%</label><div><input type="checkbox" name="customer_tax_enabled" value="1" @checked((int)getWebConfig(name:'customer_second_invoice_tax_enabled')===1)> تفعيل</div><small class="text-muted">تُضاف إلى الفاتورة الثانية فقط عند التفعيل.</small></div>
                <div class="col-md-2"><label class="form-label" for="customer-insurance-scope">نطاق العملاء</label><select class="form-select" id="customer-insurance-scope" name="customer_scope" required><option value="selected" @selected((getWebConfig(name:'customer_order_insurance_scope') ?: 'selected') === 'selected')>حسابات أحددها فقط</option><option value="all" @selected(getWebConfig(name:'customer_order_insurance_scope') === 'all')>كل العملاء</option></select></div>
                <div class="col-md-2"><label class="form-label">{{ translate('Insurance_Recovery_Days') }}</label><input class="form-control" type="number" name="customer_maturity_days" min="1" max="3650" value="{{ getWebConfig(name:'customer_order_insurance_maturity_days') ?? 90 }}" required></div>
                <div class="col-md-2"><label class="form-label">{{ translate('Customer_Insurance_Payment_Deadline_Hours') }}</label><input class="form-control" type="number" name="customer_payment_deadline_hours" min="1" max="720" value="{{ getWebConfig(name:'customer_order_insurance_payment_deadline_hours') ?? 72 }}" required><small class="text-muted">{{ translate('deadline_starts_after_purchase_payment') }}</small></div>
                <div class="col-md-2"><label class="form-label">{{ translate('Purchase_Refund_Delay_Days') }}</label><input class="form-control" type="number" name="customer_purchase_refund_delay_days" min="0" max="365" value="{{ getWebConfig(name:'customer_purchase_refund_delay_days') ?? 7 }}" required><small class="text-muted">{{ translate('refund_goes_to_purchase_wallet_only') }}</small></div>
                @else
                <div class="col-12 insurance-form-heading"><h4 class="h6 mb-0">تأمين التجار</h4></div>
                <div class="col-md-2"><label class="form-label">{{ translate('Seller_Order_Insurance') }}</label><div><input type="checkbox" name="seller_enabled" value="1" @checked((int)getWebConfig(name:'seller_order_insurance_status')===1)> {{ translate('Active') }}</div></div>
                <div class="col-md-2"><label class="form-label" for="seller-insurance-scope">نطاق التجار</label><select class="form-select" id="seller-insurance-scope" name="seller_scope" required><option value="selected" @selected((getWebConfig(name:'seller_order_insurance_scope') ?: 'selected') === 'selected')>حسابات أحددها فقط</option><option value="all" @selected(getWebConfig(name:'seller_order_insurance_scope') === 'all')>كل التجار</option></select></div>
                <div class="col-md-2"><label class="form-label">{{ translate('Payment_Deadline_Days') }}</label><input class="form-control" type="number" name="seller_pending_days" min="1" max="90" value="{{ getWebConfig(name:'seller_order_insurance_pending_days') ?? 3 }}" required></div>
                <div class="col-md-2"><label class="form-label">{{ translate('Reusable_After_Days') }}</label><input class="form-control" type="number" name="seller_reuse_after_days" min="1" max="3650" value="{{ getWebConfig(name:'seller_order_insurance_reuse_after_days') ?? 90 }}" required></div>
                @endif
                <div class="col-md-2"><button class="btn btn-primary w-100">{{ translate('save_changes') }}</button></div>
            </div>
        </form>
    </div></div>

    @include('admin-views.insurance.partials.overview-tools')

    @if($subjectType === 'customer')
    <div class="row g-3 mb-3">
        <div class="col-md-3"><div class="card insurance-stat h-100"><div class="card-body"><small>{{ translate('Welcome_Insurance_Credit') }}</small><h3>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $report['welcome_credit'])) }}</h3><span>{{ $report['welcome_redemptions'] }} {{ translate('operations') }}</span></div></div></div>
        <div class="col-md-3"><div class="card insurance-stat h-100"><div class="card-body"><small>{{ translate('Promotional_Insurance_Discounts') }}</small><h3>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $report['premium_discount'])) }}</h3><span>{{ $report['discount_redemptions'] }} {{ translate('operations') }}</span></div></div></div>
        <div class="col-md-6"><div class="alert alert-info h-100 mb-0">{{ translate('welcome_credit_and_premium_discount_are_separate_non_withdrawable_financial_movements') }}</div></div>
    </div>
    @endif

    @endif
    @if($activeSection === 'rules')
    <div class="card insurance-panel mb-3"><div class="card-header"><h3 class="mb-1">{{ translate('Add_Insurance_Rule') }}</h3><p class="text-muted mb-0">{{ translate('add_insurance_rule_section_help') }}</p></div><div class="card-body">
        <form method="post" action="{{ route('admin.insurance-center.rules.store') }}">@csrf
            <input type="hidden" name="subject_type" value="{{ $subjectType }}">
            <div class="row g-3 insurance-form-grid">
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

        <div class="card insurance-panel mb-3"><div class="card-header"><h3 class="mb-0">{{ translate(ucfirst($subjectType).'_Insurance_Rules') }}</h3></div><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>{{ translate('Priority') }}</th><th>{{ translate('Name') }}</th><th>{{ translate('Rule_Type') }}</th><th>{{ translate('Order_Range') }}</th><th>{{ translate('Account_Age') }}</th><th>{{ translate('Calculation') }}</th><th>{{ translate('Status') }}</th><th>{{ translate('Action') }}</th></tr></thead><tbody>
        @forelse($rules as $rule)<tr><td>{{ $rule->priority }}</td><td>{{ $rule->name }}<div class="small text-muted">{{ $rule->code }}</div></td><td>{{ translate($rule->rule_type) }}</td><td>{{ usdToDefaultCurrency(amount:$rule->minimum_order_amount) }} – {{ $rule->maximum_order_amount === null ? '∞' : usdToDefaultCurrency(amount:$rule->maximum_order_amount) }}</td><td>{{ $rule->minimum_account_age_days }} – {{ $rule->maximum_account_age_days ?? '∞' }}</td><td>{{ $rule->calculation_type === 'percentage' ? $rule->calculation_value.'%' : setCurrencySymbol(amount:usdToDefaultCurrency(amount:$rule->calculation_value)) }}</td><td><span class="badge badge-soft-{{ $rule->is_active ? 'success':'danger' }}">{{ translate($rule->is_active ? 'Active':'Inactive') }}</span></td><td>@if($rule->rule_type !== 'default')<div class="d-flex gap-2"><form method="post" action="{{ route('admin.insurance-center.rules.toggle',$rule) }}">@csrf<button class="btn btn-outline-primary btn-sm">{{ translate($rule->is_active?'Deactivate':'Activate') }}</button></form><form method="post" action="{{ route('admin.insurance-center.rules.delete',$rule) }}">@csrf @method('delete')<button class="btn btn-outline-danger btn-sm">{{ translate('Delete') }}</button></form></div>@else<span class="text-muted small">{{ translate('Managed_From_Default_Settings') }}</span>@endif</td></tr>
        @empty<tr><td colspan="8" class="text-center text-muted">{{ translate('no_data_found') }}</td></tr>@endforelse
        </tbody></table></div></div>

    @endif
    @if($activeSection === 'exceptions')
    <div class="card insurance-panel mb-3" id="insurance-subject-overrides">
        <div class="card-header">
            <h3 class="mb-1">{{ $subjectType === 'customer' ? 'تحديد العملاء المشمولين بالتأمين' : 'تحديد التجار المشمولين بالتأمين' }}</h3>
            <p class="text-muted mb-0">ابحث بالبريد الإلكتروني، واختر الحساب، ثم طبق القاعدة العامة أو قيمة خاصة أو إعفاء. التغيير يسري على الطلبات الجديدة فقط.</p>
        </div>
        <div class="card-body border-bottom">
            <form method="get" action="{{ route('admin.insurance-center.index') }}" class="row g-3 align-items-end mb-4">
                <input type="hidden" name="tab" value="{{ $activeTab }}"><input type="hidden" name="section" value="exceptions">
                <div class="col-md-9"><label class="form-label" for="insurance-account-search">البحث بالبريد الإلكتروني</label><input class="form-control" id="insurance-account-search" type="search" name="account_search" minlength="2" maxlength="255" value="{{ $accountSearch }}" placeholder="اكتب حرفين على الأقل من البريد الإلكتروني" required></div>
                <div class="col-md-3"><button class="btn btn-outline-primary w-100">بحث عن الحساب</button></div>
            </form>
            <form method="post" action="{{ route('admin.insurance-center.subject-overrides.store') }}">@csrf
                <input type="hidden" name="subject_type" value="{{ $subjectType }}">
                @if(mb_strlen($accountSearch) >= 2)
                    <fieldset class="mb-3"><legend class="h6">نتائج البحث — اختر حسابًا</legend>
                        <div class="list-group insurance-account-results">
                            @forelse($accountResults as $account)
                                <label class="list-group-item d-flex align-items-center gap-3"><input type="radio" name="subject_id" value="{{ $account->id }}" @checked((string) old('subject_id') === (string) $account->id) required><span><strong>{{ trim($account->f_name.' '.$account->l_name) ?: $account->email }}</strong><small class="d-block text-muted"><bdi>{{ $account->email }}</bdi> · #{{ $account->id }}</small></span></label>
                            @empty <p class="text-muted mb-0">لم نجد حسابًا بهذا البريد. جرّب كتابة جزء آخر من البريد الإلكتروني.</p>
                            @endforelse
                        </div>
                    </fieldset>
                @else
                    <p class="text-muted">ابدأ بالبحث عن بريد العميل أو التاجر لاختيار حساب.</p>
                @endif
                <div class="row g-3 align-items-end insurance-form-grid">
                    <div class="col-md-2"><label class="form-label">القرار</label><select class="form-select js-insurance-override-mode" name="mode" required><option value="included">تطبيق قواعد التأمين العامة</option><option value="required">تأمين بقيمة خاصة</option><option value="exempt">إعفاء من التأمين</option></select></div>
                    <div class="col-md-2 js-insurance-override-value d-none"><label class="form-label">طريقة الاحتساب</label><select class="form-select" name="calculation_type"><option value="percentage">نسبة مئوية</option><option value="fixed">قيمة ثابتة</option></select></div>
                    <div class="col-md-2 js-insurance-override-value d-none"><label class="form-label">القيمة</label><input class="form-control" type="number" min="0.01" step="0.01" name="calculation_value"></div>
                    <div class="col-md-2"><label class="form-label">سبب القرار</label><input class="form-control" name="reason" maxlength="500" value="{{ old('reason') }}" required></div>
                    <div class="col-md-2"><button class="btn btn-primary w-100" @disabled($accountResults->isEmpty())>حفظ إعداد الحساب</button></div>
                </div>
            </form>
        </div>
        <div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>الحساب</th><th>القرار</th><th>القيمة الخاصة</th><th>السبب</th><th>تاريخ الحفظ</th><th>إجراء</th></tr></thead><tbody>
            @forelse($subjectOverrides as $override)
                <tr><td><a href="{{ route('admin.insurance-center.accounts.show', [$subjectType, $override->subject_id]) }}">{{ $override->subject_type === 'customer' ? 'عميل' : 'تاجر' }} #{{ $override->subject_id }}</a><div class="small text-muted"><bdi>{{ $selectedAccountEmails[$override->subject_type][$override->subject_id] ?? '—' }}</bdi></div></td><td>{{ match($override->mode) { 'included' => 'تطبيق القواعد العامة', 'exempt' => 'معفى من التأمين', default => 'تأمين بقيمة خاصة' } }}</td><td>@if($override->mode === 'required'){{ $override->calculation_type === 'percentage' ? $override->calculation_value.'%' : setCurrencySymbol(amount:usdToDefaultCurrency(amount:$override->calculation_value)) }}@else—@endif</td><td>{{ $override->reason }}</td><td>{{ $override->created_at?->format('Y-m-d H:i') }}</td><td><form method="post" action="{{ route('admin.insurance-center.subject-overrides.delete',$override) }}">@csrf @method('delete')<button class="btn btn-outline-danger btn-sm">حذف</button></form></td></tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted">لا توجد حسابات محددة حتى الآن.</td></tr>
            @endforelse
        </tbody></table></div>
    </div>

    @endif
    @if($activeSection === 'incentives')
    <div class="card insurance-panel mb-3"><div class="card-header"><h3 class="mb-0">{{ translate('Insurance_Incentives') }}</h3><p class="text-muted mb-0 mt-1">{{ translate('insurance_incentives_explanation') }}</p></div><div class="card-body">
        <form method="post" action="{{ route('admin.insurance-center.incentives.store') }}">@csrf<div class="row g-3 insurance-form-grid">
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

    @endif
    @if($activeSection === 'balances')
    <div class="card insurance-panel mb-3" id="insurance-balance-governance">
        <div class="card-header"><h3 class="mb-1">{{ translate('Insurance_Balance_Governance') }}</h3><p class="text-muted mb-0">{{ translate('insurance_balance_governance_help') }}</p></div>
        <div class="card-body border-bottom">
            <form method="post" action="{{ route('admin.insurance-center.balance-actions.store') }}">@csrf
                <input type="hidden" name="request_token" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                <input type="hidden" name="subject_type" value="{{ $subjectType }}">
                <div class="row g-3 align-items-end insurance-form-grid">
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
    @endif
    @if($activeSection === 'records')
        @include('admin-views.insurance.partials.insurance-list', ['partyType' => $subjectType, 'insurances' => $subjectType === 'customer' ? $customerInsurances : $sellerInsurances])
    @endif
</div>
@endsection

@push('script')
<script>
    (function () {
        var scope = document.getElementById('customer-insurance-scope') || document.getElementById('seller-insurance-scope');
        var group = document.querySelector('[data-insurance-selected-group]');
        if (!scope || !group) return;
        function refresh() { group.style.display = scope.value === 'selected' ? '' : 'none'; }
        scope.addEventListener('change', refresh);
        refresh();
    })();
    document.querySelectorAll('.js-insurance-override-mode').forEach(function (select) {
        var fields = select.closest('form').querySelectorAll('.js-insurance-override-value');
        var refresh = function () { fields.forEach(function (field) { field.classList.toggle('d-none', select.value !== 'required'); }); };
        select.addEventListener('change', refresh); refresh();
    });
</script>
@endpush

@php
    $scope = getWebConfig(name: $subjectType === 'customer' ? 'customer_order_insurance_scope' : 'seller_order_insurance_scope') ?: 'selected';
    $defaultRule = $rules->firstWhere('rule_type', 'default');
    $tabUrl = route('admin.insurance-center.index', ['tab' => $activeTab]);
@endphp

<div class="card insurance-panel mb-3" id="insurance-account-group" data-insurance-selected-group>
    <div class="card-header"><h3 class="h5 mb-1">{{ $subjectType === 'customer' ? 'مجموعة العملاء المشمولين' : 'مجموعة التجار المشمولين' }}</h3><p class="text-muted mb-0">عند اختيار «حسابات أحددها فقط»، أضف الحسابات هنا. يمكنك العودة وإضافة المزيد في أي وقت؛ الإضافة لا تمس الأعضاء السابقين.</p></div>
    <div class="card-body border-bottom">
        <form method="get" action="{{ $tabUrl }}" class="row g-2 align-items-end mb-3">
            <input type="hidden" name="tab" value="{{ $activeTab }}">
            <div class="col-md-9"><label class="form-label" for="group-account-search">ابحث عن {{ $subjectType === 'customer' ? 'عميل' : 'تاجر' }} بالبريد الإلكتروني</label><input class="form-control" type="search" id="group-account-search" name="account_search" minlength="2" maxlength="255" value="{{ $accountSearch }}" placeholder="اكتب حرفين أو أكثر من البريد الإلكتروني"></div>
            <div class="col-md-3"><button class="btn btn-outline-primary w-100">بحث</button></div>
        </form>
        @if(mb_strlen($accountSearch) >= 2)
        <form method="post" action="{{ route('admin.insurance-center.selected-accounts.store') }}">@csrf
            <input type="hidden" name="subject_type" value="{{ $subjectType }}">
            <div class="list-group mb-3">
                @forelse($accountResults as $account)
                <label class="list-group-item d-flex gap-3 align-items-center"><input type="checkbox" name="subject_ids[]" value="{{ $account->id }}"><span><strong>{{ trim($account->f_name.' '.$account->l_name) ?: $account->email }}</strong><small class="d-block text-muted"><bdi>{{ $account->email }}</bdi> · #{{ $account->id }}</small></span><span class="ms-auto small text-muted">✓</span></label>
                @empty <div class="list-group-item text-muted">لا توجد حسابات بهذا البريد الإلكتروني.</div>
                @endforelse
            </div>
            <button class="btn btn-primary" @disabled($accountResults->isEmpty())>✓ إضافة الحسابات المحددة</button>
        </form>
        @else <p class="text-muted mb-0">ابحث عن البريد ثم ضع علامة صح أمام الحسابات المطلوبة.</p>
        @endif
    </div>
    <div class="card-body"><h4 class="h6 mb-3">الحسابات الموجودة في المجموعة ({{ $includedAccounts->total() }})</h4>
        @forelse($includedAccounts as $member)
        <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between border-bottom py-2">
            <div><a href="{{ route('admin.insurance-center.accounts.show', [$subjectType, $member->subject_id]) }}"><bdi>{{ $selectedAccountEmails[$subjectType][$member->subject_id] ?? '#'.$member->subject_id }}</bdi></a><small class="d-block text-muted">{{ $member->mode === 'required' ? 'قيمة خاصة لهذا الحساب' : 'القواعد العامة' }}</small></div>
            <form method="post" action="{{ route('admin.insurance-center.subject-overrides.delete', $member) }}">@csrf @method('delete')<button class="btn btn-outline-danger btn-sm">إزالة من المجموعة</button></form>
        </div>
        @empty <p class="text-muted mb-0">لم تضف حسابات بعد.</p>
        @endforelse
        @if($includedAccounts->hasPages())<div class="mt-3">{{ $includedAccounts->withQueryString()->links() }}</div>@endif
    </div>
</div>

<div class="card insurance-panel mb-3"><div class="card-header"><h3 class="h5 mb-1">قيمة التأمين وشروط تطبيقها</h3><p class="text-muted mb-0">القاعدة الأساسية تنطبق على الطلبات الجديدة بعد حفظها. القواعد ذات الأولوية الأعلى في تبويب «قواعد التأمين المتقدمة» قد تتقدم عليها.</p></div><div class="card-body">
    <form method="post" action="{{ $defaultRule ? route('admin.insurance-center.rules.update', $defaultRule) : route('admin.insurance-center.rules.store') }}">@csrf
        <input type="hidden" name="subject_type" value="{{ $subjectType }}"><input type="hidden" name="name" value="{{ $defaultRule?->name ?? ($subjectType === 'customer' ? 'التأمين الأساسي للعملاء' : 'التأمين الأساسي للتجار') }}"><input type="hidden" name="code" value="{{ $defaultRule?->code ?? '' }}"><input type="hidden" name="rule_type" value="default"><input type="hidden" name="priority" value="{{ $defaultRule?->priority ?? 1000 }}"><input type="hidden" name="is_active" value="1">
        <div class="row g-3">
            <div class="col-md-3"><label class="form-label">طريقة حساب التأمين</label><select class="form-select" name="calculation_type"><option value="percentage" @selected($defaultRule?->calculation_type === 'percentage')>نسبة مئوية من قيمة الطلب</option><option value="fixed" @selected($defaultRule?->calculation_type === 'fixed')>قيمة ثابتة لكل طلب</option></select></div>
            <div class="col-md-3"><label class="form-label">النسبة % أو القيمة الثابتة</label><input class="form-control" type="number" step="0.01" min="0.01" name="calculation_value" value="{{ $defaultRule ? ($defaultRule->calculation_type === 'fixed' ? usdToDefaultCurrency(amount:$defaultRule->calculation_value) : $defaultRule->calculation_value) : '' }}" required></div>
            <div class="col-md-3"><label class="form-label">أقل قيمة طلب</label><input class="form-control" type="number" step="0.01" min="0" name="minimum_order_amount" value="{{ $defaultRule ? usdToDefaultCurrency(amount:$defaultRule->minimum_order_amount) : 0 }}" required></div>
            <div class="col-md-3"><label class="form-label">أعلى قيمة طلب (اختياري)</label><input class="form-control" type="number" step="0.01" min="0" name="maximum_order_amount" value="{{ $defaultRule?->maximum_order_amount === null ? '' : usdToDefaultCurrency(amount:$defaultRule->maximum_order_amount) }}"></div>
            <div class="col-md-3"><label class="form-label">أقل عمر للحساب بالأيام</label><input class="form-control" type="number" min="0" name="minimum_account_age_days" value="{{ $defaultRule?->minimum_account_age_days ?? 0 }}"></div>
            <div class="col-md-3"><label class="form-label">أقصى عمر للحساب بالأيام (اختياري)</label><input class="form-control" type="number" min="0" name="maximum_account_age_days" value="{{ $defaultRule?->maximum_account_age_days }}"></div>
            <input type="hidden" name="starts_at" value="{{ $defaultRule?->starts_at?->format('Y-m-d H:i:s') }}"><input type="hidden" name="ends_at" value="{{ $defaultRule?->ends_at?->format('Y-m-d H:i:s') }}">
            <div class="col-md-3 d-flex align-items-end"><button class="btn btn-primary w-100">حفظ قيمة التأمين والشروط</button></div>
            <div class="col-md-3 d-flex align-items-end"><a class="btn btn-outline-primary w-100" href="{{ route('admin.insurance-center.index', ['tab'=>$activeTab,'section'=>'rules']) }}">كل القواعد المتقدمة</a></div>
        </div>
    </form>
</div></div>

@if($subjectType === 'customer')
<div class="card insurance-panel mb-3"><div class="card-header"><h3 class="h5 mb-1">ضريبة العملاء 14% والفاتورة الثانية</h3></div><div class="card-body"><p>خيار «ضريبة العملاء على المنتجات 14%» موجود أعلى الصفحة بجوار تفعيل التأمين. إن فعّلت التأمين وحده، تظهر فاتورة ثانية بالتأمين فقط. وإن فعّلت الضريبة كذلك، تجمع الفاتورة التأمين والضريبة. تُحسب الضريبة على المنتجات بعد الخصومات، دون الشحن، ولا تدخل في تأمين التاجر.</p>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-primary" href="{{ route('admin.orders.post-purchase-invoices.index') }}">إعدادات الفاتورة الثانية وسجلاتها</a>
    </div>
    <small class="text-muted d-block mt-2">للتجربة: احفظ الإعدادات، أنشئ طلبًا جديدًا، ثم افتح فاتورته الثانية وراجع بندَي التأمين والضريبة والمجموع. الإعدادات الجديدة تسري على الطلبات الجديدة.</small>
</div></div>
@endif

<div class="card insurance-panel mb-3"><div class="card-header"><h3 class="h5 mb-1">حسابات التأمين وسجل الطلبات</h3><p class="text-muted mb-0">ابحث عن حساب وافتح ملفه لمراجعة كل التأمينات ورصيده، أو افتح سجل تأمينات الطلبات كاملًا.</p></div><div class="card-body">
    <form method="get" action="{{ $tabUrl }}" class="row g-2 align-items-end"><input type="hidden" name="tab" value="{{ $activeTab }}"><div class="col-md-9"><label class="form-label" for="insurance-profile-search">البحث بالبريد الإلكتروني</label><input id="insurance-profile-search" class="form-control" name="account_search" value="{{ $accountSearch }}" minlength="2" maxlength="255"></div><div class="col-md-3"><button class="btn btn-outline-primary w-100">بحث عن حساب</button></div></form>
    @if(mb_strlen($accountSearch) >= 2)<div class="list-group mt-3">@foreach($accountResults as $account)<a class="list-group-item list-group-item-action" href="{{ route('admin.insurance-center.accounts.show', [$subjectType, $account->id]) }}"><bdi>{{ $account->email }}</bdi> · #{{ $account->id }}</a>@endforeach</div>@endif
    <a class="btn btn-outline-primary mt-3" href="{{ route('admin.insurance-center.index', ['tab'=>$activeTab,'section'=>'records']) }}">عرض سجل تأمينات كل الطلبات</a>
</div></div>

@extends('layouts.admin.app')
@section('title', 'العملاء')
@push('css_or_js')
<link rel="stylesheet" href="{{ dynamicAsset(path:'public/assets/back-end/css/customer-accounts.css') }}?v=20261107.5">
@endpush
@section('content')
@php
    $titles=['list'=>'قائمة العملاء','payments'=>'مدفوعات','debts'=>'الديون','subscriptions'=>'الاشتراكات'];
    $money=fn($amount)=>number_format((float)$amount,3).' '.$currency;
    $can=fn($permission)=>\App\Services\CustomerAccountAccess::allows($permission);
    $accountLink=fn($id)=>route('admin.customer.accounts.index',['page'=>$page,'customer_id'=>$id]);
    $identity=function($account){return \App\Services\AdministrativeReferenceService::format('customer',(int)$account->id);};
    $statuses=['pending'=>'جاري التحقق','legacy_review'=>'جاري التحقق','paid'=>'مقبول','approved'=>'ناجحة','pending_review'=>'جاري التحقق','rejected'=>'مرفوضة','cancelled'=>'ملغاة','draft'=>'مسودة','customer_requested'=>'بانتظار مراجعة الطلب','wallet_approved'=>'معتمد للرد في موعده','completed'=>'تم الرد'];
@endphp
<div class="content container-fluid customer-accounts" dir="rtl" data-ca-initial-dialog="{{ $focusedAction ? 'ca-account-'.$focusedAction : '' }}">
    @include('admin-views.customer.accounts.profile-navigation')
    <header class="ca-heading"><div><h1><i class="fi fi-rr-{{ ['list'=>'users','payments'=>'wallet','debts'=>'receipt','subscriptions'=>'calendar'][$page] }}" aria-hidden="true"></i>{{ $titles[$page] }}</h1><p>إدارة حسابات العملاء ومعاملاتهم</p></div><a class="btn btn-outline-primary" href="{{ route('admin.payment-reviews.index',['audience'=>'customer','customer_id'=>$customer?->id]) }}">مراجعة المدفوعات <i class="fi fi-rr-arrow-small-left" aria-hidden="true"></i></a></header>
    @if(session('customer_account_message'))<div class="alert alert-success" role="status">{{ session('customer_account_message') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger" role="alert"><strong>لم تكتمل العملية</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul><p>راجع البيانات والمرجع قبل إعادة المحاولة. إذا انقطع الاتصال، تحقق من سجل الحركات أولًا.</p></div>@endif
    @if($page==='payments')@include('admin-views.customer.accounts.payment-tools')@endif
    <form method="get" class="ca-search" role="search">
        @if($customer)<input type="hidden" name="customer_id" value="{{ $customer->id }}">@endif
        <div><label for="ca-search">البحث عن عميل</label><input id="ca-search" name="q" value="{{ request('q') }}" placeholder="الاسم، رقم الحساب C000123، أو الهاتف" maxlength="100" class="form-control" type="search"></div>
        @if($page==='list')<div><label for="ca-status">حالة الحساب</label><select name="status" id="ca-status" class="form-control"><option value="">كل الحالات</option value="active" @selected(request('status')==='active')>نشط</option><option value="frozen" @selected(request('status')==='frozen')>موقوف</option></select></div>@endif
        <button class="btn btn-primary" type="submit"><i class="fi fi-rr-search" aria-hidden="true"></i>بحث</button><a class="btn btn-outline-secondary" href="{{ route('admin.customer.accounts.index',['page'=>$page,'customer_id'=>$customer?->id]) }}"><i class="fi fi-rr-refresh" aria-hidden="true"></i>مسح البحث</a>
    </form>
    @if($customer)
        <section class="ca-identity" aria-label="العميل المحدد"><div><span class="ca-type">عميل</span><h2><a href="{{ route('admin.customer.view',$customer->id) }}">{{ trim($customer->f_name.' '.$customer->l_name) }}</a></h2><p><bdi>{{ $customer->administrative_reference }}</bdi> · <bdi>{{ $customer->phone ?: '—' }}</bdi> · <bdi>{{ $customer->email ?: '—' }}</bdi></p></div><a class="btn btn-outline-primary" href="{{ route('admin.customer.view',$customer->id) }}">الملف الكامل</a></section>
        @if($page!=='list')<dl class="ca-summary"><div><dt>رصيد المحفظة</dt><dd><bdi>{{ $money($customer->wallet_balance) }}</bdi></dd></div><div><dt>المديونية المتبقية</dt><dd><bdi>{{ $money($debtTotal) }}</bdi></dd></div><div><dt>الباقة الحالية</dt><dd>{{ $active?->package_name ?: 'لا توجد باقة نشطة' }}</dd></div><div><dt>انتهاء الباقة</dt><dd><bdi>{{ $active?->expires_at?->format('Y/m/d H:i') ?: '—' }}</bdi></dd></div></dl>@endif
    @endif

    @if($page==='list' || (!$customer && (request()->filled('q'))))
        @include('admin-views.customer.accounts.customer-table')
    @endif

    @if($page==='list' && $customer)
        <section class="ca-panel"><h2>إجراءات الحساب</h2><p>التعليق لا يوقف احتساب مدة الباقة. حذف الحساب يغلق الوصول ويحفظ الأموال والتاريخ المالي.</p><div class="ca-actions">
        @foreach(['suspend'=>'تعليق الحساب','reactivate'=>'تنشيط الحساب','password'=>'تعيين كلمة مرور جديدة','close'=>'حذف الحساب وإغلاق الوصول'] as $op=>$label)
            @if(!$customer->account_deleted_at && ($op!=='suspend' || $customer->is_active) && ($op!=='reactivate' || !$customer->is_active) && $can($op==='password'?'password_reset':($op==='close'?'account_close':'account_manage')))
                <button type="button" class="btn {{ $op==='close'?'btn-outline-danger':'btn-outline-primary' }}" data-ca-open="ca-account-{{ $op }}"><i class="fi fi-rr-{{ ['suspend'=>'pause-circle','reactivate'=>'play-circle','password'=>'key','close'=>'trash'][$op] }}" aria-hidden="true"></i>{{ $label }}</button>
                <dialog id="ca-account-{{ $op }}" class="ca-dialog" aria-labelledby="ca-account-title-{{ $op }}"><form method="post" action="{{ route('admin.customer.accounts.account') }}" data-ca-financial>@csrf
                    <h2 id="ca-account-title-{{ $op }}">{{ $label }}</h2><p>عميل: {{ trim($customer->f_name.' '.$customer->l_name) }} · <bdi>{{ $customer->administrative_reference }}</bdi></p><input type="hidden" name="operation" value="{{ $op }}">
                    @include('admin-views.customer.accounts.confirmation')
                    @if($op==='password')<label for="ca-password">كلمة المرور الجديدة</label><input id="ca-password" class="form-control" type="password" name="password" minlength="8" autocomplete="new-password" required><label for="ca-password-confirm">تأكيد كلمة المرور</label><input id="ca-password-confirm" class="form-control" type="password" name="password_confirmation" minlength="8" autocomplete="new-password" required>@endif
                    @if($op==='close')<p class="text-danger">إغلاق نهائي للدخول؛ لا تُمحى الأرصدة أو الديون أو المشتريات. الطلبات الجارية تمنع الإغلاق.</p>@endif
                    <footer><button type="button" class="btn btn-secondary" data-ca-close>رجوع</button><button class="btn {{ $op==='close'?'btn-danger':'btn-primary' }}" type="submit">تأكيد {{ $label }}</button></footer>
                </form></dialog>
            @endif
        @endforeach</div></section>
    @endif

    @if($page==='payments' && $customer)
        @php($tabs=['active'=>'مديونيات العملاء','settled'=>'تم السداد','history'=>'سجل المديونيات','overview'=>'ملخص الحساب','requests'=>'طلبات الشحن','transactions'=>'سجل الحركات','refunds'=>'مبالغ المرتجعات'])
        @include('admin-views.customer.accounts.tabs')
        @if($tab==='overview')
            <section class="ca-panel"><h2>إدارة المحفظة</h2><p>الشحن الخارجي يبقى «جاري التحقق» حتى اعتماده في المدفوعات. تسدد الديون من أقدمها أولًا، ويضاف صافي الشحن فقط إلى المحفظة.</p><div class="ca-actions">
            @if($can('wallet_adjust'))<button class="btn btn-primary" type="button" data-ca-open="ca-manual-credit">إضافة رصيد</button>@endif
            <a class="btn btn-outline-primary" href="{{ route('admin.payment-reviews.index',['audience'=>'customer','kind'=>'balance_deposit','customer_id'=>$customer->id]) }}">مراجعة الشحن الخارجي</a><a class="btn btn-outline-primary" href="{{ route('admin.customer.accounts.index',['page'=>'debts','customer_id'=>$customer->id]) }}">التحقق من المديونية</a><a class="btn btn-outline-primary" href="{{ $accountLink($customer->id).'&tab=refunds' }}">إضافة قيمة مرتجع معتمد</a></div></section>
        @elseif($tab==='requests')
            <section class="ca-panel"><h2>طلبات الشحن</h2><div class="ca-table" role="region" tabindex="0" aria-label="طلبات الشحن"><table><thead><tr><th>المرجع</th><th>التاريخ</th><th>إجمالي الشحن</th><th>الحالة</th><th>تفاصيل السداد</th><th>المراجعة</th></tr></thead><tbody>@forelse($requests as $deposit)
            @php($allocations=$fundingAllocations->get('customer-offline-deposit-'.$deposit->id,collect()))
            <tr><td><bdi>{{ $deposit->transaction_number }}</bdi></td><td>{{ $deposit->created_at?->format('Y/m/d H:i') }}</td><td>{{ $money($deposit->amount) }}</td><td><span class="ca-status" data-state="{{ $deposit->status }}">{{ $statuses[$deposit->status]??$deposit->status }}</span></td><td>@if($deposit->status==='paid')تم استيفاء {{ $money($allocations->sum('amount')) }}؛ صافي المحفظة {{ $money((float)$deposit->amount-$allocations->sum('amount')) }}@foreach($allocations as $allocation)<details><summary>مديونية سابقة #{{ $allocation->debt_id }}</summary><p>{{ $fundingDebts->get($allocation->debt_id)?->reason }}</p><p>قبل الشحن {{ $money($allocation->debt_before) }}؛ المستوفى {{ $money($allocation->amount) }}؛ المتبقي {{ $money($allocation->debt_after) }}</p></details>@endforeach @else لا إضافة للمحفظة قبل القبول @endif</td><td><a href="{{ route('admin.payment-reviews.index',['audience'=>'customer','record_type'=>'customer_balance_deposit','record_id'=>$deposit->id,'customer_id'=>$customer->id]) }}">فتح في المدفوعات</a></td></tr>@empty<tr><td colspan="6" class="ca-empty">لم يقدم العميل طلبات شحن بعد.</td></tr>@endforelse</tbody></table></div>{{ $requests->links() }}</section>
        @elseif($tab==='transactions')
            <section class="ca-panel"><h2>سجل حركات المحفظة</h2><div class="ca-table" role="region" tabindex="0" aria-label="الحركات"><table><thead><tr><th>التاريخ</th><th>النوع</th><th>المرجع</th><th>دائن</th><th>مدين</th><th>الرصيد بعد الحركة</th></tr></thead><tbody>@forelse($transactions as $entry)<tr><td>{{ $entry->created_at?->format('Y/m/d H:i') }}</td><td>{{ $entry->transaction_type==='add_fund_by_admin'?'إضافة إدارية':($entry->transaction_type==='add_fund'?'شحن مؤكد':($entry->transaction_type==='monthly_subscription'?'معاملة باقة':$entry->transaction_type)) }}</td><td><bdi>{{ $entry->reference ?: $entry->transaction_id }}</bdi></td><td>{{ $money($entry->credit) }}</td><td>{{ $money($entry->debit) }}</td><td>{{ $money($entry->balance) }}</td></tr>@empty<tr><td colspan="6" class="ca-empty">لا توجد حركات محفظة.</td></tr>@endforelse</tbody></table></div>{{ $transactions->links() }}</section>
        @elseif($tab==='refunds')
            @include('admin-views.customer.accounts.returns')
            <section class="ca-panel"><h2>مبالغ المرتجعات</h2><p>الرد مرتبط بطلب قائم، ويضاف إلى المحفظة دون استيفاء الديون. بقية أنواع المرتجعات تستكمل من مسار الطلب الأصلي.</p><div class="ca-table" role="region" tabindex="0" aria-label="المرتجعات"><table><thead><tr><th>الطلب</th><th>المبلغ</th><th>الحالة</th><th>تاريخ الرد</th><th>المرجع</th><th>الإجراء</th></tr></thead><tbody>@forelse($refunds as $refund)<tr><td>#{{ $refund->order_id }}</td><td>{{ $money($refund->amount) }}</td><td>{{ $statuses[$refund->status]??$refund->status }}</td><td>{{ $refund->completed_at?->format('Y/m/d H:i') ?: '—' }}</td><td>{{ $refund->reference ?: '—' }}</td><td>@if($refund->status==='wallet_approved' && $can('refunds_manage'))<button type="button" class="btn btn-outline-primary" data-ca-open="ca-refund-{{ $refund->id }}">تأكيد الرد للمحفظة</button>@endif</td></tr>@empty<tr><td colspan="6" class="ca-empty">لا توجد طلبات رد في هذا المسار.</td></tr>@endforelse</tbody></table></div>{{ $refunds->links() }}</section>
            @foreach($refunds as $refund)@if($refund->status==='wallet_approved' && $can('refunds_manage'))<dialog id="ca-refund-{{ $refund->id }}" class="ca-dialog" aria-labelledby="ca-refund-title-{{ $refund->id }}"><form method="post" action="{{ route('admin.customer.accounts.refund',$refund->id) }}" data-ca-financial>@csrf<h2 id="ca-refund-title-{{ $refund->id }}">رد {{ $money($refund->amount) }} للعميل</h2><p>الطلب #{{ $refund->order_id }} · <bdi>{{ $customer->administrative_reference }}</bdi></p>@include('admin-views.customer.accounts.confirmation')<footer><button type="button" class="btn btn-secondary" data-ca-close>رجوع</button><button class="btn btn-primary">تأكيد رد المبلغ</button></footer></form></dialog>@endif
@endforeach
        @endif
    @endif

    @if($page==='debts' || ($page==='payments' && in_array($tab,['active','settled','history'],true)))
        @php($tabs=['active'=>'غير المسددة','settled'=>'تم السداد','history'=>'سجل الديون'])
        @if($page==='debts' || !$customer)@include('admin-views.customer.accounts.tabs')@endif
        <section class="ca-panel"><div class="ca-heading"><div><h2>{{ $tabs[$tab]??'الديون' }}</h2><p>استيفاء الأقدم أولًا من الشحن المؤكد فقط. الأرشفة تحفظ سجل الدين والسداد.</p></div>@if($customer && $can('debts_manage') && $debtPolicyReady)<button class="btn btn-primary" type="button" data-ca-open="ca-debt">إضافة مديونية</button>@endif</div>
        @if(!$customer)<p>ابحث برقم الحساب لعرض تاريخ العميل. <a href="#ca-search">ابحث واختر العميل لإضافة دين</a></p>@endif
        <div class="ca-table" role="region" tabindex="0" aria-label="الديون"><table><thead><tr><th>العميل</th><th>أصل الدين</th><th>السبب</th><th>تاريخ الإنشاء</th><th>المدفوع</th><th>المتبقي</th><th>الحالة والسداد</th></tr></thead><tbody>@forelse($rows as $debt)<tr><td><a href="{{ $accountLink($debt->customer_id) }}"><bdi>{{ $identity((object)['id'=>$debt->customer_id]) }}</bdi><br>{{ $debtAccounts->get($debt->customer_id)?->f_name }}</a></td><td>{{ $money($debt->amount) }}</td><td class="ca-wrap">{{ $debt->reason }}</td><td>{{ $debt->created_at }}</td><td>{{ $money($debt->paid_amount) }}</td><td>{{ $money((float)$debt->amount-(float)$debt->paid_amount) }}</td><td>{{ $debt->settled_at?'تم السداد':((float)$debt->paid_amount>0?'سداد جزئي':'غير مسدد') }}@if($debt->settled_at)<div>{{ $debt->settled_at }}</div>@endif
        <details><summary>تاريخ السداد والمنشئ</summary><p>أنشأه الموظف #{{ $debt->created_by_admin_id }}</p>@forelse($repayments->get($debt->id,collect()) as $repayment)<p>{{ $repayment->created_at }} · {{ $money($repayment->amount) }}<br><bdi>{{ $repayment->funding_reference }}</bdi></p>@empty<p>لا توجد دفعات بعد.</p>@endforelse</details>
        @if($debt->settled_at && !$debt->archived_at && $can('debts_manage'))<form method="post" action="{{ route('admin.customer.accounts.archive',$debt->id) }}" data-ca-financial>@csrf<input type="hidden" name="customer_id" value="{{ $debt->customer_id }}"><input type="hidden" name="confirmation" value="{{ $identity((object)['id'=>$debt->customer_id]) }}"><input type="hidden" name="request_key" value="{{ \Illuminate\Support\Str::uuid() }}"><input type="hidden" name="reason" value="أرشفة دين مسدد مع حفظ التاريخ"><button class="btn btn-sm btn-outline-primary">نقل إلى سجل الديون</button></form>@endif</td></tr>@empty<tr><td colspan="7" class="ca-empty">لا توجد ديون في هذه القائمة.</td></tr>@endforelse</tbody></table></div>{{ $rows->links() }}</section>
        @if($customer && $can('debts_manage') && $debtPolicyReady)<dialog id="ca-debt" class="ca-dialog" aria-labelledby="ca-debt-title"><form method="post" action="{{ route('admin.customer.accounts.debt') }}" data-ca-financial>@csrf<h2 id="ca-debt-title">إضافة مديونية</h2><p>عميل <bdi>{{ $customer->administrative_reference }}</bdi> · {{ $customer->f_name }}</p><label for="ca-debt-amount">المبلغ ({{ $currency }})</label><input id="ca-debt-amount" class="form-control" type="number" name="amount" min="0.001" max="10000000" step="0.001" required>@include('admin-views.customer.accounts.confirmation')<footer><button class="btn btn-secondary" type="button" data-ca-close>رجوع</button><button class="btn btn-primary">تأكيد تسجيل الدين</button></footer></form></dialog>@endif
    @endif

    @if($page==='subscriptions')
        @include('admin-views.customer.accounts.subscriptions')
    @endif
    @if($customer && $page!=='subscriptions')@include('admin-views.customer.accounts.action-history')@endif
</div>
@endsection
@push('script')<script src="{{ dynamicAsset(path:'public/assets/back-end/js/customer-accounts.js') }}?v=20261107.5" defer></script>@endpush

<section class="ca-panel"><h2>عمليات المدفوعات</h2><p>الشحن المؤكد يسدد أقدم الديون أولًا؛ التعديل الإداري ورصيد المرتجع لا يستوفيان الديون. طلبات العملاء تبقى «جاري التحقق» حتى مراجعتها.</p><div class="ca-actions">
@if($can('wallet_adjust'))<button type="button" class="btn btn-primary" data-ca-open="ca-manual-credit">إضافة رصيد / قيمة مرتجع</button>@endif
@if($can('debts_manage'))<button type="button" class="btn btn-outline-primary" data-ca-open="ca-manual-debt">إضافة مديونية جديدة</button>@endif
<a class="btn btn-outline-primary" href="{{ route('admin.payment-reviews.index',['audience'=>'customer','kind'=>'balance_deposit','customer_id'=>$customer?->id]) }}">التحقق من طلبات الشحن</a></div></section>
@foreach(['credit'=>'إضافة رصيد أو قيمة مرتجع','debt'=>'إضافة مديونية'] as $tool=>$toolLabel)
@if($can($tool==='credit'?'wallet_adjust':'debts_manage'))
<dialog id="ca-manual-{{ $tool }}" class="ca-dialog" aria-label="{{ $toolLabel }}"><form method="post" action="{{ route('admin.customer.accounts.'.$tool) }}" data-ca-financial>@csrf<h2>{{ $toolLabel }}</h2>
<label>ابحث بالاسم أو رقم الحساب أو الهاتف<input class="form-control" type="search" data-ca-lookup="{{ route('admin.customer.accounts.index',['page'=>'payments','lookup'=>1]) }}" autocomplete="off"></label><div data-ca-results role="list" aria-live="polite"></div><p data-ca-selected role="status">لم يتم اختيار عميل.</p><input type="hidden" name="customer_id" required><input type="hidden" name="confirmation"><input type="hidden" name="request_key" value="{{ \Illuminate\Support\Str::uuid() }}">
@if($tool==='credit')<label>نوع الإضافة<select class="form-control" name="credit_kind"><option value="confirmed_payment">شحن مؤكد — يستوفي الدين أولًا</option><option value="adjustment">تعديل إداري — دون استيفاء الدين</option>@if($can('refunds_manage'))<option value="return_credit">رصيد مرتجع معتمد — دون استيفاء الدين</option>@endif</select></label><label>مرجع التحويل المؤكد أو اعتماد المرتجع «مطلوب لهما»<input name="approval_reference" class="form-control" maxlength="191"></label>@endif
<label>المبلغ ({{ $currency }})<input class="form-control" name="amount" type="number" step="0.001" min="0.001" max="10000000" required></label><label>السبب<textarea class="form-control" name="reason" maxlength="2000" required></textarea></label>
@if($tool==='credit')<label class="ca-check"><input type="checkbox" name="verified_approval" value="1" required> أؤكد اختيار الحساب الصحيح والتحقق من الدفع أو اعتماد التعديل/المرتجع قبل الإضافة.</label>@endif
<footer><button class="btn btn-secondary" type="button" data-ca-close>رجوع</button><button class="btn btn-primary" type="submit">تأكيد {{ $toolLabel }}</button></footer></form></dialog>
@endif
@endforeach

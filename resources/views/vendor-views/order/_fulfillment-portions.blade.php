@if($row['fulfillment_eligibility']['portion_actions_enabled'] ?? false)
<section class="vf-section" dir="rtl"><h3>قرارات وأجزاء طلب التنفيذ</h3><p>راجع التفاصيل والمستحقات قبل القبول. الاشتراك وإتاحة التفاصيل لا يعدان قبولًا.</p>
@foreach($row['fulfillment_eligibility']['portions'] as $portion)
<section class="border rounded p-3 mb-3"><h4 class="h5">جزء التنفيذ #{{ $portion['id'] }} — {{ $portion['status_label'] ?? $portion['status'] }}</h4><p>مستحق هذا الجزء: {{ webCurrencyConverter($portion['amount']) }}</p>
@if($portion['can_request_unlock'] ?? false)<a class="btn btn-primary" href="{{ route('vendor.fulfillment.quote',$portion['id']) }}">تخصيص المساحة وعرض التفاصيل</a> <a class="btn btn-outline-primary" href="{{ route('vendor.packages.index') }}">اختيار باقة</a>
@else
<div class="d-flex flex-wrap mb-3" style="gap:8px">@foreach(['available'=>'مراجعة','accepted'=>'قبول','preparing'=>'تجهيز','ready'=>'جاهز','shipping'=>'شحن','delivered'=>'تسليم'] as $step=>$stepLabel)<span class="badge {{ $portion['status']===$step?'bg-primary text-white':'bg-light text-dark' }}">{{ $stepLabel }}</span>@endforeach</div>
@if($portion['status'] === 'accepted' && count($portion['line_ids'] ?? []) > 1)
<form method="post" class="mb-2" action="{{ route('vendor.fulfillment.decide',$portion['id']) }}">@csrf<input type="hidden" name="action" value="separate"><label>اختر المنتج لفصله ومتابعة إجراءاته<select class="form-control my-2" name="line_id" required><option value="">اختر المنتج</option>@foreach($portion['line_ids'] as $lineId)@php($lineInfo = collect($row['order_details'] ?? [])->firstWhere('id', $lineId))<option value="{{ $lineId }}">{{ data_get($lineInfo, 'product_details.name', 'منتج الطلب') }} #{{ $lineId }}</option>@endforeach</select></label><button class="btn btn-outline-primary">فصل المنتج</button></form>
@else
@foreach(($portion['actions'] ?? []) as $action)
<form method="post" class="mb-2" action="{{ route('vendor.fulfillment.decide',$portion['id']) }}">@csrf<input type="hidden" name="action" value="{{ $action }}">
@if($portion['status'] === 'available' && count($portion['line_ids'] ?? []) > 1)<label>اختر المنتج لهذا القرار<select class="form-control my-2" name="line_id" required><option value="">اختر المنتج</option>@foreach($portion['line_ids'] as $lineId)@php($lineInfo = collect($row['order_details'] ?? [])->firstWhere('id', $lineId))<option value="{{ $lineId }}">{{ data_get($lineInfo, 'product_details.name', 'منتج الطلب') }} #{{ $lineId }}</option>@endforeach</select></label>@endif
@if(in_array($action,['reject','cancel'],true))<label>سبب {{ $action==='reject'?'الرفض قبل القبول':'الإلغاء بعد القبول' }}<input class="form-control my-2" name="reason" maxlength="1000" required></label>@endif
@if($action==='cancel')<input type="hidden" name="penalty_revision" value="{{ $portion['cancellation_preview']['revision'] }}"><p>الغرامة الحالية: {{ number_format((float)$portion['cancellation_preview']['assessed_amount'],2) }} ج.م. تخصم من رصيد المدفوعات ويسجل العجز دينًا. @if(($portion['cancellation_preview']['mode']??'fixed')==='percentage') النسبة {{ $portion['cancellation_preview']['percentage'] }}٪ من مستحق بضائع هذا الجزء دون الشحن والضرائب وهامش الشركة. @else مبلغ ثابت بالجنيه لهذا الجزء. @endif</p>@endif
<button class="btn {{ in_array($action,['cancel','reject'],true)?'btn-outline-danger':'btn-outline-primary' }}">{{ ['accept'=>'قبول صريح للجزء','reject'=>'رفض قبل القبول — دون غرامة','preparing'=>'بدء التجهيز','ready'=>'جاهز للتسليم للشحن','shipping'=>'بدء الشحن','cancel'=>'تأكيد الإلغاء بالغرامة المعروضة'][$action] }}</button></form>
@endforeach
@endif
@if($portion['delivery_proof_required'] ?? false)<form method="post" enctype="multipart/form-data" action="{{ route('vendor.fulfillment.proof',$portion['id']) }}">@csrf<label>إثبات تسليم هذا الجزء<input class="form-control my-2" type="file" name="proof" accept="image/*,application/pdf,video/mp4" required></label><button class="btn btn-primary">إرفاق الإثبات وتأكيد التسليم</button></form>@endif
@if(!empty($portion['accepted_at']))<p class="text-muted">القبول الصريح: {{ \Carbon\Carbon::parse($portion['accepted_at'])->format('Y-m-d H:i') }}</p>@endif
@if(!empty($portion['penalty']))<p>الغرامة المسجلة: {{ number_format((float)$portion['penalty']['assessed_amount'],2) }} ج.م — المحصل: {{ number_format((float)($portion['penalty']['wallet_collected']??0),2) }} — الدين: {{ number_format((float)($portion['penalty']['debt_assessed']??0),2) }}.</p>@endif
@endif</section>@endforeach</section>
@endif

@extends('layouts.admin.app')
@section('title','استعادة كلمة مرور العملاء')
@section('content')
<div class="content container-fluid"><h1 class="h2 mb-3">طلبات استعادة كلمة المرور</h1>
<p>تحقق من صاحب الحساب وسجّل طريقة التحقق قبل إصدار كلمة مرور مؤقتة. لا تظهر كلمة المرور في السجل أو للإدارة.</p>
<p>الكلمة المؤقتة تُرسل مشفرة إلى محادثة الدعم التي قدمت الطلب. العميل يستلمها وهو خارج الحساب، ثم يغيّرها فور الدخول.</p>
@if(session('recovery_message'))<div role="status" class="alert alert-success">{{ session('recovery_message') }}</div>@endif
@if($errors->any())<div role="alert" class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="card"><div class="table-responsive"><table class="table"><thead><tr><th>الطلب والتاريخ</th><th>العميل</th><th>الحالة</th><th>التحقق والإصدار</th></tr></thead><tbody>
@forelse($requests as $item)<tr><td>#{{ $item->id }}<br><bdi>{{ $item->created_at }}</bdi></td><td>{{ $item->f_name }}<br><bdi>{{ $item->phone }}</bdi><br>{{ $item->email }}</td>
<td>{{ ['pending'=>'بانتظار التحقق','verified'=>'تم التحقق','issued'=>'تم الإرسال — تغيير إلزامي','completed'=>'تم تغيير الكلمة','expired'=>'انتهت الصلاحية'][$item->status]??$item->status }}<br>{{ $item->verification_note }}</td>
<td><a href="{{ route('admin.contact.view',$item->contact_id) }}" class="d-block mb-2">فتح محادثة الدعم والتحقق من العميل</a>
@if(!$item->customer_id)<p>لم تتطابق البيانات مع حساب نشط. راجع البيانات مع صاحب المحادثة.</p>
@elseif($item->status==='pending')<form method="post" action="{{ route('admin.customer.password-recovery.verify',$item->id) }}">@csrf<label for="verify-note-{{ $item->id }}">طريقة التحقق من صاحب الحساب</label><textarea id="verify-note-{{ $item->id }}" name="verification_note" class="form-control" minlength="10" maxlength="2000" required></textarea><label class="d-block my-2"><input type="checkbox" name="identity_verified" value="1" required> تحققت من أن مقدم الطلب صاحب الحساب</label><button class="btn btn--primary">تسجيل التحقق</button></form>
@elseif($item->status==='verified')<form method="post" action="{{ route('admin.customer.password-recovery.issue',$item->id) }}">@csrf<button class="btn btn--primary">إصدار وإرسال كلمة مؤقتة في الشات</button></form>@else — @endif</td></tr>
@empty<tr><td colspan="4">لا توجد طلبات استعادة حتى الآن</td></tr>@endforelse
</tbody></table></div>{{ $requests->links() }}</div></div>
@endsection

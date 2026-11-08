@php($timeline = app(\App\Services\OrderTimelineService::class)->forOrder($order))
<div class="card mb-3" dir="rtl">
    <div class="card-header"><h4 class="mb-0">السجل الزمني الموحد</h4></div>
    @if(\Illuminate\Support\Facades\Schema::hasTable('order_communication_links'))
    <details class="card-body border-bottom"><summary>ربط رسالة أو محادثة موجودة بالطلب</summary>
        @if($errors->has('resource_id') || $errors->has('channel'))<div class="alert alert-danger mt-2">{{ $errors->first('resource_id') ?: $errors->first('channel') }}</div>@endif
        <form method="post" action="{{ route('admin.orders.communication-links.store', $order->id) }}" class="row g-2 mt-2">@csrf
            <div class="col-md-4"><label class="form-label">نوع المحادثة</label><select name="channel" class="form-control" required><option value="support_ticket">تذكرة دعم العميل</option><option value="vendor_thread">محادثة دعم التاجر</option><option value="chat_message">رسالة محادثة العميل</option></select></div>
            <div class="col-md-4"><label class="form-label">رقم المحادثة أو الرسالة</label><input type="number" min="1" name="resource_id" class="form-control" required></div>
            <div class="col-md-4 d-flex align-items-end"><button class="btn btn-outline-primary">ربط بالسجل</button></div>
        </form>
    </details>
    @endif
    <div class="table-responsive"><table class="table mb-0"><thead><tr><th>الوقت</th><th>المصدر والحدث</th><th>المنفذ</th><th>الحالة قبل / بعد</th><th>التفاصيل المسجلة</th></tr></thead><tbody>
        @forelse($timeline as $entry)
        <tr><td>{{ $entry['at'] }}</td><td>{{ $entry['source'] }}<div class="small text-muted">{{ $entry['type'] }}</div></td><td>{{ $entry['actor'] ?? '—' }} {{ $entry['actorId'] ? '#'.$entry['actorId'] : '' }}</td><td>{{ $entry['before'] ?? '—' }} / {{ $entry['after'] ?? '—' }}</td><td>{{ $entry['note'] ?? '' }} @if($entry['snapshot'])<details><summary>عرض البيانات المسجلة</summary><pre dir="auto" class="text-wrap">{{ json_encode($entry['snapshot'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE) }}</pre></details>@endif</td></tr>
        @empty<tr><td colspan="5" class="text-center">لا توجد حركات مسجلة.</td></tr>@endforelse
    </tbody></table></div>
</div>

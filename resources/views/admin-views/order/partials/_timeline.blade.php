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
    <div class="card-body">
        <p class="text-muted">{{ $timeline->count() }} حركة · الأحدث أولًا · افتح الحركة لعرض التفاصيل والقيم المسجلة.</p>
        @forelse($timeline as $entry)
        <details class="border rounded mb-2">
            <summary class="p-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
                <span><strong>{{ $entry['source'] }}</strong><span class="small text-muted ms-2">{{ translate($entry['type'] ?? '') }}</span></span>
                <time class="small text-muted" dir="ltr">{{ $entry['at'] }}</time>
            </summary>
            <div class="p-3 border-top">
                <div class="d-flex flex-wrap gap-3 mb-2">
                    <span>المنفذ: {{ ['admin'=>'الإدارة','seller'=>'التاجر','customer'=>'العميل','system'=>'النظام'][$entry['actor'] ?? 'system'] ?? $entry['actor'] }} {{ $entry['actorId'] ? '#'.$entry['actorId'] : '' }}</span>
                    @if($entry['before'] || $entry['after'])<span>الحالة: <span class="badge bg-secondary">{{ translate($entry['before'] ?? '—') }}</span> ← <span class="badge bg-primary">{{ translate($entry['after'] ?? '—') }}</span></span>@endif
                </div>
                @if($entry['note'])<p class="mb-2 text-break">{{ $entry['note'] }}</p>@endif
                @if($entry['snapshot'])<details><summary class="text-primary">البيانات والتغييرات المالية المحفوظة</summary><pre dir="auto" class="p-3 mt-2 border rounded text-wrap text-break small">{{ json_encode($entry['snapshot'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE) }}</pre></details>@endif
            </div>
        </details>
        @empty<p class="text-center text-muted py-3">لا توجد حركات مسجلة.</p>@endforelse
    </div>
</div>
@php
    $__customerUnread = 0;
    if (auth('customer')->check()) {
        try {
            $__customerUnread = (int) app(\App\Services\CustomerCommunicationService::class)->unreadMessageCount((int) auth('customer')->id());
        } catch (\Throwable $e) {
            $__customerUnread = 0;
        }
    }
@endphp
@if($__customerUnread > 0)
    <span class="customer-unread-badge" role="status" aria-label="{{ $__customerUnread }}"
          style="position:absolute;top:-6px;inset-inline-end:-6px;min-width:20px;height:20px;padding:0 5px;border-radius:10px;background:#e53935;color:#fff;font-size:12px;line-height:20px;text-align:center;font-weight:700;pointer-events:none;">{{ $__customerUnread > 99 ? '99+' : $__customerUnread }}</span>
@endif

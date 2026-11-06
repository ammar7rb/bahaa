<div class="vendor-inbox-empty">
    <span class="vendor-empty-icon">@include('layouts.vendor.partials.v2._workspace-icon', ['icon'=>$channel])</span>
    <h2>{{ translate($channel === 'messages' ? 'vendor_no_conversations' : 'vendor_no_notifications') }}</h2>
    <p>{{ translate($channel === 'messages' ? 'vendor_no_conversations_hint' : 'vendor_no_notifications_hint') }}</p>
</div>

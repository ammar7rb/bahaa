@php
    $workspaceConfig = ['notificationEmpty'=>translate('inbox_empty'), 'notificationError'=>translate('inbox_error'), 'notificationLoading'=>translate('inbox_loading'), 'notificationRead'=>route('admin.workspace.notifications.read'), 'notifications'=>route('admin.workspace.notifications'), 'serverNow' => now()->getTimestampMs(), 'timeZone' => config('app.timezone'), 'snapshot' => route('admin.workspace.snapshot'), 'empty' => translate('workspace_search_empty'), 'pendingEmpty' => translate('workspace_no_pending'), 'loading' => translate('workspace_loading'), 'error' => translate('workspace_search_error'), 'imageFallback'=>dynamicAsset(path:'public/assets/back-end/img/placeholder/product.png')];
@endphp
<script>
window.adminWorkspaceConfig = @json($workspaceConfig);
</script>
<script src="{{ dynamicAsset(path: 'public/assets/back-end/js/admin-workspace.js') }}?v={{ substr(hash_file('sha256', public_path('assets/back-end/js/admin-workspace.js')), 0, 16) }}"></script>

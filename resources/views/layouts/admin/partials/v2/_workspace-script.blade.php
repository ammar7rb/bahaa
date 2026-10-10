<script>
window.adminWorkspaceConfig = @json(['snapshot' => route('admin.workspace.snapshot'), 'empty' => translate('workspace_search_empty'), 'pendingEmpty' => translate('workspace_no_pending'), 'loading' => translate('workspace_loading'), 'error' => translate('workspace_search_error')]);
</script>
<script src="{{ dynamicAsset(path: 'public/assets/back-end/js/admin-workspace.js') }}?v=20261010.2"></script>

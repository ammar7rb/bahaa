<div class="modal-header border-0 pb-0">
    <h5 class="modal-title">{{ translate('Notifications') }}</h5>
    <button type="button" class="btn-close border-0" data-dismiss="modal" aria-label="{{ translate('close') }}"><i class="tio-clear"></i></button>
</div>
<div class="modal-body px-4 px-sm-5 text-center">
    <div class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle bg-soft-primary text-primary" style="width:72px;height:72px">
        <i class="fi fi-sr-bell-notification-social-media fs-28"></i>
    </div>
    <span class="badge badge-soft-primary mb-2">{{ translate('new') }}</span>
    <h3 class="mb-2">{{ translate($data->title) }}</h3>
    <p class="text-muted mb-3">{{ $data->created_at->diffForHumans() }}</p>
    <div class="rounded bg-light p-3 mb-4 text-start">
        {{ translate($data->description) }}
    </div>
    @if($actionUrl)
        <a class="btn btn--primary px-5" href="{{ $actionUrl }}">
            <i class="fi fi-sr-arrow-up-right-from-square me-1"></i>{{ $actionLabel }}
        </a>
    @else
        <button type="button" class="btn btn-outline-secondary px-5" data-dismiss="modal">{{ translate('close') }}</button>
    @endif
</div>

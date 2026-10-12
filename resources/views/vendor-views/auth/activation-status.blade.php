@extends('layouts.front-end.app')

@section('title', translate('account_activation'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('layouts.vendor.partials._dark-mode-preference')
    <link rel="stylesheet" href="{{ dynamicAsset(path: 'public/assets/back-end/css/vendor-dark-mode.css') }}">
    <link rel="stylesheet" href="{{ dynamicAsset(path: 'public/assets/front-end/css/vendor-registration-dark-mode.css') }}">
@endpush

@section('content')
    <div class="container py-5 text-align-direction vendor-public-page">
        @include('layouts.vendor.partials._dark-mode-toggle')
        <div class="row justify-content-center">
            <div class="col-lg-8">
                @if($vendor->activation_status === 'active')
                    <div id="activation-status-banner" class="alert alert-success d-flex justify-content-between align-items-center flex-wrap gap-2" role="status">
                        <div>
                            <strong>{{ translate('registration_approved') }}</strong>
                            <div class="small">{{ translate('your_registration_is_successful') }}</div>
                        </div>
                        <a class="btn btn-success" href="{{ route('vendor.dashboard.index') }}">{{ translate('continue') }}</a>
                    </div>
                @else
                    <div id="activation-status-banner" class="alert alert-danger d-flex flex-wrap justify-content-between align-items-center gap-2" role="alert">
                        <div>
                            <strong>{{ translate('account_activation') }}</strong>
                            <div>{{ translate('seller_activation_required') }}</div>
                        </div>
                        @if($ticket)
                            <span class="badge badge-danger">{{ translate('activation_ticket_open') }}</span>
                        @endif
                    </div>
                @endif

                <div class="card shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center gap-2">
                        <div>
                            <h4 class="mb-1">{{ translate('support_ticket') }}</h4>
                            <span class="text-muted small">{{ $ticket ? ('#' . $ticket->id . ' · ' . translate($ticket->status)) : translate('please-wait_for_admin_approval') }}</span>
                        </div>
                        @if($ticket)
                            <span class="badge badge-{{ $ticket->isOpen() ? 'warning' : ($ticket->status === 'approved' ? 'success' : 'secondary') }}">{{ translate($ticket->status) }}</span>
                        @endif
                    </div>
                    <div class="card-body">
                        @if($supportTicketRequired && $ticket)
                            <div id="activation-live-messages" class="border rounded p-3 mb-3 bg-light" style="max-height: 360px; overflow-y: auto;">
                                @forelse($ticket->messages as $message)
                                    <div class="mb-3 {{ $message->sender_type === 'seller' ? 'text-right' : '' }}">
                                        <div class="d-inline-block rounded p-2 {{ $message->sender_type === 'seller' ? 'bg-primary text-white' : 'bg-white border' }}">
                                            {{ $message->body }}
                                            @if(count($message->attachment_full_url))
                                                <div class="d-flex flex-wrap gap-2 mt-2">
                                                    @foreach($message->attachment_full_url as $attachment)
                                                        <a class="text-reset border rounded p-1" href="{{ $attachment['url'] }}" target="_blank">
                                                            @if(str_starts_with($attachment['mime_type'], 'image/'))
                                                                <img src="{{ $attachment['url'] }}" width="64" height="64" style="object-fit:cover" alt="{{ $attachment['name'] }}">
                                                            @else
                                                                {{ $attachment['name'] }}
                                                            @endif
                                                        </a>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                        <div class="small text-muted mt-1">{{ translate($message->sender_type) }} · {{ $message->created_at?->format('Y-m-d H:i') }}</div>
                                    </div>
                                @empty
                                    <p class="text-muted mb-0">{{ translate('no_ticket_found') }}</p>
                                @endforelse
                            </div>

                            @if($ticket->isOpen() && $vendor->activation_status !== 'active')
                                <form id="activation-live-form" method="post" enctype="multipart/form-data" action="{{ route('vendor.auth.registration.activation.message') }}">
                                    @csrf
                                    <label for="activation-message">{{ translate('send_a_message') }}</label>
                                    <div class="input-group mb-2">
                                        <textarea id="activation-message" class="form-control" name="body" rows="2" maxlength="2000" required placeholder="{{ translate('type_your_message_here') }}"></textarea>
                                        <div class="input-group-append">
                                            <button class="btn btn--primary" type="submit">{{ translate('send') }}</button>
                                        </div>
                                    </div>
                                    <input class="form-control" type="file" name="attachments[]" multiple accept="image/jpeg,image/png,image/webp,application/pdf">
                                    <small class="text-muted">{{ translate('activation_attachments_help') }}</small>
                                </form>
                            @endif
                        @else
                            <p class="mb-0 text-muted">{{ translate('please-wait_for_admin_approval') }}</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script')
    <script src="{{ dynamicAsset(path: 'public/assets/back-end/js/vendor-dark-mode.js') }}"></script>
@endpush

@push('script')
    @if($vendor->activation_status === 'active')
        <script>
            setTimeout(function () { window.location.href = @json(route('vendor.dashboard.index')); }, 3500);
        </script>
    @else
        <script>
            (function () {
                const box = document.getElementById('activation-live-messages');
                const form = document.getElementById('activation-live-form');
                const endpoint = @json(route('vendor.auth.registration.activation.messages'));
                const statusBanner = document.getElementById('activation-status-banner');
                const approvedLabel = @json(translate('registration_approved'));
                const approvedMessage = @json(translate('your_registration_is_successful'));
                const continueLabel = @json(translate('continue'));
                const csrfToken = () => form?.querySelector('input[name="_token"]')?.value
                    || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                    || '';
                let ready = false, seen = new Set();
                const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c]));
                function attachmentMarkup(attachments) {
                    return (attachments || []).map(function (attachment) {
                        const url = escapeHtml(attachment.url || '');
                        const name = escapeHtml(attachment.name || 'attachment');
                        if (!url) return '';
                        if (String(attachment.mime_type || '').startsWith('image/')) {
                            return '<a class="text-reset border rounded p-1" href="' + url + '" target="_blank" rel="noopener"><img src="' + url + '" width="64" height="64" style="object-fit:cover" alt="' + name + '"></a>';
                        }
                        return '<a class="text-reset border rounded p-1" href="' + url + '" target="_blank" rel="noopener">' + name + '</a>';
                    }).join('');
                }
                function append(message) {
                    if (!box || seen.has(String(message.id))) return;
                    seen.add(String(message.id));
                    const mine = message.sender_type === 'seller';
                    const item = document.createElement('div');
                    item.className = 'mb-3 ' + (mine ? 'text-right' : '');
                    item.innerHTML = '<div class="d-inline-block rounded p-2 ' + (mine ? 'bg-primary text-white' : 'bg-white border') + '">' + escapeHtml(message.body).replace(/\n/g, '<br>') + '</div><div class="small text-muted mt-1">' + escapeHtml(message.sender_type) + ' · ' + escapeHtml(message.created_at) + '</div>';
                    if (message.attachments?.length) {
                        const body = item.querySelector('.d-inline-block');
                        if (body) {
                            const attachments = document.createElement('div');
                            attachments.className = 'd-flex flex-wrap gap-2 mt-2';
                            attachments.innerHTML = attachmentMarkup(message.attachments);
                            body.appendChild(attachments);
                        }
                    }
                    box.appendChild(item);
                    box.scrollTop = box.scrollHeight;
                }
                function showActivated(data) {
                    if (!statusBanner || data.activation_status !== 'active') return;
                    statusBanner.className = 'alert alert-success d-flex justify-content-between align-items-center flex-wrap gap-2';
                    statusBanner.setAttribute('role', 'status');
                    statusBanner.innerHTML = '<div><strong>' + escapeHtml(approvedLabel) + '</strong><div class="small">' + escapeHtml(approvedMessage) + '</div></div><a class="btn btn-success" href="' + escapeHtml(data.dashboard_url) + '">' + escapeHtml(continueLabel) + '</a>';
                    form && form.remove();
                    window.setTimeout(() => { window.location.href = data.dashboard_url; }, 3500);
                }
                async function refresh() {
                    try {
                        const response = await fetch(endpoint, {
                            credentials: 'same-origin',
                            headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}
                        });
                        if (!response.ok) return;
                        const data = await response.json();
                        showActivated(data);
                        if (!ready) { data.messages.forEach(message => seen.add(String(message.id))); ready = true; return; }
                        data.messages.forEach(append);
                    } catch (_) {}
                }
                if (form) form.addEventListener('submit', async function (event) {
                    event.preventDefault();
                    const button = form.querySelector('button[type="submit"]');
                    button && (button.disabled = true);
                    try {
                        const token = csrfToken();
                        const payload = new FormData(form);
                        if (!payload.get('_token') && token) payload.append('_token', token);
                        const response = await fetch(form.action, {
                            method: 'POST',
                            body: payload,
                            credentials: 'same-origin',
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': token
                            }
                        });
                        if (response.status === 419) {
                            window.location.reload();
                            return;
                        }
                        if (!response.ok) {
                            let message = @json(translate('something_went_wrong'));
                            try {
                                const error = await response.json();
                                message = error.message || Object.values(error.errors || {}).flat().join(' ') || message;
                            } catch (_) {}
                            throw new Error(message);
                        }
                        const data = await response.json();
                        showActivated(data);
                        ready = true; data.messages.forEach(append); form.reset();
                    } catch (error) {
                        if (error?.message) window.alert(error.message);
                    }
                    finally { button && (button.disabled = false); }
                });
                refresh(); window.setInterval(refresh, 7000);
            })();
        </script>
    @endif
@endpush

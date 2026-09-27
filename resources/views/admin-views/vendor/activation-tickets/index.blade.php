@extends('layouts.admin.app')

@section('title', translate('seller_activation_requests'))

@section('content')
<div class="content container-fluid">
    @php($statuses = ['open', 'awaiting_seller', 'under_review', 'approved', 'rejected', 'closed'])
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h2 class="mb-1">{{ translate('seller_activation_requests') }}</h2>
            <p class="text-muted mb-0">{{ translate('seller_activation_requests_help') }}</p>
        </div>
        <a class="btn btn-outline-primary" href="{{ route('admin.support-ticket.view', ['purpose' => 'account_activation']) }}">
            {{ translate('customer_activation_requests') }}
        </a>
    </div>

    <form class="card card-body mb-3" method="get">
        <div class="row g-2">
            <div class="col-md-6"><input class="form-control" name="q" value="{{ request('q') }}" placeholder="{{ translate('search_by_ticket_email_or_phone') }}"></div>
            <div class="col-md-4"><select class="form-control" name="status"><option value="">{{ translate('all_statuses') }}</option>@foreach($statuses as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ translate($status) }}</option>@endforeach</select></div>
            <div class="col-md-2"><button class="btn btn-primary w-100">{{ translate('search') }}</button></div>
        </div>
    </form>

    @forelse($tickets as $ticket)
        <section class="card mb-3" id="ticket-{{ $ticket->id }}">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <strong>{{ $ticket->seller?->administrative_reference ?? ('V'.$ticket->seller_id) }}</strong>
                    <span class="text-muted">#{{ $ticket->id }}</span>
                    <div class="small text-muted">{{ $ticket->seller?->email }} · {{ $ticket->seller?->phone }}</div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge badge-{{ $ticket->status === 'approved' ? 'success' : ($ticket->isOpen() ? 'warning' : 'secondary') }}">{{ translate($ticket->status) }}</span>
                    <a class="btn btn-outline-secondary btn-sm" target="_blank" href="{{ route('admin.vendors.activation-tickets.print', $ticket->id) }}"><i class="fi fi-sr-print"></i> {{ translate('print_conversation') }}</a>
                </div>
            </div>
            <div class="card-body">
                <div class="border rounded p-3 mb-3 bg-light" style="max-height:380px;overflow-y:auto">
                    @forelse($ticket->messages as $message)
                        <div class="mb-3 {{ $message->sender_type === 'seller' ? 'text-end' : '' }}">
                            <div class="d-inline-block bg-white border rounded px-3 py-2 text-start">
                                @if($message->body)<div>{{ $message->body }}</div>@endif
                                @if(count($message->attachment_full_url))
                                    <div class="d-flex flex-wrap gap-2 mt-2">
                                        @foreach($message->attachment_full_url as $attachment)
                                            <a href="{{ $attachment['url'] }}" target="_blank" class="border rounded p-1 d-inline-flex align-items-center gap-1">
                                                @if(str_starts_with($attachment['mime_type'], 'image/'))<img src="{{ $attachment['url'] }}" alt="" width="54" height="54" style="object-fit:cover">@else<i class="fi fi-sr-document"></i>@endif
                                                <small>{{ $attachment['name'] }}</small>
                                            </a>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                            <div class="small text-muted mt-1">{{ translate($message->sender_type) }} · {{ $message->created_at?->format('Y-m-d H:i') }}</div>
                        </div>
                    @empty
                        <div class="text-muted">{{ translate('no_messages') }}</div>
                    @endforelse
                </div>

                @if($ticket->isOpen())
                    <form method="post" enctype="multipart/form-data" action="{{ route('admin.vendors.activation-tickets.reply', $ticket->id) }}" class="border rounded p-3 mb-3">
                        @csrf
                        <label class="form-label">{{ translate('send_message_to_seller') }}</label>
                        <textarea class="form-control mb-2" name="body" maxlength="2000" placeholder="{{ translate('type_your_message_here') }}"></textarea>
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <input class="form-control flex-grow-1" type="file" name="attachments[]" multiple accept="image/jpeg,image/png,image/webp,application/pdf">
                            <button class="btn btn-primary">{{ translate('send') }}</button>
                        </div>
                        <small class="text-muted">{{ translate('activation_attachments_help') }}</small>
                    </form>
                @endif

                <div class="d-flex flex-wrap gap-2">
                    @if($ticket->isOpen())
                        <form method="post" action="{{ route('admin.vendors.activation-tickets.approve', $ticket->id) }}" onsubmit="return confirm(@js(translate('confirm_seller_activation')))" class="d-inline">@csrf<button class="btn btn-success">{{ translate('approve_and_activate') }}</button></form>
                        <form method="post" action="{{ route('admin.vendors.activation-tickets.suspend', $ticket->id) }}" class="d-inline">@csrf<button class="btn btn-warning">{{ translate('suspend') }}</button></form>
                    @endif
                    <details><summary class="btn btn-outline-secondary">{{ translate('more_actions') }}</summary><div class="card card-body mt-2" style="min-width:320px">
                        <form method="post" action="{{ route('admin.vendors.activation-tickets.request-completion', $ticket->id) }}">@csrf<textarea class="form-control mb-2" name="note" required placeholder="{{ translate('request_completion_from_seller') }}"></textarea><button class="btn btn-info btn-sm">{{ translate('request_completion') }}</button></form>
                        <form class="mt-3" method="post" action="{{ route('admin.vendors.activation-tickets.reject', $ticket->id) }}">@csrf<textarea class="form-control mb-2" name="note" required placeholder="{{ translate('rejection_reason') }}"></textarea><button class="btn btn-danger btn-sm">{{ translate('reject') }}</button></form>
                        <form class="mt-3" method="post" action="{{ route('admin.vendors.activation-tickets.assign', $ticket->id) }}">@csrf<input class="form-control mb-2" type="number" min="1" name="admin_id" value="{{ $ticket->assigned_admin_id }}" required placeholder="{{ translate('staff_id') }}"><button class="btn btn-secondary btn-sm">{{ translate('assign') }}</button></form>
                        <form class="mt-3" method="post" action="{{ route('admin.vendors.activation-tickets.close', $ticket->id) }}">@csrf<textarea class="form-control mb-2" name="note" placeholder="{{ translate('closing_note_optional') }}"></textarea><button class="btn btn-dark btn-sm">{{ translate('close') }}</button></form>
                    </div></details>
                </div>
            </div>
        </section>
    @empty
        <div class="card card-body text-center text-muted">{{ translate('no_matching_tickets') }}</div>
    @endforelse
    <div>{{ $tickets->links() }}</div>
</div>
@endsection

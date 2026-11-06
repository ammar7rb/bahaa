<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ session('direction', 'ltr') }}">
<head>
    <meta charset="utf-8">
    <title>{{ translate('support_conversation') }} #{{ $ticket->id }}</title>
    <style>
        body{font-family:Arial,sans-serif;color:#111;margin:28px}.head{border-bottom:2px solid #222;padding-bottom:12px;margin-bottom:18px}.message{border:1px solid #bbb;border-radius:8px;padding:10px;margin:10px 0;break-inside:avoid}.message small{color:#666}.attachments{display:flex;flex-wrap:wrap;gap:10px}.attachments img{width:110px;height:90px;object-fit:cover;border:1px solid #ccc}.no-print{margin-bottom:16px}@media print{.no-print{display:none}}
    </style>
</head>
<body>
<button class="no-print" onclick="window.print()">{{ translate('print') }}</button>
<div class="head">
    <h2>{{ translate('support_conversation') }} #{{ $ticket->id }}</h2>
    <p>{{ translate('customer') }}: {{ $ticket->customer?->administrative_reference ?? \App\Services\AdministrativeReferenceService::format('customer', (int) $ticket->customer_id) }} &middot; {{ $ticket->customer?->email }} &middot; {{ $ticket->customer?->phone }}</p>
    <p>{{ translate('subject') }}: {{ $ticket->subject }} &middot; {{ translate('status') }}: {{ translate($ticket->status) }} &middot; {{ translate('printed_at') }}: {{ now()->format('Y-m-d H:i') }}</p>
</div>

<div class="message">
    <strong>{{ translate('customer') }}</strong><small> &middot; {{ $ticket->created_at?->format('Y-m-d H:i:s') }}</small>
    <p>{{ $ticket->description }}</p>
    @if($ticket->attachment_full_url)
        <div class="attachments">
            @foreach($ticket->attachment_full_url as $file)
                @if($file['path'])<a href="{{ $file['path'] }}"><img src="{{ $file['path'] }}" alt=""></a>@endif
            @endforeach
        </div>
    @endif
</div>

@foreach($ticket->conversations as $conversation)
    <div class="message">
        <strong>{{ $conversation->admin_message ? translate('admin') : translate('customer') }}</strong><small> &middot; {{ $conversation->created_at?->format('Y-m-d H:i:s') }}</small>
        <p>{{ $conversation->admin_message ?: $conversation->customer_message }}</p>
        @if($conversation->attachment_full_url)
            <div class="attachments">
                @foreach($conversation->attachment_full_url as $file)
                    @if($file['path'])<a href="{{ $file['path'] }}"><img src="{{ $file['path'] }}" alt=""></a>@endif
                @endforeach
            </div>
        @endif
    </div>
@endforeach
</body>
</html>

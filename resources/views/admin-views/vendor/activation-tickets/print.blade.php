<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ session('direction', 'ltr') }}">
<head><meta charset="utf-8"><title>{{ translate('seller_activation_conversation') }} #{{ $ticket->id }}</title><style>body{font-family:Arial,sans-serif;color:#111;margin:28px}.head{border-bottom:2px solid #222;padding-bottom:12px;margin-bottom:18px}.meta{display:grid;grid-template-columns:repeat(2,1fr);gap:8px}.message{border:1px solid #bbb;border-radius:8px;padding:10px;margin:10px 0;break-inside:avoid}.message small{color:#666}.attachments{display:flex;flex-wrap:wrap;gap:10px;margin-top:8px}.attachments img{width:110px;height:90px;object-fit:cover;border:1px solid #ccc}.no-print{margin-bottom:16px}@media print{.no-print{display:none}}</style></head>
<body>
<button class="no-print" onclick="window.print()">{{ translate('print') }}</button>
<div class="head"><h2>{{ translate('seller_activation_conversation') }} #{{ $ticket->id }}</h2><div class="meta"><span>{{ translate('seller') }}: {{ $ticket->seller?->administrative_reference ?? ('V'.$ticket->seller_id) }}</span><span>{{ translate('status') }}: {{ translate($ticket->status) }}</span><span>{{ translate('email') }}: {{ $ticket->seller?->email }}</span><span>{{ translate('phone') }}: {{ $ticket->seller?->phone }}</span><span>{{ translate('store') }}: {{ $ticket->seller?->shop?->name }}</span><span>{{ translate('printed_at') }}: {{ now()->format('Y-m-d H:i') }}</span></div></div>
@foreach($ticket->messages as $message)
    <div class="message"><strong>{{ translate($message->sender_type) }}</strong><small> · {{ $message->created_at?->format('Y-m-d H:i:s') }}</small>@if($message->body)<p>{{ $message->body }}</p>@endif
        @if(count($message->attachment_full_url))<div class="attachments">@foreach($message->attachment_full_url as $attachment)<a href="{{ $attachment['url'] }}">@if(str_starts_with($attachment['mime_type'], 'image/'))<img src="{{ $attachment['url'] }}" alt="{{ $attachment['name'] }}">@else{{ $attachment['name'] }}@endif</a>@endforeach</div>@endif
    </div>
@endforeach
</body></html>

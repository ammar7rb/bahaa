<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'sa' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>{{ translate('activation_case') }} #{{ $activationCase->id }}</title>
    <style>
        body{font-family:Arial,sans-serif;color:#202124;margin:28px}.head{display:flex;justify-content:space-between;border-bottom:2px solid #222;padding-bottom:12px;margin-bottom:18px}table{width:100%;border-collapse:collapse;margin:12px 0 24px}th,td{border:1px solid #bbb;padding:8px;text-align:start;vertical-align:top}th{background:#f1f3f5}.message{border:1px solid #ddd;border-radius:8px;padding:10px;margin:8px 0}.meta{color:#666;font-size:12px}.no-print{margin-bottom:15px}@media print{.no-print{display:none}}
    </style>
</head>
<body>
@php
    $subject = $activationCase->subjectModel();
    $prefix = $activationCase->subject_type === 'seller' ? 'M' : 'C';
@endphp
<button class="no-print" onclick="window.print()">{{ translate('print') }}</button>
<div class="head">
    <div><h2>{{ translate('activation_case') }} #{{ $activationCase->id }}</h2><div>{{ $prefix }}{{ $activationCase->subject_id }} &mdash; {{ translate($activationCase->subject_type) }}</div></div>
    <div>{{ translate('printed_at') }}: {{ now()->format('d M Y, h:i A') }}</div>
</div>
<table>
    <tr><th>{{ translate('name') }}</th><td>{{ trim(($subject?->f_name ?? '').' '.($subject?->l_name ?? '')) ?: ($subject?->name ?? '-') }}</td><th>{{ translate('status') }}</th><td>{{ translate($activationCase->status) }}</td></tr>
    <tr><th>{{ translate('email') }}</th><td>{{ $subject?->email ?? '-' }}</td><th>{{ translate('phone') }}</th><td>{{ $subject?->phone ?? '-' }}</td></tr>
    <tr><th>{{ translate('assigned_employee') }}</th><td>{{ $activationCase->assignedAdmin?->name ?? '-' }}</td><th>{{ translate('reviewed_by') }}</th><td>{{ $activationCase->reviewedBy?->name ?? '-' }} &mdash; {{ $activationCase->reviewed_at?->format('d M Y, h:i A') }}</td></tr>
</table>
<h3>{{ translate('verified_profile_information') }}</h3>
<table><thead><tr><th>{{ translate('field') }}</th><th>{{ translate('value') }}</th><th>{{ translate('verification') }}</th><th>{{ translate('employee_and_time') }}</th></tr></thead><tbody>
@foreach($activationCase->fields as $field)<tr><td>{{ $field->label }}</td><td>{{ $field->value }}</td><td>{{ translate($field->is_verified ? 'verified' : 'pending') }}</td><td>{{ $field->createdBy?->name }} &mdash; {{ $field->updated_at?->format('d M Y, h:i A') }}</td></tr>@endforeach
</tbody></table>
<h3>{{ translate('document_register') }}</h3>
<table><thead><tr><th>{{ translate('document_type') }}</th><th>{{ translate('document_number') }}</th><th>{{ translate('file') }}</th><th>{{ translate('status') }}</th></tr></thead><tbody>
@foreach($activationCase->documents as $document)<tr><td>{{ $document->document_type === 'other' ? ($document->custom_name ?: translate('other')) : translate($document->document_type) }}</td><td>{{ $document->document_number }}</td><td>{{ $document->original_name }}<br><span class="meta">SHA-256: {{ $document->sha256 }}</span></td><td>{{ translate($document->verification_status) }}</td></tr>@endforeach
</tbody></table>
<h3>{{ translate('activation_conversation') }}</h3>
@foreach($messages as $message)<div class="message"><div class="meta">{{ translate($message['sender_type'] ?? 'system') }} &mdash; {{ $message['created_at'] ?? '' }}</div>{!! nl2br(e($message['body'] ?? '')) !!}</div>@endforeach
<h3>{{ translate('audit_timeline') }}</h3>
<table><thead><tr><th>{{ translate('event') }}</th><th>{{ translate('employee') }}</th><th>{{ translate('note') }}</th><th>{{ translate('date') }}</th></tr></thead><tbody>
@foreach($activationCase->events as $event)<tr><td>{{ translate($event->event_type) }}</td><td>{{ $event->admin?->name ?? translate('system') }}</td><td>{{ $event->note }}</td><td>{{ $event->created_at?->format('d M Y, h:i A') }}</td></tr>@endforeach
</tbody></table>
</body>
</html>

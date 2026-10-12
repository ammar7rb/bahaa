<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ in_array(app()->getLocale(), ['ar','sa']) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8"><title>{{ translate('operational_print_report') }} — {{ translate($section) }}</title>
    <style>
        :root{color-scheme:light}*{box-sizing:border-box}body{font-family:Arial,Tahoma,sans-serif;color:#17202a;background:#fff;margin:24px}.toolbar{display:flex;gap:10px;justify-content:space-between;align-items:center;margin-bottom:20px}.btn{background:#0177cd;color:#fff;border:0;border-radius:6px;padding:9px 16px;cursor:pointer}.meta{display:flex;gap:18px;flex-wrap:wrap;color:#566573;font-size:12px;margin:8px 0 18px}table{width:100%;border-collapse:collapse;font-size:12px}th,td{border:1px solid #ccd1d1;padding:7px;text-align:start;vertical-align:top}th{background:#edf6fc;color:#123}tr:nth-child(even){background:#fafafa}.empty{text-align:center;padding:24px}h1{margin:0;font-size:22px}@page{size:auto;margin:12mm}@media print{body{margin:0}.no-print{display:none!important}thead{display:table-header-group}tr{break-inside:avoid}}
    </style>
</head>
<body>
    <div class="toolbar"><div><h1>{{ translate('operational_print_report') }} — {{ translate($section) }}</h1><div>{{ getWebConfig(name: 'company_name') }}</div></div><button class="btn no-print" onclick="window.print()">{{ translate('print') }}</button></div>
    <form class="meta no-print" method="get">
        <label>{{ translate('report') }} <select name="section">@foreach(['customers','sellers','orders','shipping','refunds','reviews','finances','insurance','invoices','visits'] as $item)<option value="{{ $item }}" @selected($section===$item)>{{ translate($item) }}</option>@endforeach</select></label>
        <label>{{ translate('from') }} <input type="date" name="from" value="{{ $filters['from'] ?? '' }}"></label>
        <label>{{ translate('to') }} <input type="date" name="to" value="{{ $filters['to'] ?? '' }}"></label>
        <label>{{ translate('search') }} <input name="q" value="{{ $filters['q'] ?? '' }}"></label>
        <button class="btn" type="submit">{{ translate('filter') }}</button>
    </form>
    <div class="meta"><span>{{ translate('generated_at') }}: {{ now()->format('Y-m-d H:i') }}</span><span>{{ translate('employee') }}: {{ auth('admin')->user()?->name }}</span>@foreach($filters as $key=>$value)@if($value)<span>{{ translate($key) }}: {{ $value }}</span>@endif @endforeach<span>{{ translate('records') }}: {{ count($rows) }}</span></div>
    <table><thead><tr>@foreach($columns as $column)<th>{{ translate($column) }}</th>@endforeach</tr></thead><tbody>
        @forelse($rows as $row)<tr>@foreach($columns as $column)<td>{{ data_get($row, $column, '—') }}</td>@endforeach</tr>@empty<tr><td class="empty" colspan="{{ count($columns) }}">{{ translate('no_data_found') }}</td></tr>@endforelse
    </tbody></table>
</body></html>

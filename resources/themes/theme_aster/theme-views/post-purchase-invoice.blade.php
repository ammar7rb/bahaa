@extends('theme-views.layouts.app')
@section('title', translate('second_invoice_title'))
@section('content')
    <main class="main-content py-4 mb-4"><div class="container second-stage-page">@include('web-views.partials.post-purchase-invoice-content')</div></main>
@endsection

@extends('layouts.front-end.app')
@section('title', translate('second_invoice_title'))
@section('content')
    <div class="container py-4 py-md-5 second-stage-page">@include('web-views.partials.post-purchase-invoice-content')</div>
@endsection

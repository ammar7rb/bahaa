@extends('layouts.vendor.app')
@section('title',translate('dashboard'))
@section('content')
<div class="content container-fluid vendor-dashboard"><div class="vendor-dashboard-welcome"><div><h1>{{ translate('welcome') }}، {{ auth('seller')->user()->f_name }}</h1><p>{{ translate('vendor_workspace_intro') }}</p></div><a class="btn btn--primary" href="{{ route('vendor.products.list',['type'=>'all']) }}">{{ translate('phase5_manage_spaces') }}</a></div>
@include('vendor-views.dashboard.partials.metric-cards', ['overview' => $sellerOverview['account_overview'], 'sales' => $sellerOverview['sales']])
<section class="vendor-create-product"><div><h2>{{ translate('grow_your_catalogue') }}</h2><p>{{ translate('grow_your_catalogue_hint') }}</p></div><a class="btn btn--primary" href="{{ route('vendor.products.add') }}">@include('layouts.vendor.partials.v2._workspace-icon',['icon'=>'add']) {{ translate('add_new_product') }}</a></section>
</div>
@endsection

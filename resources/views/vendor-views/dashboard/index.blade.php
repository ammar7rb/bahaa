@extends('layouts.vendor.app')
@section('title',translate('dashboard'))
@section('content')
<div class="content container-fluid vendor-dashboard"><div class="vendor-dashboard-welcome"><div><h1>{{ translate('welcome') }}، {{ auth('seller')->user()->f_name }}</h1><p>{{ translate('vendor_workspace_intro') }}</p></div><a class="btn btn--primary" href="{{ route('vendor.products.list',['type'=>'all']) }}">{{ translate('manage_products') }}</a></div>
@if(($sellerSetup['registration']['activation_status'] ?? '') !== 'active') @include('vendor-views.dashboard.partials.seller-setup-status',['setup'=>$sellerSetup]) @endif
<div class="card card-body mb-4"><h2>{{ translate('store_overview') }}</h2><div class="vendor-finance-summary"><div>{{ translate('orders') }}<strong>{{ $sellerOverview['sales']['orders_count'] }}</strong></div><div>{{ translate('products') }}<strong>{{ $sellerOverview['products']['total'] }}</strong></div><div>{{ translate('sold_units') }}<strong>{{ $sellerOverview['sales']['sold_units'] }}</strong></div></div></div>
<div class="vendor-dashboard-actions"><a class="btn btn-outline-primary" href="{{ route('vendor.orders.list','all') }}">{{ translate('view_orders') }}</a><a class="btn btn-outline-primary" href="{{ route('vendor.balance.index') }}">{{ translate('finance_my_wallet') }}</a><a class="btn btn-outline-primary" href="{{ route('vendor.invoices') }}">{{ translate('vendor_invoices') }}</a><a class="btn btn-outline-primary" href="{{ route('vendor.packages.index') }}">{{ translate('ads_manager') }}</a></div></div>
@endsection

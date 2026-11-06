@extends('theme-views.layouts.app')
@section('title', translate('register').' | '.$web_config['company_name'])
@section('content')
<main class="main-content"><section class="container"><div class="row justify-content-center"><div class="col-md-7 col-lg-5"><div class="card"><div class="card-body text-center py-5">
    @include('theme-views.partials._care-icon',['icon'=>'user.png','iconClass'=>'mb-3'])
    <h1 class="h3 mb-4">{{ translate('register') }}</h1><div class="d-flex flex-column gap-3"><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#registerModal">{{ translate('register') }}</button><button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#loginModal">{{ translate('login') }}</button></div>
</div></div></div></div></section></main>
@endsection
@push('script')
<script>document.addEventListener('DOMContentLoaded',function(){const element=document.getElementById('registerModal');if(element)new bootstrap.Modal(element).show();});</script>
@endpush

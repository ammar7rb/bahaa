@extends(in_array(theme_root_path(), ['theme_sigma_care', 'theme_aster'], true) ? 'theme-views.layouts.app' : 'layouts.front-end.app')
@section('title', 'استكمال الطلب | '.$web_config['company_name'])
@section('content')
<main class="main-content py-4" dir="rtl"><section class="container"><div class="row justify-content-center"><div class="col-md-8 col-lg-6">
    <div class="card"><div class="card-body p-4">
        <h1 class="h3 mb-3">استكمال طلبك</h1><p role="status">{{ $next['message'] }}</p>
        @if(in_array($next['action'], ['activate_account', 'contact_support'], true))
            @include('customer-checkout.next-action', ['purchaseState' => ['next_action' => $next['action']]])
        @else<a class="btn btn-primary" href="{{ route('account-oder') }}">عرض طلباتي</a>@endif
    </div></div>
</div></div></section></main>
@endsection

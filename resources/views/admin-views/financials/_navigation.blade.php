<nav class="fin-navigation" aria-label="{{ __('financials.title') }}">
    @if(\App\Utils\Helpers::module_permission_check('reports'))
        @foreach(['company','customers'] as $page)
            <a href="{{ route('admin.report.financials.'.$page) }}" @if(request()->routeIs('admin.report.financials.'.$page)) aria-current="page" @endif><i class="tio-{{ $page === 'company' ? 'chart-bar-1' : 'wallet' }}" aria-hidden="true"></i>{{ __('financials.'.$page) }}</a>
        @endforeach
    @endif
</nav>

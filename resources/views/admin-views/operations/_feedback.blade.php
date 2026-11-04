@if($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@if(session('operations_success'))<div class="alert alert-success" role="status">{{ session('operations_success') }}</div>@endif
@push('css_or_js')<link rel="stylesheet" href="{{ asset('assets/back-end/css/operations-catalog.css') }}?v={{ substr(hash_file('sha256', public_path('assets/back-end/css/operations-catalog.css')), 0, 16) }}">@endpush
@push('script')<script src="{{ asset('assets/back-end/js/operations-catalog.js') }}?v={{ substr(hash_file('sha256', public_path('assets/back-end/js/operations-catalog.js')), 0, 16) }}" defer></script>@endpush

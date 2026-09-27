@extends('layouts.admin.app')
@section('title', translate('customer_deposit_requests'))
@section('content')
<div class="content container-fluid">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ translate('customer_deposit_requests') }}</h1>
            <p class="text-muted mb-0">{{ app()->getLocale() === 'ar' ? 'راجع وسيلة التحويل وبيانات المرسل وإثبات التحويل قبل إضافة الرصيد.' : 'Verify the transfer channel, sender details and proof before crediting the balance.' }}</p>
        </div>
        <a class="btn btn-outline-primary" href="{{ route('admin.third-party.offline-payment-method.index') }}">
            {{ app()->getLocale() === 'ar' ? 'إعداد إنستاباي والمحافظ' : 'Configure InstaPay and wallets' }}
        </a>
    </div>
    @if(session('deposit_message'))<div class="alert alert-success">{{ session('deposit_message') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <form method="get" class="d-flex gap-2 mb-3">
        <select name="status" class="form-control w-auto">
            <option value="">{{ translate('all') }}</option>
            @foreach(['pending','paid','rejected'] as $status)
                <option value="{{ $status }}" {{ request('status') === $status ? 'selected' : '' }}>{{ translate('deposit_status_' . $status) }}</option>
            @endforeach
        </select><button class="btn btn--primary">{{ translate('filter') }}</button>
    </form>
    @forelse($deposits as $deposit)
        <div class="card mb-3"><div class="card-body">
            <div class="d-flex justify-content-between flex-wrap gap-3">
                <div>
                    <h2 class="h5">#{{ $deposit->id }} — {{ $deposit->customer?->f_name }} {{ $deposit->customer?->l_name }}</h2>
                    <p>{{ translate('balance_destination') }}: <strong>{{ translate($deposit->wallet_type . '_balance_title') }}</strong></p>
                    <p>{{ translate('amount') }}: <strong>{{ webCurrencyConverter($deposit->amount) }}</strong> ({{ $deposit->submitted_amount }} {{ $deposit->currency_code }})</p>
                    <p>{{ translate('payment_method') }}: {{ $deposit->method_name }}</p>
                    <p>{{ translate('deposit_transfer_reference') }}: <bdi>{{ $deposit->payment_reference }}</bdi></p>
                    @foreach($deposit->method_information ?? [] as $label => $value)
                        <p>{{ translatePaymentText($label) }}: {{ $value }}</p>
                    @endforeach
                    <p>{{ $deposit->payment_note }}</p>
                    <a class="btn btn-outline-primary" target="_blank" rel="noopener" href="{{ route('admin.customer.wallet.deposits.proof', $deposit->id) }}">{{ translate('payment_proof') }}</a>
                </div>
                <div><span class="badge badge-info">{{ translate('deposit_status_' . $deposit->status) }}</span><p>{{ $deposit->created_at?->format('Y-m-d H:i') }}</p></div>
            </div>
            @if($deposit->status === 'pending')
                <form method="post" action="{{ route('admin.customer.wallet.deposits.review', $deposit->id) }}" class="mt-3">
                    @csrf
                    <p class="alert alert-info">{{ translate('admin_deposit_review_notice') }}</p>
                    <label class="d-block">{{ translate('deposit_review_note') }}<textarea name="note" class="form-control" rows="2" maxlength="1000"></textarea></label>
                    <button class="btn btn--primary" name="decision" value="approve">{{ translate('approve_deposit_credit') }}</button>
                    <button class="btn btn-outline-danger" name="decision" value="reject">{{ translate('reject') }}</button>
                </form>
            @else
                <p class="mt-3">{{ $deposit->review_note }}</p>
            @endif
        </div></div>
    @empty
        <div class="card card-body text-center">{{ translate('no_deposit_requests') }}</div>
    @endforelse
    {{ $deposits->links() }}
</div>
@endsection

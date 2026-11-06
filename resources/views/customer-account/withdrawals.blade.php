@extends(in_array(theme_root_path(), ['theme_aster', 'theme_sigma_care'], true) ? 'theme-views.layouts.app' : 'layouts.front-end.app')
@section('title', 'سحب رصيد المحفظة')
@section('content')
<main class="container py-5" dir="rtl">
    <h1>سحب رصيد المحفظة</h1>
    <a href="{{ route('wallet') }}">العودة للمحفظة</a>
    @if(session('withdrawal_message'))<p role="status" class="alert alert-success">{{ session('withdrawal_message') }}</p>@endif
    @if($errors->any())<p role="alert" class="alert alert-danger">{{ $errors->first() }}</p>@endif
    <p>الرصيد المتاح {{ webCurrencyConverter($balance) }}. رسوم الإدارة والتشغيل {{ $feePercent }}% من مبلغ السحب. المهلة من 15 إلى 30 يومًا من اعتماد الإدارة.</p>
    <form class="card card-body my-3" method="post" action="{{ route('customer.withdrawals.store') }}">
        @csrf
        <input type="hidden" name="request_key" value="{{ \Illuminate\Support\Str::uuid() }}">
        <input type="hidden" name="expected_fee_percent" value="{{ $feePercent }}">
        <input type="hidden" name="currency_code" value="{{ session('currency_code', \App\Models\Currency::find(getWebConfig('system_default_currency'))->code) }}">
        <label for="withdrawal-amount">مبلغ السحب *</label>
        <input class="form-control" type="number" name="amount" id="withdrawal-amount" min="0.01" step="0.01" required>
        <p>الرسوم <output id="withdrawal-fee">0</output>، والصافي <output id="withdrawal-net">0</output> بنفس عملة المبلغ المدخل.</p>
        <label><input type="checkbox" name="acknowledge" value="1" required> أوافق على حجز المبلغ وخصم الرسوم الموضحة عند الصرف.</label>
        <button class="btn btn-primary mt-3" type="submit">طلب السحب</button>
    </form>
    <h2 class="h4">طلبات السحب</h2>
    @foreach($withdrawals as $row)
        <article class="card card-body mb-2">
            <strong>طلب #{{ $row->id }}</strong>
            <p>المبلغ {{ webCurrencyConverter($row->amount) }} · الرسوم {{ webCurrencyConverter($row->fee_amount) }} · الصافي {{ webCurrencyConverter($row->net_amount) }}</p>
            <p>{{ ['pending'=>'بانتظار المراجعة','approved'=>'معتمد','rejected'=>'مرفوض','completed'=>'تم الصرف'][$row->status] ?? $row->status }}</p>
            @if($row->due_at)<time>موعد الصرف {{ $row->due_at }}</time>@endif
            @if($row->admin_note)<p>{{ $row->admin_note }}</p>@endif
        </article>
    @endforeach
    {{ $withdrawals->links() }}
</main>
@endsection
@push('script')
<script>
(() => {
    const input = document.getElementById('withdrawal-amount');
    input.addEventListener('input', () => {
        const amount = Number(input.value) || 0;
        const fee = Math.round(amount * @json($feePercent) / 100 * 100) / 100;
        document.getElementById('withdrawal-fee').textContent = fee.toFixed(2);
        document.getElementById('withdrawal-net').textContent = (amount - fee).toFixed(2);
    });
})();
</script>
@endpush

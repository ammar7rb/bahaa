<section class="card card-body mb-3" dir="rtl" aria-labelledby="vendor-debt-title"><h2 id="vendor-debt-title" class="h4">الديون وسدادها</h2><p>الدين القائم: <strong><bdi>{{ number_format((float)($vendorDebt['outstanding_debt'] ?? 0), 2) }} ج.م</bdi></strong></p><p class="text-muted">الإيداع المؤكد يسدد الدين الأقدم أولًا، ويضاف الباقي إلى رصيد المدفوعات. لا يخصم من مستحقات مبيعاتك.</p>
@foreach(($vendorDebt['charges'] ?? []) as $charge)<div class="border rounded p-2 mb-2"><bdi>{{ $charge['reference'] }}</bdi> — {{ $charge['kind'] === 'cancellation_penalty' ? 'غرامة إلغاء' : 'رسوم مساحة إضافية' }}<br>قيمة الرسم: {{ number_format((float)$charge['amount'], 2) }} ج.م — المتبقي: {{ number_format((float)$charge['outstanding'], 2) }} ج.م</div>@endforeach
@foreach(($deposits ?? []) as $debtDeposit)
    @if($debtDeposit->debt_allocation)
    @php($allocation = $debtDeposit->debt_allocation)
    <details class="border rounded p-3 mb-2"><summary>توزيع الإيداع {{ $debtDeposit->transaction_number }}</summary><dl><dt>إجمالي الإيداع</dt><dd>{{ number_format((float)$allocation['gross_amount'], 2) }} ج.م</dd><dt>الدين المسدد</dt><dd>{{ number_format((float)$allocation['debt_recovered'], 2) }} ج.م</dd><dt>صافي المدفوعات المضاف</dt><dd>{{ number_format((float)$allocation['net_wallet_credit'], 2) }} ج.م</dd><dt>الدين المتبقي وقت التأكيد</dt><dd>{{ number_format((float)$allocation['remaining_debt'], 2) }} ج.م</dd></dl>@if($allocation['reversed_at'])<p>عُكس هذا الإيداع مع الاحتفاظ بالسجل.</p>@endif @foreach($allocation['references'] as $reference)<p>DEBT-{{ $reference['charge_id'] }} — {{ number_format((float)$reference['amount'], 2) }} ج.م</p>@endforeach</details>
    @endif
@endforeach
</section>

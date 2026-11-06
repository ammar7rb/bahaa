<section class="fin-panel" dir="rtl">
    <h2>ديون التجار والتحصيل</h2><p class="fin-help">الاستحقاق ليس تحصيلًا نقديًا. سداد الدين لا يكرر احتساب الرسم، والإيداع الإجمالي يظهر مرة واحدة في التحصيلات الواردة. لا تخصم الديون من مستحقات المبيعات.</p>
    <p><a class="fin-button" href="{{ route('admin.report.vendor-fulfillment.index',['seller_id'=>$filters['seller_id']??null]) }}">متابعة التنفيذ والمطابقة المالية والتصدير</a></p>
    <form method="get" class="fin-filters"><input type="hidden" name="tab" value="vendor_debts"><label>رقم التاجر<input class="form-control" type="number" min="1" name="seller_id" value="{{ $filters['seller_id'] ?? '' }}"></label><label>من<input class="form-control" type="date" name="from" value="{{ $filters['from'] ?? '' }}"></label><label>إلى<input class="form-control" type="date" name="to" value="{{ $filters['to'] ?? '' }}"></label><button class="fin-button fin-primary">عرض</button></form>
    @if($missing)<p role="status">دفتر الديون غير مهيأ بعد.</p>@endif
    <div class="cf-metrics">
        @foreach(['assessed'=>'الرسوم المستحقة','wallet_collected'=>'المحصل من رصيد المدفوعات','deposit_recovered'=>'الدين المسدد من الإيداعات','outstanding'=>'الدين القائم حاليًا','gross_deposits'=>'إجمالي الإيداعات المؤكدة','net_wallet_credit'=>'صافي رصيد المدفوعات المضاف'] as $key=>$title)
            <div class="cf-metric"><span>{{ $title }}</span><strong><bdi>{{ number_format((float)$debtSummary[$key], 2) }} ج.م</bdi></strong></div>
        @endforeach
    </div>
    <p class="fin-help">الدين القائم رصيد حالي دون تقييده بفترة الحركات. التحصيل المعروض يستبعد المبالغ المعكوسة؛ وسجل الأحداث يحتفظ بالعكس والمراجع.</p>
    <div class="fin-table-scroll"><table class="fin-table"><thead><tr><th>الحدث</th><th>التاجر</th><th>مرجع الرسم</th><th>مرجع الإيداع</th><th>النوع</th><th>المبلغ</th><th>التاريخ</th></tr></thead><tbody>
    @forelse($debtRecords as $event)<tr><td>{{ ['charge_assessed'=>'تقييم رسم','wallet_collection'=>'تحصيل من المدفوعات','deposit_recovery'=>'سداد دين من إيداع','deposit_allocated'=>'توزيع إيداع مؤكد','deposit_allocation_reversed'=>'عكس توزيع إيداع'][$event->event_type] ?? $event->event_type }}</td><td>#{{ $event->seller_id }}</td><td>{{ $event->charge_id ? 'DEBT-'.$event->charge_id : '—' }} @if($event->source_id)<small>{{ $event->source_type }} #{{ $event->source_id }}</small>@endif</td><td>{{ $event->deposit_id ? 'DEP-'.$event->deposit_id : '—' }}</td><td>{{ ['cancellation_penalty'=>'غرامة إلغاء','additional_slot'=>'رسوم مساحة إضافية'][$event->kind] ?? 'توزيع إيداع' }}</td><td><bdi>{{ number_format((float)$event->amount, 2) }} ج.م</bdi></td><td>{{ $event->created_at }}</td></tr>
    @empty<tr><td colspan="7">لا توجد أحداث مالية بهذه الفترة.</td></tr>@endforelse
    </tbody></table></div>{{ $debtRecords->links() }}
</section>

<h2 class="h5">حركة رصيد المحفظة</h2>
                <form method="get" action="{{ route('wallet') }}" class="row g-2 mb-3">
                    <input type="hidden" name="wallet_tab" value="balances">
                    <div class="col-sm-4"><label for="statement-type" class="form-label">نوع العملية</label><select id="statement-type" name="entry_type" class="form-select"><option value="">كل العمليات</option>@foreach(\Illuminate\Support\Arr::except(\App\Services\CustomerWalletStatementService::LABELS, ['increase']) as $key=>$label)<option value="{{ $key }}" @selected(request('entry_type')===$key)>{{ $label }}</option>@endforeach</select></div>
                    <div class="col-sm-3"><label for="statement-from" class="form-label">من تاريخ</label><input id="statement-from" type="date" name="from" value="{{ request('from') }}" class="form-control"></div>
                    <div class="col-sm-3"><label for="statement-to" class="form-label">إلى تاريخ</label><input id="statement-to" type="date" name="to" value="{{ request('to') }}" class="form-control"></div>
                    <div class="col-sm-2 align-self-end"><button class="btn btn--primary">تصفية</button></div>
                </form>
                <div class="table-responsive"><table class="table"><thead><tr><th>التاريخ</th><th>العملية</th><th>طريقة الدفع والمرجع</th><th>إضافة</th><th>خصم</th><th>الرصيد</th><th>ملاحظة الإدارة</th></tr></thead><tbody>
                @forelse($purchaseEntries as $entry)
                    <tr><td><bdi>{{ \Carbon\Carbon::parse($entry['created_at'])->format('Y/m/d H:i') }}</bdi></td><td>{{ $entry['entry_label'] }}@if($entry['package_name'])<br>{{ $entry['package_name'] }}@endif</td>
                        <td>{{ $entry['payment_method'] ?: 'غير مسجلة' }}<br><bdi>{{ $entry['reference_label'] }}</bdi>
                            @foreach($entry['orders'] as $link)<div><a href="{{ route('account-order-details',['id'=>$link['order_id']]) }}">طلب #{{ $link['order_id'] }}</a>@if($link['invoice_number']) · {{ $link['invoice_number'] }}@endif</div>@endforeach
                        </td><td>{{ webCurrencyConverter($entry['credited_amount']) }}@if($entry['admin_bonus']>0)<small class="d-block">تشمل مكافأة {{ webCurrencyConverter($entry['admin_bonus']) }}</small>@endif</td><td>{{ webCurrencyConverter($entry['debit']) }}</td><td>{{ webCurrencyConverter($entry['balance']) }}</td><td style="white-space:pre-wrap">{{ $entry['customer_note'] ?: '—' }}</td></tr>
                @empty<tr><td colspan="7" class="text-center text-muted py-4">لا توجد حركات تطابق التصفية</td></tr>@endforelse
                </tbody></table></div>{{ $purchaseEntries->links() }}

<div class="card mb-3" id="egypt-shipping-zone-rates">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
            <div>
                <h3 class="text-capitalize mb-1">{{ translate('egypt_shipping_zone_rates') }}</h3>
                <p class="mb-0 fs-12">سعر ثابت لكل محافظة، وسيجما تُحسب تلقائيًا بثلاثة أضعاف الشحن العادي.</p>
            </div>
            <span class="badge badge-soft-success px-3 py-2">{{ $egyptZoneRates->where('status', true)->count() }} محافظة مفعلة</span>
        </div>

        <div class="alert alert-info fs-13" role="note">
            <strong>طريقة العمل:</strong> يحدد النظام نقطة الانطلاق من المحافظة تلقائيًا، ويحسب مسافة الطريق داخليًا للمراجعة، بينما السعر النهائي ثابت حسب المحافظة.
            إذا كتبت عنوان مركز الشحن سيُحدد بدقة أكبر، وإذا تُرك فارغًا يُستخدم مركز المحافظة.
        </div>

        <div class="row g-3 mb-4">
            <div class="col-lg-5">
                <div class="bg-section rounded p-3 h-100">
                    <h4 class="fs-15 mb-1">مدة التوصيل الظاهرة للعميل</h4>
                    <p class="text-muted fs-12 mb-3">تظهر في الموقع وتُرسل إلى تطبيق العميل مع عرض السعر.</p>
                    <form method="post" action="{{ route('admin.business-settings.shipping-method.egypt-zone-rates.delivery-promises.update') }}" class="row g-3">
                        @csrf
                        <div class="col-6"><label class="form-label">العادي (أيام عمل)</label><input class="form-control" type="number" min="1" max="60" name="normal_delivery_days" value="{{ getWebConfig(name: 'shipping_promise_normal_min_days') ?? 10 }}" required></div>
                        <div class="col-6"><label class="form-label">سيجما (أيام عمل)</label><input class="form-control" type="number" min="1" max="60" name="sigma_delivery_days" value="{{ getWebConfig(name: 'shipping_promise_sigma_min_days') ?? 6 }}" required></div>
                        <div class="col-12 d-flex justify-content-end"><button class="btn btn-primary" type="submit">حفظ مدة التوصيل</button></div>
                    </form>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="bg-section rounded p-3 h-100">
                    <button class="btn btn-link text-dark text-decoration-none p-0 w-100 d-flex justify-content-between align-items-center" type="button" data-bs-toggle="collapse" data-bs-target="#add-egypt-zone-rate" aria-expanded="false">
                        <span><strong>إضافة سعر لمنطقة أكثر تحديدًا</strong><small class="d-block text-muted mt-1">اختياري: مدينة أو حي داخل محافظة.</small></span><i class="fi fi-sr-angle-down"></i>
                    </button>
                    <div class="collapse mt-3" id="add-egypt-zone-rate">
                        <form method="post" action="{{ route('admin.business-settings.shipping-method.egypt-zone-rates.store') }}" class="row g-3">
                            @csrf
                            <div class="col-md-4"><label class="form-label">{{ translate('governorate') }}</label><select class="form-select" name="governorate" required><option value="" selected disabled>{{ translate('select') }}</option>@foreach(config('egypt.governorates', []) as $governorate)<option value="{{ $governorate }}">{{ $governorate }}</option>@endforeach</select></div>
                            <div class="col-md-4"><label class="form-label">{{ translate('district') }}</label><input class="form-control" name="district"></div>
                            <div class="col-md-4"><label class="form-label">{{ translate('area') }}</label><input class="form-control" name="area"></div>
                            <div class="col-md-6"><label class="form-label">عنوان مركز الشحن (اختياري)</label><input class="form-control" name="dispatch_address" placeholder="يُستخدم مركز المحافظة عند تركه فارغًا"></div>
                            <div class="col-md-3"><label class="form-label">سعر الشحن العادي</label><input class="form-control js-normal-cost" type="number" min="0" step="0.01" name="normal_cost" value="80" required></div>
                            <div class="col-md-3"><label class="form-label">سيجما (3×)</label><input class="form-control js-sigma-preview" value="240" readonly></div>
                            <div class="col-md-4 d-flex align-items-end"><label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="normal_available" value="1" checked> <span class="form-check-label">{{ translate('normal_available') }}</span></label></div>
                            <div class="col-md-4 d-flex align-items-end"><label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="sigma_available" value="1" checked> <span class="form-check-label">{{ translate('sigma_available') }}</span></label></div>
                            <div class="col-md-4 d-flex align-items-end"><label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="status" value="1" checked> <span class="form-check-label">{{ translate('active') }}</span></label></div>
                            <div class="col-12 d-flex justify-content-end"><button class="btn btn-primary" type="submit">{{ translate('save') }}</button></div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-responsive border rounded">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-section"><tr><th>{{ translate('location') }}</th><th>نقطة الانطلاق</th><th>العادي</th><th>سيجما (3×)</th><th>الإتاحة</th><th>{{ translate('actions') }}</th></tr></thead>
                <tbody>
                @forelse($egyptZoneRates as $rate)
                    <tr>
                        <td style="min-width:220px"><div class="d-grid gap-1"><select form="zone-rate-{{ $rate->id }}" class="form-select form-select-sm" name="governorate" required><option value="" disabled @selected(!$rate->governorate)>{{ translate('select') }}</option>@foreach(config('egypt.governorates', []) as $governorate)<option value="{{ $governorate }}" @selected($rate->governorate === $governorate)>{{ $governorate }}</option>@endforeach</select><input form="zone-rate-{{ $rate->id }}" class="form-control form-control-sm" name="district" value="{{ $rate->district }}" placeholder="{{ translate('district') }}"><input form="zone-rate-{{ $rate->id }}" class="form-control form-control-sm" name="area" value="{{ $rate->area }}" placeholder="{{ translate('area') }}"></div></td>
                        <td style="min-width:220px"><input form="zone-rate-{{ $rate->id }}" class="form-control form-control-sm" name="dispatch_address" value="{{ $rate->dispatch_address }}" placeholder="مركز المحافظة تلقائيًا"><small class="text-muted">يُحدد تلقائيًا عند الحفظ</small></td>
                        <td style="min-width:130px"><input form="zone-rate-{{ $rate->id }}" class="form-control form-control-sm js-normal-cost" type="number" min="0" step="0.01" name="normal_cost" value="{{ $rate->normal_cost !== null ? usdToDefaultCurrency($rate->normal_cost) : 80 }}" required></td>
                        <td style="min-width:130px"><input class="form-control form-control-sm js-sigma-preview" value="{{ usdToDefaultCurrency(($rate->normal_cost ?? 80) * 3) }}" readonly><small class="text-muted">يُحسب تلقائيًا</small></td>
                        <td style="min-width:130px"><div class="d-flex flex-column gap-1"><label class="form-check"><input form="zone-rate-{{ $rate->id }}" class="form-check-input" type="checkbox" name="normal_available" value="1" @checked($rate->normal_available)> عادي</label><label class="form-check"><input form="zone-rate-{{ $rate->id }}" class="form-check-input" type="checkbox" name="sigma_available" value="1" @checked($rate->sigma_available)> سيجما</label><label class="form-check"><input form="zone-rate-{{ $rate->id }}" class="form-check-input" type="checkbox" name="status" value="1" @checked($rate->status)> مفعل</label></div></td>
                        <td class="text-nowrap"><form id="zone-rate-{{ $rate->id }}" method="post" action="{{ route('admin.business-settings.shipping-method.egypt-zone-rates.update', $rate->id) }}">@csrf<input type="hidden" name="street" value="{{ $rate->street }}"></form><button form="zone-rate-{{ $rate->id }}" class="btn btn-sm btn-primary" type="submit">{{ translate('save') }}</button><form method="post" action="{{ route('admin.business-settings.shipping-method.egypt-zone-rates.delete', $rate->id) }}" class="d-inline ms-1">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" type="submit">{{ translate('delete') }}</button></form></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">{{ translate('no_egypt_shipping_zone_rates') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('script')
<script>
    document.addEventListener('input', function (event) {
        if (!event.target.classList.contains('js-normal-cost')) return;
        const scope = event.target.closest('form') || event.target.closest('tr');
        const preview = scope ? scope.querySelector('.js-sigma-preview') : null;
        if (preview) preview.value = ((parseFloat(event.target.value) || 0) * 3).toFixed(2);
    });
</script>
@endpush

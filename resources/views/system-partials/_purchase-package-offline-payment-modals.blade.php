@if($offlinePaymentAvailable && $offlinePaymentMethods->count() > 0)
    <div class="mt-3">
        <label class="mb-2">{{ translate('pay_offline') }}</label>
        <div class="d-flex flex-wrap gap-2">
            @foreach($offlinePaymentMethods as $offlineMethod)
                @php($modalId = $modalIdPrefix . '-offline-payment-' . $offlineMethod->id)
                <button type="button"
                        class="btn {{ $buttonClass ?? 'btn-outline-primary' }} text-capitalize"
                        data-toggle="modal"
                        data-target="#{{ $modalId }}"
                        data-bs-toggle="modal"
                        data-bs-target="#{{ $modalId }}">
                    {{ translatePaymentText($offlineMethod->method_name) }}
                </button>

                <div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">{{ translatePaymentText($offlineMethod->method_name) }}</h5>
                                <button type="button" class="close btn-close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <form action="{{ $formAction }}" method="post" enctype="multipart/form-data">
                                @csrf
                                <input type="hidden" name="method_id" value="{{ $offlineMethod->id }}">
                                <div class="modal-body">
                                    @if(!empty($amount))
                                        <h5 class="text-center mb-3">
                                            {{ translate('amount') }} : {{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $amount)) }}
                                        </h5>
                                    @endif

                                    @if(!empty($offlineMethod->method_fields))
                                        <div class="bg-light rounded p-3 mb-3">
                                            <h6 class="text-capitalize">{{ translate('payment_information') }}</h6>
                                            <div class="row g-2 fs-12">
                                                @foreach($offlineMethod->method_fields as $methodField)
                                                    <div class="col-sm-6">
                                                        <span class="text-muted text-capitalize">{{ translatePaymentText($methodField['input_name'] ?? '') }}</span>
                                                        :
                                                        <span class="text-dark">{{ translatePaymentText($methodField['input_data'] ?? '') }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif

                                    <div class="row">
                                        @foreach(($offlineMethod->method_informations ?? []) as $information)
                                            @php($inputName = $information['customer_input'] ?? '')
                                            @php($fieldText = strtolower($inputName . ' ' . ($information['customer_placeholder'] ?? '')))
                                            @php($isImageField = str_contains($fieldText, 'screenshot') || str_contains($fieldText, 'image') || str_contains($fieldText, 'receipt') || str_contains($fieldText, 'proof'))
                                            <div class="col-sm-6">
                                                <div class="form-group">
                                                    <label class="text-capitalize">
                                                        {{ translatePaymentText($inputName) }}
                                                        @if(($information['is_required'] ?? 0) == 1)
                                                            <span class="text-danger">*</span>
                                                        @endif
                                                    </label>
                                                    @if($isImageField)
                                                        <input type="file"
                                                               name="{{ $inputName }}"
                                                               class="form-control"
                                                               accept="image/*"
                                                               {{ ($information['is_required'] ?? 0) == 1 ? 'required' : '' }}>
                                                    @else
                                                        <input type="text"
                                                               name="{{ $inputName }}"
                                                               class="form-control"
                                                               placeholder="{{ translatePaymentText($information['customer_placeholder'] ?? '') }}"
                                                               {{ ($information['is_required'] ?? 0) == 1 ? 'required' : '' }}>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach

                                        <div class="col-12">
                                            <div class="form-group">
                                                <label>{{ translate('payment_note') }}</label>
                                                <textarea class="form-control" name="payment_note" rows="4" placeholder="{{ translate('insert_note') }}"></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal" data-bs-dismiss="modal">
                                        {{ translate('close') }}
                                    </button>
                                    <button type="submit" class="btn {{ $submitClass ?? 'btn--primary' }}">
                                        {{ translate('submit') }}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif

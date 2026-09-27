@if(auth('customer')->check())
    @php
        $activationCustomer = auth('customer')->user();
        $activationStatus = app(\App\Services\CustomerActivationService::class)->payload($activationCustomer);
    @endphp
    @if(($activationStatus['banner']['visible'] ?? false))
        <div id="customer-activation-notice" class="container mt-3 text-align-direction">
            <div id="customer-activation-alert" class="alert alert-danger d-flex flex-wrap align-items-center justify-content-between gap-2 mb-0" role="alert">
                <div>
                    <strong class="d-block">{{ translate('customer_activation_required') }}</strong>
                    <span class="small">{{ $activationStatus['banner']['message'] ?? translate('customer_activation_required') }}</span>
                </div>
                @if(!empty($activationStatus['ticket_id']))
                    <a class="btn btn-danger" href="{{ route('support-ticket.index', ['id' => $activationStatus['ticket_id']]) }}">
                        {{ translate('Activate_Account') }}
                    </a>
                @endif
            </div>
        </div>
        <script>
            (function () {
                const notice = document.getElementById('customer-activation-notice');
                const alertBox = document.getElementById('customer-activation-alert');
                const endpoint = @json(route('support-ticket.activation-status'));
                const homeUrl = @json(route('home'));
                let completed = false;

                function showApproved(message, redirectUrl) {
                    if (completed || !notice || !alertBox) return;
                    completed = true;
                    alertBox.classList.remove('alert-danger');
                    alertBox.classList.add('alert-success');
                    alertBox.setAttribute('role', 'status');
                    alertBox.innerHTML = '<div><strong class="d-block">' + @json(translate('customer_account_activated')) + '</strong><span class="small">' + (message || @json(translate('customer_account_activated'))) + '</span></div>';
                    window.setTimeout(function () {
                        window.location.assign(redirectUrl || homeUrl);
                    }, 1600);
                }

                async function refreshActivation() {
                    try {
                        const response = await fetch(endpoint, {headers: {Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest'}});
                        if (!response.ok) return;
                        const data = await response.json();
                        if (data.is_active) showApproved(data.message, data.redirect_url);
                    } catch (_) {}
                }

                refreshActivation();
                window.setInterval(refreshActivation, 7000);
            })();
        </script>
    @endif
@endif

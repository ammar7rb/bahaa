@extends('layouts.vendor.app')

@section('title', translate('product_add_access_required'))

@section('content')
    @php
        $stepMeta = [
            'account' => ['label' => 'Account_Verification', 'icon' => 'fi fi-sr-user'],
            'package' => ['label' => 'Vendor_Packages', 'icon' => 'fi fi-sr-box-open'],
        ];
        $currentStep = match ($nextStep) {
            'verify_phone', 'await_activation_ticket', 'await_admin_approval' => 'account',
            default => 'package',
        };
    @endphp

    <div class="content container-fluid">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4 p-lg-5">
                <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
                    <div class="d-flex gap-3">
                        <span class="d-inline-flex justify-content-center align-items-center rounded-circle bg-warning-subtle text-warning" style="width: 48px; height: 48px;">
                            <i class="fi fi-sr-lock fs-20"></i>
                        </span>
                        <div>
                            <h1 class="h3 mb-1">{{ translate('product_add_access_required') }}</h1>
                            <p class="text-muted mb-0">{{ translate('complete_the_required_step_before_adding_a_product') }}</p>
                        </div>
                    </div>
                    <a href="{{ route('vendor.dashboard.index') }}" class="btn btn-outline-secondary">
                        <i class="fi fi-sr-dashboard"></i> {{ translate('Go_To_Dashboard') }}
                    </a>
                </div>

                <div class="alert alert-soft-warning d-flex gap-2 align-items-start mb-4" role="alert">
                    <i class="fi fi-sr-info mt-1"></i>
                    <div>{{ $blockReason }}</div>
                </div>

                <div class="row g-3 mb-4">
                    @foreach($stepMeta as $key => $meta)
                        @php
                            $completed = match ($key) {
                                'account' => $setup['registration']['can_login'] && $nextStep !== 'await_activation_ticket',
                                'package' => (bool) $setup['package']['active_subscription'],
                            };
                            $isCurrent = $key === $currentStep;
                        @endphp
                        <div class="col-md-6">
                            <div class="border rounded p-3 h-100 {{ $isCurrent ? 'border-warning bg-warning-subtle' : '' }}">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="{{ $completed ? 'fi fi-sr-check-circle text-success' : $meta['icon'] . ($isCurrent ? ' text-warning' : ' text-muted') }} fs-18"></i>
                                    <strong>{{ translate($meta['label']) }}</strong>
                                </div>
                                <div class="fs-12 text-muted mt-2">
                                    {{ $completed ? translate('completed') : ($isCurrent ? translate($nextStepAction['label']) : translate('required')) }}
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 border-top pt-4">
                    <div>
                        <div class="text-muted fs-12">{{ translate('Next_Step') }}</div>
                        <strong>{{ translate($nextStepAction['label']) }}</strong>
                    </div>
                    <a href="{{ $nextStepAction['route'] }}" class="btn btn--primary px-4">
                        <i class="{{ $nextStepAction['icon'] }}"></i>
                        {{ translate($nextStepAction['label']) }}
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection

<?php

return [
    // Enable only after the slot/payment interfaces and transition are deployed together.
    'enabled' => (bool) env('VENDOR_ADVERTISING_ENABLED', false),
    'creation_slots_enabled' => (bool) env('VENDOR_SLOT_CREATION_ENABLED', false),
    'reservation_idle_minutes' => (int) env('VENDOR_SLOT_RESERVATION_IDLE_MINUTES', 0),
    'duration_days' => 30,
    'transition_days' => 7,
    'currency' => 'EGP',
    'prices_are_final' => true,
    'packages' => [
        ['name' => 'الأساسية', 'slug' => 'vendor-basic', 'product_limit' => 5, 'package_price' => 3000, 'sort_order' => 1],
        ['name' => 'المتقدمة', 'slug' => 'vendor-advanced', 'product_limit' => 10, 'package_price' => 5000, 'sort_order' => 2],
        ['name' => 'الاحترافية', 'slug' => 'vendor-professional', 'product_limit' => 20, 'package_price' => 8000, 'sort_order' => 3],
    ],
];

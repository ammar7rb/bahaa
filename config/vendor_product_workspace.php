<?php

return [
    // Enable only after the baseline command, backend and both clients are verified.
    'enabled' => (bool) env('VENDOR_PRODUCT_WORKSPACE_ENABLED', false),
];

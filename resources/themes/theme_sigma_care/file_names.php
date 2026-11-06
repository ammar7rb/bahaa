<?php

// Reuse the complete maintained storefront contract, including customer and merchant flows.
$views = require dirname(__DIR__) . '/theme_aster/file_names.php';
return $views + [
    'get_login_modal_data' => 'theme-views.layouts.partials.modal._login',
    'get_register_modal_data' => 'theme-views.layouts.partials.modal._register',
];

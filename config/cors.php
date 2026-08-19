<?php

// Frontend (Next.js) talks to this API cross-origin, authenticating via
// Sanctum's stateful cookie flow — so credentials MUST be allowed and
// origins must be an explicit list (never '*' when supports_credentials is true).
return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter(array_map('trim', explode(',', env('FRONTEND_URLS', 'http://localhost:3000')))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];

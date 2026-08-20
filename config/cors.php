<?php

// The Next.js frontend (folad_frontend) uses Sanctum SPA cookie auth --
// credentials: "include" plus a /sanctum/csrf-cookie preflight -- which
// requires supports_credentials=true and an explicit (non-wildcard) origin
// list; a wildcard origin is incompatible with credentialed requests per
// the CORS spec.
return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter(explode(',', env('FRONTEND_URLS', 'http://localhost:3000'))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];

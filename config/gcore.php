<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Gcore Anti-DDoS API
    |--------------------------------------------------------------------------
    |
    | API key used by the admin Gcore DDoS panel to manage IaaS / Bare Metal
    | protection profiles (ACL, rate limiters, GEOIP).
    |
    */
    'api_key' => env('GCORE_API_KEY', ''),

    /*
    | Fallback ACL policy when a protected allocation has no server/egg yet,
    | or the egg has no gcore_policy set. Prefer configuring it on the Egg
    | (gcore_policy + gcore_proto).
    */
    'default_policy' => env('GCORE_DEFAULT_POLICY', 'allowlist'),
];

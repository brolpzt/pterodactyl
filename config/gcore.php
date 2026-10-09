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
    | Default ACL policy applied when syncing protected allocation ports
    | for a Gcore-enabled node (overridable per node via gcore_policy).
    */
    'default_policy' => env('GCORE_DEFAULT_POLICY', 'allowlist'),
];
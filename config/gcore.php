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
    | Legacy fallback only. Prefer gcore_policy / gcore_proto on each egg port_slot
    | (including the SERVER_PORT primary slot).
    */
    'default_policy' => env('GCORE_DEFAULT_POLICY', 'allowlist'),
];

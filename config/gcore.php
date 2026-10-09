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

    /*
    | Panel-managed game ACL rules are inserted after this many leading rules
    | (1-based UI: "below rule N"). Infra rules (allowlist, geo, tcp-server, …)
    | should occupy the slots above this index.
    */
    'acl_game_rules_after' => (int) env('GCORE_ACL_GAME_RULES_AFTER', 5),
];

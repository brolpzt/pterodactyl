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
    | Deprecated / unused. Sync now patches game dports in place and never
    | reorders ACL rules (DROP/allowlist/geo stay at their original indices).
    | Kept so existing .env keys do not break config load.
    */
    'acl_game_rules_after' => (int) env('GCORE_ACL_GAME_RULES_AFTER', 5),

    /*
    | How many days of DDoS event logs to pull on each sync (local DB retention is 90 days).
    */
    'attack_sync_days' => (int) env('GCORE_ATTACK_SYNC_DAYS', 30),

    /*
    | Remote IP lists (admin → Gcore DDoS → Listas de IP). IPv6 entries are always dropped.
    | A download that comes back empty, over max_entries, or shrinks by more than
    | max_shrink_percent is rejected and the previous version is kept (an empty sip_list
    | on Gcore means "any source").
    */
    'ip_lists' => [
        'max_shrink_percent' => (int) env('GCORE_IP_LIST_MAX_SHRINK', 50),
        'max_entries' => (int) env('GCORE_IP_LIST_MAX_ENTRIES', 5000),
        // Prefixes broader than this (e.g. 0.0.0.0/0) are ignored.
        'min_prefix' => (int) env('GCORE_IP_LIST_MIN_PREFIX', 8),
        'max_bytes' => 5 * 1024 * 1024,
        'log_retention_days' => 180,
    ],
];

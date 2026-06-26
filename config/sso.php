<?php

return [
    /*
    |--------------------------------------------------------------------------
    | External panel SSO (signed magic links)
    |--------------------------------------------------------------------------
    |
    | Allows a trusted external control panel to redirect users into the
    | Pterodactyl web UI without entering a password. See docs/sso_integration.md.
    |
    */

    'enabled' => env('SSO_ENABLED', false),

    /*
    | Shared secret used to sign SSO links. Must match the value configured in
    | your external panel. Use a long random string (64+ characters).
    */
    'secret' => env('SSO_SECRET'),

    /*
    | Maximum age of a link in seconds (checked against the "expires" parameter).
    */
    'token_ttl' => (int) env('SSO_TOKEN_TTL', 60),

    /*
    | Grace period (seconds) when validating the "expires" timestamp against this
    | server's clock. Helps when the external panel clock is slightly ahead.
    */
    'clock_skew' => (int) env('SSO_CLOCK_SKEW', 30),

    /*
    | How the "user" query parameter is resolved:
    | - external_id: matches users.external_id (recommended)
    | - id: matches users.id (numeric only)
    */
    'user_identifier' => env('SSO_USER_IDENTIFIER', 'external_id'),

    /*
    | Allow resolving servers by external_id in addition to uuid / uuidShort.
    */
    'allow_server_external_id' => env('SSO_ALLOW_SERVER_EXTERNAL_ID', false),

    /*
    | Optional list of IP addresses allowed to *request* SSO URLs (the end-user
    | browser IP is not checked). Leave empty to disable IP filtering.
    */
    'allowed_ips' => array_filter(array_map('trim', explode(',', env('SSO_ALLOWED_IPS', '')))),
];

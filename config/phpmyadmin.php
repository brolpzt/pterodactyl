<?php

return [
    /*
    |--------------------------------------------------------------------------
    | phpMyAdmin SSO
    |--------------------------------------------------------------------------
    |
    | Short-lived signed tokens open phpMyAdmin already authenticated as the
    | server database user (client) or the database host user (admin).
    |
    */

    'enabled' => env('PHPMYADMIN_SSO_ENABLED', true),

    /*
    | Legacy single-instance URL (fallback only). Prefer url_template so each
    | database host / node opens its own phpMyAdmin.
    */
    'url' => rtrim((string) env('PHPMYADMIN_URL', ''), '/'),

    /*
    | Per-node phpMyAdmin base URL. Placeholders: {fqdn}, {name}, {node}
    | Default uses phpmyadmin-{node}.hostgamer.net — Cloudflare Universal SSL
    | only covers one label under the zone (*.hostgamer.net), not phpmyadmin.{fqdn}.
    | Example: https://phpmyadmin-{node}.hostgamer.net → https://phpmyadmin-node050.hostgamer.net
    */
    'url_template' => (string) env('PHPMYADMIN_URL_TEMPLATE', 'https://phpmyadmin-{node}.hostgamer.net'),

    /*
    | Shared secret with the phpMyAdmin sso.php bridge (AES + HMAC).
    | Generate with: php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
    */
    'sso_secret' => env('PHPMYADMIN_SSO_SECRET', ''),

    /*
    | Token lifetime in seconds.
    */
    'token_ttl' => (int) env('PHPMYADMIN_SSO_TOKEN_TTL', 60),
];

<?php

declare(strict_types=1);

require __DIR__ . '/src/Env.php';
require __DIR__ . '/src/GcoreClient.php';
require __DIR__ . '/src/helpers.php';

Env::load(__DIR__ . '/.env');

$sessionName = Env::get('SESSION_NAME', 'gcore_ddos_panel') ?? 'gcore_ddos_panel';
session_name($sessionName);
session_start();

function gcore_client(): GcoreClient
{
    static $client = null;
    if ($client === null) {
        $key = Env::get('GCORE_API_KEY', '');
        if ($key === null || $key === '') {
            throw new RuntimeException('Configure GCORE_API_KEY no arquivo .env');
        }
        $client = new GcoreClient($key);
    }

    return $client;
}

<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Deploy Plans (for client-side server creation)
    | Configure egg_id and nest_id per your installation. Run: php artisan tinker
    | then: \Pterodactyl\Models\Egg::with('nest')->get(['id','name','nest_id'])
    |--------------------------------------------------------------------------
    */
    'plans' => [
        'retro' => [
            'name' => 'Retro (CS 1.6)',
            'egg_id' => env('DEPLOY_PLAN_RETRO_EGG_ID', 1),
            'nest_id' => env('DEPLOY_PLAN_RETRO_NEST_ID', 1),
            'memory' => 1024,
            'swap' => 0,
            'disk' => 10240,
            'io' => 500,
            'cpu' => 100,
            'hourly_rate' => 0.015,
        ],
        'esports' => [
            'name' => 'Esports (Minecraft)',
            'egg_id' => env('DEPLOY_PLAN_ESPORTS_EGG_ID', 2),
            'nest_id' => env('DEPLOY_PLAN_ESPORTS_NEST_ID', 1),
            'memory' => 4096,
            'swap' => 0,
            'disk' => 20480,
            'io' => 500,
            'cpu' => 200,
            'hourly_rate' => 0.05,
        ],
        'heavyduty' => [
            'name' => 'Heavy Duty (Rust)',
            'egg_id' => env('DEPLOY_PLAN_HEAVYDUTY_EGG_ID', 3),
            'nest_id' => env('DEPLOY_PLAN_HEAVYDUTY_NEST_ID', 1),
            'memory' => 12288,
            'swap' => 0,
            'disk' => 30720,
            'io' => 500,
            'cpu' => 400,
            'hourly_rate' => 0.12,
        ],
    ],
];

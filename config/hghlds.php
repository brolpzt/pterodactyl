<?php

return [
    /*
    | Features granted by a license: 1 = mode 4, 2 = schedule, 3 = both.
    */
    'features' => 3,

    /*
    | How long a license is valid for, in seconds.
    */
    'license_ttl' => 3 * 24 * 3600,

    /*
    | Size in bytes of engine_i486.so on each released version, which is how a server
    | running hgHLDS is recognized. Add the new size when releasing a version, or
    | servers on it are reported as not using hgHLDS.
    */
    'engine_sizes' => [
        1363824, // 1.0.0
    ],

    /*
    | Only connections from these ranges may state the client address through the
    | CF-Connecting-IP header. See https://www.cloudflare.com/ips/
    */
    'cloudflare_ranges' => [
        '173.245.48.0/20',
        '103.21.244.0/22',
        '103.22.200.0/22',
        '103.31.4.0/22',
        '141.101.64.0/18',
        '108.162.192.0/18',
        '190.93.240.0/20',
        '188.114.96.0/20',
        '197.234.240.0/22',
        '198.41.128.0/17',
        '162.158.0.0/15',
        '104.16.0.0/13',
        '104.24.0.0/14',
        '172.64.0.0/13',
        '131.0.72.0/22',
        '2400:cb00::/32',
        '2606:4700::/32',
        '2803:f800::/32',
        '2405:b500::/32',
        '2405:8100::/32',
        '2a06:98c0::/29',
        '2c0f:f248::/32',
    ],
];

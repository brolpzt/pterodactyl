<?php

/**
 * Laravel - A PHP Framework For Web Artisans.
 *
 * @author   Taylor Otwell <taylor@laravel.com>
 */
define('LARAVEL_START', microtime(true));

/*
|--------------------------------------------------------------------------
| Check If Application Is Under Maintenance
|--------------------------------------------------------------------------
|
| If the application is maintenance / demo mode via the "down" command we
| will require this file so that any pre-rendered template can be shown
| instead of starting the framework, which could cause an exception.
|
*/

if (file_exists(__DIR__ . '/../storage/framework/maintenance.php')) {
    require __DIR__ . '/../storage/framework/maintenance.php';
}

/*
|--------------------------------------------------------------------------
| Register The Auto Loader
|--------------------------------------------------------------------------
|
| Composer provides a convenient, automatically generated class loader for
| our application. We just need to utilize it! We'll simply require it
| into the script here so that we don't have to worry about manual
| loading any of our classes later on. It feels great to relax.
|
*/

require __DIR__ . '/../vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Redirect External Frontend Requests
|--------------------------------------------------------------------------
|
| For any non-API web request that is not the login endpoint, redirect
| the user to the external client area frontend.
|
*/
/*
if (PHP_SAPI !== 'cli' && isset($_SERVER['REQUEST_URI'])) {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';

    $isAuthLogin = $path === '/auth/login';
    $isApiRequest = $path === '/api' || strpos($path, '/api/') === 0;
    $isAdmin = $path === '/admin' || strpos($path, '/admin/') === 0;
    $isServer = $path === '/server' || strpos($path, '/server/') === 0;

    $isRestrictedPath = $isAuthLogin || $isApiRequest || $isAdmin || $isServer;

    if ($isRestrictedPath) {
        $allowedIps = ['189.28.184.26', '201.76.4.146', '143.208.215.169'];
        $clientIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        if (strpos($clientIp, ',') !== false) {
            $clientIp = trim(explode(',', $clientIp)[0]);
        }
        if (!in_array($clientIp, $allowedIps, true)) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'Acesso negado.';
            exit;
        }
    }

    if (!$isRestrictedPath) {
        header('Location: https://clientarea.hostgamer.net', true, 302);
        exit;
    }
}*/

/*
|--------------------------------------------------------------------------
| Turn On The Lights
|--------------------------------------------------------------------------
|
| We need to illuminate PHP development, so let us turn on the lights.
| This bootstraps the framework and gets it ready for use, then it
| will load up this application so that we can run it and send
| the responses back to the browser and delight our users.
|
*/

$app = require_once __DIR__ . '/../bootstrap/app.php';

/*
|--------------------------------------------------------------------------
| Run The Application
|--------------------------------------------------------------------------
|
| Once we have the application, we can handle the incoming request
| through the kernel, and send the associated response back to
| the client's browser allowing them to enjoy the creative
| and wonderful application we have prepared for them.
|
*/

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

$response->send();

$kernel->terminate($request, $response);

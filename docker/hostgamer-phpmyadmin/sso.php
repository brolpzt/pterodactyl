<?php
/**
 * phpMyAdmin SSO bridge — redeems a one-time token from the Pterodactyl panel.
 */
declare(strict_types=1);

$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
    || (($_SERVER['SERVER_PORT'] ?? null) == 443);

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_name('SignonSession');
session_start();

$token = (string) ($_GET['token'] ?? '');
$expires = (int) ($_GET['expires'] ?? 0);
$signature = (string) ($_GET['signature'] ?? '');

$secret = getenv('PHPMYADMIN_SSO_SECRET') ?: '';
$redeemUrl = getenv('PHPMYADMIN_REDEEM_URL') ?: 'https://control.hostgamer.net/api/internal/phpmyadmin/redeem';

function fail(int $code, string $message): void
{
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
    exit;
}

if ($secret === '' || strlen($secret) < 32) {
    fail(500, 'SSO secret not configured.');
}
if ($token === '' || $expires < 1 || $signature === '') {
    fail(400, 'Missing SSO parameters.');
}
if ($expires < time()) {
    fail(403, 'SSO link expired.');
}

$expected = hash_hmac('sha256', $token . '|' . $expires, $secret);
if (!hash_equals($expected, $signature)) {
    fail(403, 'Invalid SSO signature.');
}

$payload = json_encode([
    'token' => $token,
    'expires' => $expires,
    'signature' => $signature,
], JSON_THROW_ON_ERROR);

$ch = curl_init($redeemUrl);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Bearer ' . $secret,
    ],
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
]);
$body = curl_exec($ch);
$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

if ($body === false) {
    fail(502, 'Redeem request failed: ' . $err);
}

$data = json_decode($body, true);
if ($status !== 200 || !is_array($data)) {
    fail(403, 'Unable to redeem SSO token.');
}

$host = (string) ($data['host'] ?? '');
$port = (string) ($data['port'] ?? '3306');
$user = (string) ($data['username'] ?? '');
$pass = (string) ($data['password'] ?? '');
$onlyDb = $data['only_db'] ?? [];
if ($host === '' || $user === '') {
    fail(502, 'Incomplete credentials from panel.');
}
if (!is_array($onlyDb)) {
    $onlyDb = [$onlyDb];
}
$onlyDb = array_values(array_filter(array_map('strval', $onlyDb)));

$_SESSION['PMA_single_signon_user'] = $user;
$_SESSION['PMA_single_signon_password'] = $pass;
$_SESSION['PMA_single_signon_host'] = $host;
$_SESSION['PMA_single_signon_port'] = $port;
$_SESSION['PMA_single_signon_cfgupdate'] = [
    'only_db' => count($onlyDb) === 1 ? $onlyDb[0] : $onlyDb,
];

session_write_close();
header('Location: ./index.php');
exit;

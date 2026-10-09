<?php

declare(strict_types=1);

function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/** @return array{type: string, message: string}|null */
function take_flash(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);

    return $f;
}

function is_logged_in(): bool
{
    return !empty($_SESSION['auth']);
}

function require_auth(): void
{
    if (!is_logged_in()) {
        redirect('/login.php');
    }
}

/** @return list<int> */
function parse_port_list(string $input): array
{
    $out = [];
    foreach (preg_split('/[\s,;]+/', trim($input)) as $part) {
        if ($part === '' || !ctype_digit($part)) {
            continue;
        }
        $p = (int) $part;
        if ($p >= 1 && $p <= 65535) {
            $out[] = $p;
        }
    }

    return array_values(array_unique($out));
}

/** @return list<string> */
function parse_ip_list(string $input): array
{
    $out = [];
    foreach (preg_split('/[\s,;]+/', trim($input)) as $part) {
        $part = trim($part);
        if ($part !== '') {
            $out[] = $part;
        }
    }

    return array_values(array_unique($out));
}

/** @return list<string> */
function parse_proto_list(string $input): array
{
    $allowed = GcoreClient::PROTOCOLS;
    $out = [];
    foreach (preg_split('/[\s,;]+/', strtolower(trim($input))) as $part) {
        if (in_array($part, $allowed, true)) {
            $out[] = $part;
        }
    }

    return array_values(array_unique($out));
}

/** @param array<string, mixed> $profile */
function extract_profile_form_data(array $profile): array
{
    $rate = ['low' => 50, 'medium' => 150, 'high' => 300, 'geo' => 300];
    $geoip = [];
    $acl = [];

    foreach ($profile['fields'] as $field) {
        $name = $field['name'] ?? '';
        $val = $field['field_value'] ?? null;
        match ($name) {
            'Rate limiter low' => $rate['low'] = (int) $val,
            'Rate limiter medium' => $rate['medium'] = (int) $val,
            'Rate limiter high' => $rate['high'] = (int) $val,
            'Rate limiter geo' => $rate['geo'] = (int) $val,
            'GEOIP list' => $geoip = is_array($val) ? $val : [],
            'ACL list' => $acl = is_array($val) ? $val : [],
            default => null,
        };
    }

    return compact('rate', 'geoip', 'acl');
}

/** @return list<array<string, mixed>> */
function acl_from_post(array $post): array
{
    $rules = $post['acl'] ?? [];
    if (!is_array($rules)) {
        return [];
    }

    $out = [];
    foreach ($rules as $row) {
        if (!is_array($row)) {
            continue;
        }
        $policy = trim((string) ($row['policy'] ?? ''));
        if ($policy === '') {
            continue;
        }
        $out[] = [
            'policy' => $policy,
            'sip_list' => parse_ip_list((string) ($row['sip_list'] ?? '')),
            'dport_list' => parse_port_list((string) ($row['dport_list'] ?? '')),
            'proto_list' => parse_proto_list((string) ($row['proto_list'] ?? '')),
            'sport_list' => parse_port_list((string) ($row['sport_list'] ?? '')),
        ];
    }

    return $out;
}

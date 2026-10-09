<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';
require_auth();

$error = null;
$profiles = [];

try {
    $profiles = gcore_client()->listProfiles();
} catch (Throwable $e) {
    $error = $e->getMessage();
}

ob_start();
?>
<h1 class="page-title">Perfis IaaS</h1>
<p class="meta-row">Anti-DDoS bare metal · edite ACL e rate limiters por IP</p>

<?php if ($error): ?>
    <div class="flash flash-err"><?= h($error) ?></div>
<?php endif; ?>

<?php if (!$error && $profiles === []): ?>
    <p style="color:var(--muted)">Nenhum perfil nesta conta.</p>
<?php else: ?>
<div class="acl-wrap" style="max-height:none">
<table class="data list">
    <thead>
    <tr>
        <th class="col-idx">#</th>
        <th>ID</th>
        <th>IP protegido</th>
        <th>POP</th>
        <th>Template</th>
        <th>Plano</th>
        <th>BGP</th>
        <th>Status</th>
        <th></th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($profiles as $n => $p): ?>
        <?php
        $st = (string) ($p['status']['status'] ?? '—');
        $chip = str_contains(strtolower($st), 'pending') ? 'status-pending' : 'status-updated';
        $opt = $p['options'] ?? [];
        ?>
        <tr>
            <td class="col-idx"><?= $n + 1 ?></td>
            <td><?= (int) $p['id'] ?></td>
            <td><code><?= h($p['ip_address'] ?? '') ?></code></td>
            <td><?= h($p['site'] ?? '') ?></td>
            <td><?= h($p['profile_template']['name'] ?? '') ?></td>
            <td><?= h($p['plan'] ?? '') ?></td>
            <td><?= !empty($opt['bgp']) ? 'on' : 'off' ?></td>
            <td><span class="chip <?= h($chip) ?>"><?= h($st) ?></span></td>
            <td><a class="btn btn-primary" href="profile.php?id=<?= (int) $p['id'] ?>">Editar ACL</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
<?php
$content = ob_get_clean();
$title = 'Perfis';
require dirname(__DIR__) . '/templates/layout.php';

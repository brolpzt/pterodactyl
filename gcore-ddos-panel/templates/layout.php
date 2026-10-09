<?php
/** @var string $title */
/** @var string $content */
$flash = take_flash();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($title) ?> · Gcore DDoS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/panel.css">
</head>
<body>
<header class="topbar">
    <div class="topbar-brand">Gcore DDoS · ACL</div>
    <?php if (is_logged_in()): ?>
    <nav class="topbar-nav">
        <a href="index.php">Perfis</a>
        <a href="logout.php">Sair</a>
    </nav>
    <?php endif; ?>
</header>

<?php if ($flash): ?>
    <div class="flash <?= $flash['type'] === 'ok' ? 'flash-ok' : 'flash-err' ?>"><?= h($flash['message']) ?></div>
<?php endif; ?>

<main class="page">
<?= $content ?>
</main>
</body>
</html>

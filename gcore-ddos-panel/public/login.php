<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

if (is_logged_in()) {
    redirect('index.php');
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pass = $_POST['password'] ?? '';
    $expected = Env::get('ADMIN_PASSWORD', '');
    if ($expected !== null && $expected !== '' && hash_equals($expected, $pass)) {
        $_SESSION['auth'] = true;
        redirect('index.php');
    }
    $error = 'Senha incorreta.';
}

ob_start();
?>
<div class="login-box">
    <h1 class="page-title">Entrar</h1>
    <?php if ($error): ?><div class="flash flash-err" style="border-radius:4px;margin-bottom:10px"><?= h($error) ?></div><?php endif; ?>
    <form method="post">
        <div class="field field-full">
            <label>Senha do painel</label>
            <input type="password" name="password" required autofocus>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;margin-top:8px">Acessar</button>
    </form>
</div>
<?php
$content = ob_get_clean();
$title = 'Login';
require dirname(__DIR__) . '/templates/layout.php';

<?php
// Página de login dos autores.

require_once __DIR__ . '/../app/auth.php';

if (usuario_atual() !== null) {
    header('Location: index.php');
    exit;
}

$erro  = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $senha = $_POST['senha'] ?? '';

    if (!csrf_valido($_POST['csrf'] ?? null)) {
        $erro = 'Sessão expirada. Tente novamente.';
    } elseif ($email === '' || $senha === '') {
        $erro = 'Informe e-mail e senha.';
    } elseif (login_tentar($email, $senha)) {
        header('Location: index.php');
        exit;
    } else {
        $erro = 'E-mail ou senha incorretos.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Entrar · Autores</title>
    <link rel="stylesheet" href="<?= asset_url('assets/css/style.css') ?>">
</head>
<body class="pagina-login">
    <main class="login-box">
        <h1 class="page-title">Área do autor</h1>
        <p class="texto-2">Entre com seu e-mail e senha.</p>

        <?php if ($erro !== ''): ?>
            <p class="alerta" role="alert"><?= e($erro) ?></p>
        <?php endif; ?>

        <form method="post" action="login.php" class="form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

            <label for="email">E-mail</label>
            <input type="email" id="email" name="email" value="<?= e($email) ?>" autocomplete="username" required autofocus>

            <label for="senha">Senha</label>
            <input type="password" id="senha" name="senha" autocomplete="current-password" required>

            <button type="submit" class="btn btn-primary" data-carregando="Entrando…">Entrar</button>
        </form>
    </main>
    <script src="<?= asset_url('assets/js/app.js') ?>"></script>
</body>
</html>

<?php
// Cria o primeiro usuário pelo navegador. Só funciona enquanto não existir nenhum usuário; depois se desativa sozinha.

require_once __DIR__ . '/../app/auth.php';

$total = (int) db()->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
if ($total > 0) {
    http_response_code(404);
    exit('Página não encontrada.');
}

$erro  = '';
$nome  = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome  = trim($_POST['nome'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $senha = $_POST['senha'] ?? '';

    if (!csrf_valido($_POST['csrf'] ?? null)) {
        $erro = 'Sessão expirada. Tente novamente.';
    } elseif ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Informe um nome e um e-mail válido.';
    } elseif (strlen($senha) < 8) {
        $erro = 'A senha precisa ter pelo menos 8 caracteres.';
    } else {
        $stmt = db()->prepare('INSERT INTO usuarios (nome, email, senha_hash) VALUES (?, ?, ?)');
        $stmt->execute([$nome, $email, password_hash($senha, PASSWORD_DEFAULT)]);
        header('Location: login.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Primeiro acesso · Autores</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="pagina-login">
    <main class="login-box">
        <h1 class="page-title">Primeiro acesso</h1>
        <p class="texto-2">Crie o primeiro usuário do sistema.</p>

        <?php if ($erro !== ''): ?>
            <p class="alerta" role="alert"><?= e($erro) ?></p>
        <?php endif; ?>

        <form method="post" action="primeiro-acesso.php" class="form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

            <label for="nome">Nome</label>
            <input type="text" id="nome" name="nome" value="<?= e($nome) ?>" required autofocus>

            <label for="email">E-mail</label>
            <input type="email" id="email" name="email" value="<?= e($email) ?>" required>

            <label for="senha">Senha (mínimo 8 caracteres)</label>
            <input type="password" id="senha" name="senha" autocomplete="new-password" required>

            <button type="submit" class="btn btn-primary" data-carregando="Criando…">Criar usuário</button>
        </form>
    </main>
    <script src="assets/js/app.js"></script>
</body>
</html>

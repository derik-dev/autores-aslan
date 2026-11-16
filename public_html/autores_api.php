<?php
// API da tela Autores (só a editora): criar conta de autor e redefinir senha.

require_once __DIR__ . '/../app/http.php';

$usuario = exigir_api();
if (!eh_editora($usuario)) {
    falha('Só a editora pode gerenciar autores.', 403);
}
$acao = $_GET['acao'] ?? '';

// Senha temporária fácil de ditar: letras e números sem caracteres confusos.
function senha_temporaria(): string
{
    $alfabeto = 'abcdefghjkmnpqrstuvwxyzACDEFGHJKLMNPQRTUVWXYZ2346789';
    $s = '';
    for ($i = 0; $i < 12; $i++) {
        $s .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
    }
    return $s;
}

try {
    if ($acao === 'criar') {
        $nome  = mb_substr(trim((string) ($_POST['nome'] ?? '')), 0, 120, 'UTF-8');
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        if ($nome === '') {
            falha('Escreva o nome do autor.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            falha('Escreva um e-mail válido.');
        }
        $s = db()->prepare('SELECT id FROM usuarios WHERE email = ?');
        $s->execute([$email]);
        if ($s->fetch()) {
            falha('Já existe um usuário com este e-mail.');
        }
        $senha = senha_temporaria();
        db()->prepare('INSERT INTO usuarios (nome, email, senha_hash) VALUES (?, ?, ?)')
            ->execute([$nome, $email, password_hash($senha, PASSWORD_DEFAULT)]);
        resp(['ok' => true, 'email' => $email, 'senha' => $senha]);
    }

    if ($acao === 'redefinir') {
        $id = (int) ($_POST['id'] ?? 0);
        $s = db()->prepare('SELECT email FROM usuarios WHERE id = ?');
        $s->execute([$id]);
        $email = $s->fetchColumn();
        if (!$email) {
            falha('Autor não encontrado.', 404);
        }
        $senha = senha_temporaria();
        db()->prepare('UPDATE usuarios SET senha_hash = ? WHERE id = ?')->execute([password_hash($senha, PASSWORD_DEFAULT), $id]);
        resp(['ok' => true, 'email' => $email, 'senha' => $senha]);
    }

    falha('Ação desconhecida.', 404);
} catch (Throwable $e) {
    error_log('autores_api: ' . $e->getMessage());
    falha('Erro interno. Tente de novo.', 500);
}

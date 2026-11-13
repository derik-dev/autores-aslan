<?php
// Autenticação e controle de sessão dos usuários.

require_once __DIR__ . '/db.php';

function sessao_iniciar(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_name('autores_sid');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function usuario_atual(): ?array
{
    sessao_iniciar();
    return $_SESSION['usuario'] ?? null;
}

// Use no topo de toda página protegida.
function exigir_login(): array
{
    $usuario = usuario_atual();
    if ($usuario === null) {
        header('Location: login.php');
        exit;
    }
    header('Cache-Control: no-store');
    return $usuario;
}

function login_tentar(string $email, string $senha): bool
{
    sessao_iniciar();

    $stmt = db()->prepare('SELECT id, nome, email, senha_hash FROM usuarios WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $u = $stmt->fetch();

    // Verifica sempre uma senha, mesmo sem usuário, para não revelar se o e-mail existe pelo tempo de resposta.
    $hash = $u ? $u['senha_hash'] : password_hash('invalido', PASSWORD_DEFAULT);
    $ok   = password_verify($senha, $hash) && $u;

    if (!$ok) {
        usleep(400000); // freio simples contra tentativas em sequência
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['usuario'] = ['id' => (int) $u['id'], 'nome' => $u['nome'], 'email' => $u['email']];
    return true;
}

function logout(): void
{
    sessao_iniciar();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function csrf_token(): string
{
    sessao_iniciar();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_valido(?string $token): bool
{
    sessao_iniciar();
    return is_string($token) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

// Escapa texto antes de imprimir no HTML.
function e(?string $texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

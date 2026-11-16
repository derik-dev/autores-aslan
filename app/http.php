<?php
// Ajudantes das APIs internas em JSON e funções de uso geral.

require_once __DIR__ . '/auth.php';

function resp(array $dados, int $codigo = 200): void
{
    http_response_code($codigo);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

function falha(string $mensagem, int $codigo = 400): void
{
    resp(['ok' => false, 'erro' => $mensagem], $codigo);
}

// Prepara uma API em JSON: exige login, método POST e token CSRF. Devolve o usuário.
function exigir_api(): array
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $usuario = usuario_atual();
    if ($usuario === null) {
        falha('Sua sessão expirou. Entre de novo.', 401);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        falha('Método inválido.', 405);
    }
    if (!csrf_valido($_SERVER['HTTP_X_CSRF'] ?? ($_POST['csrf'] ?? null))) {
        falha('Sessão expirada. Recarregue a página.', 403);
    }
    session_write_close();
    return $usuario;
}

// A editora (administração) é o usuário de id 1 ou quem estiver em 'admin_emails' no config.php.
function eh_editora(array $usuario): bool
{
    if ((int) $usuario['id'] === 1) {
        return true;
    }
    $config = require __DIR__ . '/config.php';
    $lista  = array_map('strtolower', (array) ($config['admin_emails'] ?? []));
    return in_array(strtolower((string) ($usuario['email'] ?? '')), $lista, true);
}

function brl($valor): string
{
    return 'R$ ' . number_format((float) $valor, 2, ',', '.');
}

function num_br($n): string
{
    return number_format((float) $n, 0, ',', '.');
}

// Pasta de armazenamento fora da área pública (mesma regra da tela de áudio).
function pasta_storage(string $subpasta): string
{
    $config = require __DIR__ . '/config.php';
    $base   = $config['storage_path'] ?? (__DIR__ . '/storage');
    $pasta  = rtrim($base, '/\\') . '/' . $subpasta;
    if (!is_dir($pasta) && !mkdir($pasta, 0755, true) && !is_dir($pasta)) {
        throw new RuntimeException('Não consegui criar a pasta de arquivos no servidor.');
    }
    $negar = rtrim($base, '/\\') . '/.htaccess';
    if (!is_file($negar)) {
        @file_put_contents($negar, "Require all denied\n");
    }
    return $pasta;
}

function token_aleatorio(int $bytes = 20): string
{
    return bin2hex(random_bytes($bytes));
}

<?php
// Cria um autor pelo terminal (SSH). Uso: php app/criar_usuario.php  — não funciona pelo navegador.

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/db.php';

function perguntar(string $texto, bool $oculto = false): string
{
    echo $texto;
    if ($oculto) {
        system('stty -echo');
    }
    $resposta = trim((string) fgets(STDIN));
    if ($oculto) {
        system('stty echo');
        echo "\n";
    }
    return $resposta;
}

$nome  = perguntar('Nome: ');
$email = strtolower(perguntar('E-mail: '));
$senha = perguntar('Senha (mínimo 8 caracteres): ', true);

if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($senha) < 8) {
    exit("Dados inválidos. Nada foi criado.\n");
}

try {
    $stmt = db()->prepare('INSERT INTO usuarios (nome, email, senha_hash) VALUES (?, ?, ?)');
    $stmt->execute([$nome, $email, password_hash($senha, PASSWORD_DEFAULT)]);
    echo "Usuário criado: $email\n";
} catch (PDOException $e) {
    exit(($e->getCode() === '23000') ? "Esse e-mail já está cadastrado.\n" : "Erro ao criar usuário.\n");
}

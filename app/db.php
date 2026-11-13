<?php
// Conexão com o banco de dados MySQL (PDO). Uso: $pdo = db();

function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $config = require __DIR__ . '/config.php';

    try {
        $pdo = new PDO(
            "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4",
            $config['db_user'],
            $config['db_pass'],
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    } catch (PDOException $e) {
        // Não expõe host/usuário/senha na tela; o detalhe vai para o log do servidor.
        error_log('Erro de conexão com o banco: ' . $e->getMessage());
        http_response_code(500);
        exit('Erro interno. Tente novamente mais tarde.');
    }

    return $pdo;
}

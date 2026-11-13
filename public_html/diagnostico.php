<?php
// TEMPORÁRIO: diagnostica a conexão com o banco sem mostrar a senha. Remover depois de usar.

header('Content-Type: text/plain; charset=utf-8');

$arquivo = __DIR__ . '/../app/config.php';
echo 'config.php existe: ' . (is_file($arquivo) ? 'sim' : 'NAO') . "\n";
if (!is_file($arquivo)) {
    exit("Crie o arquivo app/config.php no servidor.\n");
}

$c = require $arquivo;
if (!is_array($c)) {
    exit("O config.php nao devolve uma lista (confira se tem 'return [' e '];').\n");
}

foreach (['db_host', 'db_name', 'db_user', 'db_pass'] as $chave) {
    $v = $c[$chave] ?? null;
    if ($v === null) {
        echo "$chave: FALTANDO (nome da chave errado ou apagado)\n";
        continue;
    }
    $placeholder = strpos($v, 'SEU_') === 0 || strpos($v, 'SUA_') === 0;
    echo "$chave: " . strlen($v) . ' caracteres' . ($placeholder ? ' - AINDA E PLACEHOLDER, preencha' : '') . "\n";
}
echo 'db_host vale: ' . ($c['db_host'] ?? '') . "\n";

try {
    $pdo = new PDO(
        "mysql:host={$c['db_host']};dbname={$c['db_name']};charset=utf8mb4",
        $c['db_user'],
        $c['db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "Conexao com o banco: OK\n";
} catch (Throwable $e) {
    $dicas = [
        '1045' => 'usuario ou senha do banco incorretos',
        '1044' => 'esse usuario nao tem acesso a esse banco',
        '1049' => 'nome do banco nao existe',
        '2002' => 'host errado (tente localhost ou 127.0.0.1)',
        '2006' => 'servidor do banco recusou a conexao',
    ];
    $codigo = (string) $e->getCode();
    echo "Conexao com o banco: FALHOU. Codigo $codigo - " . ($dicas[$codigo] ?? 'ver codigo') . "\n";
    exit;
}

try {
    $n = $pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
    echo "Tabela usuarios: existe, $n usuario(s)\n";
} catch (Throwable $e) {
    echo "Tabela usuarios: NAO EXISTE (importe o schema.sql no banco certo)\n";
}

<?php
// Modelo de CSV para importar vendas.

require_once __DIR__ . '/../app/auth.php';

if (usuario_atual() === null) {
    http_response_code(401);
    exit('Entre no sistema.');
}

$csv = "\xEF\xBB\xBF"
    . "autor_email;livro;canal;data;quantidade;valor_bruto;receita_autor\r\n"
    . "autor@exemplo.com;A Casa do Mar;Amazon;15/10/2026;3;89,70;26,91\r\n"
    . "autor@exemplo.com;A Casa do Mar;Site da editora;2026-10-20;1;39,90;15,96\r\n";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="modelo-vendas.csv"');
header('Content-Length: ' . strlen($csv));
echo $csv;

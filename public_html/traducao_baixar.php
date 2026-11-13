<?php
// Baixa a tradução (texto dos trechos já traduzidos) em um arquivo .txt.

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/traducoes.php';

$usuario = usuario_atual();
if ($usuario === null) {
    http_response_code(401);
    exit('Entre no sistema para baixar.');
}
session_write_close();

$id = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT titulo, idioma FROM traducoes WHERE id = ? AND usuario_id = ?');
$stmt->execute([$id, $usuario['id']]);
$t = $stmt->fetch();
if (!$t) {
    http_response_code(404);
    exit('Tradução não encontrada.');
}

$texto = trad_texto_final($id);
if ($texto === '') {
    http_response_code(404);
    exit('Nenhum trecho foi traduzido ainda.');
}

$nome = trim(preg_replace('/[^\p{L}\p{N} _-]+/u', '', $t['titulo'] . ' ' . $t['idioma']));
$nome = $nome !== '' ? $nome : 'traducao';

header('Content-Type: text/plain; charset=utf-8');
header('Content-Length: ' . strlen($texto));
header("Content-Disposition: attachment; filename=\"traducao.txt\"; filename*=UTF-8''" . rawurlencode($nome) . '.txt');
echo $texto;

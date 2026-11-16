<?php
// Entrega a imagem de um cartão de inspiração (só para o dono).

require_once __DIR__ . '/../app/http.php';

$usuario = usuario_atual();
if ($usuario === null) {
    http_response_code(401);
    exit;
}
session_write_close();

$id = (int) ($_GET['id'] ?? 0);
$s  = db()->prepare('SELECT imagem FROM inspiracoes WHERE id = ? AND usuario_id = ?');
$s->execute([$id, $usuario['id']]);
$nome = $s->fetchColumn();
if (!$nome) {
    http_response_code(404);
    exit;
}

$caminho = pasta_storage('inspiracao/' . (int) $usuario['id']) . '/' . basename((string) $nome);
if (!is_file($caminho)) {
    http_response_code(404);
    exit;
}
$tipos = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'];
$ext   = strtolower(pathinfo($caminho, PATHINFO_EXTENSION));

header('Content-Type: ' . ($tipos[$ext] ?? 'application/octet-stream'));
header('Content-Length: ' . filesize($caminho));
header('Cache-Control: private, max-age=86400');
header('X-Content-Type-Options: nosniff');
readfile($caminho);

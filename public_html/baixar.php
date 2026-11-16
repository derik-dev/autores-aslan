<?php
// Página pública de download (sem login): quem recebe o link baixa o arquivo, respeitando validade e limite.

require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/audios.php';
require_once __DIR__ . '/../app/entregas.php';

function pagina_publica(string $titulo, string $corpo): void
{
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<main class="publico">
    <?= $corpo ?>
</main>
</body>
</html>
<?php
}

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

$token = (string) ($_GET['t'] ?? $_POST['t'] ?? '');
$link  = null;
if (preg_match('/^[a-f0-9]{40}$/', $token)) {
    try {
        $s = db()->prepare('SELECT l.*, e.titulo, e.arquivo, e.nome_original, e.audio_id, e.usuario_id FROM entrega_links l JOIN entregas e ON e.id = l.entrega_id WHERE l.token = ?');
        $s->execute([$token]);
        $link = $s->fetch() ?: null;
    } catch (Throwable $e) {
        error_log('baixar.php: ' . $e->getMessage());
    }
}

if (!$link) {
    http_response_code(404);
    pagina_publica('Link não encontrado', '<h1 class="page-title">Link não encontrado</h1><p class="texto-2">Confira se você copiou o endereço completo ou peça um novo link a quem enviou.</p>');
    exit;
}

[$valido, $motivo] = ent_estado_link($link);
if (!$valido) {
    http_response_code(410);
    pagina_publica('Link indisponível', '<h1 class="page-title">Este link não está mais disponível</h1><p class="texto-2">' . h($motivo) . '. Peça um novo link a quem enviou.</p>');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $restam = (int) $link['limite'] - (int) $link['baixados'];
    pagina_publica(
        $link['titulo'],
        '<h1 class="page-title">' . h($link['titulo']) . '</h1>'
        . '<p class="texto-2">Para ' . h($link['destinatario']) . '. Você ainda pode baixar ' . $restam . ' vez(es)'
        . ($link['expira_em'] ? ', até ' . h(date('d/m/Y', strtotime($link['expira_em']))) : '') . '.</p>'
        . '<form method="post"><input type="hidden" name="t" value="' . h($token) . '">'
        . '<button type="submit" class="btn btn-primary">Baixar</button></form>'
    );
    exit;
}

// Conta o download de forma atômica: só segue se ainda houver saldo e o link continuar válido.
$conta = db()->prepare(
    'UPDATE entrega_links SET baixados = baixados + 1
     WHERE id = ? AND ativo = 1 AND baixados < limite AND (expira_em IS NULL OR expira_em > NOW())'
);
$conta->execute([$link['id']]);
if ($conta->rowCount() === 0) {
    http_response_code(410);
    pagina_publica('Link indisponível', '<h1 class="page-title">Este link não está mais disponível</h1><p class="texto-2">Peça um novo link a quem enviou.</p>');
    exit;
}

set_time_limit(0);
while (ob_get_level() > 0) {
    ob_end_clean();
}

$nomeBase = trim(preg_replace('/[^\p{L}\p{N} ._-]+/u', '', (string) $link['nome_original']));
$nomeBase = $nomeBase !== '' ? $nomeBase : 'arquivo';
$disposicao = "attachment; filename=\"download\"; filename*=UTF-8''" . rawurlencode($nomeBase);

if ($link['audio_id']) {
    // Áudio gerado: junta os trechos MP3 em sequência.
    $s = db()->prepare('SELECT ordem FROM audio_trechos WHERE audio_id = ? AND feito = 1 ORDER BY ordem');
    $s->execute([$link['audio_id']]);
    $arquivos = [];
    $total = 0;
    foreach ($s->fetchAll() as $linha) {
        $c = audio_arquivo((int) $link['audio_id'], (int) $linha['ordem']);
        if (is_file($c)) {
            $arquivos[] = $c;
            $total += filesize($c);
        }
    }
    if (!$arquivos) {
        http_response_code(404);
        exit('Arquivo indisponível.');
    }
    header('Content-Type: audio/mpeg');
    header('Content-Length: ' . $total);
    header('Content-Disposition: ' . $disposicao);
    foreach ($arquivos as $c) {
        readfile($c);
    }
    exit;
}

$caminho = pasta_storage('entregas/' . (int) $link['usuario_id']) . '/' . basename((string) $link['arquivo']);
if (!is_file($caminho)) {
    http_response_code(404);
    exit('Arquivo indisponível.');
}
header('Content-Type: application/octet-stream');
header('Content-Length: ' . filesize($caminho));
header('Content-Disposition: ' . $disposicao);
header('X-Content-Type-Options: nosniff');
readfile($caminho);

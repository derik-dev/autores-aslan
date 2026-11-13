<?php
// Entrega o MP3 de um áudio (todos os trechos já gerados, em sequência). Aceita "Range" para avançar no player.

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/audios.php';

$usuario = usuario_atual();
if ($usuario === null) {
    http_response_code(401);
    exit('Entre no sistema para ouvir.');
}
session_write_close();

$id = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT titulo FROM audios WHERE id = ? AND usuario_id = ?');
$stmt->execute([$id, $usuario['id']]);
$audio = $stmt->fetch();
if (!$audio) {
    http_response_code(404);
    exit('Áudio não encontrado.');
}

$stmt = db()->prepare('SELECT ordem FROM audio_trechos WHERE audio_id = ? AND feito = 1 ORDER BY ordem');
$stmt->execute([$id]);

$arquivos = [];
$total    = 0;
foreach ($stmt->fetchAll() as $linha) {
    $caminho = audio_arquivo($id, (int) $linha['ordem']);
    if (is_file($caminho)) {
        $tam        = filesize($caminho);
        $arquivos[] = [$caminho, $tam];
        $total     += $tam;
    }
}
if (!$arquivos) {
    http_response_code(404);
    exit('Nenhum trecho foi gerado ainda.');
}

$inicio = 0;
$fim    = $total - 1;
$codigo = 200;
if (isset($_SERVER['HTTP_RANGE']) && preg_match('/^bytes=(\d*)-(\d*)$/', trim($_SERVER['HTTP_RANGE']), $m)) {
    if ($m[1] === '' && $m[2] !== '') {
        $inicio = max(0, $total - (int) $m[2]);
    } else {
        if ($m[1] !== '') {
            $inicio = (int) $m[1];
        }
        if ($m[2] !== '') {
            $fim = min($fim, (int) $m[2]);
        }
    }
    if ($inicio > $fim || $inicio >= $total) {
        http_response_code(416);
        header('Content-Range: bytes */' . $total);
        exit;
    }
    $codigo = 206;
}

while (ob_get_level() > 0) {
    ob_end_clean();
}
set_time_limit(0);

http_response_code($codigo);
header('Content-Type: audio/mpeg');
header('Accept-Ranges: bytes');
header('Content-Length: ' . ($fim - $inicio + 1));
header('Cache-Control: private, max-age=0');
if ($codigo === 206) {
    header('Content-Range: bytes ' . $inicio . '-' . $fim . '/' . $total);
}
if (!empty($_GET['baixar'])) {
    $nome = trim(preg_replace('/[^\p{L}\p{N} _-]+/u', '', $audio['titulo']));
    $nome = $nome !== '' ? $nome : 'audio';
    header("Content-Disposition: attachment; filename=\"audio.mp3\"; filename*=UTF-8''" . rawurlencode($nome) . '.mp3');
}

$posicao  = 0;
$restante = $fim - $inicio + 1;
foreach ($arquivos as [$caminho, $tam]) {
    if ($restante <= 0) {
        break;
    }
    if ($posicao + $tam <= $inicio) {
        $posicao += $tam;
        continue;
    }
    $salto = max(0, $inicio - $posicao);
    $h     = fopen($caminho, 'rb');
    if (!$h) {
        break;
    }
    fseek($h, $salto);
    $falta = min($tam - $salto, $restante);
    while ($falta > 0 && !feof($h)) {
        $buf = fread($h, min(65536, $falta));
        if ($buf === false || $buf === '') {
            break;
        }
        echo $buf;
        $falta    -= strlen($buf);
        $restante -= strlen($buf);
    }
    fclose($h);
    $posicao += $tam;
}

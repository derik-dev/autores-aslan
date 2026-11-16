<?php
// Entrega digital: arquivos (PDF, EPUB, MP3…) e links de download com validade e limite de usos.

require_once __DIR__ . '/http.php';
require_once __DIR__ . '/audios.php';

// Endereço do sistema como o navegador vê (funciona em subpasta, como /autores).
function url_base(): string
{
    $https  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host   = preg_replace('/[^A-Za-z0-9.:-]/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $caminho = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    $pasta   = rtrim(str_replace('\\', '/', dirname($caminho)), '/');
    return ($https ? 'https' : 'http') . '://' . $host . $pasta;
}

function ent_link_publico(string $token): string
{
    return url_base() . '/baixar.php?t=' . $token;
}

// Cria um link de download. Devolve o token.
function ent_criar_link(int $entregaId, string $destinatario, int $dias, int $limite): string
{
    $token  = token_aleatorio(20);
    $expira = $dias > 0 ? date('Y-m-d H:i:s', time() + $dias * 86400) : null;
    db()->prepare('INSERT INTO entrega_links (entrega_id, token, destinatario, expira_em, limite) VALUES (?, ?, ?, ?, ?)')
        ->execute([$entregaId, $token, mb_substr($destinatario, 0, 190, 'UTF-8'), $expira, max(1, $limite)]);
    return $token;
}

// Entregas do usuário, cada uma com seus links.
function ent_listar(int $usuarioId): array
{
    $pdo = db();
    $s = $pdo->prepare('SELECT id, titulo, nome_original, audio_id, tamanho, criado_em FROM entregas WHERE usuario_id = ? ORDER BY id DESC');
    $s->execute([$usuarioId]);
    $entregas = $s->fetchAll();
    foreach ($entregas as &$e) {
        $l = $pdo->prepare('SELECT id, token, destinatario, expira_em, limite, baixados, ativo FROM entrega_links WHERE entrega_id = ? ORDER BY id DESC');
        $l->execute([$e['id']]);
        $e['links'] = $l->fetchAll();
    }
    unset($e);
    return $entregas;
}

function ent_tamanho_legivel(int $bytes): string
{
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1, ',', '.') . ' MB';
    }
    return number_format(max(1, $bytes / 1024), 0, ',', '.') . ' KB';
}

// Estado de um link: [ok(bool), mensagem].
function ent_estado_link(array $l): array
{
    if (!(int) $l['ativo']) {
        return [false, 'Link cancelado'];
    }
    if ($l['expira_em'] !== null && strtotime($l['expira_em']) < time()) {
        return [false, 'Link expirado'];
    }
    if ((int) $l['baixados'] >= (int) $l['limite']) {
        return [false, 'Limite de downloads atingido'];
    }
    return [true, 'Ativo'];
}

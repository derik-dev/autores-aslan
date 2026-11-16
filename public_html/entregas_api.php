<?php
// API de Entregas: guardar arquivo, criar/cancelar link e excluir.

require_once __DIR__ . '/../app/entregas.php';

$usuario = exigir_api();
$acao    = $_GET['acao'] ?? '';

function entrega_do_usuario(int $id, int $usuarioId): array
{
    $s = db()->prepare('SELECT * FROM entregas WHERE id = ? AND usuario_id = ?');
    $s->execute([$id, $usuarioId]);
    $e = $s->fetch();
    if (!$e) {
        falha('Entrega não encontrada.', 404);
    }
    return $e;
}

try {
    if ($acao === 'criar') {
        $titulo  = mb_substr(trim((string) ($_POST['titulo'] ?? '')), 0, 200, 'UTF-8');
        $audioId = (int) ($_POST['audio_id'] ?? 0);
        if ($titulo === '') {
            falha('Dê um título à entrega.');
        }

        if ($audioId > 0) {
            // Entrega de um áudio já gerado na tela de Texto em áudio.
            $s = db()->prepare("SELECT id FROM audios WHERE id = ? AND usuario_id = ? AND status = 'concluido'");
            $s->execute([$audioId, $usuario['id']]);
            if (!$s->fetch()) {
                falha('Escolha um áudio que já esteja pronto.');
            }
            $tam = (int) db()->query('SELECT COALESCE(SUM(bytes), 0) FROM audio_trechos WHERE audio_id = ' . $audioId)->fetchColumn();
            db()->prepare('INSERT INTO entregas (usuario_id, titulo, audio_id, nome_original, tamanho) VALUES (?, ?, ?, ?, ?)')
                ->execute([$usuario['id'], $titulo, $audioId, $titulo . '.mp3', $tam]);
            resp(['ok' => true]);
        }

        $arq = $_FILES['arquivo'] ?? null;
        if (!$arq || $arq['error'] === UPLOAD_ERR_NO_FILE) {
            falha('Escolha um arquivo ou um áudio pronto.');
        }
        if ($arq['error'] === UPLOAD_ERR_INI_SIZE || $arq['error'] === UPLOAD_ERR_FORM_SIZE) {
            falha('O arquivo é maior que o limite de envio do servidor.');
        }
        if ($arq['error'] !== UPLOAD_ERR_OK) {
            falha('Não consegui receber o arquivo. Tente de novo.');
        }
        $ext = strtolower(pathinfo($arq['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['pdf', 'epub', 'mobi', 'azw3', 'mp3', 'm4a', 'zip', 'docx', 'txt'], true)) {
            falha('Tipo de arquivo não aceito. Envie PDF, EPUB, MOBI, AZW3, MP3, M4A, ZIP, DOCX ou TXT.');
        }
        $pasta = pasta_storage('entregas/' . (int) $usuario['id']);
        $nome  = token_aleatorio(12) . '.' . $ext;
        if (!move_uploaded_file($arq['tmp_name'], $pasta . '/' . $nome)) {
            falha('Não consegui guardar o arquivo no servidor.');
        }
        db()->prepare('INSERT INTO entregas (usuario_id, titulo, arquivo, nome_original, tamanho) VALUES (?, ?, ?, ?, ?)')
            ->execute([$usuario['id'], $titulo, $nome, mb_substr(basename($arq['name']), 0, 200, 'UTF-8'), (int) $arq['size']]);
        resp(['ok' => true]);
    }

    if ($acao === 'criar_link') {
        $e = entrega_do_usuario((int) ($_POST['id'] ?? 0), $usuario['id']);
        $dest = trim((string) ($_POST['destinatario'] ?? ''));
        if ($dest === '') {
            falha('Diga para quem é o link (nome ou e-mail).');
        }
        $dias   = max(0, min(365, (int) ($_POST['dias'] ?? 7)));
        $limite = max(1, min(100, (int) ($_POST['limite'] ?? 3)));
        $token  = ent_criar_link((int) $e['id'], $dest, $dias, $limite);
        resp(['ok' => true, 'url' => ent_link_publico($token), 'titulo' => $e['titulo']]);
    }

    if ($acao === 'cancelar_link') {
        $id = (int) ($_POST['link_id'] ?? 0);
        db()->prepare('UPDATE entrega_links l JOIN entregas e ON e.id = l.entrega_id SET l.ativo = 0 WHERE l.id = ? AND e.usuario_id = ?')
            ->execute([$id, $usuario['id']]);
        resp(['ok' => true]);
    }

    if ($acao === 'excluir') {
        $e = entrega_do_usuario((int) ($_POST['id'] ?? 0), $usuario['id']);
        if ($e['arquivo']) {
            @unlink(pasta_storage('entregas/' . (int) $usuario['id']) . '/' . basename($e['arquivo']));
        }
        db()->prepare('DELETE FROM entregas WHERE id = ? AND usuario_id = ?')->execute([$e['id'], $usuario['id']]);
        resp(['ok' => true]);
    }

    falha('Ação desconhecida.', 404);
} catch (Throwable $e) {
    error_log('entregas_api: ' . $e->getMessage());
    falha('Erro interno. Tente de novo.', 500);
}

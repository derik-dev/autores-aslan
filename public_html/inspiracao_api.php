<?php
// API da área de Inspiração: criar e excluir cartões.

require_once __DIR__ . '/../app/http.php';

$usuario = exigir_api();
$acao    = $_GET['acao'] ?? '';
const INSP_TIPOS = ['ideia', 'personagem', 'pesquisa', 'imagem', 'link'];

try {
    if ($acao === 'criar') {
        $tipo     = (string) ($_POST['tipo'] ?? '');
        $titulo   = trim((string) ($_POST['titulo'] ?? ''));
        $conteudo = trim((string) ($_POST['conteudo'] ?? ''));
        $url      = trim((string) ($_POST['url'] ?? ''));

        if (!in_array($tipo, INSP_TIPOS, true)) {
            falha('Escolha o tipo do cartão.');
        }
        if ($titulo === '') {
            falha('Dê um título ao cartão.');
        }
        if ($url !== '' && !preg_match('#^https?://#i', $url)) {
            falha('O link precisa começar com http:// ou https://');
        }
        $titulo   = mb_substr($titulo, 0, 200, 'UTF-8');
        $conteudo = mb_substr($conteudo, 0, 10000, 'UTF-8');
        $url      = mb_substr($url, 0, 500, 'UTF-8');

        $nomeImg = null;
        $arq = $_FILES['imagem'] ?? null;
        if ($arq && $arq['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($arq['error'] !== UPLOAD_ERR_OK) {
                falha('Não consegui receber a imagem. Tente de novo.');
            }
            if ($arq['size'] > 5 * 1024 * 1024) {
                falha('A imagem passa de 5 MB.');
            }
            $info = @getimagesize($arq['tmp_name']);
            $exts = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
            if (!$info || !isset($exts[$info[2]])) {
                falha('Envie uma imagem JPG, PNG, GIF ou WEBP.');
            }
            $pasta   = pasta_storage('inspiracao/' . (int) $usuario['id']);
            $nomeImg = token_aleatorio(12) . '.' . $exts[$info[2]];
            if (!move_uploaded_file($arq['tmp_name'], $pasta . '/' . $nomeImg)) {
                falha('Não consegui guardar a imagem no servidor.');
            }
        }

        db()->prepare('INSERT INTO inspiracoes (usuario_id, tipo, titulo, conteudo, url, imagem) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$usuario['id'], $tipo, $titulo, $conteudo !== '' ? $conteudo : null, $url !== '' ? $url : null, $nomeImg]);
        resp(['ok' => true]);
    }

    if ($acao === 'excluir') {
        $id = (int) ($_POST['id'] ?? 0);
        $s = db()->prepare('SELECT imagem FROM inspiracoes WHERE id = ? AND usuario_id = ?');
        $s->execute([$id, $usuario['id']]);
        $linha = $s->fetch();
        if (!$linha) {
            falha('Cartão não encontrado.', 404);
        }
        if ($linha['imagem']) {
            @unlink(pasta_storage('inspiracao/' . (int) $usuario['id']) . '/' . basename($linha['imagem']));
        }
        db()->prepare('DELETE FROM inspiracoes WHERE id = ? AND usuario_id = ?')->execute([$id, $usuario['id']]);
        resp(['ok' => true]);
    }

    falha('Ação desconhecida.', 404);
} catch (Throwable $e) {
    error_log('inspiracao_api: ' . $e->getMessage());
    falha('Erro interno. Tente de novo.', 500);
}

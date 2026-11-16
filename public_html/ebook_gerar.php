<?php
// Recebe o formulário da tela E-book e devolve o EPUB para baixar ou a página para imprimir/salvar em PDF.

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/audios.php';
require_once __DIR__ . '/../app/ebook.php';

$usuario = usuario_atual();
if ($usuario === null) {
    header('Location: login.php');
    exit;
}

function voltar_com_erro(string $mensagem): void
{
    header('Location: ebook.php?erro=' . rawurlencode($mensagem));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_valido($_POST['csrf'] ?? null)) {
    voltar_com_erro('Sessão expirada. Tente de novo.');
}
session_write_close();

$titulo  = mb_substr(trim((string) ($_POST['titulo'] ?? '')), 0, 200, 'UTF-8');
$autor   = mb_substr(trim((string) ($_POST['autor'] ?? '')), 0, 150, 'UTF-8');
$idioma  = (string) ($_POST['idioma'] ?? 'pt-BR');
$formato = (string) ($_POST['formato'] ?? 'epub');
$detectar = !empty($_POST['capitulos']);
$texto   = (string) ($_POST['texto'] ?? '');

if (!in_array($idioma, ['pt-BR', 'pt-PT', 'en', 'es', 'fr', 'it', 'de'], true)) {
    $idioma = 'pt-BR';
}
if ($titulo === '') {
    voltar_com_erro('Dê um título ao livro.');
}

// Arquivo enviado (.txt ou .docx) tem prioridade sobre o texto colado.
$arq = $_FILES['arquivo'] ?? null;
if ($arq && $arq['error'] === UPLOAD_ERR_OK) {
    if ($arq['size'] > 20 * 1024 * 1024) {
        voltar_com_erro('O arquivo passa de 20 MB.');
    }
    $ext = strtolower(pathinfo($arq['name'], PATHINFO_EXTENSION));
    if ($ext === 'docx') {
        try {
            $texto = audio_extrair_docx($arq['tmp_name']);
        } catch (Throwable $e) {
            voltar_com_erro($e->getMessage());
        }
    } elseif ($ext === 'txt') {
        $texto = (string) file_get_contents($arq['tmp_name']);
        if (!mb_check_encoding($texto, 'UTF-8')) {
            $texto = mb_convert_encoding($texto, 'UTF-8', 'Windows-1252');
        }
        $texto = (string) preg_replace('/^\xEF\xBB\xBF/', '', $texto);
    } else {
        voltar_com_erro('Envie um arquivo .txt ou .docx.');
    }
}
if (trim($texto) === '') {
    voltar_com_erro('Cole ou envie o texto do livro.');
}
if (mb_strlen($texto, 'UTF-8') > EBOOK_MAX_CHARS) {
    voltar_com_erro('O texto é grande demais para um único e-book.');
}

$caps = ebook_capitulos($texto, $detectar, $titulo);
if (!$caps) {
    voltar_com_erro('Não encontrei texto para o e-book.');
}

if ($formato === 'pdf') {
    header('Content-Type: text/html; charset=utf-8');
    echo ebook_html_impressao($titulo, $autor, $idioma, $caps);
    exit;
}

// Capa opcional (JPG ou PNG).
$capa = null;
$img  = $_FILES['capa'] ?? null;
if ($img && $img['error'] === UPLOAD_ERR_OK) {
    if ($img['size'] > 5 * 1024 * 1024) {
        voltar_com_erro('A capa passa de 5 MB.');
    }
    $info = @getimagesize($img['tmp_name']);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
        voltar_com_erro('A capa precisa ser uma imagem JPG ou PNG.');
    }
    $capa = ['dados' => (string) file_get_contents($img['tmp_name']), 'ext' => $info[2] === IMAGETYPE_PNG ? 'png' : 'jpg'];
}

try {
    $arquivo = ebook_gerar_epub($titulo, $autor, $idioma, $caps, $capa);
} catch (Throwable $e) {
    error_log('ebook_gerar: ' . $e->getMessage());
    voltar_com_erro('Não consegui gerar o e-book. ' . $e->getMessage());
}

$nome = trim((string) preg_replace('/[^\p{L}\p{N} _-]+/u', '', $titulo));
$nome = $nome !== '' ? $nome : 'ebook';
header('Content-Type: application/epub+zip');
header('Content-Length: ' . filesize($arquivo));
header("Content-Disposition: attachment; filename=\"ebook.epub\"; filename*=UTF-8''" . rawurlencode($nome) . '.epub');
readfile($arquivo);
@unlink($arquivo);

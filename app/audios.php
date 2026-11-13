<?php
// Lógica da função "Texto em áudio": dividir o texto em trechos, ler arquivos .docx e guardar os MP3.

require_once __DIR__ . '/db.php';

const AUDIO_TRECHO    = 4500;    // caracteres por trecho (cabe com folga no limite de cada pedido)
const AUDIO_MAX_CHARS = 600000;  // limite por áudio (~330 páginas)

// Pasta onde ficam os MP3 de um áudio (fora da área pública).
function audio_pasta(int $id): string
{
    $config = require __DIR__ . '/config.php';
    $base   = $config['storage_path'] ?? (__DIR__ . '/storage');
    return rtrim($base, '/\\') . '/audios/' . $id;
}

function audio_arquivo(int $id, int $ordem): string
{
    return audio_pasta($id) . '/' . sprintf('%05d', $ordem) . '.mp3';
}

function audio_garantir_pasta(int $id): string
{
    $pasta = audio_pasta($id);
    if (!is_dir($pasta) && !mkdir($pasta, 0755, true) && !is_dir($pasta)) {
        throw new RuntimeException('Não consegui criar a pasta de áudios no servidor.');
    }
    // Reforço: nega acesso direto pelo navegador à pasta de armazenamento.
    $negar = dirname(dirname($pasta)) . '/.htaccess';
    if (!is_file($negar)) {
        @file_put_contents($negar, "Require all denied\n");
    }
    return $pasta;
}

function audio_apagar_pasta(int $id): void
{
    $pasta = audio_pasta($id);
    if (!is_dir($pasta)) {
        return;
    }
    foreach (glob($pasta . '/*') ?: [] as $arquivo) {
        if (is_file($arquivo)) {
            @unlink($arquivo);
        }
    }
    @rmdir($pasta);
}

// Divide um texto em trechos de até $max caracteres, sem cortar no meio de parágrafo ou frase.
function audio_dividir(string $texto, int $max = AUDIO_TRECHO): array
{
    $texto = str_replace(["\r\n", "\r", "\0"], ["\n", "\n", ''], $texto);

    $pedacos = [];
    foreach (explode("\n", $texto) as $linha) {
        $linha = trim($linha);
        if ($linha === '') {
            continue;
        }
        if (mb_strlen($linha, 'UTF-8') <= $max) {
            $pedacos[] = $linha;
        } else {
            foreach (audio_quebrar_longo($linha, $max) as $parte) {
                $pedacos[] = $parte;
            }
        }
    }

    // Junta parágrafos seguidos até encher o trecho.
    $trechos = [];
    $atual   = '';
    foreach ($pedacos as $p) {
        $junto = $atual === '' ? $p : $atual . "\n\n" . $p;
        if (mb_strlen($junto, 'UTF-8') <= $max) {
            $atual = $junto;
        } else {
            if ($atual !== '') {
                $trechos[] = $atual;
            }
            $atual = $p;
        }
    }
    if ($atual !== '') {
        $trechos[] = $atual;
    }
    return $trechos;
}

// Quebra um parágrafo gigante por frases e, se preciso, por espaços.
function audio_quebrar_longo(string $paragrafo, int $max): array
{
    $saida  = [];
    $atual  = '';
    $frases = preg_split('/(?<=[.!?…])\s+/u', $paragrafo);
    if ($frases === false) {
        $frases = [$paragrafo];
    }
    foreach ($frases as $frase) {
        while (mb_strlen($frase, 'UTF-8') > $max) {
            $parte  = mb_substr($frase, 0, $max, 'UTF-8');
            $espaco = mb_strrpos($parte, ' ', 0, 'UTF-8');
            if ($espaco !== false && $espaco > $max / 2) {
                $parte = mb_substr($parte, 0, $espaco, 'UTF-8');
            }
            if ($atual !== '') {
                $saida[] = $atual;
                $atual   = '';
            }
            $saida[] = trim($parte);
            $frase   = ltrim(mb_substr($frase, mb_strlen($parte, 'UTF-8'), null, 'UTF-8'));
        }
        if ($frase === '') {
            continue;
        }
        if ($atual === '') {
            $atual = $frase;
        } elseif (mb_strlen($atual . ' ' . $frase, 'UTF-8') <= $max) {
            $atual .= ' ' . $frase;
        } else {
            $saida[] = $atual;
            $atual   = $frase;
        }
    }
    if ($atual !== '') {
        $saida[] = $atual;
    }
    return $saida;
}

// Lê o texto de um arquivo .docx (Word) sem bibliotecas extras.
function audio_extrair_docx(string $caminho): string
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('O servidor não consegue abrir arquivos .docx. Envie em .txt ou cole o texto.');
    }
    $zip = new ZipArchive();
    if ($zip->open($caminho) !== true) {
        throw new RuntimeException('Não consegui abrir esse arquivo .docx.');
    }
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();
    if ($xml === false) {
        throw new RuntimeException('Esse arquivo não parece ser um .docx válido.');
    }
    $xml = preg_replace('/<w:br\b[^>]*\/>/', "\n", $xml);
    $xml = preg_replace('/<w:tab\b[^>]*\/>/', ' ', $xml);
    $xml = str_replace('</w:p>', "\n", $xml);
    $txt = strip_tags($xml);
    return html_entity_decode($txt, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

// Áudios do usuário, com andamento: [id, titulo, modelo, total_chars, status, erro, criado_em, total, feitos].
function audio_listar(int $usuarioId): array
{
    $sql = 'SELECT a.id, a.titulo, a.modelo, a.total_chars, a.status, a.erro, a.criado_em,
                   COUNT(t.id) AS total, COALESCE(SUM(t.feito), 0) AS feitos
            FROM audios a
            LEFT JOIN audio_trechos t ON t.audio_id = a.id
            WHERE a.usuario_id = ?
            GROUP BY a.id
            ORDER BY a.id DESC';
    $stmt = db()->prepare($sql);
    $stmt->execute([$usuarioId]);
    return $stmt->fetchAll();
}

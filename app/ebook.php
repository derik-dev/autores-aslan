<?php
// Diagramação simples: transforma um texto em EPUB 3 (e-book) ou em uma página pronta para imprimir/salvar como PDF.

const EBOOK_MAX_CHARS = 2000000;

// Remove caracteres que não podem existir em XML/XHTML.
function ebook_limpar(string $s): string
{
    $s = str_replace(["\r\n", "\r"], "\n", $s);
    return (string) preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $s);
}

function ebook_esc(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

// Uma linha de texto vira HTML de parágrafo: escapa e converte **negrito** e *itálico*.
function ebook_inline(string $linha): string
{
    $h = ebook_esc($linha);
    $h = preg_replace('/\*\*(.+?)\*\*/us', '<strong>$1</strong>', $h);
    $h = preg_replace('/(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?![\w*])/us', '<em>$1</em>', $h);
    return (string) $h;
}

function ebook_eh_quebra_de_cena(string $linha): bool
{
    return (bool) preg_match('/^(\*\s*\*\s*\*|-{3,}|#{3,}|\*{3,}|~{3,})$/', trim($linha));
}

function ebook_eh_titulo_de_capitulo(string $linha): bool
{
    $l = trim($linha);
    if ($l === '' || mb_strlen($l, 'UTF-8') > 90) {
        return false;
    }
    return (bool) preg_match('/^(#{1,2}\s+\S.*|(cap[ií]tulo|chapter|parte|part|pr[óo]logo|ep[íi]logo|prologue|epilogue)\b.*)$/iu', $l);
}

// Divide o texto em capítulos: [['titulo' => ..., 'linhas' => [...]], ...]
function ebook_capitulos(string $texto, bool $detectar, string $tituloPadrao): array
{
    $texto = ebook_limpar($texto);
    $caps  = [];
    $atual = ['titulo' => $tituloPadrao, 'linhas' => [], 'implicito' => true];

    foreach (explode("\n", $texto) as $linha) {
        $t = trim($linha);
        if ($t === '') {
            continue;
        }
        if ($detectar && ebook_eh_titulo_de_capitulo($t)) {
            if ($atual['linhas'] || !$atual['implicito']) {
                $caps[] = $atual;
            }
            $atual = ['titulo' => trim(preg_replace('/^#{1,2}\s+/', '', $t)), 'linhas' => [], 'implicito' => false];
            continue;
        }
        $atual['linhas'][] = $t;
    }
    if ($atual['linhas'] || !$atual['implicito']) {
        $caps[] = $atual;
    }
    return $caps;
}

function ebook_corpo_capitulo(array $cap, bool $comTitulo = true): string
{
    $h = $comTitulo ? '<h1>' . ebook_esc($cap['titulo']) . "</h1>\n" : '';
    foreach ($cap['linhas'] as $linha) {
        if (ebook_eh_quebra_de_cena($linha)) {
            $h .= '<p class="cena">* * *</p>' . "\n";
        } else {
            $h .= '<p>' . ebook_inline($linha) . "</p>\n";
        }
    }
    return $h;
}

const EBOOK_CSS = "body { line-height: 1.5; margin: 0 5%; }\n"
    . "h1 { text-align: center; margin: 2.5em 0 1.5em; font-size: 1.6em; font-weight: bold; }\n"
    . "p { margin: 0; text-indent: 1.4em; text-align: justify; }\n"
    . "h1 + p, p.cena + p { text-indent: 0; }\n"
    . "p.cena { text-align: center; text-indent: 0; margin: 1.5em 0; }\n"
    . ".capa { text-align: center; margin: 0; }\n"
    . ".capa img { max-width: 100%; max-height: 100%; }\n"
    . ".titulo-livro { text-align: center; margin-top: 30%; }\n"
    . ".titulo-livro p { text-indent: 0; text-align: center; }\n";

function ebook_xhtml(string $titulo, string $corpo, string $idioma, string $classeBody = ''): string
{
    return '<?xml version="1.0" encoding="utf-8"?>' . "\n"
        . '<!DOCTYPE html>' . "\n"
        . '<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops" lang="' . ebook_esc($idioma) . '" xml:lang="' . ebook_esc($idioma) . '">' . "\n"
        . '<head><meta charset="utf-8"/><title>' . ebook_esc($titulo) . '</title><link rel="stylesheet" type="text/css" href="style.css"/></head>' . "\n"
        . '<body' . ($classeBody !== '' ? ' class="' . $classeBody . '"' : '') . '>' . "\n" . $corpo . "</body>\n</html>\n";
}

// Gera o EPUB num arquivo temporário e devolve o caminho. $capa = ['dados' => bytes, 'ext' => 'jpg'|'png'] ou null.
function ebook_gerar_epub(string $titulo, string $autor, string $idioma, array $caps, ?array $capa): string
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('O servidor não consegue criar arquivos EPUB (falta o suporte a ZIP).');
    }
    $tmp = tempnam(sys_get_temp_dir(), 'epub');
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::OVERWRITE | ZipArchive::CREATE) !== true) {
        throw new RuntimeException('Não consegui criar o arquivo EPUB.');
    }

    // O "mimetype" precisa ser o primeiro arquivo e ficar sem compressão.
    $zip->addFromString('mimetype', 'application/epub+zip');
    $zip->setCompressionName('mimetype', ZipArchive::CM_STORE);
    $zip->addFromString('META-INF/container.xml',
        '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
        . '<container version="1.0" xmlns="urn:oasis:names:tc:opendocument:xmlns:container"><rootfiles>'
        . '<rootfile full-path="OEBPS/content.opf" media-type="application/oebps-package+xml"/></rootfiles></container>');
    $zip->addFromString('OEBPS/style.css', EBOOK_CSS);

    $manifest = '<item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>' . "\n"
        . '<item id="css" href="style.css" media-type="text/css"/>' . "\n";
    $spine = '';
    $navItens = '';

    if ($capa) {
        $mime = $capa['ext'] === 'png' ? 'image/png' : 'image/jpeg';
        $zip->addFromString('OEBPS/capa.' . $capa['ext'], $capa['dados']);
        $zip->addFromString('OEBPS/capa.xhtml', ebook_xhtml('Capa', '<div class="capa"><img src="capa.' . $capa['ext'] . '" alt="Capa"/></div>' . "\n", $idioma));
        $manifest .= '<item id="capa-img" href="capa.' . $capa['ext'] . '" media-type="' . $mime . '" properties="cover-image"/>' . "\n"
            . '<item id="capa" href="capa.xhtml" media-type="application/xhtml+xml"/>' . "\n";
        $spine .= '<itemref idref="capa"/>' . "\n";
    }

    $folha = '<div class="titulo-livro"><h1>' . ebook_esc($titulo) . '</h1>' . ($autor !== '' ? '<p>' . ebook_esc($autor) . '</p>' : '') . "</div>\n";
    $zip->addFromString('OEBPS/titulo.xhtml', ebook_xhtml($titulo, $folha, $idioma));
    $manifest .= '<item id="titulo" href="titulo.xhtml" media-type="application/xhtml+xml"/>' . "\n";
    $spine .= '<itemref idref="titulo"/>' . "\n";

    foreach ($caps as $i => $cap) {
        $id  = sprintf('cap%03d', $i + 1);
        $zip->addFromString('OEBPS/' . $id . '.xhtml', ebook_xhtml($cap['titulo'], ebook_corpo_capitulo($cap), $idioma));
        $manifest .= '<item id="' . $id . '" href="' . $id . '.xhtml" media-type="application/xhtml+xml"/>' . "\n";
        $spine    .= '<itemref idref="' . $id . '"/>' . "\n";
        $navItens .= '<li><a href="' . $id . '.xhtml">' . ebook_esc($cap['titulo']) . '</a></li>' . "\n";
    }

    $nav = '<nav epub:type="toc" id="toc"><h1>Sumário</h1><ol>' . "\n" . $navItens . '</ol></nav>' . "\n";
    $zip->addFromString('OEBPS/nav.xhtml', ebook_xhtml('Sumário', $nav, $idioma));

    $uuid = sprintf('%08x-%04x-%04x-%04x-%012x', random_int(0, 0xffffffff), random_int(0, 0xffff), random_int(0x4000, 0x4fff), random_int(0x8000, 0xbfff), random_int(0, 0xffffffffffff));
    $opf = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
        . '<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="uid">' . "\n"
        . '<metadata xmlns:dc="http://purl.org/dc/elements/1.1/">' . "\n"
        . '<dc:identifier id="uid">urn:uuid:' . $uuid . '</dc:identifier>' . "\n"
        . '<dc:title>' . ebook_esc($titulo) . '</dc:title>' . "\n"
        . ($autor !== '' ? '<dc:creator>' . ebook_esc($autor) . '</dc:creator>' . "\n" : '')
        . '<dc:language>' . ebook_esc($idioma) . '</dc:language>' . "\n"
        . '<meta property="dcterms:modified">' . gmdate('Y-m-d\TH:i:s\Z') . '</meta>' . "\n"
        . '</metadata>' . "\n<manifest>\n" . $manifest . "</manifest>\n<spine>\n" . $spine . "</spine>\n</package>\n";
    $zip->addFromString('OEBPS/content.opf', $opf);
    $zip->close();
    return $tmp;
}

// Página HTML formatada como livro, pronta para "Imprimir → Salvar como PDF".
function ebook_html_impressao(string $titulo, string $autor, string $idioma, array $caps): string
{
    $sumario = '';
    $corpo   = '';
    foreach ($caps as $i => $cap) {
        $sumario .= '<li><a href="#c' . ($i + 1) . '">' . ebook_esc($cap['titulo']) . '</a></li>';
        $corpo   .= '<section class="capitulo" id="c' . ($i + 1) . '">' . ebook_corpo_capitulo($cap) . '</section>';
    }
    $css = '@page { size: A5; margin: 18mm 16mm; }'
        . 'body { font-family: Georgia, "Times New Roman", serif; font-size: 11.5pt; line-height: 1.5; color: #000; background: #fff; margin: 0; }'
        . '.barra { position: sticky; top: 0; background: #f4f4f4; border-bottom: 1px solid #ccc; padding: 10px 16px; font-family: Inter, Arial, sans-serif; font-size: 14px; display: flex; gap: 12px; align-items: center; }'
        . '.barra button { font: inherit; padding: 6px 12px; border: 1px solid #999; border-radius: 6px; background: #fff; cursor: pointer; }'
        . '.livro { max-width: 148mm; margin: 0 auto; padding: 16px; }'
        . '.folha, .sumario { page-break-after: always; text-align: center; }'
        . '.folha h1 { margin-top: 30%; font-size: 26pt; } .folha p { font-size: 14pt; }'
        . '.sumario ol { text-align: left; } .sumario a { color: inherit; text-decoration: none; }'
        . '.capitulo { page-break-before: always; } h1 { text-align: center; margin: 2em 0 1.5em; font-size: 18pt; }'
        . 'p { margin: 0; text-indent: 1.4em; text-align: justify; orphans: 3; widows: 3; }'
        . 'h1 + p, p.cena + p { text-indent: 0; } p.cena { text-align: center; text-indent: 0; margin: 1.5em 0; }'
        . '@media print { .barra { display: none; } .livro { padding: 0; max-width: none; } }';
    return '<!DOCTYPE html><html lang="' . ebook_esc($idioma) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . ebook_esc($titulo) . '</title><style>' . $css . '</style></head><body>'
        . '<div class="barra"><button type="button" onclick="window.print()">Imprimir ou salvar como PDF</button><span>No diálogo de impressão, escolha "Salvar como PDF".</span></div>'
        . '<div class="livro"><div class="folha"><h1>' . ebook_esc($titulo) . '</h1>' . ($autor !== '' ? '<p>' . ebook_esc($autor) . '</p>' : '') . '</div>'
        . '<div class="sumario"><h1>Sumário</h1><ol>' . $sumario . '</ol></div>' . $corpo . '</div></body></html>';
}

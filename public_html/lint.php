<?php
// TEMPORÁRIO: confere se algum arquivo PHP do projeto tem erro de sintaxe. Remover depois de usar.

header('Content-Type: text/plain; charset=utf-8');

$raiz     = realpath(__DIR__ . '/..');
$pastas   = [__DIR__, __DIR__ . '/../app'];
$total    = 0;
$problemas = 0;

foreach ($pastas as $pasta) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pasta, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $arq) {
        $caminho = $arq->getPathname();
        if ($arq->getExtension() !== 'php' || strpos($caminho, DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR) !== false) {
            continue;
        }
        $total++;
        $nome = str_replace($raiz, '', realpath($caminho));
        try {
            token_get_all((string) file_get_contents($caminho), TOKEN_PARSE);
            echo "OK    $nome\n";
        } catch (ParseError $e) {
            $problemas++;
            echo "ERRO  $nome (linha " . $e->getLine() . '): ' . $e->getMessage() . "\n";
        }
    }
}
echo "\n$total arquivos conferidos, $problemas com erro.\n";
echo 'PHP ' . PHP_VERSION . ' | curl: ' . (function_exists('curl_init') ? 'sim' : 'NAO')
   . ' | mbstring: ' . (function_exists('mb_strlen') ? 'sim' : 'NAO')
   . ' | zip (.docx): ' . (class_exists('ZipArchive') ? 'sim' : 'NAO') . "\n";

<?php
// API do painel de vendas (só a editora): importar CSV e desfazer importação.

require_once __DIR__ . '/../app/http.php';

$usuario = exigir_api();
if (!eh_editora($usuario)) {
    falha('Só a editora pode importar vendas.', 403);
}
$acao = $_GET['acao'] ?? '';

// "1.234,56" ou "1234.56" ou "R$ 12,5" → float
function numero_csv(string $v): float
{
    $v = trim(str_replace(['R$', ' ', "\xC2\xA0"], '', $v));
    if ($v === '') {
        return 0.0;
    }
    if (strpos($v, ',') !== false) {
        $v = str_replace('.', '', $v);
        $v = str_replace(',', '.', $v);
    }
    return (float) $v;
}

// "15/10/2026" ou "2026-10-15" → "2026-10-15" (ou null)
function data_csv(string $v): ?string
{
    $v = trim($v);
    if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $v, $m)) {
        return checkdate((int) $m[2], (int) $m[1], (int) $m[3]) ? sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]) : null;
    }
    if (preg_match('#^(\d{4})-(\d{1,2})-(\d{1,2})#', $v, $m)) {
        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]) : null;
    }
    return null;
}

function chave_coluna(string $s): string
{
    $s = mb_strtolower(trim($s), 'UTF-8');
    $s = strtr($s, ['á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ç' => 'c']);
    return preg_replace('/[^a-z0-9]+/', '_', $s);
}

try {
    if ($acao === 'importar') {
        $arq = $_FILES['arquivo'] ?? null;
        if (!$arq || $arq['error'] !== UPLOAD_ERR_OK) {
            falha('Não consegui receber o arquivo. Tente de novo.');
        }
        if ($arq['size'] > 10 * 1024 * 1024) {
            falha('O arquivo passa de 10 MB.');
        }
        $conteudo = (string) file_get_contents($arq['tmp_name']);
        $conteudo = preg_replace('/^\xEF\xBB\xBF/', '', $conteudo);
        if (!mb_check_encoding($conteudo, 'UTF-8')) {
            $conteudo = mb_convert_encoding($conteudo, 'UTF-8', 'Windows-1252');
        }
        $primeira = strtok($conteudo, "\n");
        $sep = substr_count((string) $primeira, ';') >= substr_count((string) $primeira, ',') ? ';' : ',';

        $h = fopen('php://temp', 'r+');
        fwrite($h, $conteudo);
        rewind($h);

        $cab = fgetcsv($h, 0, $sep);
        if (!$cab) {
            falha('O arquivo está vazio.');
        }
        $idx = array_flip(array_map('chave_coluna', $cab));
        $obrig = ['autor_email', 'livro', 'canal', 'data', 'quantidade', 'valor_bruto', 'receita_autor'];
        $faltam = array_values(array_filter($obrig, function ($c) use ($idx) { return !isset($idx[$c]); }));
        if ($faltam) {
            falha('Faltam colunas no arquivo: ' . implode(', ', $faltam) . '. Baixe o modelo de CSV.');
        }

        $pdo = db();
        $autores = [];
        foreach ($pdo->query('SELECT id, email FROM usuarios')->fetchAll() as $u) {
            $autores[strtolower($u['email'])] = (int) $u['id'];
        }

        $pdo->beginTransaction();
        try {
            $pdo->prepare('INSERT INTO importacoes_vendas (usuario_id, arquivo) VALUES (?, ?)')
                ->execute([$usuario['id'], mb_substr($arq['name'], 0, 200, 'UTF-8')]);
            $impId = (int) $pdo->lastInsertId();
            $ins = $pdo->prepare('INSERT INTO vendas (importacao_id, autor_id, livro, canal, data_venda, quantidade, valor_bruto, receita_autor) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');

            $ok = 0;
            $ign = 0;
            $motivos = [];
            $linhaNum = 1;
            while (($l = fgetcsv($h, 0, $sep)) !== false) {
                $linhaNum++;
                if (count($l) === 1 && trim((string) $l[0]) === '') {
                    continue;
                }
                if ($ok + $ign >= 50000) {
                    break;
                }
                $email = strtolower(trim((string) ($l[$idx['autor_email']] ?? '')));
                $data  = data_csv((string) ($l[$idx['data']] ?? ''));
                $livro = trim((string) ($l[$idx['livro']] ?? ''));
                if (!isset($autores[$email])) {
                    $ign++;
                    $motivos[$email === '' ? '(e-mail vazio)' : $email] = 1;
                    continue;
                }
                if ($data === null || $livro === '') {
                    $ign++;
                    $motivos['linha ' . $linhaNum . ' (data ou livro inválido)'] = 1;
                    continue;
                }
                $ins->execute([
                    $impId,
                    $autores[$email],
                    mb_substr($livro, 0, 200, 'UTF-8'),
                    mb_substr(trim((string) ($l[$idx['canal']] ?? '')), 0, 80, 'UTF-8') ?: 'Outro',
                    $data,
                    (int) numero_csv((string) ($l[$idx['quantidade']] ?? '1')),
                    numero_csv((string) ($l[$idx['valor_bruto']] ?? '0')),
                    numero_csv((string) ($l[$idx['receita_autor']] ?? '0')),
                ]);
                $ok++;
            }
            if ($ok === 0) {
                $pdo->rollBack();
                $dica = $motivos ? ' Problemas: ' . implode('; ', array_slice(array_keys($motivos), 0, 5)) . '.' : '';
                falha('Nenhuma linha foi importada.' . $dica);
            }
            $pdo->prepare('UPDATE importacoes_vendas SET linhas = ?, ignoradas = ? WHERE id = ?')->execute([$ok, $ign, $impId]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        resp(['ok' => true, 'linhas' => $ok, 'ignoradas' => $ign, 'problemas' => array_slice(array_keys($motivos), 0, 5)]);
    }

    if ($acao === 'excluir_importacao') {
        $id = (int) ($_POST['id'] ?? 0);
        db()->prepare('DELETE FROM importacoes_vendas WHERE id = ?')->execute([$id]);
        resp(['ok' => true]);
    }

    falha('Ação desconhecida.', 404);
} catch (Throwable $e) {
    error_log('vendas_api: ' . $e->getMessage());
    falha('Erro interno. Tente de novo.', 500);
}

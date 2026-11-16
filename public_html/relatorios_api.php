<?php
// API de Análises e Mercado: gerar e excluir relatórios.

require_once __DIR__ . '/../app/http.php';
require_once __DIR__ . '/../app/relatorios.php';

$usuario = exigir_api();
$acao    = $_GET['acao'] ?? '';

try {
    if ($acao === 'gerar_analise') {
        $editora = eh_editora($usuario);
        $autorId = $editora ? 0 : (int) $usuario['id'];
        $quem    = $editora ? 'a equipe da editora (todos os autores juntos)' : 'o próprio autor, falando com ele';
        set_time_limit(150);
        try {
            $texto = rel_analise_vendas($autorId, $quem);
        } catch (Throwable $e) {
            falha(mb_substr($e->getMessage(), 0, 480, 'UTF-8'), 502);
        }
        $id = rel_salvar($usuario['id'], 'analise', 'Análise de vendas de ' . date('d/m/Y'), $texto);
        resp(['ok' => true, 'id' => $id]);
    }

    if ($acao === 'gerar_mercado') {
        $titulo  = trim((string) ($_POST['titulo'] ?? ''));
        $genero  = trim((string) ($_POST['genero'] ?? ''));
        $publico = trim((string) ($_POST['publico'] ?? ''));
        $sinopse = trim((string) ($_POST['sinopse'] ?? ''));
        if ($titulo === '' || $sinopse === '') {
            falha('Preencha o título e a sinopse do livro.');
        }
        $titulo  = mb_substr($titulo, 0, 200, 'UTF-8');
        $genero  = mb_substr($genero, 0, 100, 'UTF-8');
        $publico = mb_substr($publico, 0, 150, 'UTF-8');
        $sinopse = mb_substr($sinopse, 0, 4000, 'UTF-8');
        set_time_limit(150);
        try {
            $texto = rel_mercado($titulo, $genero, $publico, $sinopse);
        } catch (Throwable $e) {
            falha(mb_substr($e->getMessage(), 0, 480, 'UTF-8'), 502);
        }
        $id = rel_salvar($usuario['id'], 'mercado', 'Mercado: ' . $titulo, $texto);
        resp(['ok' => true, 'id' => $id]);
    }

    if ($acao === 'excluir') {
        $id = (int) ($_POST['id'] ?? 0);
        db()->prepare('DELETE FROM relatorios WHERE id = ? AND usuario_id = ?')->execute([$id, $usuario['id']]);
        resp(['ok' => true]);
    }

    falha('Ação desconhecida.', 404);
} catch (Throwable $e) {
    error_log('relatorios_api: ' . $e->getMessage());
    falha('Erro interno. Tente de novo.', 500);
}

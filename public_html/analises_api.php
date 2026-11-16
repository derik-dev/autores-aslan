<?php
// API da tela Análises: relatório estruturado e conversa livre sobre as vendas.

require_once __DIR__ . '/../app/http.php';
require_once __DIR__ . '/../app/relatorios.php';
require_once __DIR__ . '/../app/analise.php';

$usuario = exigir_api();
$editora = eh_editora($usuario);
$acao    = $_GET['acao'] ?? '';

// Autor analisado: a editora escolhe (0 = todos); o autor só vê as próprias vendas.
function autor_do_escopo(array $usuario, bool $editora, $pedido): int
{
    return $editora ? max(0, (int) $pedido) : (int) $usuario['id'];
}

function texto_do_escopo(bool $editora, int $autorId): string
{
    if (!$editora) {
        return 'as próprias vendas do autor (falando diretamente com ele)';
    }
    if ($autorId === 0) {
        return 'vendas de todos os autores da editora (visão da editora)';
    }
    $s = db()->prepare('SELECT nome FROM usuarios WHERE id = ?');
    $s->execute([$autorId]);
    return 'vendas do autor ' . (string) ($s->fetchColumn() ?: '#' . $autorId) . ' (visão da editora)';
}

function conversa_do_usuario(int $id, int $usuarioId): array
{
    $s = db()->prepare('SELECT * FROM analise_conversas WHERE id = ? AND usuario_id = ?');
    $s->execute([$id, $usuarioId]);
    $c = $s->fetch();
    if (!$c) {
        falha('Conversa não encontrada.', 404);
    }
    return $c;
}

function relatorio_do_usuario(int $id, int $usuarioId): ?array
{
    $s = db()->prepare("SELECT conteudo FROM relatorios WHERE id = ? AND usuario_id = ? AND tipo = 'analise2'");
    $s->execute([$id, $usuarioId]);
    $c = $s->fetchColumn();
    $r = $c ? json_decode((string) $c, true) : null;
    return is_array($r) ? $r : null;
}

try {
    // ---------- Relatório estruturado ----------
    if ($acao === 'gerar_relatorio') {
        $foco = (string) ($_POST['foco'] ?? 'geral');
        if (!isset(AN_FOCOS[$foco])) {
            $foco = 'geral';
        }
        $meses = (int) ($_POST['meses'] ?? 0);
        if (!isset(AN_PERIODOS[$meses])) {
            $meses = 0;
        }
        $autorId = autor_do_escopo($usuario, $editora, $_POST['autor'] ?? 0);
        $escopo  = texto_do_escopo($editora, $autorId);

        set_time_limit(170);
        try {
            $r = an_relatorio($autorId, $meses, $foco, $escopo);
        } catch (Throwable $e) {
            falha(mb_substr($e->getMessage(), 0, 480, 'UTF-8'), 502);
        }
        $obj = [
            'meta' => [
                'foco' => $foco, 'foco_rotulo' => AN_FOCOS[$foco], 'meses' => $meses, 'periodo_rotulo' => AN_PERIODOS[$meses],
                'escopo' => $escopo, 'autor_id' => $autorId, 'gerado_em' => date('d/m/Y H:i'),
            ],
            'fatos' => $r['fatos'],
            'ia' => $r['ia'],
        ];
        $id = rel_salvar($usuario['id'], 'analise2', AN_FOCOS[$foco] . ' · ' . AN_PERIODOS[$meses], json_encode($obj, JSON_UNESCAPED_UNICODE));
        resp(['ok' => true, 'id' => $id, 'relatorio' => $obj]);
    }

    if ($acao === 'abrir_relatorio') {
        $r = relatorio_do_usuario((int) ($_POST['id'] ?? 0), $usuario['id']);
        if ($r === null) {
            falha('Relatório não encontrado.', 404);
        }
        resp(['ok' => true, 'relatorio' => $r]);
    }

    if ($acao === 'excluir_relatorio') {
        db()->prepare("DELETE FROM relatorios WHERE id = ? AND usuario_id = ? AND tipo = 'analise2'")
            ->execute([(int) ($_POST['id'] ?? 0), $usuario['id']]);
        resp(['ok' => true]);
    }

    // ---------- Conversa ----------
    if ($acao === 'chat_novo') {
        $autorId     = autor_do_escopo($usuario, $editora, $_POST['autor'] ?? 0);
        $relatorioId = (int) ($_POST['relatorio_id'] ?? 0);
        if ($relatorioId > 0 && relatorio_do_usuario($relatorioId, $usuario['id']) === null) {
            falha('Relatório não encontrado.', 404);
        }
        db()->prepare('INSERT INTO analise_conversas (usuario_id, autor_filtro, relatorio_id) VALUES (?, ?, ?)')
            ->execute([$usuario['id'], $autorId, $relatorioId > 0 ? $relatorioId : null]);
        resp(['ok' => true, 'id' => (int) db()->lastInsertId()]);
    }

    if ($acao === 'chat_enviar') {
        $mensagem = trim((string) ($_POST['mensagem'] ?? ''));
        if ($mensagem === '') {
            falha('Escreva a sua pergunta.');
        }
        if (mb_strlen($mensagem, 'UTF-8') > 2000) {
            falha('A pergunta passou de 2.000 caracteres. Resuma um pouco.');
        }
        $pdo = db();
        $conversaId = (int) ($_POST['conversa_id'] ?? 0);
        if ($conversaId > 0) {
            $conversa = conversa_do_usuario($conversaId, $usuario['id']);
        } else {
            $autorId = autor_do_escopo($usuario, $editora, $_POST['autor'] ?? 0);
            $pdo->prepare('INSERT INTO analise_conversas (usuario_id, autor_filtro) VALUES (?, ?)')->execute([$usuario['id'], $autorId]);
            $conversaId = (int) $pdo->lastInsertId();
            $conversa   = conversa_do_usuario($conversaId, $usuario['id']);
        }

        $n = $pdo->prepare('SELECT COUNT(*) FROM analise_mensagens WHERE conversa_id = ?');
        $n->execute([$conversaId]);
        if ((int) $n->fetchColumn() >= 80) {
            falha('Esta conversa ficou longa demais. Comece uma nova conversa.');
        }

        // O autor nunca analisa vendas de outro: o filtro é sempre o dele.
        $autorId = $editora ? (int) $conversa['autor_filtro'] : (int) $usuario['id'];
        $fatos   = an_fatos($autorId, 0);
        if (!empty($fatos['vazio'])) {
            falha('Ainda não há vendas importadas para conversar sobre.');
        }

        $pdo->prepare("INSERT INTO analise_mensagens (conversa_id, papel, conteudo) VALUES (?, 'user', ?)")->execute([$conversaId, $mensagem]);
        $mensagemId = (int) $pdo->lastInsertId();

        $h = $pdo->prepare('SELECT papel, conteudo FROM analise_mensagens WHERE conversa_id = ? ORDER BY id DESC LIMIT 16');
        $h->execute([$conversaId]);
        $historico = [];
        foreach (array_reverse($h->fetchAll()) as $m) {
            $historico[] = ['role' => $m['papel'], 'content' => $m['conteudo']];
        }
        $relatorio = $conversa['relatorio_id'] ? relatorio_do_usuario((int) $conversa['relatorio_id'], $usuario['id']) : null;

        set_time_limit(130);
        try {
            $resposta = an_chat_responder($fatos, texto_do_escopo($editora, $autorId), an_tabela($autorId, 0), $relatorio, $historico);
        } catch (Throwable $e) {
            // Desfaz a pergunta para o usuário poder tentar de novo sem duplicar.
            $pdo->prepare('DELETE FROM analise_mensagens WHERE id = ?')->execute([$mensagemId]);
            falha(mb_substr($e->getMessage(), 0, 480, 'UTF-8'), 502);
        }

        $pdo->prepare("INSERT INTO analise_mensagens (conversa_id, papel, conteudo) VALUES (?, 'assistant', ?)")->execute([$conversaId, $resposta]);
        $titulo = $conversa['titulo'];
        if ($titulo === 'Nova conversa') {
            $titulo = mb_substr(preg_replace('/\s+/u', ' ', $mensagem), 0, 60, 'UTF-8');
            $pdo->prepare('UPDATE analise_conversas SET titulo = ? WHERE id = ?')->execute([$titulo, $conversaId]);
        } else {
            $pdo->prepare('UPDATE analise_conversas SET atualizado_em = NOW() WHERE id = ?')->execute([$conversaId]);
        }
        resp(['ok' => true, 'conversa_id' => $conversaId, 'titulo' => $titulo, 'resposta' => $resposta]);
    }

    if ($acao === 'chat_excluir') {
        $c = conversa_do_usuario((int) ($_POST['id'] ?? 0), $usuario['id']);
        db()->prepare('DELETE FROM analise_conversas WHERE id = ?')->execute([$c['id']]);
        resp(['ok' => true]);
    }

    falha('Ação desconhecida.', 404);
} catch (Throwable $e) {
    error_log('analises_api: ' . $e->getMessage());
    falha('Erro interno. Tente de novo.', 500);
}

<?php
// API da tela Análises: relatório estruturado e conversa (versão sem streaming, usada como reserva).

require_once __DIR__ . '/../app/analise_chat.php';

$usuario = exigir_api();
$editora = eh_editora($usuario);
$acao    = $_GET['acao'] ?? '';

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
                'escopo' => $escopo, 'autor_id' => $autorId, 'gerado_em' => date('d/m/Y H:i'), 'assinatura' => an_assinatura($autorId, $meses),
            ],
            'fatos' => $r['fatos'],
            'ia' => $r['ia'],
        ];
        $id = rel_salvar($usuario['id'], 'analise2', AN_FOCOS[$foco] . ' · ' . AN_PERIODOS[$meses], json_encode($obj, JSON_UNESCAPED_UNICODE));
        // "Atualizar relatório": o novo substitui o anterior, para a lista não encher de cópias.
        $substituir = (int) ($_POST['substituir'] ?? 0);
        if ($substituir > 0 && $substituir !== $id) {
            db()->prepare("DELETE FROM relatorios WHERE id = ? AND usuario_id = ? AND tipo = 'analise2'")->execute([$substituir, $usuario['id']]);
        }
        resp(['ok' => true, 'id' => $id, 'relatorio' => $obj]);
    }

    if ($acao === 'abrir_relatorio') {
        $r = relatorio_do_usuario((int) ($_POST['id'] ?? 0), $usuario['id']);
        if ($r === null) {
            falha('Relatório não encontrado.', 404);
        }
        // Compara os dados de hoje com os da época em que o relatório foi gerado.
        $desatualizado = false;
        if (!empty($r['meta']['assinatura'])) {
            $autorRel = $editora ? (int) ($r['meta']['autor_id'] ?? 0) : (int) $usuario['id'];
            $desatualizado = an_assinatura($autorRel, (int) ($r['meta']['meses'] ?? 0)) !== $r['meta']['assinatura'];
        }
        resp(['ok' => true, 'relatorio' => $r, 'desatualizado' => $desatualizado]);
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

    // Versão sem streaming (reserva): devolve a resposta inteira de uma vez.
    if ($acao === 'chat_enviar') {
        $ctx = an_preparar_chat($usuario, $editora);
        set_time_limit(130);
        try {
            $resposta = oa_chat($ctx['mensagens'], 2200, 110, 0.4);
        } catch (Throwable $e) {
            db()->prepare('DELETE FROM analise_mensagens WHERE id = ?')->execute([$ctx['mensagem_id']]);
            falha(mb_substr($e->getMessage(), 0, 480, 'UTF-8'), 502);
        }
        $titulo = an_concluir_chat($ctx, $resposta);
        resp(['ok' => true, 'conversa_id' => $ctx['conversa_id'], 'titulo' => $titulo, 'resposta' => $resposta]);
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

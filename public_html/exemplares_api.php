<?php
// API de Exemplares: campanhas e leitores (aprovar, recusar, resenha, fechar, excluir).

require_once __DIR__ . '/../app/entregas.php';

$usuario = exigir_api();
$acao    = $_GET['acao'] ?? '';

function arc_do_usuario(int $id, int $usuarioId): array
{
    $s = db()->prepare('SELECT * FROM arcs WHERE id = ? AND usuario_id = ?');
    $s->execute([$id, $usuarioId]);
    $a = $s->fetch();
    if (!$a) {
        falha('Campanha não encontrada.', 404);
    }
    return $a;
}

try {
    if ($acao === 'criar') {
        $titulo    = mb_substr(trim((string) ($_POST['titulo'] ?? '')), 0, 200, 'UTF-8');
        $descricao = mb_substr(trim((string) ($_POST['descricao'] ?? '')), 0, 3000, 'UTF-8');
        $entregaId = (int) ($_POST['entrega_id'] ?? 0);
        $vagas     = max(1, min(5000, (int) ($_POST['vagas'] ?? 50)));
        if ($titulo === '') {
            falha('Dê um título à campanha.');
        }
        if ($entregaId > 0) {
            $s = db()->prepare('SELECT id FROM entregas WHERE id = ? AND usuario_id = ?');
            $s->execute([$entregaId, $usuario['id']]);
            if (!$s->fetch()) {
                falha('Entrega não encontrada.');
            }
        }
        db()->prepare('INSERT INTO arcs (usuario_id, titulo, descricao, entrega_id, token, vagas) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$usuario['id'], $titulo, $descricao !== '' ? $descricao : null, $entregaId > 0 ? $entregaId : null, token_aleatorio(12), $vagas]);
        resp(['ok' => true]);
    }

    if ($acao === 'alternar') {
        $a = arc_do_usuario((int) ($_POST['id'] ?? 0), $usuario['id']);
        db()->prepare('UPDATE arcs SET aberto = ? WHERE id = ?')->execute([(int) $a['aberto'] ? 0 : 1, $a['id']]);
        resp(['ok' => true]);
    }

    if ($acao === 'excluir') {
        $a = arc_do_usuario((int) ($_POST['id'] ?? 0), $usuario['id']);
        db()->prepare('DELETE FROM arcs WHERE id = ?')->execute([$a['id']]);
        resp(['ok' => true]);
    }

    // Ações sobre um leitor: precisam de leitor + campanha do usuário.
    $leitorId = (int) ($_POST['leitor_id'] ?? 0);
    $s = db()->prepare('SELECT l.*, a.usuario_id, a.entrega_id, a.titulo AS arc_titulo FROM arc_leitores l JOIN arcs a ON a.id = l.arc_id WHERE l.id = ? AND a.usuario_id = ?');
    $s->execute([$leitorId, $usuario['id']]);
    $leitor = $s->fetch();
    if (!$leitor) {
        falha('Leitor não encontrado.', 404);
    }

    if ($acao === 'aprovar') {
        if (!$leitor['entrega_id']) {
            falha('Escolha um arquivo (entrega) para esta campanha antes de aprovar leitores.');
        }
        $token = $leitor['link_token'];
        if (!$token) {
            $token = ent_criar_link((int) $leitor['entrega_id'], $leitor['nome'] . ' <' . $leitor['email'] . '>', 30, 2);
        }
        db()->prepare("UPDATE arc_leitores SET status = 'aprovado', link_token = ? WHERE id = ?")->execute([$token, $leitor['id']]);
        resp(['ok' => true, 'url' => ent_link_publico($token)]);
    }

    if ($acao === 'recusar') {
        if ($leitor['link_token']) {
            db()->prepare('UPDATE entrega_links SET ativo = 0 WHERE token = ?')->execute([$leitor['link_token']]);
        }
        db()->prepare("UPDATE arc_leitores SET status = 'recusado' WHERE id = ?")->execute([$leitor['id']]);
        resp(['ok' => true]);
    }

    if ($acao === 'resenhou') {
        db()->prepare('UPDATE arc_leitores SET resenhou = ? WHERE id = ?')->execute([(int) $leitor['resenhou'] ? 0 : 1, $leitor['id']]);
        resp(['ok' => true]);
    }

    falha('Ação desconhecida.', 404);
} catch (Throwable $e) {
    error_log('exemplares_api: ' . $e->getMessage());
    falha('Erro interno. Tente de novo.', 500);
}

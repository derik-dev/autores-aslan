<?php
// API interna da função "Tradução". Responde sempre em JSON.

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/traducoes.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function resp(array $dados, int $codigo = 200): void
{
    http_response_code($codigo);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

function falha(string $mensagem, int $codigo = 400): void
{
    resp(['ok' => false, 'erro' => $mensagem], $codigo);
}

$usuario = usuario_atual();
if ($usuario === null) {
    falha('Sua sessão expirou. Entre de novo.', 401);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    falha('Método inválido.', 405);
}
if (!csrf_valido($_SERVER['HTTP_X_CSRF'] ?? ($_POST['csrf'] ?? null))) {
    falha('Sessão expirada. Recarregue a página.', 403);
}
session_write_close();

$acao = $_GET['acao'] ?? '';

function traducao_do_usuario(int $id, int $usuarioId): array
{
    $stmt = db()->prepare('SELECT * FROM traducoes WHERE id = ? AND usuario_id = ?');
    $stmt->execute([$id, $usuarioId]);
    $t = $stmt->fetch();
    if (!$t) {
        falha('Tradução não encontrada.', 404);
    }
    return $t;
}

// Contagem [traduzidos, aprovados, total] de uma tradução.
function trad_contagem(int $id): array
{
    $s = db()->prepare('SELECT COUNT(*) AS total, COALESCE(SUM(feito), 0) AS feitos, COALESCE(SUM(aprovado), 0) AS aprovados FROM traducao_trechos WHERE traducao_id = ?');
    $s->execute([$id]);
    $r = $s->fetch();
    return [(int) $r['feitos'], (int) $r['aprovados'], (int) $r['total']];
}

try {
    // ---------- Criar a tradução (divide o livro em trechos) ----------
    if ($acao === 'criar') {
        $titulo     = trim((string) ($_POST['titulo'] ?? ''));
        $texto      = (string) ($_POST['texto'] ?? '');
        $idioma     = (string) ($_POST['idioma'] ?? '');
        $instrucoes = trim((string) ($_POST['instrucoes'] ?? ''));

        if ($titulo === '') {
            falha('Dê um título à tradução.');
        }
        $titulo = mb_substr($titulo, 0, 200, 'UTF-8');
        if (!isset(TRAD_IDIOMAS[$idioma]) && $idioma !== TRAD_REVISAO) {
            falha('Escolha o idioma da tradução.');
        }
        $instrucoes = mb_substr($instrucoes, 0, 6000, 'UTF-8');
        if (mb_strlen($texto, 'UTF-8') > TRAD_MAX_CHARS * 1.3) {
            falha('O texto é grande demais. Divida em partes de até ' . number_format(TRAD_MAX_CHARS, 0, ',', '.') . ' caracteres.');
        }
        $trechos = audio_dividir($texto, TRAD_TRECHO);
        if (!$trechos) {
            falha('Cole ou envie o texto do livro.');
        }
        $total = 0;
        foreach ($trechos as $t) {
            $total += mb_strlen($t, 'UTF-8');
        }
        if ($total > TRAD_MAX_CHARS) {
            falha('O texto passa do limite de ' . number_format(TRAD_MAX_CHARS, 0, ',', '.') . ' caracteres. Divida em partes.');
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('INSERT INTO traducoes (usuario_id, titulo, idioma, instrucoes, total_chars) VALUES (?, ?, ?, ?, ?)')
                ->execute([$usuario['id'], $titulo, $idioma, $instrucoes !== '' ? $instrucoes : null, $total]);
            $tid = (int) $pdo->lastInsertId();
            $ins = $pdo->prepare('INSERT INTO traducao_trechos (traducao_id, ordem, original, chars) VALUES (?, ?, ?, ?)');
            foreach ($trechos as $i => $t) {
                $ins->execute([$tid, $i + 1, $t, mb_strlen($t, 'UTF-8')]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        resp(['ok' => true, 'id' => $tid, 'total' => count($trechos)]);
    }

    // ---------- Traduzir o próximo trecho pendente ----------
    if ($acao === 'processar') {
        $id  = (int) ($_POST['id'] ?? 0);
        $t   = traducao_do_usuario($id, $usuario['id']);
        $pdo = db();

        [$feitos, , $total] = trad_contagem($id);
        if ($total > 0 && $feitos >= $total) {
            $pdo->prepare("UPDATE traducoes SET status = 'concluido', erro = NULL WHERE id = ?")->execute([$id]);
            resp(['ok' => true, 'feitos' => $feitos, 'total' => $total, 'concluido' => true]);
        }

        // Trava: só um processamento por tradução ao mesmo tempo.
        $trava = $pdo->prepare(
            "UPDATE traducoes SET status = 'processando', erro = NULL, atualizado_em = NOW()
             WHERE id = ? AND usuario_id = ?
               AND (status <> 'processando' OR atualizado_em < (NOW() - INTERVAL 170 SECOND))"
        );
        $trava->execute([$id, $usuario['id']]);
        if ($trava->rowCount() === 0) {
            falha('Esta tradução ainda está sendo feita (talvez em outra aba). Se você fechou a aba, espere 3 minutos e continue.', 409);
        }

        ignore_user_abort(true);
        set_time_limit(175);

        $prox = $pdo->prepare('SELECT ordem, original FROM traducao_trechos WHERE traducao_id = ? AND feito = 0 ORDER BY ordem LIMIT 1');
        $prox->execute([$id]);
        $trecho = $prox->fetch();

        try {
            if (!$trecho) {
                throw new RuntimeException('Não há trechos para traduzir.');
            }
            $ant = $pdo->prepare('SELECT traduzido FROM traducao_trechos WHERE traducao_id = ? AND ordem = ? AND feito = 1');
            $ant->execute([$id, (int) $trecho['ordem'] - 1]);
            $contexto = trad_fim_do_texto($ant->fetchColumn() ?: '');

            $traduzido = trad_traduzir($trecho['original'], $t['idioma'], (string) $t['instrucoes'], $contexto);
            $pdo->prepare('UPDATE traducao_trechos SET traduzido = ?, feito = 1, aprovado = 0 WHERE traducao_id = ? AND ordem = ?')
                ->execute([$traduzido, $id, $trecho['ordem']]);
        } catch (Throwable $e) {
            $mensagem = mb_substr($e->getMessage(), 0, 480, 'UTF-8');
            $pdo->prepare("UPDATE traducoes SET status = 'erro', erro = ? WHERE id = ?")->execute([$mensagem, $id]);
            [$feitos, , $total] = trad_contagem($id);
            resp(['ok' => false, 'erro' => $mensagem, 'feitos' => $feitos, 'total' => $total], 502);
        }

        [$feitos, , $total] = trad_contagem($id);
        $concluido = $feitos >= $total;
        $pdo->prepare('UPDATE traducoes SET status = ?, erro = NULL WHERE id = ?')
            ->execute([$concluido ? 'concluido' : 'pendente', $id]);
        resp(['ok' => true, 'feitos' => $feitos, 'total' => $total, 'concluido' => $concluido]);
    }

    // ---------- Revisão: salvar a edição de um trecho ----------
    if ($acao === 'salvar') {
        $id    = (int) ($_POST['id'] ?? 0);
        $ordem = (int) ($_POST['ordem'] ?? 0);
        $texto = trim((string) ($_POST['texto'] ?? ''));
        traducao_do_usuario($id, $usuario['id']);
        if ($texto === '') {
            falha('A tradução do trecho não pode ficar vazia.');
        }
        if (mb_strlen($texto, 'UTF-8') > 30000) {
            falha('O trecho ficou grande demais.');
        }
        db()->prepare('UPDATE traducao_trechos SET traduzido = ?, feito = 1 WHERE traducao_id = ? AND ordem = ?')
            ->execute([$texto, $id, $ordem]);
        [, $aprovados, $total] = trad_contagem($id);
        resp(['ok' => true, 'aprovados' => $aprovados, 'total' => $total]);
    }

    // ---------- Revisão: salvar a edição do texto original de um trecho ----------
    if ($acao === 'salvar_original') {
        $id    = (int) ($_POST['id'] ?? 0);
        $ordem = (int) ($_POST['ordem'] ?? 0);
        $texto = trim((string) ($_POST['texto'] ?? ''));
        traducao_do_usuario($id, $usuario['id']);
        if ($texto === '') {
            falha('O texto original do trecho não pode ficar vazio.');
        }
        if (mb_strlen($texto, 'UTF-8') > 30000) {
            falha('O trecho ficou grande demais.');
        }
        // Mudou o original: a tradução deixa de estar aprovada até ser revista.
        db()->prepare('UPDATE traducao_trechos SET original = ?, chars = ?, aprovado = 0 WHERE traducao_id = ? AND ordem = ?')
            ->execute([$texto, mb_strlen($texto, 'UTF-8'), $id, $ordem]);
        [, $aprovados, $total] = trad_contagem($id);
        resp(['ok' => true, 'aprovados' => $aprovados, 'total' => $total]);
    }

    // ---------- Revisão: corrigir ortografia e gramática do original (não grava; o editor decide) ----------
    if ($acao === 'corrigir') {
        $id    = (int) ($_POST['id'] ?? 0);
        $texto = trim((string) ($_POST['texto'] ?? ''));
        traducao_do_usuario($id, $usuario['id']);
        if ($texto === '') {
            falha('Não há texto para corrigir.');
        }
        if (mb_strlen($texto, 'UTF-8') > 30000) {
            falha('O trecho ficou grande demais.');
        }
        set_time_limit(175);
        try {
            $corrigido = trad_corrigir($texto);
        } catch (Throwable $e) {
            falha(mb_substr($e->getMessage(), 0, 480, 'UTF-8'), 502);
        }
        resp(['ok' => true, 'texto' => $corrigido]);
    }

    // ---------- Revisão: conferência por IA (compara original e tradução, só lista problemas) ----------
    if ($acao === 'conferir') {
        $id    = (int) ($_POST['id'] ?? 0);
        $ordem = (int) ($_POST['ordem'] ?? 0);
        $t     = traducao_do_usuario($id, $usuario['id']);
        $q = db()->prepare('SELECT original, traduzido, feito FROM traducao_trechos WHERE traducao_id = ? AND ordem = ?');
        $q->execute([$id, $ordem]);
        $p = $q->fetch();
        if (!$p || (int) $p['feito'] !== 1) {
            falha('Este trecho ainda não foi traduzido.');
        }
        set_time_limit(175);
        try {
            $parecer = trad_conferir($p['original'], (string) $p['traduzido'], $t['idioma']);
        } catch (Throwable $e) {
            falha(mb_substr($e->getMessage(), 0, 480, 'UTF-8'), 502);
        }
        resp(['ok' => true, 'texto' => $parecer]);
    }

    // ---------- Revisão: aprovar ou desfazer a aprovação de um trecho ----------
    if ($acao === 'aprovar') {
        $id    = (int) ($_POST['id'] ?? 0);
        $ordem = (int) ($_POST['ordem'] ?? 0);
        $valor = !empty($_POST['valor']) ? 1 : 0;
        traducao_do_usuario($id, $usuario['id']);
        db()->prepare('UPDATE traducao_trechos SET aprovado = ? WHERE traducao_id = ? AND ordem = ? AND feito = 1')
            ->execute([$valor, $id, $ordem]);
        [, $aprovados, $total] = trad_contagem($id);
        resp(['ok' => true, 'aprovado' => $valor, 'aprovados' => $aprovados, 'total' => $total]);
    }

    // ---------- Revisão: aprovar todos os trechos traduzidos ----------
    if ($acao === 'aprovar_todos') {
        $id = (int) ($_POST['id'] ?? 0);
        traducao_do_usuario($id, $usuario['id']);
        db()->prepare('UPDATE traducao_trechos SET aprovado = 1 WHERE traducao_id = ? AND feito = 1')->execute([$id]);
        [, $aprovados, $total] = trad_contagem($id);
        resp(['ok' => true, 'aprovados' => $aprovados, 'total' => $total]);
    }

    // ---------- Revisão: traduzir um trecho de novo, com um pedido do revisor ----------
    if ($acao === 'retraduzir') {
        $id     = (int) ($_POST['id'] ?? 0);
        $ordem  = (int) ($_POST['ordem'] ?? 0);
        $ajuste = mb_substr(trim((string) ($_POST['ajuste'] ?? '')), 0, 400, 'UTF-8');
        $t      = traducao_do_usuario($id, $usuario['id']);

        $pdo = db();
        $q = $pdo->prepare('SELECT original FROM traducao_trechos WHERE traducao_id = ? AND ordem = ?');
        $q->execute([$id, $ordem]);
        $original = $q->fetchColumn();
        if ($original === false) {
            falha('Trecho não encontrado.', 404);
        }
        $ant = $pdo->prepare('SELECT traduzido FROM traducao_trechos WHERE traducao_id = ? AND ordem = ? AND feito = 1');
        $ant->execute([$id, $ordem - 1]);
        $contexto = trad_fim_do_texto($ant->fetchColumn() ?: '');

        set_time_limit(175);
        try {
            $novo = trad_traduzir($original, $t['idioma'], (string) $t['instrucoes'], $contexto, $ajuste);
        } catch (Throwable $e) {
            falha(mb_substr($e->getMessage(), 0, 480, 'UTF-8'), 502);
        }
        $pdo->prepare('UPDATE traducao_trechos SET traduzido = ?, feito = 1, aprovado = 0 WHERE traducao_id = ? AND ordem = ?')
            ->execute([$novo, $id, $ordem]);
        [, $aprovados, $total] = trad_contagem($id);
        resp(['ok' => true, 'texto' => $novo, 'aprovados' => $aprovados, 'total' => $total]);
    }

    // ---------- Excluir ----------
    if ($acao === 'excluir') {
        $id = (int) ($_POST['id'] ?? 0);
        traducao_do_usuario($id, $usuario['id']);
        db()->prepare('DELETE FROM traducoes WHERE id = ? AND usuario_id = ?')->execute([$id, $usuario['id']]);
        resp(['ok' => true]);
    }

    falha('Ação desconhecida.', 404);
} catch (Throwable $e) {
    error_log('traducao_api: ' . $e->getMessage());
    falha('Erro interno. Tente de novo.', 500);
}

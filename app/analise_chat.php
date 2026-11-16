<?php
// Lógica compartilhada da conversa de Análises (usada pela API normal e pela API em streaming).

require_once __DIR__ . '/http.php';
require_once __DIR__ . '/relatorios.php';
require_once __DIR__ . '/analise.php';

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

// Valida a pergunta, cria a conversa se preciso, grava a pergunta e monta tudo o que a IA precisa.
// Encerra com erro JSON (via falha) se algo estiver errado.
function an_preparar_chat(array $usuario, bool $editora): array
{
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
        $conversa = conversa_do_usuario($conversaId, (int) $usuario['id']);
    } else {
        $autorNovo = autor_do_escopo($usuario, $editora, $_POST['autor'] ?? 0);
        $pdo->prepare('INSERT INTO analise_conversas (usuario_id, autor_filtro) VALUES (?, ?)')->execute([$usuario['id'], $autorNovo]);
        $conversaId = (int) $pdo->lastInsertId();
        $conversa   = conversa_do_usuario($conversaId, (int) $usuario['id']);
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
    $relatorio = $conversa['relatorio_id'] ? relatorio_do_usuario((int) $conversa['relatorio_id'], (int) $usuario['id']) : null;

    $mensagens = array_merge(
        [['role' => 'system', 'content' => an_chat_sistema($fatos, texto_do_escopo($editora, $autorId), an_tabela($autorId, 0), $relatorio)]],
        $historico
    );

    return [
        'conversa_id' => $conversaId,
        'conversa'    => $conversa,
        'mensagem'    => $mensagem,
        'mensagem_id' => $mensagemId,
        'mensagens'   => $mensagens,
    ];
}

// Grava a resposta e dá o título à conversa (gerado pela IA; se falhar, usa o começo da pergunta).
function an_concluir_chat(array $ctx, string $resposta): string
{
    $pdo = db();
    $pdo->prepare("INSERT INTO analise_mensagens (conversa_id, papel, conteudo) VALUES (?, 'assistant', ?)")->execute([$ctx['conversa_id'], $resposta]);
    $titulo = (string) $ctx['conversa']['titulo'];
    if ($titulo === 'Nova conversa') {
        try {
            $titulo = an_titulo($ctx['mensagem'], $resposta);
        } catch (Throwable $e) {
            $titulo = '';
        }
        if ($titulo === '') {
            $titulo = mb_substr((string) preg_replace('/\s+/u', ' ', $ctx['mensagem']), 0, 60, 'UTF-8');
        }
        $pdo->prepare('UPDATE analise_conversas SET titulo = ? WHERE id = ?')->execute([$titulo, $ctx['conversa_id']]);
    } else {
        $pdo->prepare('UPDATE analise_conversas SET atualizado_em = NOW() WHERE id = ?')->execute([$ctx['conversa_id']]);
    }
    return $titulo;
}

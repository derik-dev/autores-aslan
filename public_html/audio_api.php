<?php
// API interna da tela "Texto em áudio". Responde sempre em JSON.

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/elevenlabs.php';
require_once __DIR__ . '/../app/audios.php';

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

$acao   = $_GET['acao'] ?? '';
$ehPost = $_SERVER['REQUEST_METHOD'] === 'POST';

if ($ehPost && !csrf_valido($_SERVER['HTTP_X_CSRF'] ?? ($_POST['csrf'] ?? null))) {
    falha('Sessão expirada. Recarregue a página.', 403);
}

// Libera a sessão: pedidos longos não podem travar o resto do sistema.
session_write_close();

// Devolve o áudio do usuário ou encerra com 404.
function audio_do_usuario(int $id, int $usuarioId): array
{
    $stmt = db()->prepare('SELECT * FROM audios WHERE id = ? AND usuario_id = ?');
    $stmt->execute([$id, $usuarioId]);
    $audio = $stmt->fetch();
    if (!$audio) {
        falha('Áudio não encontrado.', 404);
    }
    return $audio;
}

try {
    if ($acao === 'vozes') {
        try {
            $vozes = el_vozes();
        } catch (Throwable $e) {
            falha($e->getMessage(), 502);
        }
        resp(['ok' => true, 'vozes' => $vozes, 'creditos' => el_creditos()]);
    }

    if (!$ehPost) {
        falha('Método inválido.', 405);
    }

    // ---------- Ler texto de um arquivo enviado ----------
    if ($acao === 'extrair') {
        $arq = $_FILES['arquivo'] ?? null;
        if (!$arq || $arq['error'] !== UPLOAD_ERR_OK) {
            falha('Não consegui receber o arquivo. Tente de novo.');
        }
        if ($arq['size'] > 20 * 1024 * 1024) {
            falha('O arquivo passa de 20 MB.');
        }
        $ext = strtolower(pathinfo($arq['name'], PATHINFO_EXTENSION));
        if ($ext === 'docx') {
            try {
                $texto = audio_extrair_docx($arq['tmp_name']);
            } catch (Throwable $e) {
                falha($e->getMessage());
            }
        } elseif ($ext === 'txt') {
            $texto = (string) file_get_contents($arq['tmp_name']);
            if (!mb_check_encoding($texto, 'UTF-8')) {
                $texto = mb_convert_encoding($texto, 'UTF-8', 'Windows-1252');
            }
            $texto = preg_replace('/^\xEF\xBB\xBF/', '', $texto);
        } else {
            falha('Envie um arquivo .txt ou .docx.');
        }
        if (trim($texto) === '') {
            falha('Não encontrei texto nesse arquivo.');
        }
        resp(['ok' => true, 'texto' => $texto]);
    }

    // ---------- Criar o áudio (divide o texto em trechos) ----------
    if ($acao === 'criar') {
        $titulo = trim((string) ($_POST['titulo'] ?? ''));
        $texto  = (string) ($_POST['texto'] ?? '');
        $voz    = (string) ($_POST['voz'] ?? '');
        $modelo = (string) ($_POST['modelo'] ?? 'qualidade');

        if ($titulo === '') {
            falha('Dê um título ao áudio.');
        }
        $titulo = mb_substr($titulo, 0, 200, 'UTF-8');
        if (!el_voz_valida($voz)) {
            falha('Escolha uma voz.');
        }
        if (!isset(EL_MODELOS[$modelo])) {
            falha('Escolha a qualidade.');
        }
        if (mb_strlen($texto, 'UTF-8') > AUDIO_MAX_CHARS * 1.3) {
            falha('O texto é grande demais. Divida em partes de até ' . number_format(AUDIO_MAX_CHARS, 0, ',', '.') . ' caracteres.');
        }
        $trechos = audio_dividir($texto);
        if (!$trechos) {
            falha('Cole ou envie um texto para gerar o áudio.');
        }
        $total = 0;
        foreach ($trechos as $t) {
            $total += mb_strlen($t, 'UTF-8');
        }
        if ($total > AUDIO_MAX_CHARS) {
            falha('O texto passa do limite de ' . number_format(AUDIO_MAX_CHARS, 0, ',', '.') . ' caracteres. Divida em partes.');
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('INSERT INTO audios (usuario_id, titulo, voz_id, modelo, total_chars) VALUES (?, ?, ?, ?, ?)')
                ->execute([$usuario['id'], $titulo, $voz, $modelo, $total]);
            $audioId = (int) $pdo->lastInsertId();
            $ins = $pdo->prepare('INSERT INTO audio_trechos (audio_id, ordem, texto, chars) VALUES (?, ?, ?, ?)');
            foreach ($trechos as $i => $t) {
                $ins->execute([$audioId, $i + 1, $t, mb_strlen($t, 'UTF-8')]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        resp(['ok' => true, 'id' => $audioId, 'total' => count($trechos)]);
    }

    // ---------- Gerar o próximo trecho pendente ----------
    if ($acao === 'processar') {
        $id    = (int) ($_POST['id'] ?? 0);
        $audio = audio_do_usuario($id, $usuario['id']);
        $pdo   = db();

        $contar = function () use ($pdo, $id): array {
            $s = $pdo->prepare('SELECT COUNT(*) AS total, COALESCE(SUM(feito), 0) AS feitos FROM audio_trechos WHERE audio_id = ?');
            $s->execute([$id]);
            $r = $s->fetch();
            return [(int) $r['feitos'], (int) $r['total']];
        };

        [$feitos, $total] = $contar();
        if ($total > 0 && $feitos >= $total) {
            $pdo->prepare("UPDATE audios SET status = 'concluido', erro = NULL WHERE id = ?")->execute([$id]);
            resp(['ok' => true, 'feitos' => $feitos, 'total' => $total, 'concluido' => true]);
        }

        // Trava: só um processamento por áudio ao mesmo tempo.
        $trava = $pdo->prepare(
            "UPDATE audios SET status = 'processando', erro = NULL, atualizado_em = NOW()
             WHERE id = ? AND usuario_id = ?
               AND (status <> 'processando' OR atualizado_em < (NOW() - INTERVAL 150 SECOND))"
        );
        $trava->execute([$id, $usuario['id']]);
        if ($trava->rowCount() === 0) {
            falha('Este áudio ainda está sendo gerado (talvez em outra aba). Se você fechou a aba, espere 2 minutos e continue.', 409);
        }

        ignore_user_abort(true);
        set_time_limit(170);

        $prox = $pdo->prepare('SELECT ordem, texto FROM audio_trechos WHERE audio_id = ? AND feito = 0 ORDER BY ordem LIMIT 1');
        $prox->execute([$id]);
        $trecho = $prox->fetch();

        try {
            if (!$trecho) {
                throw new RuntimeException('Não há trechos para gerar.');
            }
            $mp3 = el_sintetizar($trecho['texto'], $audio['voz_id'], $audio['modelo']);
            audio_garantir_pasta($id);
            if (file_put_contents(audio_arquivo($id, (int) $trecho['ordem']), $mp3, LOCK_EX) === false) {
                throw new RuntimeException('Não consegui gravar o arquivo de áudio no servidor.');
            }
            $pdo->prepare('UPDATE audio_trechos SET feito = 1, bytes = ? WHERE audio_id = ? AND ordem = ?')
                ->execute([strlen($mp3), $id, $trecho['ordem']]);
        } catch (Throwable $e) {
            $mensagem = mb_substr($e->getMessage(), 0, 480, 'UTF-8');
            $pdo->prepare("UPDATE audios SET status = 'erro', erro = ? WHERE id = ?")->execute([$mensagem, $id]);
            [$feitos, $total] = $contar();
            resp(['ok' => false, 'erro' => $mensagem, 'feitos' => $feitos, 'total' => $total], 502);
        }

        [$feitos, $total] = $contar();
        $concluido = $feitos >= $total;
        $pdo->prepare('UPDATE audios SET status = ?, erro = NULL WHERE id = ?')
            ->execute([$concluido ? 'concluido' : 'pendente', $id]);
        resp(['ok' => true, 'feitos' => $feitos, 'total' => $total, 'concluido' => $concluido]);
    }

    // ---------- Excluir ----------
    if ($acao === 'excluir') {
        $id = (int) ($_POST['id'] ?? 0);
        audio_do_usuario($id, $usuario['id']);
        audio_apagar_pasta($id);
        db()->prepare('DELETE FROM audios WHERE id = ? AND usuario_id = ?')->execute([$id, $usuario['id']]);
        resp(['ok' => true]);
    }

    falha('Ação desconhecida.', 404);
} catch (Throwable $e) {
    error_log('audio_api: ' . $e->getMessage());
    falha('Erro interno. Tente de novo.', 500);
}

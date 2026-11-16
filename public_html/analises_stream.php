<?php
// Conversa de Análises em streaming: a resposta da IA chega aos poucos, em eventos "data: {json}".
// Eventos: inicio, texto, fim, titulo, erro. Erros de validação voltam como JSON comum (antes do streaming).

require_once __DIR__ . '/../app/analise_chat.php';

$usuario = exigir_api();
$editora = eh_editora($usuario);

try {
    $ctx = an_preparar_chat($usuario, $editora);
} catch (Throwable $e) {
    error_log('analises_stream (preparar): ' . $e->getMessage());
    falha('Erro interno. Tente de novo.', 500);
}

// A partir daqui a resposta é um fluxo de eventos, sem buffer nem compressão.
@ini_set('zlib.output_compression', '0');
@ini_set('output_buffering', '0');
@ini_set('implicit_flush', '1');
if (function_exists('apache_setenv')) {
    @apache_setenv('no-gzip', '1');
}
while (ob_get_level() > 0) {
    @ob_end_clean();
}
header_remove('Content-Type');
header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache, no-transform');
header('X-Accel-Buffering: no');
header('X-LiteSpeed-Cache-Control: no-cache');
ignore_user_abort(true);
set_time_limit(160);

function evento(array $dados): void
{
    echo 'data: ' . json_encode($dados, JSON_UNESCAPED_UNICODE) . "\n\n";
    @flush();
}

// Preenchimento inicial: força proxies e servidores a começarem a entregar o fluxo.
echo ': ' . str_repeat(' ', 2048) . "\n\n";
@flush();
evento(['tipo' => 'inicio', 'conversa_id' => $ctx['conversa_id']]);

try {
    $resposta = oa_chat_stream($ctx['mensagens'], function (string $trecho): void {
        evento(['tipo' => 'texto', 't' => $trecho]);
    }, 2200, 130, 0.4);
} catch (Throwable $e) {
    // Desfaz a pergunta para o usuário poder tentar de novo sem duplicar.
    db()->prepare('DELETE FROM analise_mensagens WHERE id = ?')->execute([$ctx['mensagem_id']]);
    evento(['tipo' => 'erro', 'erro' => mb_substr($e->getMessage(), 0, 480, 'UTF-8')]);
    exit;
}

evento(['tipo' => 'fim']);

// Depois de entregar a resposta, grava e cria o título (a IA escolhe um título curto para a conversa).
try {
    $titulo = an_concluir_chat($ctx, $resposta);
    evento(['tipo' => 'titulo', 'titulo' => $titulo, 'conversa_id' => $ctx['conversa_id']]);
} catch (Throwable $e) {
    error_log('analises_stream (concluir): ' . $e->getMessage());
}

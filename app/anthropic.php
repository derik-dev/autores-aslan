<?php
// Cliente simples da API da Anthropic (Claude), usado na tradução de livros.

const CL_URL    = 'https://api.anthropic.com/v1/messages';
const CL_MODELO = 'claude-sonnet-5-5';

function cl_chave(): string
{
    $config = require __DIR__ . '/config.php';
    $chave  = trim((string) ($config['anthropic_key'] ?? ''));
    if ($chave === '' || strpos($chave, 'SUA_') === 0) {
        throw new RuntimeException('A chave da Anthropic ainda não foi colocada no config.php.');
    }
    return $chave;
}

// Traduz o erro da API para uma frase que o autor entende.
function cl_mensagem_erro(int $codigo, string $corpo): string
{
    $json = json_decode($corpo, true);
    $msg  = is_array($json) ? (string) ($json['error']['message'] ?? '') : '';

    if (stripos($msg, 'credit balance') !== false) {
        return 'Os créditos da API da Anthropic acabaram. Adicione créditos e continue de onde parou.';
    }
    if ($codigo === 401 || $codigo === 403) {
        return 'A Anthropic recusou a chave. Confira a chave no config.php.';
    }
    if ($codigo === 404) {
        return 'A Anthropic não encontrou o modelo de tradução. Avise o suporte.';
    }
    if ($codigo === 429) {
        return 'A Anthropic recebeu pedidos demais ao mesmo tempo. Espere um pouco e continue.';
    }
    if ($codigo === 529 || $codigo >= 500) {
        return 'A Anthropic está instável agora. Continue em alguns instantes.';
    }
    if ($codigo === 400) {
        return 'A Anthropic recusou este trecho do texto.';
    }
    return 'Erro inesperado da Anthropic (código ' . $codigo . ').';
}

// Envia uma mensagem e devolve o texto da resposta.
function cl_mensagem(string $sistema, string $usuario, int $maxTokens = 8192, int $timeout = 150): string
{
    $corpo = json_encode([
        'model'      => CL_MODELO,
        'max_tokens' => $maxTokens,
        'system'     => $sistema,
        'messages'   => [['role' => 'user', 'content' => $usuario]],
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init(CL_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $corpo,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_HTTPHEADER     => [
            'x-api-key: ' . cl_chave(),
            'anthropic-version: 2023-06-01',
            'content-type: application/json',
        ],
    ]);
    $resposta = curl_exec($ch);
    if ($resposta === false) {
        $motivo = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('Não consegui falar com a Anthropic (' . $motivo . '). Tente continuar em instantes.');
    }
    $codigo = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($codigo !== 200) {
        throw new RuntimeException(cl_mensagem_erro($codigo, (string) $resposta));
    }
    $json  = json_decode((string) $resposta, true);
    $texto = '';
    foreach (($json['content'] ?? []) as $bloco) {
        if (($bloco['type'] ?? '') === 'text') {
            $texto .= (string) ($bloco['text'] ?? '');
        }
    }
    if (($json['stop_reason'] ?? '') === 'max_tokens') {
        throw new RuntimeException('A tradução de um trecho ficou grande demais e foi cortada. Tente de novo.');
    }
    if (trim($texto) === '') {
        throw new RuntimeException('A Anthropic devolveu uma resposta vazia. Tente de novo.');
    }
    return trim($texto);
}

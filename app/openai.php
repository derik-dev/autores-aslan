<?php
// Cliente simples da API da OpenAI (Chat Completions), usado na tradução de livros.

const OA_URL        = 'https://api.openai.com/v1/chat/completions';
const OA_MODELO_PAD = 'gpt-4.1';

function oa_config(): array
{
    return require __DIR__ . '/config.php';
}

function oa_chave(): string
{
    $chave = trim((string) (oa_config()['openai_key'] ?? ''));
    if ($chave === '' || strpos($chave, 'SUA_') === 0) {
        throw new RuntimeException('A chave da OpenAI ainda não foi colocada no config.php.');
    }
    return $chave;
}

// Modelo usado na tradução. Pode ser trocado no config.php com 'openai_model'.
function oa_modelo(): string
{
    $m = trim((string) (oa_config()['openai_model'] ?? ''));
    return $m !== '' ? $m : OA_MODELO_PAD;
}

// Traduz o erro da API para uma frase que o autor entende.
function oa_mensagem_erro(int $codigo, string $corpo): string
{
    $json = json_decode($corpo, true);
    $tipo = is_array($json) ? (string) ($json['error']['code'] ?? $json['error']['type'] ?? '') : '';

    if ($tipo === 'insufficient_quota') {
        return 'Os créditos da API da OpenAI acabaram. Adicione créditos e continue de onde parou.';
    }
    if ($codigo === 401) {
        return 'A OpenAI recusou a chave. Confira a chave no config.php.';
    }
    if ($codigo === 403) {
        return 'A OpenAI não deixou esta chave usar o modelo de tradução. Confira as permissões da chave.';
    }
    if ($codigo === 404) {
        return 'A OpenAI não encontrou o modelo de tradução. Confira "openai_model" no config.php.';
    }
    if ($codigo === 429) {
        return 'A OpenAI recebeu pedidos demais ao mesmo tempo. Espere um pouco e continue.';
    }
    if ($codigo >= 500) {
        return 'A OpenAI está instável agora. Continue em alguns instantes.';
    }
    if ($codigo === 400) {
        return 'A OpenAI recusou este trecho do texto.';
    }
    return 'Erro inesperado da OpenAI (código ' . $codigo . ').';
}

// Envia uma mensagem e devolve o texto da resposta.
function oa_mensagem(string $sistema, string $usuario, int $maxTokens = 8000, int $timeout = 150): string
{
    $corpo = json_encode([
        'model'                 => oa_modelo(),
        'max_completion_tokens' => $maxTokens,
        'messages'              => [
            ['role' => 'system', 'content' => $sistema],
            ['role' => 'user', 'content' => $usuario],
        ],
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init(OA_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $corpo,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . oa_chave(),
            'Content-Type: application/json',
        ],
    ]);
    $resposta = curl_exec($ch);
    if ($resposta === false) {
        $motivo = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('Não consegui falar com a OpenAI (' . $motivo . '). Tente continuar em instantes.');
    }
    $codigo = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($codigo !== 200) {
        throw new RuntimeException(oa_mensagem_erro($codigo, (string) $resposta));
    }
    $json   = json_decode((string) $resposta, true);
    $escolha = $json['choices'][0] ?? [];
    if (($escolha['finish_reason'] ?? '') === 'length') {
        throw new RuntimeException('A tradução de um trecho ficou grande demais e foi cortada. Tente de novo.');
    }
    $texto = trim((string) ($escolha['message']['content'] ?? ''));
    if ($texto === '') {
        throw new RuntimeException('A OpenAI devolveu uma resposta vazia. Tente de novo.');
    }
    return $texto;
}

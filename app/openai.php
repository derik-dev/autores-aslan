<?php
// Cliente simples da API da OpenAI (Chat Completions): tradução, revisão, análises e conversa.

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

// Modelo usado. Pode ser trocado no config.php com 'openai_model'.
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
        return 'A OpenAI não deixou esta chave usar o modelo. Confira as permissões da chave.';
    }
    if ($codigo === 404) {
        return 'A OpenAI não encontrou o modelo. Confira "openai_model" no config.php.';
    }
    if ($codigo === 429) {
        return 'A OpenAI recebeu pedidos demais ao mesmo tempo. Espere um pouco e tente de novo.';
    }
    if ($codigo >= 500) {
        return 'A OpenAI está instável agora. Tente em alguns instantes.';
    }
    if ($codigo === 400) {
        return 'A OpenAI recusou este pedido.';
    }
    return 'Erro inesperado da OpenAI (código ' . $codigo . ').';
}

// Conversa com várias mensagens: [['role' => 'system'|'user'|'assistant', 'content' => '...'], ...]
// $json = true pede a resposta em JSON (a instrução precisa mencionar JSON).
function oa_chat(array $mensagens, int $maxTokens = 8000, int $timeout = 150, ?float $temperatura = null, bool $json = false): string
{
    $dados = [
        'model'                 => oa_modelo(),
        'max_completion_tokens' => $maxTokens,
        'messages'              => $mensagens,
    ];
    // Temperatura baixa = respostas mais estáveis. Modelos de raciocínio (o1, o3, gpt-5…) não aceitam o ajuste.
    if ($temperatura !== null && !preg_match('/^(o\d|gpt-5)/i', $dados['model'])) {
        $dados['temperature'] = $temperatura;
    }
    if ($json) {
        $dados['response_format'] = ['type' => 'json_object'];
    }

    $ch = curl_init(OA_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($dados, JSON_UNESCAPED_UNICODE),
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
        throw new RuntimeException('Não consegui falar com a OpenAI (' . $motivo . '). Tente de novo em instantes.');
    }
    $codigo = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($codigo !== 200) {
        throw new RuntimeException(oa_mensagem_erro($codigo, (string) $resposta));
    }
    $decodificado = json_decode((string) $resposta, true);
    $escolha      = $decodificado['choices'][0] ?? [];
    if (($escolha['finish_reason'] ?? '') === 'length') {
        throw new RuntimeException('A resposta ficou grande demais e foi cortada. Tente de novo.');
    }
    $texto = trim((string) ($escolha['message']['content'] ?? ''));
    if ($texto === '') {
        throw new RuntimeException('A OpenAI devolveu uma resposta vazia. Tente de novo.');
    }
    return $texto;
}

// Atalho: uma instrução de sistema e uma mensagem do usuário.
function oa_mensagem(string $sistema, string $usuario, int $maxTokens = 8000, int $timeout = 150, ?float $temperatura = null): string
{
    return oa_chat(
        [['role' => 'system', 'content' => $sistema], ['role' => 'user', 'content' => $usuario]],
        $maxTokens,
        $timeout,
        $temperatura
    );
}

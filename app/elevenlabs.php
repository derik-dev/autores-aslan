<?php
// Cliente simples da API do ElevenLabs: lista vozes, mostra créditos e gera áudio a partir de texto.

const EL_BASE = 'https://api.elevenlabs.io/v1';

// Modelos oferecidos na tela. 'custo' = créditos gastos por caractere (confira no seu plano do ElevenLabs).
const EL_MODELOS = [
    'qualidade' => ['id' => 'eleven_multilingual_v2', 'rotulo' => 'Qualidade (1 crédito por caractere)',     'custo' => 1.0],
    'economico' => ['id' => 'eleven_flash_v2_5',      'rotulo' => 'Econômico (0,5 crédito por caractere)', 'custo' => 0.5],
];

function el_chave(): string
{
    $config = require __DIR__ . '/config.php';
    $chave  = trim((string) ($config['elevenlabs_key'] ?? ''));
    if ($chave === '' || strpos($chave, 'SUA_') === 0) {
        throw new RuntimeException('A chave do ElevenLabs ainda não foi colocada no config.php.');
    }
    return $chave;
}

function el_voz_valida(string $voz): bool
{
    return (bool) preg_match('/^[A-Za-z0-9]{10,40}$/', $voz);
}

// Faz uma chamada à API. Devolve [código HTTP, corpo da resposta].
function el_chamar(string $metodo, string $caminho, ?array $corpo = null, int $timeout = 100): array
{
    $cabecalhos = ['xi-api-key: ' . el_chave(), 'Accept: */*'];
    $opcoes = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_CUSTOMREQUEST  => $metodo,
    ];
    if ($corpo !== null) {
        $cabecalhos[] = 'Content-Type: application/json';
        $opcoes[CURLOPT_POSTFIELDS] = json_encode($corpo, JSON_UNESCAPED_UNICODE);
    }
    $opcoes[CURLOPT_HTTPHEADER] = $cabecalhos;

    $ch = curl_init(EL_BASE . $caminho);
    curl_setopt_array($ch, $opcoes);
    $resposta = curl_exec($ch);
    if ($resposta === false) {
        $motivo = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('Não consegui falar com o ElevenLabs (' . $motivo . '). Tente continuar em instantes.');
    }
    $codigo = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return [$codigo, (string) $resposta];
}

// Traduz o erro da API para uma frase que o autor entende.
function el_mensagem_erro(int $codigo, string $corpo): string
{
    $json   = json_decode($corpo, true);
    $detail = is_array($json) ? ($json['detail'] ?? null) : null;
    $status = is_array($detail) ? (string) ($detail['status'] ?? '') : '';

    if ($status === 'quota_exceeded') {
        return 'Os créditos do ElevenLabs acabaram. Adicione créditos e continue de onde parou.';
    }
    if ($codigo === 401 || $codigo === 403) {
        return 'O ElevenLabs recusou a chave. Confira a chave no config.php e as permissões dela.';
    }
    if ($codigo === 404) {
        return 'O ElevenLabs não encontrou essa voz. Escolha outra voz.';
    }
    if ($codigo === 422 || $codigo === 400) {
        return 'O ElevenLabs recusou este trecho do texto ou a voz escolhida.';
    }
    if ($codigo === 429) {
        return 'O ElevenLabs recebeu pedidos demais ao mesmo tempo. Espere um pouco e continue.';
    }
    if ($codigo >= 500) {
        return 'O ElevenLabs está instável agora. Continue em alguns instantes.';
    }
    return 'Erro inesperado do ElevenLabs (código ' . $codigo . ').';
}

// Lista as vozes da conta: [['id','nome','categoria','amostra'], ...]
function el_vozes(): array
{
    [$codigo, $corpo] = el_chamar('GET', '/voices', null, 30);
    if ($codigo !== 200) {
        throw new RuntimeException(el_mensagem_erro($codigo, $corpo));
    }
    $json  = json_decode($corpo, true);
    $vozes = [];
    foreach (($json['voices'] ?? []) as $v) {
        if (empty($v['voice_id']) || empty($v['name'])) {
            continue;
        }
        $vozes[] = [
            'id'        => $v['voice_id'],
            'nome'      => $v['name'],
            'categoria' => $v['category'] ?? '',
            'amostra'   => $v['preview_url'] ?? '',
        ];
    }
    usort($vozes, function ($a, $b) {
        return strcasecmp($a['nome'], $b['nome']);
    });
    return $vozes;
}

// Créditos usados e limite do plano; null se a chave não puder consultar.
function el_creditos(): ?array
{
    try {
        [$codigo, $corpo] = el_chamar('GET', '/user/subscription', null, 15);
    } catch (Throwable $e) {
        return null;
    }
    if ($codigo !== 200) {
        return null;
    }
    $j = json_decode($corpo, true);
    if (!is_array($j) || !isset($j['character_count'], $j['character_limit'])) {
        return null;
    }
    return ['usados' => (int) $j['character_count'], 'limite' => (int) $j['character_limit']];
}

// Gera o áudio (bytes de um MP3) de um trecho de texto.
function el_sintetizar(string $texto, string $voz, string $modeloChave): string
{
    if (!el_voz_valida($voz)) {
        throw new RuntimeException('Voz inválida.');
    }
    $modelo = EL_MODELOS[$modeloChave]['id'] ?? EL_MODELOS['qualidade']['id'];
    [$codigo, $corpo] = el_chamar(
        'POST',
        '/text-to-speech/' . $voz . '?output_format=mp3_44100_128',
        ['text' => $texto, 'model_id' => $modelo],
        120
    );
    if ($codigo !== 200 || $corpo === '') {
        throw new RuntimeException(el_mensagem_erro($codigo, $corpo));
    }
    return $corpo;
}

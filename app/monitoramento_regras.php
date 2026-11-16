<?php
// Regras determinísticas: funcionam sem IA, banco ou rede.

const MON_PADRAO = [
    'ativo' => 1,
    'queda_pct' => 25,
    'crescimento_pct' => 40,
    'concentracao_pct' => 70,
    'dias_sem_dados' => 45,
];

function mon_hoje(): DateTimeImmutable
{
    return new DateTimeImmutable('today', new DateTimeZone('America/Sao_Paulo'));
}

function mon_validar_regras(array $entrada): array
{
    $regras = ['ativo' => !empty($entrada['ativo']) ? 1 : 0];
    foreach (['queda_pct' => [1, 100], 'crescimento_pct' => [1, 500], 'concentracao_pct' => [50, 100], 'dias_sem_dados' => [7, 365]] as $campo => [$min, $max]) {
        $valor = filter_var($entrada[$campo] ?? null, FILTER_VALIDATE_INT);
        if ($valor === false || $valor === null || $valor < $min || $valor > $max) {
            throw new InvalidArgumentException('Confira os limites das regras. Use números inteiros nos intervalos indicados.');
        }
        $regras[$campo] = $valor;
    }
    return $regras;
}

function mon_dinheiro(float $valor): string
{
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

function mon_mes(string $mes): string
{
    return substr($mes, 5, 2) . '/' . substr($mes, 0, 4);
}

// Um alerta por regra/período/evidência. Reexecuções preservam a marcação de leitura.
function mon_alerta(string $tipo, string $nivel, string $titulo, string $texto, array $evidencia, string $periodo): array
{
    $json = json_encode($evidencia, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    return [
        'tipo' => $tipo, 'nivel' => $nivel, 'titulo' => $titulo, 'texto' => $texto,
        'evidencia' => $json, 'periodo' => $periodo,
        'chave' => hash('sha256', $tipo . '|' . $periodo . '|' . $json),
    ];
}

function mon_avaliar(array $dados, array $regras, DateTimeImmutable $hoje): array
{
    if (empty($dados['registros'])) {
        return []; // Sem vendas não significa queda nem atraso de importação.
    }
    $alertas = [];
    $ultima = new DateTimeImmutable($dados['ultima_venda'], $hoje->getTimezone());
    $dias = (int) $ultima->diff($hoje)->format('%r%a');
    if ($dias >= $regras['dias_sem_dados']) {
        $alertas[] = mon_alerta('dados_antigos', 'atencao', 'Os dados de vendas precisam de atualização',
            'O último registro de venda é de ' . $ultima->format('d/m/Y') . '. Já se passaram ' . $dias . ' dias. Confira se há relatórios para importar; isso não prova ausência de vendas.',
            ['ultima_venda' => $dados['ultima_venda']], $ultima->format('Y-m'));
    }

    // Só meses encerrados com registros. Não transformar uma lacuna de importação em receita zero.
    $mensal = array_values(array_filter($dados['mensal'], function ($m) use ($hoje) {
        return $m['mes'] < $hoje->format('Y-m');
    }));
    usort($mensal, function ($a, $b) { return strcmp($a['mes'], $b['mes']); });
    $n = count($mensal);
    if ($n >= 2) {
        $atual = $mensal[$n - 1];
        $anterior = $mensal[$n - 2];
        $previsto = (new DateTimeImmutable($anterior['mes'] . '-01'))->modify('+1 month')->format('Y-m');
        if ($previsto === $atual['mes'] && (float) $anterior['receita'] > 0) {
            $a = (float) $anterior['receita'];
            $b = (float) $atual['receita'];
            $pct = ($b - $a) / $a * 100;
            $tipo = $pct <= -$regras['queda_pct'] ? 'queda' : ($pct >= $regras['crescimento_pct'] ? 'crescimento' : null);
            if ($tipo !== null) {
                $queda = $tipo === 'queda';
                $alertas[] = mon_alerta($tipo, $queda ? 'atencao' : 'oportunidade',
                    'A receita ' . ($queda ? 'caiu ' : 'cresceu ') . number_format(abs($pct), 1, ',', '.') . '%',
                    mon_mes($anterior['mes']) . ': ' . mon_dinheiro($a) . ' → ' . mon_mes($atual['mes']) . ': ' . mon_dinheiro($b)
                    . '. Compare os relatórios importados e confirme se os dois meses estão completos antes de decidir.',
                    ['mes_anterior' => $anterior['mes'], 'receita_anterior' => round($a, 2), 'mes_atual' => $atual['mes'], 'receita_atual' => round($b, 2)], $atual['mes']);
            }
        }
    }

    foreach (['livros' => 'livro', 'canais' => 'canal'] as $colecao => $tipo) {
        $itens = $dados[$colecao] ?? [];
        $total = array_sum(array_column($itens, 'receita'));
        if ($total <= 0 || !$itens) {
            continue;
        }
        usort($itens, function ($a, $b) {
            return ((float) $b['receita'] <=> (float) $a['receita']) ?: strcmp($a['nome'], $b['nome']);
        });
        $lider = $itens[0];
        $pct = (float) $lider['receita'] / $total * 100;
        if ($pct >= $regras['concentracao_pct']) {
            $periodo = $dados['periodo_concentracao'];
            $alertas[] = mon_alerta('concentracao_' . $tipo, 'atencao',
                number_format($pct, 1, ',', '.') . '% da receita positiva em um ' . $tipo,
                $lider['nome'] . ' reúne ' . mon_dinheiro((float) $lider['receita']) . ' de ' . mon_dinheiro($total)
                . ' entre ' . mon_mes($periodo['inicio']) . ' e ' . mon_mes($periodo['fim'])
                . '. Avalie a dependência deste ' . $tipo . '. A base considera receitas positivas, sem descontar estornos.',
                ['nome' => $lider['nome'], 'receita' => round((float) $lider['receita'], 2), 'total' => round($total, 2), 'inicio' => $periodo['inicio'], 'fim' => $periodo['fim']], $periodo['fim']);
        }
    }
    return $alertas;
}

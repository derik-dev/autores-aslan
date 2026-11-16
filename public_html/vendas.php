<?php
// Painel de vendas: cada autor vê as próprias vendas e receitas; a editora importa os relatórios (CSV) e vê todos.

require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/http.php';

$usuario = exigir_login();
$editora = eh_editora($usuario);

$tabelasOk = true;
$autores = $importacoes = $mensal = $porLivro = $porCanal = [];
$tot = ['q' => 0, 'b' => 0, 'r' => 0];
$autorFiltro = $editora ? (int) ($_GET['autor'] ?? 0) : (int) $usuario['id'];

try {
    $pdo  = db();
    $cond = $autorFiltro > 0 ? 'autor_id = ?' : '1 = 1';
    $par  = $autorFiltro > 0 ? [$autorFiltro] : [];

    $s = $pdo->prepare("SELECT COALESCE(SUM(quantidade),0) q, COALESCE(SUM(valor_bruto),0) b, COALESCE(SUM(receita_autor),0) r FROM vendas WHERE $cond");
    $s->execute($par);
    $tot = $s->fetch();

    $s = $pdo->prepare("SELECT DATE_FORMAT(data_venda, '%Y-%m') m, SUM(quantidade) q, SUM(receita_autor) r FROM vendas WHERE $cond GROUP BY m ORDER BY m DESC LIMIT 12");
    $s->execute($par);
    $mensal = array_reverse($s->fetchAll());

    $s = $pdo->prepare("SELECT livro, SUM(quantidade) q, SUM(valor_bruto) b, SUM(receita_autor) r FROM vendas WHERE $cond GROUP BY livro ORDER BY r DESC LIMIT 20");
    $s->execute($par);
    $porLivro = $s->fetchAll();

    $s = $pdo->prepare("SELECT canal, SUM(quantidade) q, SUM(receita_autor) r FROM vendas WHERE $cond GROUP BY canal ORDER BY r DESC");
    $s->execute($par);
    $porCanal = $s->fetchAll();

    if ($editora) {
        $autores     = $pdo->query('SELECT id, nome FROM usuarios ORDER BY nome')->fetchAll();
        $importacoes = $pdo->query('SELECT id, arquivo, linhas, ignoradas, criado_em FROM importacoes_vendas ORDER BY id DESC LIMIT 10')->fetchAll();
    }
} catch (Throwable $e) {
    error_log('vendas.php: ' . $e->getMessage());
    $tabelasOk = false;
}

$maxReceita = 0.0;
foreach ($mensal as $m) {
    $maxReceita = max($maxReceita, (float) $m['r']);
}


// Destaques: o que mais importa depois dos totais.
$melhorMes = null;
foreach ($mensal as $m) {
    if ($melhorMes === null || (float) $m['r'] > (float) $melhorMes['r']) {
        $melhorMes = $m;
    }
}
$livroLider   = $porLivro[0] ?? null;
$ticketMedio  = (int) $tot['q'] > 0 ? (float) $tot['b'] / (int) $tot['q'] : 0.0;
$participacao = (float) $tot['b'] > 0 ? (float) $tot['r'] / (float) $tot['b'] * 100 : 0.0;

layout_inicio('Vendas', 'vendas', $usuario);
?>
        <p class="texto-2"><?= $editora ? 'Importe os relatórios de vendas e acompanhe a receita de cada autor.' : 'Acompanhe quanto os seus livros venderam e quanto você recebe.' ?></p>

<?php if (!$tabelasOk): ?>
        <p class="alerta" role="alert">As tabelas de vendas ainda não existem no banco. Importe o arquivo <strong>database/schema.sql</strong> no phpMyAdmin e recarregue esta página.</p>
<?php else: ?>
        <div id="vendas-app" data-csrf="<?= e(csrf_token()) ?>">

<?php $semVendas = ((int) $tot['q'] === 0 && (float) $tot['r'] === 0.0); ?>
<?php if ($editora): ?>
            <form method="get" class="filtro-autor">
                <label for="autor">Ver vendas de</label>
                <select id="autor" name="autor" onchange="this.form.submit()">
                    <option value="0">Todos os autores</option>
<?php foreach ($autores as $a): ?>
                    <option value="<?= (int) $a['id'] ?>"<?= $autorFiltro === (int) $a['id'] ? ' selected' : '' ?>><?= e($a['nome']) ?></option>
<?php endforeach; ?>
                </select>
            </form>
<?php endif; ?>

<?php if ((int) $tot['q'] === 0 && (float) $tot['r'] === 0.0): ?>
            <div class="vazio">
                <strong>Nenhuma venda ainda</strong>
                <?= $editora ? 'Importe um relatório de vendas abaixo para ver os números.' : 'Quando a editora importar o relatório de vendas, os seus números aparecem aqui.' ?>
            </div>
<?php else: ?>
            <div class="numeros">
                <div><strong><?= e(brl($tot['r'])) ?></strong><span>receita do autor</span></div>
                <div><strong><?= e(brl($tot['b'])) ?></strong><span>vendas brutas</span></div>
                <div><strong><?= num_br($tot['q']) ?></strong><span>exemplares vendidos</span></div>
            </div>

            <section class="bloco col-7">
                <h2 class="bloco-titulo">Receita por mês</h2>
                <ul class="lista-barras">
<?php foreach ($mensal as $m):
    $pct = $maxReceita > 0 ? max(2, (int) round((float) $m['r'] / $maxReceita * 100)) : 0;
    [$ano, $mes] = explode('-', $m['m']);
?>
                    <li>
                        <span class="barra-rotulo"><?= e($mes . '/' . $ano) ?></span>
                        <span class="barra-h"><i style="width: <?= $pct ?>%"></i></span>
                        <span class="barra-valor"><?= e(brl($m['r'])) ?> · <?= num_br($m['q']) ?> un.</span>
                    </li>
<?php endforeach; ?>
                </ul>
            </section>

            <section class="bloco col-5">
                <h2 class="bloco-titulo">Destaques</h2>
                <div class="mini-numeros">
<?php if ($melhorMes): [$a, $mm] = explode('-', $melhorMes['m']); ?>
                    <div><strong><?= e($mm . '/' . $a) ?></strong><span>melhor mês · <?= e(brl($melhorMes['r'])) ?></span></div>
<?php endif; ?>
<?php if ($livroLider): ?>
                    <div><strong class="quebra"><?= e($livroLider['livro']) ?></strong><span>livro que mais rendeu · <?= e(brl($livroLider['r'])) ?></span></div>
<?php endif; ?>
                    <div><strong><?= e(brl($ticketMedio)) ?></strong><span>vendas brutas por exemplar</span></div>
                    <div><strong><?= e(number_format($participacao, 0, ',', '.')) ?>%</strong><span>das vendas brutas ficam com o autor</span></div>
                </div>
            </section>

            <section class="bloco col-7">
                <h2 class="bloco-titulo">Por livro</h2>
                <div class="tabela-rolagem">
                    <table class="tabela tabela-num">
                        <thead><tr><th>Livro</th><th>Exemplares</th><th>Vendas brutas</th><th>Receita do autor</th></tr></thead>
                        <tbody>
<?php foreach ($porLivro as $l): ?>
                            <tr><td><?= e($l['livro']) ?></td><td><?= num_br($l['q']) ?></td><td><?= e(brl($l['b'])) ?></td><td><?= e(brl($l['r'])) ?></td></tr>
<?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="bloco col-5">
                <h2 class="bloco-titulo">Por canal de venda</h2>
                <div class="tabela-rolagem">
                    <table class="tabela tabela-num">
                        <thead><tr><th>Canal</th><th>Exemplares</th><th>Receita do autor</th></tr></thead>
                        <tbody>
<?php foreach ($porCanal as $c): ?>
                            <tr><td><?= e($c['canal']) ?></td><td><?= num_br($c['q']) ?></td><td><?= e(brl($c['r'])) ?></td></tr>
<?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            

            
<?php endif; ?>

<?php if ($editora): ?>
            <details class="importar todas"<?= $semVendas ? ' open' : '' ?>>
                <summary>Importar relatório de vendas</summary>
                <p class="texto-2">Envie um arquivo CSV com as colunas <strong>autor_email, livro, canal, data, quantidade, valor_bruto, receita_autor</strong>. O e-mail precisa ser o mesmo do cadastro do autor.</p>
                <div class="linha-acoes">
                    <label for="csv" class="btn btn-sec btn-sm" id="rotulo-csv">Enviar arquivo CSV</label>
                    <input type="file" id="csv" accept=".csv,text/csv" hidden>
                    <a class="btn btn-sec btn-sm" href="vendas_modelo.php">Baixar modelo de CSV</a>
                </div>
                <p id="imp-resultado" class="texto-2" aria-live="polite"></p>
                <p id="imp-erro" class="alerta" role="alert" hidden></p>
<?php if ($importacoes): ?>
                <ul class="lista-audios">
<?php foreach ($importacoes as $i): ?>
                    <li class="linha-audio" data-id="<?= (int) $i['id'] ?>">
                        <div class="linha-topo">
                            <div class="linha-info">
                                <strong><?= e($i['arquivo']) ?></strong>
                                <span class="meta"><?= num_br($i['linhas']) ?> linhas importadas · <?= num_br($i['ignoradas']) ?> ignoradas · <?= e(date('d/m/Y H:i', strtotime($i['criado_em']))) ?></span>
                            </div>
                            <button type="button" class="btn btn-sec btn-sm" data-acao="excluir-imp">Desfazer importação</button>
                        </div>
                    </li>
<?php endforeach; ?>
                </ul>
<?php endif; ?>
            </details>
<?php endif; ?>
        </div>
<?php if ($editora): ?>
        <script src="<?= asset_url('assets/js/vendas.js') ?>"></script>
<?php endif; ?>
<?php endif; ?>
<?php layout_fim(); ?>

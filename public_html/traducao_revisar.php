<?php
// Revisão da tradução, no estilo de um pull request: original à esquerda, tradução editável à direita.

require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/traducoes.php';

$usuario = exigir_login();

$id = (int) ($_GET['id'] ?? 0);
$trad   = null;
$trechos = [];
try {
    $stmt = db()->prepare('SELECT * FROM traducoes WHERE id = ? AND usuario_id = ?');
    $stmt->execute([$id, $usuario['id']]);
    $trad = $stmt->fetch();
    if ($trad) {
        $stmt = db()->prepare('SELECT ordem, original, traduzido, chars, feito, aprovado FROM traducao_trechos WHERE traducao_id = ? ORDER BY ordem');
        $stmt->execute([$id]);
        $trechos = $stmt->fetchAll();
    }
} catch (Throwable $e) {
    error_log('traducao_revisar.php: ' . $e->getMessage());
}

if (!$trad) {
    http_response_code(404);
    layout_inicio('Revisão da tradução', 'traducao', $usuario);
    ?>
        <div class="vazio">
            <strong>Tradução não encontrada</strong>
            Ela pode ter sido excluída. <a href="traducao.php">Voltar para Tradução</a>
        </div>
<?php
    layout_fim();
    exit;
}

$total     = count($trechos);
$feitos    = 0;
$aprovados = 0;
foreach ($trechos as $p) {
    $feitos    += (int) $p['feito'];
    $aprovados += (int) $p['aprovado'];
}
$idiomaNome = TRAD_IDIOMAS[$trad['idioma']] ?? $trad['idioma'];
$pct        = $total ? (int) round($aprovados / $total * 100) : 0;

function num(int $n): string
{
    return number_format($n, 0, ',', '.');
}

layout_inicio('Revisão da tradução', 'traducao', $usuario);
?>
        <p class="texto-2"><a href="traducao.php">Tradução</a> / <strong><?= e($trad['titulo']) ?></strong> para <?= e($idiomaNome) ?>. À esquerda fica o seu texto, que você pode corrigir. À direita fica a tradução, que você ajusta e aprova trecho a trecho.</p>

        <div id="revisao" data-csrf="<?= e(csrf_token()) ?>" data-id="<?= (int) $trad['id'] ?>">

            <div class="trad-barra">
                <div class="trad-barra-topo">
                    <strong id="rev-contagem"><?= $aprovados ?> de <?= $total ?> trechos aprovados</strong>
                    <div class="linha-acoes">
                        <button type="button" class="btn btn-sec btn-sm" id="rev-todos">Aprovar todos os trechos</button>
                        <a class="btn btn-sec btn-sm" href="traducao_baixar.php?id=<?= (int) $trad['id'] ?>">Baixar .txt</a>
                        <a class="btn btn-sec btn-sm" href="audio.php?traducao=<?= (int) $trad['id'] ?>">Gerar áudio desta tradução</a>
                    </div>
                </div>
                <div class="barra-audio" aria-hidden="true"><i id="rev-barra" style="width: <?= $pct ?>%"></i></div>
                <div class="trad-filtros" role="group" aria-label="Filtrar trechos">
                    <button type="button" class="btn btn-sec btn-sm filtro ativo" data-filtro="todos">Todos</button>
                    <button type="button" class="btn btn-sec btn-sm filtro" data-filtro="pendentes">Pendentes</button>
                    <button type="button" class="btn btn-sec btn-sm filtro" data-filtro="aprovados">Aprovados</button>
                    <span id="rev-aviso" class="texto-2" aria-live="polite"></span>
                </div>
            </div>

<?php if ($feitos < $total): ?>
            <p class="alerta" role="alert">Faltam <?= $total - $feitos ?> trechos para traduzir. <a href="traducao.php">Volte para Tradução</a> e clique em Continuar traduzindo.</p>
<?php endif; ?>

            <p id="rev-erro" class="alerta" role="alert" hidden></p>

<?php foreach ($trechos as $p):
    $ord  = (int) $p['ordem'];
    $feito = (int) $p['feito'] === 1;
    $apr  = (int) $p['aprovado'] === 1;
?>
            <section class="trad-bloco<?= $apr ? ' aprovado' : '' ?>" data-ordem="<?= $ord ?>" data-feito="<?= $feito ? 1 : 0 ?>">
                <div class="trad-cabeca">
                    <span class="meta">Trecho <?= $ord ?> de <?= $total ?> · <?= num((int) $p['chars']) ?> caracteres</span>
                    <span class="chip <?= $apr ? 'chip-ok' : 'chip-neutro' ?> trad-chip"><?= $apr ? 'Aprovado' : ($feito ? 'Pendente' : 'Não traduzido') ?></span>
                </div>
                <div class="trad-colunas">
                    <div class="trad-lado">
                        <label class="trad-rotulo" for="o<?= $ord ?>">Seu texto (editável)</label>
                        <textarea id="o<?= $ord ?>" class="trad-area trad-orig" rows="6"><?= e($p['original']) ?></textarea>
                        <div class="trad-ferr">
                            <button type="button" class="btn btn-sec btn-sm" data-acao="corrigir">Corrigir ortografia e gramática</button>
                            <button type="button" class="btn btn-sec btn-sm" data-acao="desfazer" hidden>Desfazer correção</button>
                            <span class="texto-2 trad-estado-o" aria-live="polite"></span>
                        </div>
                    </div>
                    <div class="trad-lado">
                        <label class="trad-rotulo" for="t<?= $ord ?>">Tradução em <?= e($idiomaNome) ?></label>
<?php if ($feito): ?>
                        <textarea id="t<?= $ord ?>" class="trad-area trad-texto" rows="6"><?= e($p['traduzido']) ?></textarea>
<?php else: ?>
                        <div class="trad-original texto-2">Este trecho ainda não foi traduzido.</div>
<?php endif; ?>
                    </div>
                </div>
<?php if ($feito): ?>
                <div class="trad-rodape">
                    <button type="button" class="btn btn-sec btn-sm" data-acao="aprovar"><?= $apr ? 'Desfazer aprovação' : 'Aprovar trecho' ?></button>
                    <button type="button" class="btn btn-sec btn-sm" data-acao="pedir">Traduzir de novo…</button>
                    <span class="texto-2 trad-estado" aria-live="polite"></span>
                </div>
                <div class="trad-novo" hidden>
                    <label for="a<?= $ord ?>">O que você quer mudar neste trecho? (opcional)</label>
                    <input type="text" id="a<?= $ord ?>" maxlength="400" placeholder="Ex.: Mais informal. Manter &quot;saudade&quot; sem traduzir." autocomplete="off">
                    <button type="button" class="btn btn-sec btn-sm" data-acao="retraduzir">Traduzir este trecho de novo</button>
                </div>
<?php endif; ?>
            </section>
<?php endforeach; ?>
        </div>
        <script src="<?= asset_url('assets/js/revisar.js') ?>"></script>
<?php layout_fim(); ?>

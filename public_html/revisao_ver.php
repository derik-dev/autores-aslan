<?php
// Revisão de textos: o texto com as correções marcadas, uma barra lateral para navegar e um balão que explica cada uma.

require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/revisao.php';

$usuario = exigir_login();
$id = (int) ($_GET['id'] ?? 0);

$trad    = null;
$dados   = ['trechos' => []];
$tabelaOk = true;
try {
    $s = db()->prepare('SELECT id, titulo, idioma, status FROM traducoes WHERE id = ? AND usuario_id = ?');
    $s->execute([$id, $usuario['id']]);
    $trad = $s->fetch() ?: null;

    if ($trad) {
        $s = db()->prepare('SELECT ordem, original, feito FROM traducao_trechos WHERE traducao_id = ? ORDER BY ordem');
        $s->execute([$id]);
        $trechos = $s->fetchAll();

        $porTrecho = [];
        try {
            $s = db()->prepare('SELECT id, ordem, seq, pos, tam, original, corrigido, tipo, motivo, estado FROM revisao_alteracoes WHERE traducao_id = ? ORDER BY ordem, pos');
            $s->execute([$id]);
            foreach ($s->fetchAll() as $a) {
                $porTrecho[(int) $a['ordem']][] = [
                    'id' => (int) $a['id'], 'pos' => (int) $a['pos'], 'tam' => (int) $a['tam'],
                    'o' => $a['original'], 'c' => $a['corrigido'], 'k' => $a['tipo'], 'm' => (string) $a['motivo'], 'e' => $a['estado'],
                ];
            }
        } catch (Throwable $e) {
            $tabelaOk = false;
            error_log('revisao_ver (alterações): ' . $e->getMessage());
        }

        foreach ($trechos as $t) {
            $dados['trechos'][] = [
                'ordem' => (int) $t['ordem'], 'texto' => $t['original'], 'feito' => (int) $t['feito'] === 1,
                'alt' => $porTrecho[(int) $t['ordem']] ?? [],
            ];
        }
    }
} catch (Throwable $e) {
    error_log('revisao_ver.php: ' . $e->getMessage());
}

if ($trad && $trad['idioma'] !== TRAD_REVISAO) {
    header('Location: traducao_revisar.php?id=' . (int) $trad['id']);
    exit;
}

layout_inicio('Revisão do texto', 'revisao', $usuario);
echo '<link rel="stylesheet" href="' . asset_url('assets/css/revisao.css') . '">' . "\n";

if (!$trad):
?>
        <div class="vazio">
            <strong>Texto não encontrado</strong>
            Pode ter sido eliminado. <a href="revisao.php">Voltar à Revisão de textos</a>
        </div>
<?php elseif (!$tabelaOk): ?>
        <p class="alerta" role="alert">A tabela das correções ainda não existe na base de dados. Importe o ficheiro <strong>database/schema.sql</strong> no phpMyAdmin e recarregue esta página.</p>
<?php else:
    $semFeito = 0;
    foreach ($dados['trechos'] as $t) {
        if (!$t['feito']) {
            $semFeito++;
        }
    }
?>
        <p class="texto-2"><a href="revisao.php">Revisão de textos</a> / <strong><?= e($trad['titulo']) ?></strong></p>

<?php if ($semFeito > 0): ?>
        <p class="alerta" role="alert">Faltam rever <?= $semFeito ?> trecho(s). <a href="revisao.php">Volte à Revisão de textos</a> e carregue em Continuar.</p>
<?php endif; ?>

        <div id="rv-app" data-csrf="<?= e(csrf_token()) ?>" data-id="<?= (int) $trad['id'] ?>">
            <div class="rv-barra">
                <div class="rv-resumo">
                    <strong id="rv-titulo-contagem">0 correções</strong>
                    <span class="meta" id="rv-detalhe-contagem"></span>
                </div>
                <div class="rv-acoes">
                    <div class="rv-modos" role="group" aria-label="Como ver o texto">
                        <button type="button" class="btn btn-sec btn-sm ativo" data-modo="marcas">Com marcas</button>
                        <button type="button" class="btn btn-sec btn-sm" data-modo="limpo">Texto corrigido</button>
                        <button type="button" class="btn btn-sec btn-sm" data-modo="original">Original</button>
                    </div>
                    <button type="button" class="btn btn-sec btn-sm" id="rv-aceitar-todas">Aceitar todas</button>
                    <button type="button" class="btn btn-sec btn-sm" id="rv-copiar">Copiar texto corrigido</button>
                    <a class="btn btn-sec btn-sm" href="traducao_baixar.php?id=<?= (int) $trad['id'] ?>">Transferir .txt</a>
                    <a class="btn btn-sec btn-sm" href="audio.php?traducao=<?= (int) $trad['id'] ?>">Gerar áudio deste texto</a>
                </div>
            </div>
            <p class="alerta" role="alert" id="rv-erro" hidden></p>

            <div class="rv-grade">
                <section class="bloco rv-doc" id="rv-doc" data-modo="marcas">
                    <h2 class="bloco-titulo">Texto</h2>
                    <p class="rv-legenda texto-2">O <del>riscado</del> é o que estava escrito. O <ins>destacado</ins> é a proposta da IA. Carregue numa marca para ver porquê.</p>
                    <div id="rv-texto"></div>
                </section>

                <aside class="rv-lateral" aria-label="Correções">
                    <section class="bloco">
                        <h2 class="bloco-titulo">Correções</h2>
                        <div class="rv-filtros" id="rv-filtros"></div>
                        <ol class="rv-lista" id="rv-lista"></ol>
                    </section>
                </aside>
            </div>

            <div class="rv-balao" id="rv-balao" role="dialog" aria-live="polite" hidden></div>
        </div>
        <script type="application/json" id="rv-dados"><?= json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
        <script>window.REV_TIPOS = <?= json_encode(REV_TIPOS, JSON_UNESCAPED_UNICODE) ?>;</script>
        <script src="<?= asset_url('assets/js/revisao_ver.js') ?>"></script>
<?php endif; ?>
<?php layout_fim(); ?>

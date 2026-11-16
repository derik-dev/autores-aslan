<?php
// Revisão de textos: a IA corrige ortografia e gramática (português de Portugal) e mostra cada correção marcada, com explicação.

require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/http.php';
require_once __DIR__ . '/../app/revisao.php';

$usuario = exigir_login();

$tabelasOk   = true;
$tabelaCorrecoes = true;
$itens       = [];
$correcoes   = [];
try {
    $itens = trad_listar($usuario['id'], true);
} catch (Throwable $e) {
    error_log('revisao.php: ' . $e->getMessage());
    $tabelasOk = false;
}
if ($tabelasOk) {
    try {
        $s = db()->prepare(
            "SELECT a.traducao_id, COUNT(*) AS n, SUM(a.estado = 'pendente') AS pendentes
             FROM revisao_alteracoes a JOIN traducoes t ON t.id = a.traducao_id
             WHERE t.usuario_id = ? GROUP BY a.traducao_id"
        );
        $s->execute([$usuario['id']]);
        foreach ($s->fetchAll() as $c) {
            $correcoes[(int) $c['traducao_id']] = ['n' => (int) $c['n'], 'pendentes' => (int) $c['pendentes']];
        }
    } catch (Throwable $e) {
        $tabelaCorrecoes = false;
    }
}

$rotulosStatus = ['concluido' => 'Revisto', 'erro' => 'Erro', 'processando' => 'A rever', 'pendente' => 'Incompleto'];
$classesStatus = ['concluido' => 'chip-ok', 'erro' => 'chip-erro', 'processando' => 'chip-neutro', 'pendente' => 'chip-neutro'];

layout_inicio('Revisão de textos', 'revisao', $usuario);
echo '<link rel="stylesheet" href="' . asset_url('assets/css/revisao.css') . '">' . "
";
?>
        <p class="texto-2">Carregue um ficheiro. A IA corrige a ortografia, a acentuação, a gramática e a pontuação sem mudar o seu estilo, e mostra cada correção marcada no texto, com a explicação.</p>

<?php if (!$tabelasOk || !$tabelaCorrecoes): ?>
        <p class="alerta" role="alert">Faltam tabelas na base de dados. Importe o ficheiro <strong>database/schema.sql</strong> no phpMyAdmin e recarregue esta página.</p>
<?php endif; ?>
<?php if ($tabelasOk): ?>
        <div id="trad-app" class="rv-envio" data-modo="rev" data-csrf="<?= e(csrf_token()) ?>">

            <section class="bloco">
                <h2 class="bloco-titulo">Novo texto para rever</h2>
                <div class="form">
                    <input type="file" id="arquivo" accept=".txt,.docx" hidden>
                    <label for="arquivo" id="rv-soltar" class="rv-soltar" tabindex="0">
                        <strong id="rv-soltar-titulo">Carregar um ficheiro</strong>
                        <span class="texto-2" id="rotulo-arquivo">Arraste o ficheiro para aqui ou carregue para o escolher. Aceita .txt e .docx.</span>
                    </label>
                    <p id="resumo" class="texto-2" hidden></p>

                    <p id="erro-form" class="alerta" role="alert" hidden></p>
                    <div>
                        <button type="button" id="gerar" class="btn btn-primary" disabled>Rever texto</button>
                    </div>
                </div>
            </section>

            <section id="andamento" class="rv-carga" hidden role="dialog" aria-modal="true" aria-labelledby="and-titulo" aria-live="polite">
                <div class="rv-carga-caixa">
                    <p class="rv-carga-rotulo">A rever o texto</p>
                    <h2 class="rv-carga-nome" id="and-titulo"></h2>
                    <p class="rv-carga-pct" id="and-pct">0%</p>
                    <div class="barra-audio" aria-hidden="true"><i id="and-barra"></i></div>
                    <p id="and-texto" class="rv-carga-estado"></p>
                    <p class="meta">Não feche esta página até terminar. Quando acabar, abre sozinha as correções.</p>
                    <p id="and-erro" class="alerta" role="alert" hidden></p>
                    <div class="rv-b-botoes rv-carga-botoes">
                        <button type="button" id="and-tentar" class="btn btn-primary btn-sm" hidden>Continuar de onde ficou</button>
                        <button type="button" id="and-voltar" class="btn btn-sec btn-sm" hidden>Voltar</button>
                    </div>
                </div>
            </section>

            <section class="bloco">
                <h2 class="bloco-titulo">Os meus textos revistos</h2>
<?php if (!$itens): ?>
                <div class="vazio">
                    <strong>Ainda não reviu nenhum texto</strong>
                    Carregue um ficheiro e depois em Rever texto. O resultado aparece aqui, com as correções marcadas.
                </div>
<?php else: ?>
                <ul class="lista-audios">
<?php foreach ($itens as $a):
    $total  = (int) $a['total'];
    $feitos = (int) $a['feitos'];
    $st     = $a['status'];
    $c      = $correcoes[(int) $a['id']] ?? null;
?>
                    <li class="linha-audio" data-id="<?= (int) $a['id'] ?>" data-titulo="<?= e($a['titulo']) ?>">
                        <div class="linha-topo">
                            <div class="linha-info">
                                <strong><?= e($a['titulo']) ?></strong>
                                <span class="meta"><?= num_br($a['total_chars']) ?> caracteres · <?= $feitos ?> de <?= $total ?> trechos revistos<?= $c ? ' · ' . $c['n'] . ($c['n'] === 1 ? ' correção' : ' correções') . ($c['pendentes'] > 0 ? ' (' . $c['pendentes'] . ' por rever)' : '') : '' ?> · <?= e(date('d/m/Y H:i', strtotime($a['criado_em']))) ?></span>
                            </div>
                            <span class="chip <?= e($classesStatus[$st] ?? 'chip-neutro') ?>"><?= e($rotulosStatus[$st] ?? $st) ?></span>
                        </div>
<?php if ($st === 'erro' && $a['erro']): ?>
                        <p class="status-erro"><?= e($a['erro']) ?></p>
<?php endif; ?>
                        <div class="linha-acoes">
<?php if ($st !== 'concluido'): ?>
                            <button type="button" class="btn btn-sec btn-sm" data-acao="continuar">Continuar</button>
<?php endif; ?>
<?php if ($feitos > 0): ?>
                            <a class="btn btn-sec btn-sm" href="revisao_ver.php?id=<?= (int) $a['id'] ?>">Ver correções</a>
                            <a class="btn btn-sec btn-sm" href="traducao_baixar.php?id=<?= (int) $a['id'] ?>">Transferir .txt</a>
<?php endif; ?>
                            <button type="button" class="btn btn-sec btn-sm" data-acao="excluir">Eliminar</button>
                        </div>
                    </li>
<?php endforeach; ?>
                </ul>
<?php endif; ?>
            </section>
        </div>
        <script src="<?= asset_url('assets/js/revisao.js') ?>"></script>
<?php endif; ?>
<?php layout_fim(); ?>

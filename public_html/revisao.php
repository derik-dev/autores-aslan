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
?>
        <p class="texto-2">Cole ou carregue um texto. A IA corrige a ortografia, a acentuação, a gramática e a pontuação sem mudar o seu estilo, e mostra cada correção marcada no texto, com a explicação.</p>

<?php if (!$tabelasOk || !$tabelaCorrecoes): ?>
        <p class="alerta" role="alert">Faltam tabelas na base de dados. Importe o ficheiro <strong>database/schema.sql</strong> no phpMyAdmin e recarregue esta página.</p>
<?php endif; ?>
<?php if ($tabelasOk): ?>
        <div id="trad-app" data-modo="rev" data-csrf="<?= e(csrf_token()) ?>">

            <section class="bloco">
                <h2 class="bloco-titulo">Novo texto para rever</h2>
                <div class="form">
                    <label for="titulo">Título</label>
                    <input type="text" id="titulo" maxlength="200" placeholder="Ex.: Capítulo 3 – O regresso" autocomplete="off">

                    <div class="rotulo-linha">
                        <label for="texto">Texto</label>
                        <label for="arquivo" class="btn btn-sec btn-sm" id="rotulo-arquivo">Carregar ficheiro (.txt ou .docx)</label>
                        <input type="file" id="arquivo" accept=".txt,.docx" hidden>
                    </div>
                    <textarea id="texto" rows="10" placeholder="Cole aqui o texto que quer rever…"></textarea>
                    <p id="resumo" class="texto-2">Ainda não há texto.</p>

                    <p id="erro-form" class="alerta" role="alert" hidden></p>
                    <div>
                        <button type="button" id="gerar" class="btn btn-primary">Rever texto</button>
                    </div>
                </div>
            </section>

            <section id="andamento" class="bloco" hidden aria-live="polite">
                <h2 class="bloco-titulo" id="and-titulo"></h2>
                <div class="barra-audio" aria-hidden="true"><i id="and-barra"></i></div>
                <p id="and-texto" class="texto-2"></p>
                <p id="and-erro" class="alerta" role="alert" hidden></p>
                <button type="button" id="and-tentar" class="btn btn-sec btn-sm" hidden>Continuar de onde ficou</button>
            </section>

            <section class="bloco">
                <h2 class="bloco-titulo">Os meus textos revistos</h2>
<?php if (!$itens): ?>
                <div class="vazio">
                    <strong>Ainda não reviu nenhum texto</strong>
                    Cole um texto e carregue em Rever texto. O resultado aparece aqui, com as correções marcadas.
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

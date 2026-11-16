<?php
// Tela "Revisão de textos": a IA corrige ortografia e gramática, trecho a trecho, e o editor revisa lado a lado.

require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/traducoes.php';
require_once __DIR__ . '/../app/http.php';

$usuario = exigir_login();

$tabelasOk = true;
$itens     = [];
try {
    $itens = trad_listar($usuario['id'], true);
} catch (Throwable $e) {
    error_log('revisao.php: ' . $e->getMessage());
    $tabelasOk = false;
}

$rotulosStatus = ['concluido' => 'Revisado', 'erro' => 'Erro', 'processando' => 'Revisando', 'pendente' => 'Incompleto'];
$classesStatus = ['concluido' => 'chip-ok', 'erro' => 'chip-erro', 'processando' => 'chip-neutro', 'pendente' => 'chip-neutro'];

layout_inicio('Revisão de textos', 'revisao', $usuario);
?>
        <p class="texto-2">Cole ou envie um texto. A IA corrige ortografia, acentuação, gramática e pontuação sem mudar o seu estilo, e você confere cada trecho lado a lado.</p>

<?php if (!$tabelasOk): ?>
        <p class="alerta" role="alert">As tabelas ainda não existem no banco. Importe o arquivo <strong>database/schema.sql</strong> no phpMyAdmin e recarregue esta página.</p>
<?php else: ?>
        <div id="trad-app" data-modo="rev" data-csrf="<?= e(csrf_token()) ?>">

            <section class="bloco">
                <h2 class="bloco-titulo">Novo texto para revisar</h2>
                <div class="form">
                    <label for="titulo">Título</label>
                    <input type="text" id="titulo" maxlength="200" placeholder="Ex.: Capítulo 3 – A volta" autocomplete="off">

                    <div class="rotulo-linha">
                        <label for="texto">Texto</label>
                        <label for="arquivo" class="btn btn-sec btn-sm" id="rotulo-arquivo">Enviar arquivo (.txt ou .docx)</label>
                        <input type="file" id="arquivo" accept=".txt,.docx" hidden>
                    </div>
                    <textarea id="texto" rows="10" placeholder="Cole aqui o texto que você quer revisar…"></textarea>
                    <p id="resumo" class="texto-2">Nenhum texto ainda.</p>

                    <p id="erro-form" class="alerta" role="alert" hidden></p>
                    <div>
                        <button type="button" id="gerar" class="btn btn-primary">Revisar texto</button>
                    </div>
                </div>
            </section>

            <section id="andamento" class="bloco" hidden aria-live="polite">
                <h2 class="bloco-titulo" id="and-titulo"></h2>
                <div class="barra-audio" aria-hidden="true"><i id="and-barra"></i></div>
                <p id="and-texto" class="texto-2"></p>
                <p id="and-erro" class="alerta" role="alert" hidden></p>
                <button type="button" id="and-tentar" class="btn btn-sec btn-sm" hidden>Continuar de onde parou</button>
            </section>

            <section class="bloco">
                <h2 class="bloco-titulo">Meus textos revisados</h2>
<?php if (!$itens): ?>
                <div class="vazio">
                    <strong>Nenhum texto revisado ainda</strong>
                    Cole um texto acima e clique em Revisar texto. O resultado aparece aqui, para conferir lado a lado.
                </div>
<?php else: ?>
                <ul class="lista-audios">
<?php foreach ($itens as $a):
    $total     = (int) $a['total'];
    $feitos    = (int) $a['feitos'];
    $aprovados = (int) $a['aprovados'];
    $st        = $a['status'];
?>
                    <li class="linha-audio" data-id="<?= (int) $a['id'] ?>" data-titulo="<?= e($a['titulo']) ?>">
                        <div class="linha-topo">
                            <div class="linha-info">
                                <strong><?= e($a['titulo']) ?></strong>
                                <span class="meta"><?= num_br((int) $a['total_chars']) ?> caracteres · <?= $feitos ?> de <?= $total ?> trechos revisados · <?= $aprovados ?> aprovados · <?= e(date('d/m/Y H:i', strtotime($a['criado_em']))) ?></span>
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
                            <a class="btn btn-sec btn-sm" href="traducao_revisar.php?id=<?= (int) $a['id'] ?>">Conferir lado a lado</a>
                            <a class="btn btn-sec btn-sm" href="traducao_baixar.php?id=<?= (int) $a['id'] ?>">Baixar .txt</a>
<?php endif; ?>
                            <button type="button" class="btn btn-sec btn-sm" data-acao="excluir">Excluir</button>
                        </div>
                    </li>
<?php endforeach; ?>
                </ul>
<?php endif; ?>
            </section>
        </div>
        <script src="<?= asset_url('assets/js/traducao.js') ?>"></script>
<?php endif; ?>
<?php layout_fim(); ?>

<?php
// Exemplares antecipados: cria uma campanha com página de inscrição pública e acompanha os leitores.

require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/entregas.php';

$usuario = exigir_login();

$tabelasOk = true;
$campanhas = [];
$entregas  = [];
try {
    $pdo = db();
    $s = $pdo->prepare('SELECT id, titulo FROM entregas WHERE usuario_id = ? ORDER BY id DESC');
    $s->execute([$usuario['id']]);
    $entregas = $s->fetchAll();

    $s = $pdo->prepare('SELECT * FROM arcs WHERE usuario_id = ? ORDER BY id DESC');
    $s->execute([$usuario['id']]);
    $campanhas = $s->fetchAll();
    foreach ($campanhas as &$c) {
        $l = $pdo->prepare('SELECT id, nome, email, onde, status, link_token, resenhou, criado_em FROM arc_leitores WHERE arc_id = ? ORDER BY id DESC');
        $l->execute([$c['id']]);
        $c['leitores'] = $l->fetchAll();
    }
    unset($c);
} catch (Throwable $e) {
    error_log('exemplares.php: ' . $e->getMessage());
    $tabelasOk = false;
}

$rotulosStatus = ['pendente' => 'Aguardando', 'aprovado' => 'Aprovado', 'recusado' => 'Recusado'];
$classesStatus = ['pendente' => 'chip-neutro', 'aprovado' => 'chip-ok', 'recusado' => 'chip-erro'];

layout_inicio('Exemplares', 'exemplares', $usuario);
?>
        <p class="texto-2">Monte um grupo de leitores que recebem o livro antes do lançamento. Eles se inscrevem numa página pública, você aprova e o link de download é gerado.</p>

<?php if (!$tabelasOk): ?>
        <p class="alerta" role="alert">As tabelas ainda não existem no banco. Importe o arquivo <strong>database/schema.sql</strong> no phpMyAdmin e recarregue esta página.</p>
<?php else: ?>
        <div id="arc-app" data-csrf="<?= e(csrf_token()) ?>">
            <section class="bloco">
                <h2 class="bloco-titulo">Nova campanha</h2>
                <div class="form">
                    <label for="a-titulo">Livro</label>
                    <input type="text" id="a-titulo" maxlength="200" autocomplete="off">
                    <label for="a-descricao">Convite para os leitores (opcional)</label>
                    <textarea id="a-descricao" rows="4" maxlength="3000" placeholder="Conte em poucas linhas do que é o livro e o que você espera dos leitores."></textarea>
                    <div class="duas-colunas">
                        <div class="coluna">
                            <label for="a-entrega">Arquivo que os leitores vão receber</label>
                            <select id="a-entrega">
                                <option value="0"><?= $entregas ? 'Escolher depois' : 'Nenhum (crie uma entrega primeiro)' ?></option>
<?php foreach ($entregas as $en): ?>
                                <option value="<?= (int) $en['id'] ?>"><?= e($en['titulo']) ?></option>
<?php endforeach; ?>
                            </select>
                        </div>
                        <div class="coluna">
                            <label for="a-vagas">Vagas</label>
                            <input type="number" id="a-vagas" min="1" max="5000" value="50">
                        </div>
                    </div>
                    <p id="a-erro" class="alerta" role="alert" hidden></p>
                    <div>
                        <button type="button" id="a-criar" class="btn btn-primary">Criar campanha</button>
                    </div>
                </div>
            </section>

            <section class="bloco">
                <h2 class="bloco-titulo">Minhas campanhas</h2>
<?php if (!$campanhas): ?>
                <div class="vazio">
                    <strong>Nenhuma campanha ainda</strong>
                    Crie uma campanha acima. Você recebe um endereço para divulgar aos leitores.
                </div>
<?php else: ?>
                <ul class="lista-audios">
<?php foreach ($campanhas as $c):
    $pagina = url_base() . '/exemplar.php?c=' . $c['token'];
    $aprovados = count(array_filter($c['leitores'], function ($l) { return $l['status'] === 'aprovado'; }));
    $resenhas  = count(array_filter($c['leitores'], function ($l) { return (int) $l['resenhou'] === 1; }));
?>
                    <li class="linha-audio" data-id="<?= (int) $c['id'] ?>">
                        <div class="linha-topo">
                            <div class="linha-info">
                                <strong><?= e($c['titulo']) ?></strong>
                                <span class="meta"><?= count($c['leitores']) ?> inscritos · <?= $aprovados ?> aprovados · <?= $resenhas ?> resenhas · <?= (int) $c['vagas'] ?> vagas</span>
                            </div>
                            <span class="chip <?= (int) $c['aberto'] ? 'chip-ok' : 'chip-neutro' ?>"><?= (int) $c['aberto'] ? 'Inscrições abertas' : 'Inscrições fechadas' ?></span>
                        </div>
                        <p class="texto-2 l-resultado">Página para divulgar: <?= e($pagina) ?></p>
                        <div class="linha-acoes">
                            <button type="button" class="btn btn-sec btn-sm" data-acao="copiar" data-url="<?= e($pagina) ?>">Copiar endereço da página</button>
                            <button type="button" class="btn btn-sec btn-sm" data-acao="alternar"><?= (int) $c['aberto'] ? 'Fechar inscrições' : 'Reabrir inscrições' ?></button>
                            <button type="button" class="btn btn-sec btn-sm" data-acao="excluir-arc">Excluir campanha</button>
                        </div>
<?php if ($c['leitores']): ?>
                        <div class="tabela-rolagem">
                            <table class="tabela">
                                <thead><tr><th>Leitor</th><th>Onde divulga</th><th>Situação</th><th>Ações</th></tr></thead>
                                <tbody>
<?php foreach ($c['leitores'] as $l): ?>
                                    <tr data-leitor="<?= (int) $l['id'] ?>" data-titulo="<?= e($c['titulo']) ?>" data-nome="<?= e($l['nome']) ?>" data-email="<?= e($l['email']) ?>">
                                        <td><strong><?= e($l['nome']) ?></strong><br><span class="meta"><?= e($l['email']) ?></span></td>
                                        <td style="white-space: normal"><?= e($l['onde'] ?: '—') ?></td>
                                        <td><span class="chip <?= e($classesStatus[$l['status']]) ?>"><?= e($rotulosStatus[$l['status']]) ?></span><?= (int) $l['resenhou'] ? ' <span class="chip chip-ok">Resenhou</span>' : '' ?></td>
                                        <td>
                                            <div class="linha-acoes">
<?php if ($l['status'] !== 'aprovado'): ?>
                                                <button type="button" class="btn btn-sec btn-sm" data-acao="aprovar">Aprovar e gerar link</button>
<?php else: ?>
                                                <button type="button" class="btn btn-sec btn-sm" data-acao="copiar" data-url="<?= e(ent_link_publico($l['link_token'])) ?>">Copiar link</button>
                                                <button type="button" class="btn btn-sec btn-sm" data-acao="resenhou"><?= (int) $l['resenhou'] ? 'Desmarcar resenha' : 'Marcar como resenhou' ?></button>
<?php endif; ?>
<?php if ($l['status'] !== 'recusado'): ?>
                                                <button type="button" class="btn btn-sec btn-sm" data-acao="recusar">Recusar</button>
<?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
<?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
<?php else: ?>
                        <p class="texto-2">Ninguém se inscreveu ainda. Divulgue o endereço da página.</p>
<?php endif; ?>
                        <p class="texto-2 l-resultado arc-msg" aria-live="polite"></p>
                    </li>
<?php endforeach; ?>
                </ul>
<?php endif; ?>
            </section>
        </div>
        <script src="<?= asset_url('assets/js/exemplares.js') ?>"></script>
<?php endif; ?>
<?php layout_fim(); ?>

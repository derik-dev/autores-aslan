<?php
// Tela "Análises": um agente que lê os números de vendas e escreve um relatório com destaques, alertas e próximas ações.

require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/http.php';
require_once __DIR__ . '/../app/relatorios.php';

$usuario = exigir_login();
$editora = eh_editora($usuario);

$tabelasOk = true;
$lista     = [];
try {
    $lista = rel_listar($usuario['id'], 'analise');
} catch (Throwable $e) {
    error_log('analises.php: ' . $e->getMessage());
    $tabelasOk = false;
}

layout_inicio('Análises', 'analises', $usuario);
?>
        <p class="texto-2"><?= $editora ? 'A IA lê as vendas de todos os autores e aponta destaques, alertas e o que fazer.' : 'A IA lê as suas vendas e aponta destaques, alertas e o que fazer.' ?> Ela usa só os números importados no painel de Vendas.</p>

<?php if (!$tabelasOk): ?>
        <p class="alerta" role="alert">As tabelas ainda não existem no banco. Importe o arquivo <strong>database/schema.sql</strong> no phpMyAdmin e recarregue esta página.</p>
<?php else: ?>
        <div id="rel-app" data-csrf="<?= e(csrf_token()) ?>" data-acao="gerar_analise">
            <section class="bloco">
                <h2 class="bloco-titulo">Nova análise</h2>
                <p id="rel-erro" class="alerta" role="alert" hidden></p>
                <p id="rel-estado" class="texto-2" aria-live="polite"></p>
                <div>
                    <button type="button" id="rel-gerar" class="btn btn-primary">Gerar análise agora</button>
                </div>
            </section>

            <section class="bloco">
                <h2 class="bloco-titulo">Análises anteriores</h2>
<?php if (!$lista): ?>
                <div class="vazio">
                    <strong>Nenhuma análise ainda</strong>
                    Clique em Gerar análise agora. Antes, confira se há vendas importadas na tela de Vendas.
                </div>
<?php else: ?>
                <ul class="lista-audios">
<?php foreach ($lista as $r): ?>
                    <li class="linha-audio" data-id="<?= (int) $r['id'] ?>">
                        <div class="linha-topo">
                            <div class="linha-info">
                                <strong><?= e($r['titulo']) ?></strong>
                                <span class="meta"><?= e(date('d/m/Y H:i', strtotime($r['criado_em']))) ?></span>
                            </div>
                            <button type="button" class="btn btn-sec btn-sm" data-acao="excluir-rel">Excluir</button>
                        </div>
                        <div class="trad-original rel-texto"><?= e($r['conteudo']) ?></div>
                    </li>
<?php endforeach; ?>
                </ul>
<?php endif; ?>
            </section>
        </div>
        <script src="<?= asset_url('assets/js/relatorios.js') ?>"></script>
<?php endif; ?>
<?php layout_fim(); ?>

<?php
// Tela "Mercado": pesquisa assistida de palavras-chave, categorias e posicionamento. São hipóteses, não dados reais de busca.

require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/http.php';
require_once __DIR__ . '/../app/relatorios.php';

$usuario = exigir_login();

$tabelasOk = true;
$lista     = [];
try {
    $lista = rel_listar($usuario['id'], 'mercado');
} catch (Throwable $e) {
    error_log('mercado.php: ' . $e->getMessage());
    $tabelasOk = false;
}

layout_inicio('Mercado', 'mercado', $usuario);
?>
        <p class="texto-2">Descreva o livro e receba ideias de palavras-chave, categorias, posicionamento e títulos.</p>
        <p class="alerta">A IA não consulta buscadores nem listas de mais vendidos. O resultado são hipóteses para você validar antes de decidir, não dados reais de busca.</p>

<?php if (!$tabelasOk): ?>
        <p class="alerta" role="alert">As tabelas ainda não existem no banco. Importe o arquivo <strong>database/schema.sql</strong> no phpMyAdmin e recarregue esta página.</p>
<?php else: ?>
        <div id="rel-app" data-csrf="<?= e(csrf_token()) ?>" data-acao="gerar_mercado">
            <section class="bloco">
                <h2 class="bloco-titulo">Novo livro para pesquisar</h2>
                <div class="form">
                    <label for="m-titulo">Título do livro</label>
                    <input type="text" id="m-titulo" maxlength="200" autocomplete="off">
                    <div class="duas-colunas">
                        <div class="coluna">
                            <label for="m-genero">Gênero</label>
                            <input type="text" id="m-genero" maxlength="100" placeholder="Ex.: thriller sobrenatural" autocomplete="off">
                        </div>
                        <div class="coluna">
                            <label for="m-publico">Público</label>
                            <input type="text" id="m-publico" maxlength="150" placeholder="Ex.: adultos que gostam de suspense" autocomplete="off">
                        </div>
                    </div>
                    <label for="m-sinopse">Sinopse</label>
                    <textarea id="m-sinopse" rows="6" maxlength="4000" placeholder="Conte a história ou o conteúdo do livro em poucas linhas."></textarea>
                    <p id="rel-erro" class="alerta" role="alert" hidden></p>
                    <p id="rel-estado" class="texto-2" aria-live="polite"></p>
                    <div>
                        <button type="button" id="rel-gerar" class="btn btn-primary">Pesquisar mercado</button>
                    </div>
                </div>
            </section>

            <section class="bloco">
                <h2 class="bloco-titulo">Pesquisas anteriores</h2>
<?php if (!$lista): ?>
                <div class="vazio">
                    <strong>Nenhuma pesquisa ainda</strong>
                    Preencha os dados do livro acima e clique em Pesquisar mercado.
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

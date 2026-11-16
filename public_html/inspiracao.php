<?php
// Área de inspiração: cartões de ideias, personagens, pesquisas, imagens e links de cada autor.

require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/http.php';

$usuario = exigir_login();

$tabelasOk = true;
$cartoes   = [];
try {
    $s = db()->prepare('SELECT id, tipo, titulo, conteudo, url, imagem, criado_em FROM inspiracoes WHERE usuario_id = ? ORDER BY id DESC');
    $s->execute([$usuario['id']]);
    $cartoes = $s->fetchAll();
} catch (Throwable $e) {
    error_log('inspiracao.php: ' . $e->getMessage());
    $tabelasOk = false;
}

$tipos = ['ideia' => 'Ideia', 'personagem' => 'Personagem', 'pesquisa' => 'Pesquisa', 'imagem' => 'Imagem', 'link' => 'Link'];

layout_inicio('Inspiração', 'inspiracao', $usuario);
?>
        <p class="texto-2">Guarde num só lugar as ideias, os personagens, as pesquisas, as imagens e os links do seu próximo livro.</p>

<?php if (!$tabelasOk): ?>
        <p class="alerta" role="alert">As tabelas ainda não existem no banco. Importe o arquivo <strong>database/schema.sql</strong> no phpMyAdmin e recarregue esta página.</p>
<?php else: ?>
        <div id="insp-app" data-csrf="<?= e(csrf_token()) ?>">
            <section class="bloco">
                <h2 class="bloco-titulo">Novo cartão</h2>
                <div class="form">
                    <div class="duas-colunas">
                        <div class="coluna">
                            <label for="tipo">Tipo</label>
                            <select id="tipo">
<?php foreach ($tipos as $k => $rot): ?>
                                <option value="<?= e($k) ?>"><?= e($rot) ?></option>
<?php endforeach; ?>
                            </select>
                        </div>
                        <div class="coluna">
                            <label for="titulo">Título</label>
                            <input type="text" id="titulo" maxlength="200" autocomplete="off">
                        </div>
                    </div>
                    <label for="conteudo">Anotações</label>
                    <textarea id="conteudo" rows="5" maxlength="10000" placeholder="Escreva a ideia, a descrição do personagem, o que você descobriu na pesquisa…"></textarea>
                    <div class="duas-colunas">
                        <div class="coluna">
                            <label for="url">Link (opcional)</label>
                            <input type="text" id="url" maxlength="500" placeholder="https://" autocomplete="off">
                        </div>
                        <div class="coluna">
                            <label for="imagem" id="rotulo-imagem">Imagem (opcional, até 5 MB)</label>
                            <input type="file" id="imagem" accept="image/jpeg,image/png,image/gif,image/webp">
                        </div>
                    </div>
                    <p id="insp-erro" class="alerta" role="alert" hidden></p>
                    <div>
                        <button type="button" id="insp-salvar" class="btn btn-primary">Salvar cartão</button>
                    </div>
                </div>
            </section>

            <section class="bloco">
                <h2 class="bloco-titulo">Meus cartões</h2>
<?php if (!$cartoes): ?>
                <div class="vazio">
                    <strong>Nenhum cartão ainda</strong>
                    Crie o primeiro cartão acima. Tudo o que você guardar aqui fica só para você.
                </div>
<?php else: ?>
                <div class="trad-filtros" role="group" aria-label="Filtrar por tipo">
                    <button type="button" class="btn btn-sec btn-sm filtro ativo" data-filtro="todos">Todos</button>
<?php foreach ($tipos as $k => $rot): ?>
                    <button type="button" class="btn btn-sec btn-sm filtro" data-filtro="<?= e($k) ?>"><?= e($rot) ?></button>
<?php endforeach; ?>
                </div>
                <div class="grade-insp">
<?php foreach ($cartoes as $c): ?>
                    <article class="cartao-insp" data-id="<?= (int) $c['id'] ?>" data-tipo="<?= e($c['tipo']) ?>">
<?php if ($c['imagem']): ?>
                        <img src="inspiracao_img.php?id=<?= (int) $c['id'] ?>" alt="<?= e($c['titulo']) ?>" loading="lazy">
<?php endif; ?>
                        <div class="cartao-corpo">
                            <span class="chip chip-neutro"><?= e($tipos[$c['tipo']] ?? $c['tipo']) ?></span>
                            <strong><?= e($c['titulo']) ?></strong>
<?php if ($c['conteudo']): ?>
                            <p class="cartao-texto"><?= e($c['conteudo']) ?></p>
<?php endif; ?>
<?php if ($c['url']): ?>
                            <a href="<?= e($c['url']) ?>" target="_blank" rel="noopener noreferrer">Abrir link</a>
<?php endif; ?>
                            <div class="linha-acoes">
                                <button type="button" class="btn btn-sec btn-sm" data-acao="excluir-insp">Excluir</button>
                            </div>
                        </div>
                    </article>
<?php endforeach; ?>
                </div>
<?php endif; ?>
            </section>
        </div>
        <script src="<?= asset_url('assets/js/inspiracao.js') ?>"></script>
<?php endif; ?>
<?php layout_fim(); ?>

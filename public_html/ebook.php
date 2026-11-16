<?php
// Tela "E-book": transforma o texto do livro em EPUB (ou em página para salvar como PDF).

require_once __DIR__ . '/../app/layout.php';

$usuario = exigir_login();
$erro    = (string) ($_GET['erro'] ?? '');

layout_inicio('E-book', 'ebook', $usuario);
?>
        <p class="texto-2">Cole o texto do livro ou envie um arquivo. Você recebe um e-book EPUB, com sumário e capa, pronto para as lojas e leitores, ou uma página formatada para salvar como PDF.</p>

<?php if ($erro !== ''): ?>
        <p class="alerta" role="alert"><?= e(mb_substr($erro, 0, 300, 'UTF-8')) ?></p>
<?php endif; ?>

        <section class="bloco">
            <h2 class="bloco-titulo">Novo e-book</h2>
            <form method="post" action="ebook_gerar.php" enctype="multipart/form-data" class="form" id="ebook-form">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

                <div class="duas-colunas">
                    <div class="coluna">
                        <label for="titulo">Título do livro</label>
                        <input type="text" id="titulo" name="titulo" maxlength="200" required autocomplete="off">
                    </div>
                    <div class="coluna">
                        <label for="autor">Autor</label>
                        <input type="text" id="autor" name="autor" maxlength="150" autocomplete="off">
                    </div>
                </div>

                <div class="duas-colunas">
                    <div class="coluna">
                        <label for="idioma">Idioma do livro</label>
                        <select id="idioma" name="idioma">
                            <option value="pt-BR">Português do Brasil</option>
                            <option value="pt-PT">Português de Portugal</option>
                            <option value="en">Inglês</option>
                            <option value="es">Espanhol</option>
                            <option value="fr">Francês</option>
                            <option value="it">Italiano</option>
                            <option value="de">Alemão</option>
                        </select>
                    </div>
                    <div class="coluna">
                        <label for="formato">O que você quer receber?</label>
                        <select id="formato" name="formato">
                            <option value="epub">E-book (EPUB)</option>
                            <option value="pdf">Página para salvar como PDF</option>
                        </select>
                    </div>
                </div>

                <div class="rotulo-linha">
                    <label for="texto">Texto do livro</label>
                    <label for="arquivo" class="btn btn-sec btn-sm" id="rotulo-arquivo">Enviar arquivo (.txt ou .docx)</label>
                    <input type="file" id="arquivo" name="arquivo" accept=".txt,.docx" hidden>
                </div>
                <textarea id="texto" name="texto" rows="12" placeholder="Cole aqui o texto. Cada linha vira um parágrafo. Títulos como &quot;Capítulo 1&quot; ou &quot;# Título&quot; viram capítulos do sumário."></textarea>
                <p class="texto-2" id="nome-arquivo">Se você enviar um arquivo, o texto colado é ignorado.</p>

                <label for="capa">Capa (opcional, JPG ou PNG, só para o EPUB)</label>
                <input type="file" id="capa" name="capa" accept="image/jpeg,image/png">

                <label class="marcar"><input type="checkbox" name="capitulos" value="1" checked> Detectar capítulos automaticamente (linhas que começam com "Capítulo", "Parte", "Prólogo" ou "#")</label>
                <p class="texto-2">Quebras de cena: uma linha só com <strong>***</strong>. Itálico: <strong>*assim*</strong>. Negrito: <strong>**assim**</strong>.</p>

                <div>
                    <button type="submit" class="btn btn-primary" data-carregando="Gerando…">Gerar e-book</button>
                </div>
            </form>
        </section>
        <script>
            (function () {
                var a = document.getElementById('arquivo');
                var info = document.getElementById('nome-arquivo');
                var titulo = document.getElementById('titulo');
                // O e-book baixa sem trocar de página: devolve o botão ao normal depois do envio.
                var form = document.getElementById('ebook-form');
                var botao = form.querySelector('button[type="submit"]');
                var rotulo = botao.textContent;
                form.addEventListener('submit', function () {
                    setTimeout(function () { botao.disabled = false; botao.textContent = rotulo; }, 6000);
                });
                a.addEventListener('change', function () {
                    if (!a.files[0]) { return; }
                    info.textContent = 'Arquivo escolhido: ' + a.files[0].name + '. O texto colado será ignorado.';
                    if (!titulo.value.trim()) { titulo.value = a.files[0].name.replace(/\.[^.]+$/, ''); }
                });
            })();
        </script>
<?php layout_fim(); ?>

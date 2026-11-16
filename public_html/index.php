<?php
// Início: índice das funções do sistema, agrupadas como no menu.

require_once __DIR__ . '/../app/layout.php';

$usuario = exigir_login();

$descricoes = [
    'audio'      => 'Converta textos, até um livro inteiro, em áudio.',
    'traducao'   => 'Traduza o livro e revise lado a lado com o original.',
    'revisao'    => 'Corrija ortografia e gramática e confira trecho a trecho.',
    'ebook'      => 'Transforme o texto em EPUB, ou em página para salvar como PDF.',
    'inspiracao' => 'Guarde ideias, personagens, pesquisas e imagens.',
    'entregas'   => 'Envie o livro por link seguro, com validade e limite.',
    'exemplares' => 'Monte um grupo de leitores antes do lançamento.',
    'vendas'     => 'Acompanhe vendas e receitas por mês, livro e canal.',
    'analises'   => 'A IA lê os números e aponta destaques e alertas.',
    'mercado'    => 'Ideias de palavras-chave, categorias e posicionamento.',
    'autores'    => 'Crie contas de autor e redefina senhas.',
];

layout_inicio('Início', 'index', $usuario);
?>
        <p class="texto-2">Olá, <?= e($usuario['nome']) ?>. Escolha uma função para começar.</p>

<?php foreach (MENU_GRUPOS as $grupo => $chaves): ?>
<?php if (in_array('autores', $chaves, true) && !eh_editora($usuario)) { continue; } ?>
        <section class="indice-grupo">
            <h2 class="indice-titulo"><?= e($grupo) ?></h2>
            <ul class="indice">
<?php foreach ($chaves as $chave): [$rotulo, $link, $ico] = MENU[$chave]; ?>
                <li>
                    <a class="indice-item" href="<?= e($link) ?>">
                        <?= icone($ico) ?>
                        <span class="indice-nome"><?= e($rotulo) ?></span>
                        <span class="indice-desc"><?= e($descricoes[$chave] ?? '') ?></span>
                    </a>
                </li>
<?php endforeach; ?>
            </ul>
        </section>
<?php endforeach; ?>
<?php layout_fim(); ?>

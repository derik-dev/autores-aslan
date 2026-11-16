<?php
// Dashboard do autor: ponto de partida para as funções do sistema.

require_once __DIR__ . '/../app/layout.php';

$usuario = exigir_login();
layout_inicio('Início', 'index', $usuario);

$funcoes = [
    ['audio.php',      'Texto em áudio', 'Converta textos, até um livro inteiro, em áudio.'],
    ['traducao.php',   'Tradução',       'Traduza seu livro e revise lado a lado.'],
    ['revisao.php',    'Revisão de textos', 'Corrija ortografia e gramática e confira trecho a trecho.'],
    ['ebook.php',      'E-book',         'Transforme o texto em EPUB ou PDF.'],
    ['inspiracao.php', 'Inspiração',     'Guarde ideias, personagens, pesquisas e imagens.'],
    ['entregas.php',   'Entregas',       'Envie o livro por link seguro, com validade.'],
    ['exemplares.php', 'Exemplares',     'Monte um grupo de leitores antes do lançamento.'],
    ['vendas.php',     'Vendas',         'Acompanhe vendas e receitas dos seus livros.'],
    ['analises.php',   'Análises',       'A IA lê seus números e aponta o que fazer.'],
    ['mercado.php',    'Mercado',        'Ideias de palavras-chave, categorias e posicionamento.'],
];
?>
        <p class="texto-2">Olá, <?= e($usuario['nome']) ?>. Escolha uma função para começar.</p>

        <div class="cards">
<?php foreach ($funcoes as [$link, $titulo, $texto]): ?>
            <a class="card" href="<?= e($link) ?>">
                <h2><?= e($titulo) ?></h2>
                <p><?= e($texto) ?></p>
            </a>
<?php endforeach; ?>
        </div>
<?php layout_fim(); ?>

<?php
// Dashboard do autor: ponto de partida para as funções do sistema.

require_once __DIR__ . '/../app/layout.php';

$usuario = exigir_login();
layout_inicio('Início', 'index', $usuario);
?>
        <p class="texto-2">Olá, <?= e($usuario['nome']) ?>. Escolha uma função para começar.</p>

        <div class="cards">
            <a class="card" href="audio.php">
                <h2>Texto em áudio</h2>
                <p>Converta seus textos em áudio.</p>
            </a>
            <a class="card" href="traducao.php">
                <h2>Tradução</h2>
                <p>Traduza seu livro e revise lado a lado.</p>
            </a>
            <a class="card" href="vendas.php">
                <h2>Vendas</h2>
                <p>Acompanhe as vendas dos seus livros.</p>
            </a>
            <a class="card" href="revisao.php">
                <h2>Revisão de textos</h2>
                <p>Revise e corrija seus textos.</p>
            </a>
        </div>
<?php layout_fim(); ?>

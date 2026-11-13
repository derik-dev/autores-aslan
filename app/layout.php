<?php
// Layout compartilhado das páginas internas (menu lateral + área de conteúdo).

require_once __DIR__ . '/auth.php';

const MENU = [
    'index'   => ['Início',            'index.php'],
    'audio'   => ['Texto em áudio',    'audio.php'],
    'vendas'  => ['Vendas',            'vendas.php'],
    'revisao' => ['Revisão de textos', 'revisao.php'],
];

function layout_inicio(string $titulo, string $ativo, array $usuario): void
{
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titulo) ?> · Autores</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app">
    <button class="menu-toggle" type="button" aria-label="Abrir menu" data-menu-toggle>Menu</button>
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">Autores</div>
        <nav class="sidebar-nav">
            <?php foreach (MENU as $chave => [$rotulo, $link]): ?>
                <a href="<?= e($link) ?>" class="<?= $chave === $ativo ? 'ativo' : '' ?>"><?= e($rotulo) ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-user">
            <span><?= e($usuario['nome']) ?></span>
            <form method="post" action="logout.php">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <button type="submit" class="link-sair">Sair</button>
            </form>
        </div>
    </aside>
    <main class="conteudo">
        <h1 class="page-title"><?= e($titulo) ?></h1>
<?php
}

function layout_fim(): void
{
    ?>
    </main>
</div>
<script src="assets/js/app.js"></script>
</body>
</html>
<?php
}

<?php
// Layout compartilhado das páginas internas (menu lateral + área de conteúdo).

require_once __DIR__ . '/auth.php';

const MENU = [
    'index'   => ['Início',            'index.php',   'casa'],
    'audio'   => ['Texto em áudio',    'audio.php',   'audio'],
    'traducao' => ['Tradução',         'traducao.php', 'traducao'],
    'vendas'  => ['Vendas',            'vendas.php',  'grafico'],
    'revisao' => ['Revisão de textos', 'revisao.php', 'revisao'],
];

// Ícones Lucide (linha fina), inline para não depender de serviço externo.
const ICONES = [
    'casa'    => '<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
    'audio'   => '<path d="M2 10v3"/><path d="M6 6v11"/><path d="M10 3v18"/><path d="M14 8v7"/><path d="M18 5v13"/><path d="M22 10v3"/>',
    'grafico' => '<path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/>',
    'traducao' => '<path d="m5 8 6 6"/><path d="m4 14 6-6 2-3"/><path d="M2 5h12"/><path d="M7 2h1"/><path d="m22 22-5-10-5 10"/><path d="M14 18h6"/>',
    'revisao' => '<path d="m6 16 6-12 6 12"/><path d="M8 12h8"/><path d="m16 20 2 2 4-4"/>',
    'sair'    => '<path d="m16 17 5-5-5-5"/><path d="M21 12H9"/><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>',
];

function icone(string $nome): string
{
    return '<svg class="icone" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">' . (ICONES[$nome] ?? '') . '</svg>';
}

function layout_inicio(string $titulo, string $ativo, array $usuario): void
{
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titulo) ?> · Autores</title>
    <link rel="stylesheet" href="<?= asset_url('assets/css/style.css') ?>">
</head>
<body>
<div class="app">
    <button class="menu-toggle" type="button" aria-label="Abrir menu" data-menu-toggle>Menu</button>
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">Autores</div>
        <nav class="sidebar-nav">
            <?php foreach (MENU as $chave => [$rotulo, $link, $ico]): ?>
                <a href="<?= e($link) ?>" class="<?= $chave === $ativo ? 'ativo' : '' ?>"><?= icone($ico) ?><span><?= e($rotulo) ?></span></a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-user">
            <span><?= e($usuario['nome']) ?></span>
            <form method="post" action="logout.php">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <button type="submit" class="link-sair"><?= icone('sair') ?>Sair</button>
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
<script src="<?= asset_url('assets/js/app.js') ?>"></script>
</body>
</html>
<?php
}

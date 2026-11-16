<?php
// Layout compartilhado das páginas internas (menu lateral + área de conteúdo).

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/http.php';

const MENU = [
    'index'     => ['Início',            'index.php',      'casa'],
    'audio'     => ['Texto em áudio',    'audio.php',      'audio'],
    'traducao'  => ['Tradução',          'traducao.php',   'traducao'],
    'revisao'   => ['Revisão de textos', 'revisao.php',    'revisao'],
    'ebook'     => ['E-book',            'ebook.php',      'livro'],
    'inspiracao' => ['Inspiração',       'inspiracao.php', 'ideia'],
    'entregas'  => ['Entregas',          'entregas.php',   'enviar'],
    'exemplares' => ['Exemplares',       'exemplares.php', 'pessoas'],
    'vendas'    => ['Vendas',            'vendas.php',     'grafico'],
    'analises'  => ['Análises',          'analises.php',   'pulso'],
    'mercado'   => ['Mercado',           'mercado.php',    'busca'],
    'autores'   => ['Autores',           'autores.php',    'autor'],
];

// Como o menu lateral e o início agrupam as funções.
const MENU_GRUPOS = [
    'Criar'    => ['audio', 'traducao', 'revisao', 'ebook', 'inspiracao'],
    'Publicar' => ['entregas', 'exemplares'],
    'Negócio'  => ['vendas', 'analises', 'mercado'],
    'Editora'  => ['autores'],
];

// Ícones Lucide (linha fina), inline para não depender de serviço externo.
const ICONES = [
    'casa'    => '<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
    'audio'   => '<path d="M2 10v3"/><path d="M6 6v11"/><path d="M10 3v18"/><path d="M14 8v7"/><path d="M18 5v13"/><path d="M22 10v3"/>',
    'grafico' => '<path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/>',
    'traducao' => '<path d="m5 8 6 6"/><path d="m4 14 6-6 2-3"/><path d="M2 5h12"/><path d="M7 2h1"/><path d="m22 22-5-10-5 10"/><path d="M14 18h6"/>',
    'revisao' => '<path d="m6 16 6-12 6 12"/><path d="M8 12h8"/><path d="m16 20 2 2 4-4"/>',
    'livro'   => '<path d="M12 7v14"/><path d="M3 18a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h5a4 4 0 0 1 4 4 4 4 0 0 1 4-4h5a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1h-6a3 3 0 0 0-3 3 3 3 0 0 0-3-3z"/>',
    'ideia'   => '<path d="M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5A6 6 0 0 0 6 8c0 1 .2 2.2 1.5 3.5.7.7 1.3 1.5 1.5 2.5"/><path d="M9 18h6"/><path d="M10 22h4"/>',
    'enviar'  => '<path d="m22 2-7 20-4-9-9-4z"/><path d="M22 2 11 13"/>',
    'pessoas' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
    'pulso'   => '<path d="M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2"/>',
    'busca'   => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
    'autor'   => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6"/><path d="M22 11h-6"/>',
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
        <a class="sidebar-brand" href="index.php" aria-label="Autores: início">A</a>
        <nav class="sidebar-nav" aria-label="Principal">
            <?php [$rotulo, $link, $ico] = MENU['index']; ?>
            <a href="<?= e($link) ?>" data-rotulo="<?= e($rotulo) ?>" class="<?= $ativo === 'index' ? 'ativo' : '' ?>"<?= $ativo === 'index' ? ' aria-current="page"' : '' ?>><?= icone($ico) ?><span class="nav-rotulo"><?= e($rotulo) ?></span></a>
            <?php foreach (MENU_GRUPOS as $grupo => $chaves): ?>
                <?php if (in_array('autores', $chaves, true) && !eh_editora($usuario)) { continue; } ?>
                <div class="nav-sep" aria-hidden="true"></div>
                <div class="nav-grupo"><?= e($grupo) ?></div>
                <?php foreach ($chaves as $chave): [$rotulo, $link, $ico] = MENU[$chave]; ?>
                    <a href="<?= e($link) ?>" data-rotulo="<?= e($rotulo) ?>" class="<?= $chave === $ativo ? 'ativo' : '' ?>"<?= $chave === $ativo ? ' aria-current="page"' : '' ?>><?= icone($ico) ?><span class="nav-rotulo"><?= e($rotulo) ?></span></a>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-user">
            <span class="avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($usuario['nome'], 0, 1, 'UTF-8'), 'UTF-8')) ?></span>
            <span class="usuario-nome"><?= e($usuario['nome']) ?></span>
            <form method="post" action="logout.php">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <button type="submit" class="link-sair" data-rotulo="Sair"><?= icone('sair') ?><span class="nav-rotulo">Sair</span></button>
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

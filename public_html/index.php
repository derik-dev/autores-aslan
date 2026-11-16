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

// "Continue de onde parou": áudios e traduções/revisões que ainda não terminaram.
$andamento = [];
try {
    $pdo = db();
    $s = $pdo->prepare("SELECT id, titulo, status FROM audios WHERE usuario_id = ? AND status <> 'concluido' ORDER BY id DESC LIMIT 4");
    $s->execute([$usuario['id']]);
    foreach ($s->fetchAll() as $a) {
        $andamento[] = ['audio', 'audio.php', $a['titulo'], $a['status'] === 'erro' ? 'Áudio com erro: abra para continuar' : 'Áudio incompleto: continue a gerar'];
    }

    $s = $pdo->prepare(
        'SELECT t.id, t.titulo, t.idioma, COUNT(p.id) AS total, COALESCE(SUM(p.feito), 0) AS feitos, COALESCE(SUM(p.aprovado), 0) AS aprovados
         FROM traducoes t LEFT JOIN traducao_trechos p ON p.traducao_id = t.id
         WHERE t.usuario_id = ? GROUP BY t.id
         HAVING feitos < total OR aprovados < total
         ORDER BY t.id DESC LIMIT 6'
    );
    $s->execute([$usuario['id']]);
    foreach ($s->fetchAll() as $t) {
        $rev   = $t['idioma'] === 'rev';
        $total = (int) $t['total'];
        if ((int) $t['feitos'] < $total) {
            $andamento[] = [$rev ? 'revisao' : 'traducao', $rev ? 'revisao.php' : 'traducao.php', $t['titulo'], ($rev ? 'Revisão' : 'Tradução') . ' incompleta: ' . (int) $t['feitos'] . ' de ' . $total . ' trechos'];
        } else {
            $andamento[] = [$rev ? 'revisao' : 'traducao', 'traducao_revisar.php?id=' . (int) $t['id'], $t['titulo'], 'Para aprovar: ' . ($total - (int) $t['aprovados']) . ' de ' . $total . ' trechos'];
        }
    }
} catch (Throwable $e) {
    $andamento = [];
}

layout_inicio('Início', 'index', $usuario);
?>
        <p class="texto-2">Olá, <?= e($usuario['nome']) ?>. Escolha uma função para começar.</p>

<?php if ($andamento): ?>
        <section class="indice-grupo indice-destaque">
            <h2 class="indice-titulo">Continue de onde parou</h2>
            <ul class="indice">
<?php foreach ($andamento as [$chave, $link, $nome, $situacao]): [, , $ico] = MENU[$chave]; ?>
                <li>
                    <a class="indice-item" href="<?= e($link) ?>">
                        <?= icone($ico) ?>
                        <span class="indice-nome"><?= e($nome) ?></span>
                        <span class="indice-desc"><?= e($situacao) ?></span>
                    </a>
                </li>
<?php endforeach; ?>
            </ul>
        </section>
<?php endif; ?>

        <div class="indice-grade">
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
        </div>
<?php layout_fim(); ?>

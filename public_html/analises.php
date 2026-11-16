<?php
// Tela "Análises": conversa livre sobre as vendas (como um chat) ou relatório estruturado de analista.

require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/http.php';
require_once __DIR__ . '/../app/analise.php';

$usuario = exigir_login();
$editora = eh_editora($usuario);
$modo    = (($_GET['modo'] ?? 'conversa') === 'relatorio') ? 'relatorio' : 'conversa';

$tabelasOk  = true;
$resumo     = ['n' => 0, 'livros' => 0, 'canais' => 0, 'inicio' => null, 'fim' => null];
$relatorios = $conversas = $autores = $mensagens = [];
$conversa   = null;
$conversaId = (int) ($_GET['c'] ?? 0);

try {
    $pdo = db();

    $sql = 'SELECT COUNT(*) AS n, COUNT(DISTINCT livro) AS livros, COUNT(DISTINCT canal) AS canais, MIN(data_venda) AS inicio, MAX(data_venda) AS fim FROM vendas'
        . ($editora ? '' : ' WHERE autor_id = ?');
    $s = $pdo->prepare($sql);
    $s->execute($editora ? [] : [$usuario['id']]);
    $resumo = $s->fetch();

    $s = $pdo->prepare("SELECT id, titulo, criado_em FROM relatorios WHERE usuario_id = ? AND tipo = 'analise2' ORDER BY id DESC LIMIT 20");
    $s->execute([$usuario['id']]);
    $relatorios = $s->fetchAll();

    $s = $pdo->prepare('SELECT id, titulo, atualizado_em FROM analise_conversas WHERE usuario_id = ? ORDER BY atualizado_em DESC, id DESC LIMIT 20');
    $s->execute([$usuario['id']]);
    $conversas = $s->fetchAll();

    if ($editora) {
        $autores = $pdo->query('SELECT id, nome FROM usuarios ORDER BY nome')->fetchAll();
    }

    if ($conversaId > 0) {
        $s = $pdo->prepare('SELECT * FROM analise_conversas WHERE id = ? AND usuario_id = ?');
        $s->execute([$conversaId, $usuario['id']]);
        $conversa = $s->fetch() ?: null;
        if ($conversa) {
            $s = $pdo->prepare('SELECT papel, conteudo FROM analise_mensagens WHERE conversa_id = ? ORDER BY id');
            $s->execute([$conversaId]);
            $mensagens = $s->fetchAll();
        }
    }
} catch (Throwable $e) {
    error_log('analises.php: ' . $e->getMessage());
    $tabelasOk = false;
}

$temVendas = (int) $resumo['n'] > 0;
$mes = function (?string $d): string {
    return $d ? date('m/Y', strtotime($d)) : '';
};

$sugestoes = [
    'Qual foi o meu melhor mês e o que pode explicá-lo?',
    'Qual livro mais contribui para a receita e qual está perdendo força?',
    'Compare os meus canais de venda.',
    'Quanto devo receber nos próximos 3 meses, no ritmo atual?',
    'Algum mês fugiu do padrão?',
    'O que eu faria para aumentar a receita?',
];
if ($editora) {
    array_splice($sugestoes, 3, 0, ['Qual autor concentra mais receita?']);
}

layout_inicio('Análises', 'analises', $usuario);
?>
        <p class="texto-2">Entenda as suas vendas conversando com um analista ou gerando um relatório. Os números são calculados pelo sistema; a IA interpreta e recomenda.</p>

<?php if (!$tabelasOk): ?>
        <p class="alerta" role="alert">As tabelas ainda não existem no banco. Importe o arquivo <strong>database/schema.sql</strong> no phpMyAdmin e recarregue esta página.</p>
<?php else: ?>
        <div id="an-app" data-csrf="<?= e(csrf_token()) ?>" data-modo="<?= e($modo) ?>" data-editora="<?= $editora ? '1' : '0' ?>" data-conversa="<?= $conversa ? (int) $conversa['id'] : 0 ?>" data-ultimo-relatorio="<?= $relatorios ? (int) $relatorios[0]['id'] : 0 ?>" data-relatorio="<?= (int) ($_GET['r'] ?? 0) ?>">

            <nav class="an-abas" aria-label="Modo">
                <a href="analises.php?modo=conversa" class="<?= $modo === 'conversa' ? 'ativo' : '' ?>"<?= $modo === 'conversa' ? ' aria-current="page"' : '' ?>>Conversa</a>
                <a href="analises.php?modo=relatorio" class="<?= $modo === 'relatorio' ? 'ativo' : '' ?>"<?= $modo === 'relatorio' ? ' aria-current="page"' : '' ?>>Relatório</a>
            </nav>

<?php if (!$temVendas): ?>
            <div class="vazio an-sem-dados">
                <strong>Ainda não há vendas para analisar</strong>
                <?= $editora ? 'Importe um relatório de vendas na tela de Vendas e volte aqui.' : 'Quando a editora importar o relatório de vendas, você poderá analisar os seus números aqui.' ?>
            </div>
<?php elseif ($modo === 'conversa'): ?>
            <div class="an-grade an-grade-chat">
                <section class="an-painel an-chat" aria-label="Conversa">
                    <header class="an-chat-topo">
                        <div class="an-chat-titulo">
                            <strong id="an-chat-titulo"><?= $conversa ? e($conversa['titulo']) : 'Nova conversa' ?></strong>
                            <span class="meta">Responde com base nas vendas importadas</span>
                        </div>
                        <div class="linha-acoes">
                            <a class="btn btn-sec btn-sm" href="analises.php?modo=conversa">Nova conversa</a>
<?php if ($conversa): ?>
                            <button type="button" class="btn btn-sec btn-sm" id="an-excluir-conversa">Excluir conversa</button>
<?php endif; ?>
                        </div>
                    </header>

                    <div class="an-thread" id="an-thread" aria-live="polite">
<?php if (!$mensagens): ?>
                        <div class="an-vazio" id="an-vazio">
                            <strong>Pergunte o que quiser sobre as suas vendas</strong>
                            Eu leio os números importados e respondo com evidências. Escolha uma pergunta ao lado ou escreva a sua.
                        </div>
<?php endif; ?>
<?php foreach ($mensagens as $m): ?>
<?php if ($m['papel'] === 'user'): ?>
                        <div class="an-msg an-msg-user"><div class="an-msg-papel">Você</div><div class="an-msg-texto"><?= e($m['conteudo']) ?></div></div>
<?php else: ?>
                        <div class="an-msg an-msg-ia"><div class="an-msg-papel">Analista</div><div class="an-msg-texto" data-md><?= e($m['conteudo']) ?></div></div>
<?php endif; ?>
<?php endforeach; ?>
                    </div>

                    <p class="alerta" role="alert" id="an-chat-erro" hidden></p>
                    <form class="an-compositor" id="an-form">
                        <textarea id="an-pergunta" rows="2" maxlength="2000" placeholder="Pergunte sobre as suas vendas…" aria-label="Sua pergunta"></textarea>
                        <div class="an-compositor-rodape">
                            <span class="meta">Enter envia · Shift+Enter quebra a linha</span>
                            <button type="submit" class="btn btn-primary" id="an-enviar">Enviar pergunta</button>
                        </div>
                    </form>
                </section>

                <aside class="an-lateral">
                    <section class="bloco">
                        <h2 class="bloco-titulo">Dados disponíveis</h2>
                        <p class="an-dados"><?= number_format((int) $resumo['n'], 0, ',', '.') ?> registros de venda<br><?= $mes($resumo['inicio']) ?> a <?= $mes($resumo['fim']) ?> · <?= (int) $resumo['livros'] ?> livro(s) · <?= (int) $resumo['canais'] ?> canal(is)</p>
<?php if ($editora): ?>
                        <label class="an-rotulo" for="an-autor">Analisar</label>
                        <select id="an-autor"<?= $conversa ? ' disabled' : '' ?>>
                            <option value="0">Todos os autores</option>
<?php foreach ($autores as $a): ?>
                            <option value="<?= (int) $a['id'] ?>"<?= ($conversa && (int) $conversa['autor_filtro'] === (int) $a['id']) ? ' selected' : '' ?>><?= e($a['nome']) ?></option>
<?php endforeach; ?>
                        </select>
<?php endif; ?>
                    </section>

                    <section class="bloco">
                        <h2 class="bloco-titulo">Perguntas para começar</h2>
                        <div class="an-sugestoes">
<?php foreach ($sugestoes as $q): ?>
                            <button type="button" class="an-sugestao" data-pergunta="<?= e($q) ?>"><?= e($q) ?></button>
<?php endforeach; ?>
                        </div>
                    </section>

                    <section class="bloco" id="an-painel-conversas"<?= $conversas ? '' : ' hidden' ?>>
                        <h2 class="bloco-titulo">Conversas anteriores</h2>
                        <ul class="an-lista" id="an-lista-conversas">
<?php foreach ($conversas as $c): ?>
                            <li data-id="<?= (int) $c['id'] ?>"><a href="analises.php?modo=conversa&amp;c=<?= (int) $c['id'] ?>" class="<?= ($conversa && (int) $conversa['id'] === (int) $c['id']) ? 'ativo' : '' ?>"><span><?= e($c['titulo']) ?></span><small><?= e(date('d/m/Y', strtotime($c['atualizado_em']))) ?></small></a></li>
<?php endforeach; ?>
                        </ul>
                    </section>
                </aside>
            </div>

<?php else: ?>
            <div class="an-grade an-grade-rel">
                <aside class="an-lateral">
                    <section class="bloco">
                        <h2 class="bloco-titulo">Novo relatório</h2>
                        <div class="form">
                            <label for="an-foco">Foco da análise</label>
                            <select id="an-foco">
<?php foreach (AN_FOCOS as $k => $rot): ?>
                                <option value="<?= e($k) ?>"><?= e($rot) ?></option>
<?php endforeach; ?>
                            </select>
                            <label for="an-periodo">Período</label>
                            <select id="an-periodo">
<?php foreach (AN_PERIODOS as $k => $rot): ?>
                                <option value="<?= (int) $k ?>"><?= e($rot) ?></option>
<?php endforeach; ?>
                            </select>
<?php if ($editora): ?>
                            <label for="an-autor">Autor</label>
                            <select id="an-autor">
                                <option value="0">Todos os autores</option>
<?php foreach ($autores as $a): ?>
                                <option value="<?= (int) $a['id'] ?>"><?= e($a['nome']) ?></option>
<?php endforeach; ?>
                            </select>
<?php endif; ?>
                            <p class="alerta" role="alert" id="an-rel-erro" hidden></p>
                            <p class="texto-2" id="an-rel-estado" aria-live="polite"></p>
                            <div><button type="button" class="btn btn-primary" id="an-gerar">Gerar relatório</button></div>
                        </div>
                    </section>

                    <section class="bloco">
                        <h2 class="bloco-titulo">Relatórios anteriores</h2>
<?php if (!$relatorios): ?>
                        <p class="texto-2" id="an-sem-relatorios">Nenhum relatório ainda.</p>
<?php endif; ?>
                        <ul class="an-lista" id="an-lista-relatorios">
<?php foreach ($relatorios as $r): ?>
                            <li data-id="<?= (int) $r['id'] ?>"><a href="#" data-abrir="<?= (int) $r['id'] ?>"><span><?= e($r['titulo']) ?></span><small><?= e(date('d/m/Y H:i', strtotime($r['criado_em']))) ?></small></a></li>
<?php endforeach; ?>
                        </ul>
                    </section>
                </aside>

                <div class="an-relatorio" id="an-relatorio-view">
                    <div class="vazio" id="an-rel-vazio">
                        <strong>Nenhum relatório aberto</strong>
                        Escolha o foco e o período e clique em Gerar relatório. Os indicadores são calculados com os seus números; a IA interpreta e recomenda.
                    </div>
                </div>
            </div>
<?php endif; ?>
        </div>
        <script src="<?= asset_url('assets/js/analises.js') ?>"></script>
<?php endif; ?>
<?php layout_fim(); ?>

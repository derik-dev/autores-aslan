<?php
// Tela "Tradução": envia o livro, escolhe o idioma e acompanha a tradução trecho por trecho.

require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/traducoes.php';

$usuario = exigir_login();

$tabelasOk = true;
$itens     = [];
try {
    $itens = trad_listar($usuario['id'], false);
} catch (Throwable $e) {
    error_log('traducao.php: ' . $e->getMessage());
    $tabelasOk = false;
}

$rotulosStatus = ['concluido' => 'Traduzido', 'erro' => 'Erro', 'processando' => 'Traduzindo', 'pendente' => 'Incompleto'];
$classesStatus = ['concluido' => 'chip-ok', 'erro' => 'chip-erro', 'processando' => 'chip-neutro', 'pendente' => 'chip-neutro'];

function num(int $n): string
{
    return number_format($n, 0, ',', '.');
}

layout_inicio('Tradução', 'traducao', $usuario);
?>
        <p class="texto-2">Envie o livro, escolha o idioma e revise a tradução lado a lado com o original.</p>

<?php if (!$tabelasOk): ?>
        <p class="alerta" role="alert">As tabelas de tradução ainda não existem no banco. Importe o arquivo <strong>database/schema.sql</strong> no phpMyAdmin e recarregue esta página.</p>
<?php else: ?>
        <div id="trad-app" data-csrf="<?= e(csrf_token()) ?>">

            <section class="bloco">
                <h2 class="bloco-titulo">Nova tradução</h2>
                <div class="form">
                    <label for="titulo">Título do livro</label>
                    <input type="text" id="titulo" maxlength="200" placeholder="Ex.: A Casa do Mar" autocomplete="off">

                    <label for="idioma">Traduzir para</label>
                    <select id="idioma">
                        <?php foreach (TRAD_IDIOMAS as $cod => $nome): ?>
                            <option value="<?= e($cod) ?>"><?= e($nome) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label for="instrucoes">Ficha do livro, glossário e personagens (opcional)</label>
                    <textarea id="instrucoes" rows="6" maxlength="6000" placeholder="Gênero: thriller sobrenatural. Tom: tenso e seco. Diálogos: coloquial brasileiro.&#10;Glossário: The Stranger = O Desconhecido (sempre com maiúscula); Gabriel = manter.&#10;Gabriel: direto, sarcástico quando assustado, frases curtas. Nunca formal."></textarea>
                    <p class="texto-2">Quanto mais concreto, melhor. Os termos do glossário são obrigatórios em todo o livro.</p>

                    <div class="rotulo-linha">
                        <label for="texto">Texto do livro</label>
                        <label for="arquivo" class="btn btn-sec btn-sm" id="rotulo-arquivo">Enviar arquivo (.txt ou .docx)</label>
                        <input type="file" id="arquivo" accept=".txt,.docx" hidden>
                    </div>
                    <textarea id="texto" rows="10" placeholder="Cole aqui o texto do livro ou do capítulo…"></textarea>
                    <p id="resumo" class="texto-2">Nenhum texto ainda.</p>

                    <p id="erro-form" class="alerta" role="alert" hidden></p>
                    <div>
                        <button type="button" id="gerar" class="btn btn-primary">Traduzir livro</button>
                    </div>
                </div>
            </section>

            <section id="andamento" class="bloco" hidden aria-live="polite">
                <h2 class="bloco-titulo" id="and-titulo"></h2>
                <div class="barra-audio" aria-hidden="true"><i id="and-barra"></i></div>
                <p id="and-texto" class="texto-2"></p>
                <p id="and-erro" class="alerta" role="alert" hidden></p>
                <button type="button" id="and-tentar" class="btn btn-sec btn-sm" hidden>Continuar de onde parou</button>
            </section>

            <section class="bloco">
                <h2 class="bloco-titulo">Minhas traduções</h2>
<?php if (!$itens): ?>
                <div class="vazio">
                    <strong>Nenhuma tradução ainda</strong>
                    Cole o texto acima e clique em Traduzir livro. Depois você revisa trecho por trecho, lado a lado com o original.
                </div>
<?php else: ?>
                <ul class="lista-audios">
<?php foreach ($itens as $a):
    $total     = (int) $a['total'];
    $feitos    = (int) $a['feitos'];
    $aprovados = (int) $a['aprovados'];
    $st        = $a['status'];
    $idiomaNome = TRAD_IDIOMAS[$a['idioma']] ?? $a['idioma'];
?>
                    <li class="linha-audio" data-id="<?= (int) $a['id'] ?>" data-titulo="<?= e($a['titulo']) ?>">
                        <div class="linha-topo">
                            <div class="linha-info">
                                <strong><?= e($a['titulo']) ?></strong>
                                <span class="meta">Para <?= e($idiomaNome) ?> · <?= num((int) $a['total_chars']) ?> caracteres · <?= $feitos ?> de <?= $total ?> trechos traduzidos · <?= $aprovados ?> aprovados · <?= e(date('d/m/Y H:i', strtotime($a['criado_em']))) ?></span>
                            </div>
                            <span class="chip <?= e($classesStatus[$st] ?? 'chip-neutro') ?>"><?= e($rotulosStatus[$st] ?? $st) ?></span>
                        </div>
<?php if ($st === 'erro' && $a['erro']): ?>
                        <p class="status-erro"><?= e($a['erro']) ?></p>
<?php endif; ?>
                        <div class="linha-acoes">
<?php if ($st !== 'concluido'): ?>
                            <button type="button" class="btn btn-sec btn-sm" data-acao="continuar">Continuar traduzindo</button>
<?php endif; ?>
<?php if ($feitos > 0): ?>
                            <a class="btn btn-sec btn-sm" href="traducao_revisar.php?id=<?= (int) $a['id'] ?>">Revisar tradução</a>
                            <a class="btn btn-sec btn-sm" href="traducao_baixar.php?id=<?= (int) $a['id'] ?>">Baixar .txt</a>
<?php endif; ?>
                            <button type="button" class="btn btn-sec btn-sm" data-acao="excluir">Excluir</button>
                        </div>
                    </li>
<?php endforeach; ?>
                </ul>
<?php endif; ?>
            </section>
        </div>
        <script src="<?= asset_url('assets/js/traducao.js') ?>"></script>
<?php endif; ?>
<?php layout_fim(); ?>

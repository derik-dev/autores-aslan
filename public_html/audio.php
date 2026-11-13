<?php
// Tela "Texto em áudio": cola ou envia um texto (até um livro inteiro) e gera o áudio com o ElevenLabs.

require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/elevenlabs.php';
require_once __DIR__ . '/../app/audios.php';

$usuario = exigir_login();

$tabelasOk = true;
$audios    = [];
try {
    $audios = audio_listar($usuario['id']);
} catch (Throwable $e) {
    error_log('audio.php: ' . $e->getMessage());
    $tabelasOk = false;
}

$rotulosStatus = ['concluido' => 'Pronto', 'erro' => 'Erro', 'processando' => 'Gerando', 'pendente' => 'Incompleto'];
$classesStatus = ['concluido' => 'chip-ok', 'erro' => 'chip-erro', 'processando' => 'chip-neutro', 'pendente' => 'chip-neutro'];

function num(int $n): string
{
    return number_format($n, 0, ',', '.');
}

layout_inicio('Texto em áudio', 'audio', $usuario);
?>
        <p class="texto-2">Cole um texto ou envie um arquivo e gere o áudio. Funciona com textos longos, até um livro inteiro.</p>

<?php if (!$tabelasOk): ?>
        <p class="alerta" role="alert">As tabelas de áudio ainda não existem no banco. Importe o arquivo <strong>database/schema.sql</strong> no phpMyAdmin e recarregue esta página.</p>
<?php else: ?>
        <div id="audio-app" data-csrf="<?= e(csrf_token()) ?>">

            <section class="bloco">
                <h2 class="bloco-titulo">Novo áudio</h2>
                <div class="form">
                    <label for="titulo">Título</label>
                    <input type="text" id="titulo" maxlength="200" placeholder="Ex.: Capítulo 1 – A chegada" autocomplete="off">

                    <div class="duas-colunas">
                        <div class="coluna">
                            <label for="voz">Voz</label>
                            <select id="voz" disabled>
                                <option value="">Carregando vozes…</option>
                            </select>
                            <audio id="amostra" controls preload="none" hidden></audio>
                        </div>
                        <div class="coluna">
                            <label for="modelo">Qualidade</label>
                            <select id="modelo">
                                <?php foreach (EL_MODELOS as $chave => $m): ?>
                                    <option value="<?= e($chave) ?>" data-custo="<?= e((string) $m['custo']) ?>"><?= e($m['rotulo']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <p id="creditos" class="texto-2" hidden></p>
                    <p id="aviso-api" class="alerta" role="alert" hidden></p>

                    <div class="rotulo-linha">
                        <label for="texto">Texto</label>
                        <label for="arquivo" class="btn btn-sec btn-sm" id="rotulo-arquivo">Enviar arquivo (.txt ou .docx)</label>
                        <input type="file" id="arquivo" accept=".txt,.docx" hidden>
                    </div>
                    <textarea id="texto" rows="12" placeholder="Cole aqui o texto do livro ou do capítulo…"></textarea>
                    <p id="resumo" class="texto-2">Nenhum texto ainda.</p>

                    <p id="erro-form" class="alerta" role="alert" hidden></p>
                    <div>
                        <button type="button" id="gerar" class="btn btn-primary">Gerar áudio</button>
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
                <h2 class="bloco-titulo">Meus áudios</h2>
<?php if (!$audios): ?>
                <div class="vazio">
                    <strong>Nenhum áudio ainda</strong>
                    Cole um texto acima e clique em Gerar áudio. Os áudios prontos aparecem aqui para ouvir e baixar.
                </div>
<?php else: ?>
                <ul class="lista-audios">
<?php foreach ($audios as $a):
    $total  = (int) $a['total'];
    $feitos = (int) $a['feitos'];
    $st     = $a['status'];
    $modeloRotulo = $a['modelo'] === 'economico' ? 'Econômico' : 'Qualidade';
?>
                    <li class="linha-audio" data-id="<?= (int) $a['id'] ?>" data-titulo="<?= e($a['titulo']) ?>">
                        <div class="linha-topo">
                            <div class="linha-info">
                                <strong><?= e($a['titulo']) ?></strong>
                                <span class="meta"><?= num((int) $a['total_chars']) ?> caracteres · <?= $feitos ?> de <?= $total ?> trechos · <?= e($modeloRotulo) ?> · <?= e(date('d/m/Y H:i', strtotime($a['criado_em']))) ?></span>
                            </div>
                            <span class="chip <?= e($classesStatus[$st] ?? 'chip-neutro') ?>"><?= e($rotulosStatus[$st] ?? $st) ?></span>
                        </div>
<?php if ($st === 'erro' && $a['erro']): ?>
                        <p class="status-erro"><?= e($a['erro']) ?></p>
<?php endif; ?>
                        <div class="linha-acoes">
<?php if ($st !== 'concluido'): ?>
                            <button type="button" class="btn btn-sec btn-sm" data-acao="continuar">Continuar gerando</button>
<?php endif; ?>
<?php if ($feitos > 0): ?>
                            <a class="btn btn-sec btn-sm" href="audio_baixar.php?id=<?= (int) $a['id'] ?>&amp;baixar=1">Baixar MP3</a>
<?php endif; ?>
                            <button type="button" class="btn btn-sec btn-sm" data-acao="excluir">Excluir</button>
                        </div>
<?php if ($feitos > 0): ?>
                        <audio controls preload="none" src="audio_baixar.php?id=<?= (int) $a['id'] ?>"></audio>
<?php endif; ?>
                    </li>
<?php endforeach; ?>
                </ul>
<?php endif; ?>
            </section>
        </div>
        <script src="<?= asset_url('assets/js/audio.js') ?>"></script>
<?php endif; ?>
<?php layout_fim(); ?>

<?php
// Entregas: guarda arquivos (e-book, PDF, audiolivro) e gera links de download com validade e limite.

require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/entregas.php';

$usuario = exigir_login();

$tabelasOk = true;
$entregas  = [];
$audios    = [];
try {
    $entregas = ent_listar($usuario['id']);
    $s = db()->prepare("SELECT id, titulo FROM audios WHERE usuario_id = ? AND status = 'concluido' ORDER BY id DESC");
    $s->execute([$usuario['id']]);
    $audios = $s->fetchAll();
} catch (Throwable $e) {
    error_log('entregas.php: ' . $e->getMessage());
    $tabelasOk = false;
}

layout_inicio('Entregas', 'entregas', $usuario);
?>
        <p class="texto-2">Guarde o arquivo do livro e envie um link seguro para cada leitor, com validade e limite de downloads.</p>

<?php if (!$tabelasOk): ?>
        <p class="alerta" role="alert">As tabelas ainda não existem no banco. Importe o arquivo <strong>database/schema.sql</strong> no phpMyAdmin e recarregue esta página.</p>
<?php else: ?>
        <div id="ent-app" data-csrf="<?= e(csrf_token()) ?>">
            <section class="bloco">
                <h2 class="bloco-titulo">Nova entrega</h2>
                <div class="form">
                    <label for="e-titulo">Título</label>
                    <input type="text" id="e-titulo" maxlength="200" placeholder="Ex.: A Casa do Mar (EPUB)" autocomplete="off">

                    <label for="e-arquivo">Arquivo (PDF, EPUB, MOBI, MP3, ZIP…)</label>
                    <input type="file" id="e-arquivo" accept=".pdf,.epub,.mobi,.azw3,.mp3,.m4a,.zip,.docx,.txt">

<?php if ($audios): ?>
                    <label for="e-audio">Ou use um áudio que você já gerou</label>
                    <select id="e-audio">
                        <option value="0">Nenhum (vou enviar um arquivo)</option>
<?php foreach ($audios as $a): ?>
                        <option value="<?= (int) $a['id'] ?>"><?= e($a['titulo']) ?></option>
<?php endforeach; ?>
                    </select>
<?php endif; ?>

                    <p id="e-erro" class="alerta" role="alert" hidden></p>
                    <p id="e-estado" class="texto-2" aria-live="polite"></p>
                    <div>
                        <button type="button" id="e-salvar" class="btn btn-primary">Guardar entrega</button>
                    </div>
                </div>
            </section>

            <section class="bloco">
                <h2 class="bloco-titulo">Minhas entregas</h2>
<?php if (!$entregas): ?>
                <div class="vazio">
                    <strong>Nenhuma entrega ainda</strong>
                    Guarde um arquivo acima. Depois você cria um link para cada leitor.
                </div>
<?php else: ?>
                <ul class="lista-audios">
<?php foreach ($entregas as $en): ?>
                    <li class="linha-audio" data-id="<?= (int) $en['id'] ?>" data-titulo="<?= e($en['titulo']) ?>">
                        <div class="linha-topo">
                            <div class="linha-info">
                                <strong><?= e($en['titulo']) ?></strong>
                                <span class="meta"><?= e($en['nome_original'] ?: '') ?> · <?= e(ent_tamanho_legivel((int) $en['tamanho'])) ?><?= $en['audio_id'] ? ' · áudio gerado' : '' ?> · <?= e(date('d/m/Y', strtotime($en['criado_em']))) ?></span>
                            </div>
                            <button type="button" class="btn btn-sec btn-sm" data-acao="excluir-ent">Excluir</button>
                        </div>

<?php if ($en['links']): ?>
                        <ul class="lista-links">
<?php foreach ($en['links'] as $l):
    [$valido, $motivo] = ent_estado_link($l);
?>
                            <li data-link="<?= (int) $l['id'] ?>">
                                <span><strong><?= e($l['destinatario']) ?></strong> · <?= (int) $l['baixados'] ?> de <?= (int) $l['limite'] ?> downloads<?= $l['expira_em'] ? ' · até ' . e(date('d/m/Y', strtotime($l['expira_em']))) : '' ?></span>
                                <span class="chip <?= $valido ? 'chip-ok' : 'chip-neutro' ?>"><?= e($motivo) ?></span>
<?php if ($valido): ?>
                                <button type="button" class="btn btn-sec btn-sm" data-acao="copiar" data-url="<?= e(ent_link_publico($l['token'])) ?>">Copiar link</button>
                                <button type="button" class="btn btn-sec btn-sm" data-acao="cancelar-link">Cancelar link</button>
<?php endif; ?>
                            </li>
<?php endforeach; ?>
                        </ul>
<?php endif; ?>

                        <div class="novo-link">
                            <input type="text" class="l-dest" maxlength="190" placeholder="Para quem? (nome ou e-mail)" autocomplete="off">
                            <label class="l-rotulo">Validade (dias) <input type="number" class="l-dias" min="1" max="365" value="7"></label>
                            <label class="l-rotulo">Downloads <input type="number" class="l-limite" min="1" max="100" value="3"></label>
                            <button type="button" class="btn btn-sec btn-sm" data-acao="criar-link">Criar link</button>
                        </div>
                        <p class="texto-2 l-resultado" aria-live="polite"></p>
                    </li>
<?php endforeach; ?>
                </ul>
<?php endif; ?>
            </section>
        </div>
        <script src="<?= asset_url('assets/js/entregas.js') ?>"></script>
<?php endif; ?>
<?php layout_fim(); ?>

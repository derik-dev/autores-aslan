<?php
// Tela de revisão de textos (em construção).

require_once __DIR__ . '/../app/layout.php';

$usuario = exigir_login();
layout_inicio('Revisão de textos', 'revisao', $usuario);
?>
        <div class="vazio">
            <strong>Nenhum texto para revisar</strong>
            Esta função está em construção. Volte ao <a href="index.php">início</a> para usar as outras.
        </div>
<?php layout_fim(); ?>

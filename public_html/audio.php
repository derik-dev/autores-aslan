<?php
// Tela de conversão de texto em áudio (em construção).

require_once __DIR__ . '/../app/layout.php';

$usuario = exigir_login();
layout_inicio('Texto em áudio', 'audio', $usuario);
?>
        <div class="vazio">
            <strong>Nenhum áudio ainda</strong>
            Esta função está em construção. Volte ao <a href="index.php">início</a> para usar as outras.
        </div>
<?php layout_fim(); ?>

<?php
// Tela de conversão de texto em áudio (em construção).

require_once __DIR__ . '/../app/layout.php';

$usuario = exigir_login();
layout_inicio('Texto em áudio', 'audio', $usuario);
?>
        <p class="texto-2">Esta função ainda está em construção.</p>
<?php layout_fim(); ?>

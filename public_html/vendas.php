<?php
// Tela de acompanhamento de vendas (em construção).

require_once __DIR__ . '/../app/layout.php';

$usuario = exigir_login();
layout_inicio('Vendas', 'vendas', $usuario);
?>
        <p class="texto-2">Esta função ainda está em construção.</p>
<?php layout_fim(); ?>

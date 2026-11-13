<?php
// Tela de acompanhamento de vendas (em construção).

require_once __DIR__ . '/../app/layout.php';

$usuario = exigir_login();
layout_inicio('Vendas', 'vendas', $usuario);
?>
        <div class="vazio">
            <strong>Nenhuma venda para mostrar</strong>
            Esta função está em construção. Volte ao <a href="index.php">início</a> para usar as outras.
        </div>
<?php layout_fim(); ?>

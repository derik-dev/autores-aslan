<?php
// Página pública de inscrição de leitores para receber um exemplar antecipado (sem login).

require_once __DIR__ . '/../app/db.php';

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function pagina(string $titulo, string $corpo): void
{
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= h($titulo) ?></title>
    <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<main class="publico">
    <?= $corpo ?>
</main>
</body>
</html>
<?php
}

$token = (string) ($_GET['c'] ?? $_POST['c'] ?? '');
$arc   = null;
if (preg_match('/^[a-f0-9]{24}$/', $token)) {
    try {
        $s = db()->prepare('SELECT * FROM arcs WHERE token = ?');
        $s->execute([$token]);
        $arc = $s->fetch() ?: null;
    } catch (Throwable $e) {
        error_log('exemplar.php: ' . $e->getMessage());
    }
}

if (!$arc) {
    http_response_code(404);
    pagina('Convite não encontrado', '<h1 class="page-title">Convite não encontrado</h1><p class="texto-2">Confira se você copiou o endereço completo.</p>');
    exit;
}

$usados = (int) db()->query('SELECT COUNT(*) FROM arc_leitores WHERE arc_id = ' . (int) $arc['id'] . " AND status <> 'recusado'")->fetchColumn();
$cheio  = $usados >= (int) $arc['vagas'];
$aberto = (int) $arc['aberto'] === 1 && !$cheio;

$mensagem = '';
$erro     = '';
$enviado  = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $aberto) {
    $nome  = trim((string) ($_POST['nome'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $onde  = trim((string) ($_POST['onde'] ?? ''));

    if (!empty($_POST['site'])) {
        // Campo escondido preenchido: é um robô. Finge que deu certo.
        $enviado = true;
    } elseif ($nome === '' || mb_strlen($nome, 'UTF-8') > 120) {
        $erro = 'Escreva o seu nome.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
        $erro = 'Escreva um e-mail válido.';
    } else {
        try {
            db()->prepare('INSERT INTO arc_leitores (arc_id, nome, email, onde) VALUES (?, ?, ?, ?)')
                ->execute([$arc['id'], $nome, $email, mb_substr($onde, 0, 300, 'UTF-8') ?: null]);
            $enviado = true;
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $erro = 'Este e-mail já está inscrito. Aguarde o contato da editora.';
            } else {
                error_log('exemplar.php: ' . $e->getMessage());
                $erro = 'Não consegui registrar agora. Tente de novo em instantes.';
            }
        }
    }
}

$titulo = '<h1 class="page-title">' . h($arc['titulo']) . '</h1>';
$descr  = $arc['descricao'] ? '<p class="texto-2" style="white-space: pre-wrap">' . h($arc['descricao']) . '</p>' : '';

if ($enviado) {
    pagina($arc['titulo'], $titulo . '<p><strong>Inscrição recebida!</strong></p><p class="texto-2">Se você for escolhido, vai receber um e-mail da editora com o link para ler o livro.</p>');
    exit;
}
if (!$aberto) {
    pagina($arc['titulo'], $titulo . $descr . '<p class="alerta">As inscrições para este livro estão encerradas.</p>');
    exit;
}

$form = '<form method="post" class="form">'
    . '<input type="hidden" name="c" value="' . h($token) . '">'
    . '<label for="nome">Seu nome</label><input type="text" id="nome" name="nome" maxlength="120" required value="' . h((string) ($_POST['nome'] ?? '')) . '">'
    . '<label for="email">Seu e-mail</label><input type="email" id="email" name="email" maxlength="190" required value="' . h((string) ($_POST['email'] ?? '')) . '">'
    . '<label for="onde">Onde você pretende divulgar a sua opinião? (blog, Instagram, loja…)</label><input type="text" id="onde" name="onde" maxlength="300" value="' . h((string) ($_POST['onde'] ?? '')) . '">'
    . '<div style="position:absolute;left:-9999px" aria-hidden="true"><label for="site">Não preencha</label><input type="text" id="site" name="site" tabindex="-1" autocomplete="off"></div>'
    . ($erro !== '' ? '<p class="alerta" role="alert">' . h($erro) . '</p>' : '')
    . '<div><button type="submit" class="btn btn-primary">Quero ler este livro</button></div>'
    . '</form>';

pagina($arc['titulo'], $titulo . $descr . '<p class="texto-2">Vagas limitadas. A editora escolhe os leitores e envia o link por e-mail.</p>' . $form);

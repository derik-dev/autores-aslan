<?php
// Encerra a sessão do usuário (somente via POST com token CSRF).

require_once __DIR__ . '/../app/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valido($_POST['csrf'] ?? null)) {
    logout();
}
header('Location: login.php');
exit;

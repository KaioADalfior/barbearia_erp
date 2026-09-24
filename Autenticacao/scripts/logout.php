<?php
// logout.php
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/DiscordLogger.php';

if (isset($_SESSION['nome'])) {
    DiscordLogger::sessoes('🚪 Logout', [
        ['name' => '👤 Usuário', 'value' => $_SESSION['nome'] . ' (' . ($_SESSION['tipo'] ?? '—') . ')', 'inline' => true],
    ]);
}

session_unset();

// Além de destruir os dados no servidor, expira o cookie no navegador —
// evita que o mesmo cookie antigo continue sendo enviado à toa depois do
// logout (defesa extra contra Session Hijacking/reuso de cookie).
if (ini_get('session.use_cookies')) {
    $parametros = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $parametros['path'],
        $parametros['domain'],
        $parametros['secure'],
        $parametros['httponly']
    );
}

session_destroy();
header('Location: ../paginas/login.php');
exit;
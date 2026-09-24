<?php
// login_desbloquear.php
// Remove o(s) registro(s) de tentativas de login erradas, liberando o
// acesso imediatamente sem precisar esperar a janela de bloqueio passar.
// Só mexe na tabela de controle (LoginTentativas) — nunca toca em senha,
// conta ou qualquer outro dado do barbeiro/admin.
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/guard.php';
exigirSessao(['admin']);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /login-tentativas');
    exit;
}

csrf_verificar(json: false, redirecionarPara: '/login-tentativas');

$acao = $_POST['acao'] ?? '';

if ($acao === 'desbloquear-todos') {
    $qtd = (int) $pdo->query('SELECT COUNT(*) FROM LoginTentativas WHERE sucesso = 0')->fetchColumn();
    $pdo->exec('DELETE FROM LoginTentativas WHERE sucesso = 0');

    DiscordLogger::admin('🔓 Todos os bloqueios de login removidos', [
        ['name' => '🔢 Registros removidos', 'value' => (string) $qtd, 'inline' => true],
        ['name' => '👑 Desbloqueado por', 'value' => $_SESSION['nome'] ?? ('#' . ($_SESSION['id'] ?? '—')), 'inline' => true],
    ]);

    header('Location: /login-tentativas?status=desbloqueado-todos');
    exit;
}

if ($acao === 'desbloquear-um') {
    $chave = trim($_POST['chave'] ?? '');

    if ($chave !== '') {
        $stmt = $pdo->prepare('DELETE FROM LoginTentativas WHERE chave = :chave');
        $stmt->execute(['chave' => $chave]);

        DiscordLogger::admin('🔓 Login desbloqueado manualmente', [
            ['name' => '🔑 IP + login', 'value' => $chave, 'inline' => false],
            ['name' => '👑 Desbloqueado por', 'value' => $_SESSION['nome'] ?? ('#' . ($_SESSION['id'] ?? '—')), 'inline' => true],
        ]);
    }

    header('Location: /login-tentativas?status=desbloqueado-sucesso');
    exit;
}

header('Location: /login-tentativas');
exit;

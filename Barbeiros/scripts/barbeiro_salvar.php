<?php
// barbeiro_salvar.php
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/guard.php';
exigirSessao(['admin']);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../paginas/barbeiro_cadastrar.php');
    exit;
}

csrf_verificar(json: false, redirecionarPara: '../paginas/barbeiro_cadastrar.php');

$nome     = trim($_POST['nome'] ?? '');
$login    = trim($_POST['login'] ?? '');
$telefone = trim($_POST['telefone'] ?? '');
$senha    = trim($_POST['senha'] ?? '');

if ($nome === '' || $login === '' || $telefone === '' || $senha === '' || strlen($senha) < 6) {
    header('Location: ../paginas/barbeiro_cadastrar.php?status=erro');
    exit;
}

// Verifica se já existe um barbeiro com esse login
$stmt = $pdo->prepare('SELECT id_barbeiro FROM Barbeiro WHERE login = :login LIMIT 1');
$stmt->execute(['login' => $login]);

if ($stmt->fetch()) {
    header('Location: ../paginas/barbeiro_cadastrar.php?status=login-existe');
    exit;
}

// Senha é salva com hash (recomendado). auth.php já está preparado
// para validar tanto hashes quanto senhas antigas em texto puro.
$senhaHash = password_hash($senha, PASSWORD_DEFAULT);

$stmt = $pdo->prepare(
    'INSERT INTO Barbeiro (nome, login, senha, telefone) VALUES (:nome, :login, :senha, :telefone)'
);
$stmt->execute([
    'nome'     => $nome,
    'login'    => $login,
    'senha'    => $senhaHash,
    'telefone' => $telefone,
]);

DiscordLogger::admin('✂️ Novo barbeiro cadastrado', [
    ['name' => '👤 Nome', 'value' => $nome, 'inline' => true],
    ['name' => '🔑 Login', 'value' => $login, 'inline' => true],
    ['name' => '📞 Telefone', 'value' => $telefone, 'inline' => true],
    ['name' => '👑 Cadastrado por', 'value' => $_SESSION['nome'] ?? ('#' . ($_SESSION['id'] ?? '—')), 'inline' => false],
]);

header('Location: ../paginas/barbeiro_cadastrar.php?status=sucesso');
exit;
<?php
// barbeiro_atualizar.php
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/guard.php';
exigirSessao(['admin']);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../paginas/barbeiro_listar.php');
    exit;
}

csrf_verificar(json: false, redirecionarPara: '../paginas/barbeiro_listar.php');

$id       = (int) ($_POST['id'] ?? 0);
$nome     = trim($_POST['nome'] ?? '');
$login    = trim($_POST['login'] ?? '');
$telefone = trim($_POST['telefone'] ?? '');
$novaSenha = trim($_POST['senha'] ?? ''); // vazio = não altera a senha

// Query string usada para reabrir o modal com os dados preenchidos caso
// a validação falhe — igual ao padrão já usado em Servicos/Clientes.
$paramsVolta = http_build_query([
    'edit_id'  => $id,
    'nome'     => $nome,
    'login'    => $login,
    'telefone' => $telefone,
]);

if ($id <= 0 || $nome === '' || $login === '' || $telefone === '' || ($novaSenha !== '' && strlen($novaSenha) < 6)) {
    header('Location: ../paginas/barbeiro_listar.php?status=edicao-erro&' . $paramsVolta);
    exit;
}

// Confirma que o barbeiro existe
$stmt = $pdo->prepare('SELECT id_barbeiro FROM Barbeiro WHERE id_barbeiro = :id LIMIT 1');
$stmt->execute(['id' => $id]);
if (!$stmt->fetch()) {
    header('Location: ../paginas/barbeiro_listar.php?status=nao-encontrado');
    exit;
}

// Login precisa continuar único, mas ignorando o próprio registro
$stmt = $pdo->prepare('SELECT id_barbeiro FROM Barbeiro WHERE login = :login AND id_barbeiro != :id LIMIT 1');
$stmt->execute(['login' => $login, 'id' => $id]);
if ($stmt->fetch()) {
    header('Location: ../paginas/barbeiro_listar.php?status=login-existe&' . $paramsVolta);
    exit;
}

if ($novaSenha !== '') {
    $senhaHash = password_hash($novaSenha, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare(
        'UPDATE Barbeiro SET nome = :nome, login = :login, telefone = :telefone, senha = :senha WHERE id_barbeiro = :id'
    );
    $stmt->execute([
        'nome'     => $nome,
        'login'    => $login,
        'telefone' => $telefone,
        'senha'    => $senhaHash,
        'id'       => $id,
    ]);
} else {
    $stmt = $pdo->prepare(
        'UPDATE Barbeiro SET nome = :nome, login = :login, telefone = :telefone WHERE id_barbeiro = :id'
    );
    $stmt->execute([
        'nome'     => $nome,
        'login'    => $login,
        'telefone' => $telefone,
        'id'       => $id,
    ]);
}

// Se a senha foi trocada pelo admin, o barbeiro provavelmente está com uma
// sessão aberta em outro dispositivo com a senha antiga — não dá pra
// invalidar sessões individuais nesse sistema (não guarda um id de sessão
// por usuário), então isso é só um efeito colateral esperado: ele precisa
// logar de novo com a senha nova da próxima vez.

DiscordLogger::admin('✏️ Dados de barbeiro atualizados', [
    ['name' => '👤 Nome', 'value' => $nome, 'inline' => true],
    ['name' => '🔑 Login', 'value' => $login, 'inline' => true],
    ['name' => '🔒 Senha alterada?', 'value' => $novaSenha !== '' ? 'Sim' : 'Não', 'inline' => true],
    ['name' => '👑 Editado por', 'value' => $_SESSION['nome'] ?? ('#' . ($_SESSION['id'] ?? '—')), 'inline' => false],
]);

header('Location: ../paginas/barbeiro_listar.php?status=edicao-sucesso');
exit;

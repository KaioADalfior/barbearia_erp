<?php
// perfil_atualizar.php
// Atualiza nome completo e usuário (login) do barbeiro logado.
// A troca de senha tem endpoint próprio (Configuracoes/scripts/senha_atualizar.php,
// reaproveitado via o campo "voltar") e a foto tem o seu (perfil_foto_atualizar.php).

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/guard.php';
exigirSessao(['barbeiro']);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../paginas/perfil.php');
    exit;
}

csrf_verificar(json: false, redirecionarPara: '../paginas/perfil.php');

function voltarComErro(string $msg): void
{
    header('Location: ../paginas/perfil.php?perfil_erro=' . urlencode($msg));
    exit;
}

$idBarbeiro = (int) $_SESSION['id'];
$nome       = trim($_POST['nome'] ?? '');
$login      = trim($_POST['login'] ?? '');

if ($nome === '' || $login === '') {
    voltarComErro('Preencha o nome completo e o usuário.');
}

if (mb_strlen($nome) > 100) {
    voltarComErro('O nome completo é muito longo.');
}

if (!preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $login)) {
    voltarComErro('O usuário deve ter entre 3 e 50 caracteres (letras, números, ponto, traço ou underline).');
}

// Usuário precisa continuar único entre os barbeiros
$stmt = $pdo->prepare('SELECT id_barbeiro FROM Barbeiro WHERE login = :login AND id_barbeiro <> :id LIMIT 1');
$stmt->execute(['login' => $login, 'id' => $idBarbeiro]);

if ($stmt->fetch()) {
    voltarComErro('Esse usuário já está em uso. Escolha outro.');
}

$stmt = $pdo->prepare('UPDATE Barbeiro SET nome = :nome, login = :login WHERE id_barbeiro = :id');
$stmt->execute(['nome' => $nome, 'login' => $login, 'id' => $idBarbeiro]);

// Mantém a sessão em dia com os novos dados
$_SESSION['nome']  = $nome;
$_SESSION['login'] = $login;

DiscordLogger::configuracoes('👤 Perfil atualizado', [
    ['name' => '🆔 Barbeiro', 'value' => '#' . $idBarbeiro, 'inline' => true],
    ['name' => '👤 Nome',     'value' => $nome, 'inline' => true],
    ['name' => '🔑 Usuário',  'value' => $login, 'inline' => true],
]);

header('Location: ../paginas/perfil.php?perfil_sucesso=1');
exit;
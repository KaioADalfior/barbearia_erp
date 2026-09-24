<?php
// cliente_salvar.php
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/guard.php';
exigirSessao(['barbeiro']);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /clientes');
    exit;
}

csrf_verificar(json: false, redirecionarPara: '/clientes');

$nome     = trim($_POST['nome'] ?? '');
$telefone = trim($_POST['telefone'] ?? '');
$email    = trim($_POST['email'] ?? '');
$ativo    = ($_POST['ativo'] ?? '1') === '0' ? 0 : 1;

// Query string usada para reabrir o modal de cadastro com os dados
// preenchidos, caso a validação falhe.
$paramsVolta = http_build_query([
    'nome'     => $nome,
    'telefone' => $telefone,
    'email'    => $email,
    'ativo'    => $ativo,
]);

if ($nome === '' || $telefone === '') {
    header('Location: /clientes?status=cadastro-erro&' . $paramsVolta);
    exit;
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: /clientes?status=cadastro-email-invalido&' . $paramsVolta);
    exit;
}

$stmt = $pdo->prepare(
    'INSERT INTO Cliente (nome, telefone, email, ativo) VALUES (:nome, :telefone, :email, :ativo)'
);
$stmt->execute([
    'nome'     => $nome,
    'telefone' => $telefone,
    'email'    => $email !== '' ? $email : null,
    'ativo'    => $ativo,
]);

DiscordLogger::clientes('🆕 Cliente cadastrado', [
    ['name' => '🆔 ID', 'value' => '#' . $pdo->lastInsertId(), 'inline' => true],
    ['name' => '👤 Nome', 'value' => $nome, 'inline' => true],
    ['name' => '📞 Telefone', 'value' => $telefone, 'inline' => true],
    ['name' => '📧 E-mail', 'value' => $email ?: 'Não informado', 'inline' => true],
]);

header('Location: /clientes?status=cadastro-sucesso&nome=' . urlencode($nome));
exit;
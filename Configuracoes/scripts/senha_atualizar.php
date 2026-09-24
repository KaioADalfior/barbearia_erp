<?php
// scripts/senha_atualizar.php
// Processa a troca de senha do usuário logado (Administrador ou Barbeiro)

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/csrf.php';

$tipo = $_SESSION['tipo'] ?? null;
if ($tipo !== 'admin' && $tipo !== 'barbeiro') {
    header('Location: ../../Autenticacao/paginas/login.php');
    exit;
}

// Página de origem: por padrão volta para Configurações, mas o mesmo
// formulário/endpoint também é usado pela tela Barbeiro > Perfil.
$destinos = [
    'configuracoes' => '/configuracoes',
    'perfil'        => '../../Perfil/paginas/perfil.php',
];
$voltarPara = $_POST['voltar'] ?? $_GET['voltar'] ?? 'configuracoes';
$urlVolta   = $destinos[$voltarPara] ?? $destinos['configuracoes'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $urlVolta);
    exit;
}

csrf_verificar(json: false, redirecionarPara: $urlVolta);

$senhaAtual   = trim($_POST['senha_atual'] ?? '');
$senhaNova    = trim($_POST['senha_nova'] ?? '');
$senhaConfirma = trim($_POST['senha_confirma'] ?? '');

function voltarComErro(string $msg): void {
    global $urlVolta;
    header('Location: ' . $urlVolta . '?senha_erro=' . urlencode($msg));
    exit;
}

if ($senhaAtual === '' || $senhaNova === '' || $senhaConfirma === '') {
    voltarComErro('Preencha todos os campos de senha.');
}

if ($senhaNova !== $senhaConfirma) {
    voltarComErro('A nova senha e a confirmação não coincidem.');
}

if (strlen($senhaNova) < 6) {
    voltarComErro('A nova senha deve ter pelo menos 6 caracteres.');
}

// Define tabela e coluna de id conforme o tipo de usuário logado
if ($tipo === 'admin') {
    $tabela  = 'Administrador';
    $colId   = 'id_Admin';
} else {
    $tabela  = 'Barbeiro';
    $colId   = 'id_barbeiro';
}

$id = $_SESSION['id'];

$stmt = $pdo->prepare("SELECT senha FROM {$tabela} WHERE {$colId} = :id LIMIT 1");
$stmt->execute(['id' => $id]);
$usuario = $stmt->fetch();

if (!$usuario) {
    voltarComErro('Usuário não encontrado.');
}

// Aceita hash (password_verify) ou texto puro (compatibilidade com dados de teste)
$senhaAtualOk = password_verify($senhaAtual, $usuario['senha']) || hash_equals((string) $usuario['senha'], $senhaAtual);

if (!$senhaAtualOk) {
    voltarComErro('A senha atual informada está incorreta.');
}

$novoHash = password_hash($senhaNova, PASSWORD_DEFAULT);

$update = $pdo->prepare("UPDATE {$tabela} SET senha = :senha WHERE {$colId} = :id");
$update->execute(['senha' => $novoHash, 'id' => $id]);

DiscordLogger::configuracoes('🔒 Senha alterada', [
    ['name' => '👤 Conta', 'value' => ($_SESSION['nome'] ?? '—') . " ({$tipo})", 'inline' => false],
]);

header('Location: ' . $urlVolta . '?senha_sucesso=1');
exit;
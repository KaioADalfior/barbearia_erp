<?php
// servico_salvar.php
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/guard.php';
exigirSessao(['barbeiro']);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../paginas/servico_listar.php');
    exit;
}

csrf_verificar(json: false, redirecionarPara: '../paginas/servico_listar.php');

$nome            = trim($_POST['nome'] ?? '');
$duracaoMinutos  = (int) ($_POST['duracao_minutos'] ?? 0);
$valorBruto      = trim($_POST['valor'] ?? '');
$valor           = (float) str_replace(',', '.', preg_replace('/[^\d,\.]/', '', $valorBruto));

// Query string usada para reabrir o modal de cadastro com os dados
// preenchidos, caso a validação falhe.
$paramsVolta = http_build_query([
    'nome'             => $nome,
    'duracao_minutos'  => $duracaoMinutos,
    'valor'            => $valorBruto,
]);

if ($nome === '' || $duracaoMinutos <= 0 || $valor <= 0) {
    header('Location: ../paginas/servico_listar.php?status=cadastro-erro&' . $paramsVolta);
    exit;
}

$stmt = $pdo->prepare(
    'INSERT INTO Servico (nome, duracao_minutos, valor, ativo) VALUES (:nome, :duracao_minutos, :valor, 1)'
);
$stmt->execute([
    'nome'            => $nome,
    'duracao_minutos' => $duracaoMinutos,
    'valor'           => $valor,
]);

DiscordLogger::servicos('🆕 Serviço cadastrado', [
    ['name' => '🆔 ID', 'value' => '#' . $pdo->lastInsertId(), 'inline' => true],
    ['name' => '💈 Nome', 'value' => $nome, 'inline' => true],
    ['name' => '⏱️ Duração', 'value' => $duracaoMinutos . ' min', 'inline' => true],
    ['name' => '💰 Valor', 'value' => 'R$ ' . number_format($valor, 2, ',', '.'), 'inline' => true],
    ['name' => '👤 Cadastrado por', 'value' => $_SESSION['nome'] ?? ('#' . ($_SESSION['id'] ?? '—')), 'inline' => true],
]);

header('Location: ../paginas/servico_listar.php?status=cadastro-sucesso&nome=' . urlencode($nome));
exit;
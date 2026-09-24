<?php
// servico_status.php
// Alterna o status do serviço entre ativo/inativo. Nunca exclui o registro.
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

$id   = (int) ($_POST['id'] ?? 0);
$acao = $_POST['acao'] ?? '';

if ($id <= 0 || !in_array($acao, ['inativar', 'reativar'], true)) {
    header('Location: ../paginas/servico_listar.php?status=status-erro');
    exit;
}

$novoStatus = $acao === 'inativar' ? 0 : 1;

$stmtNome = $pdo->prepare('SELECT nome FROM Servico WHERE idServico = :id');
$stmtNome->execute(['id' => $id]);
$nome = $stmtNome->fetchColumn();

$stmt = $pdo->prepare('UPDATE Servico SET ativo = :ativo WHERE idServico = :id');
$stmt->execute([
    'ativo' => $novoStatus,
    'id'    => $id,
]);

DiscordLogger::servicos(
    $acao === 'inativar' ? '⛔ Serviço inativado' : '🟢 Serviço reativado',
    [
        ['name' => '🆔 Serviço', 'value' => "#{$id}", 'inline' => true],
        ['name' => '💈 Nome', 'value' => $nome ?: '—', 'inline' => true],
    ],
    $acao === 'inativar' ? DiscordLogger::COR_ALERTA : DiscordLogger::COR_SUCESSO
);

$statusMsg = $acao === 'inativar' ? 'inativado-sucesso' : 'reativado-sucesso';
header('Location: ../paginas/servico_listar.php?status=' . $statusMsg . '&nome=' . urlencode($nome ?: ''));
exit;
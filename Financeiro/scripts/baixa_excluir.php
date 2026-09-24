<?php
// baixa_excluir.php
// Endpoint AJAX (POST) chamado pelo botão de excluir em
// Financeiro/paginas/financeiro_baixa.php, após confirmação via SweetAlert2.
// Permite ao barbeiro corrigir um lançamento cadastrado errado.

require_once __DIR__ . '/../../includes/session.php';
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/guard.php';
exigirSessao(['barbeiro'], json: true);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/FinanceiroService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'erro' => 'Método não permitido.']);
    exit;
}

$idBarbeiro    = (int) $_SESSION['id'];
$idLancamento  = (int) ($_POST['idLancamento'] ?? 0);

if ($idLancamento <= 0) {
    echo json_encode(['ok' => false, 'erro' => 'Lançamento inválido.']);
    exit;
}

try {
    $excluido = FinanceiroService::excluirLancamento($pdo, $idBarbeiro, $idLancamento);

    if (!$excluido) {
        echo json_encode(['ok' => false, 'erro' => 'Lançamento não encontrado.']);
        exit;
    }

    DiscordLogger::financeiroLancamentos('🗑️ Lançamento excluído', [
        ['name' => '🆔 Lançamento', 'value' => '#' . $idLancamento, 'inline' => true],
    ], DiscordLogger::COR_ALERTA);

    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    DiscordLogger::erro('💥 Falha ao excluir lançamento', $e);
    http_response_code(500);
    echo json_encode(['ok' => false, 'erro' => 'Erro ao excluir o lançamento.']);
}

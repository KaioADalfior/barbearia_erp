<?php
// relatorio_excluir.php
// Endpoint AJAX (POST) chamado pelo botão de excluir em
// Financeiro/paginas/financeiro_relatorios.php, após confirmação via SweetAlert2.

require_once __DIR__ . '/../../includes/session.php';
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/guard.php';
exigirSessao(['barbeiro'], json: true);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/RelatorioService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'erro' => 'Método não permitido.']);
    exit;
}

$idBarbeiro  = (int) $_SESSION['id'];
$idRelatorio = (int) ($_POST['idRelatorio'] ?? 0);

if ($idRelatorio <= 0) {
    echo json_encode(['ok' => false, 'erro' => 'Relatório inválido.']);
    exit;
}

try {
    $excluido = RelatorioService::excluir($pdo, $idBarbeiro, $idRelatorio);

    if (!$excluido) {
        echo json_encode(['ok' => false, 'erro' => 'Relatório não encontrado.']);
        exit;
    }

    DiscordLogger::financeiroRelatorios('🗑️ Relatório financeiro excluído', [
        ['name' => '🆔 Relatório', 'value' => '#' . $idRelatorio, 'inline' => true],
    ], DiscordLogger::COR_ALERTA);

    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    DiscordLogger::erro('💥 Falha ao excluir relatório financeiro', $e);
    http_response_code(500);
    echo json_encode(['ok' => false, 'erro' => 'Erro ao excluir o relatório.']);
}

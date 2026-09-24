<?php
// agendamento_cancelados_excluir.php
// Endpoint AJAX (POST) usado pelo botão "Excluir Agendamentos cancelados"
// em Agendamentos/paginas/agendamento_listar.php. Exclui PERMANENTEMENTE
// todos os agendamentos com Status = 'cancelado' do barbeiro logado —
// nunca de outro barbeiro, e nunca agendado/confirmado/concluído/ausente.
//
// Segurança extra (defensiva, não deveria fazer diferença no uso normal):
// um agendamento cancelado nunca tem lançamento financeiro vinculado (só
// agendamentos concluídos geram lançamento, ver
// FinanceiroService::concluirAgendamentoComPagamento) — o NOT EXISTS
// abaixo garante que, mesmo que isso aconteça por algum motivo fora do
// fluxo normal, essa linha específica é simplesmente pulada (preserva a
// integridade) em vez de travar a exclusão inteira.

require_once __DIR__ . '/../../includes/session.php';
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/guard.php';
exigirSessao(['barbeiro'], json: true);

require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'erro' => 'Método não permitido.']);
    exit;
}

$idBarbeiro = (int) $_SESSION['id'];

try {
    $stmt = $pdo->prepare(
        "DELETE a FROM Agendamentos a
         INNER JOIN Horario h ON h.idHorario = a.idHorario
         WHERE h.id_barbeiro = :b
           AND a.Status = 'cancelado'
           AND NOT EXISTS (SELECT 1 FROM FinanceiroLancamentos f WHERE f.idAgendamento = a.idAgendamento)"
    );
    $stmt->execute(['b' => $idBarbeiro]);
    $totalExcluido = $stmt->rowCount();

    DiscordLogger::agendamentos('🧹 Agendamentos cancelados excluídos em massa', [
        ['name' => '🗑️ Quantidade', 'value' => (string) $totalExcluido, 'inline' => true],
    ], DiscordLogger::COR_ALERTA);

    echo json_encode(['ok' => true, 'totalExcluido' => $totalExcluido]);
} catch (\Throwable $e) {
    DiscordLogger::erro('💥 Falha ao excluir agendamentos cancelados em massa', $e);
    http_response_code(500);
    echo json_encode(['ok' => false, 'erro' => 'Não foi possível excluir agora. Tente novamente em alguns segundos.']);
}

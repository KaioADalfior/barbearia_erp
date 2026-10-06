<?php
// Financeiro/scripts/comissao_baixar.php
// Endpoint AJAX (POST): baixa/acerto de comissões. SOMENTE Proprietário.
//   acao=pagar   -> marca as comissões PENDENTES informadas como PAGAS
//   acao=reabrir -> desfaz uma baixa feita por engano (PAGA -> PENDENTE)
//   ids[]        -> idComissao das linhas
// Nada é apagado: cada mudança fica anotada no histórico da comissão e
// comissões CANCELADAS nunca são alteradas por aqui.

require_once __DIR__ . '/../../includes/session.php';
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/guard.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/AcessoService.php';
AcessoService::exigirProprietario($pdo, json: true);

require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/ComissaoService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'erro' => 'Método não permitido.']);
    exit;
}

csrf_verificar(json: true);

$acao = $_POST['acao'] ?? '';
$ids  = $_POST['ids'] ?? [];
$ids  = is_array($ids) ? $ids : [$ids];
$ids  = array_values(array_unique(array_filter(array_map('intval', $ids), fn($v) => $v > 0)));

if (!in_array($acao, ['pagar', 'reabrir'], true) || empty($ids)) {
    echo json_encode(['ok' => false, 'erro' => 'Selecione ao menos uma comissão.']);
    exit;
}

$quem = (string) ($_SESSION['nome'] ?? ('#' . ($_SESSION['id'] ?? '')));

try {
    $pdo->beginTransaction();
    $alteradas = $acao === 'pagar'
        ? ComissaoService::marcarPagas($pdo, $ids, $quem)
        : ComissaoService::reabrir($pdo, $ids, $quem);
    $pdo->commit();

    DiscordLogger::financeiroLancamentos(
        $acao === 'pagar' ? '💸 Comissões pagas (baixa)' : '↩️ Baixa de comissão desfeita',
        [
            ['name' => '🔢 Quantidade', 'value' => (string) $alteradas, 'inline' => true],
            ['name' => '👑 Por', 'value' => $quem, 'inline' => true],
        ]
    );

    echo json_encode(['ok' => true, 'alteradas' => $alteradas, 'solicitadas' => count($ids)]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    DiscordLogger::erro('💥 Falha na baixa de comissões', $e);
    http_response_code(500);
    echo json_encode(['ok' => false, 'erro' => 'Não foi possível atualizar as comissões agora.']);
}

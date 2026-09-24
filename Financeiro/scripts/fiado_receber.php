<?php
// fiado_receber.php
// Endpoint AJAX (POST) chamado pelo modal "Receber" em
// Financeiro/paginas/financeiro_fiados.php. Confirma o recebimento de um
// VALOR (livre, editável pelo barbeiro) do saldo devedor de um cliente.
// Abate o valor dos lançamentos pendentes mais antigos primeiro (FIFO);
// só marca como "pago" (sai do Extrato/Fiados) o(s) lançamento(s) que
// forem totalmente quitados — o restante continua pendente com saldo
// reduzido, e fica registrado no histórico do cliente.

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
$idCliente     = (int) ($_POST['idCliente'] ?? 0);
$valorBruto    = $_POST['valor'] ?? '';
$formasBrutas  = $_POST['formas'] ?? [];
$formasBrutas  = is_array($formasBrutas) ? $formasBrutas : [];

if ($idCliente <= 0) {
    echo json_encode(['ok' => false, 'erro' => 'Cliente inválido.']);
    exit;
}

// Aceita tanto "30.00" quanto "30,00" vindo do input.
$valor = (float) str_replace(',', '.', (string) $valorBruto);
if ($valor <= 0) {
    echo json_encode(['ok' => false, 'erro' => 'Informe um valor válido, maior que zero.']);
    exit;
}

$formas = FinanceiroService::normalizarFormasPagamento($formasBrutas);
if (empty($formas)) {
    echo json_encode(['ok' => false, 'erro' => 'Selecione ao menos uma forma de pagamento.']);
    exit;
}

try {
    $resultado = FinanceiroService::receberFiadoCliente($pdo, $idBarbeiro, $idCliente, $valor, $formas);

    if (!$resultado['ok']) {
        echo json_encode(['ok' => false, 'erro' => $resultado['erro']]);
        exit;
    }

    DiscordLogger::financeiroLancamentos('💰 Fiado recebido', [
        ['name' => '🆔 Cliente', 'value' => '#' . $idCliente, 'inline' => true],
        ['name' => '💵 Valor recebido', 'value' => number_format($resultado['valorAplicado'], 2, ',', '.'), 'inline' => true],
        ['name' => '📌 Saldo restante', 'value' => number_format($resultado['saldoRestante'], 2, ',', '.'), 'inline' => true],
        ['name' => '💳 Forma de pagamento', 'value' => implode(', ', $formas), 'inline' => true],
    ], DiscordLogger::COR_SUCESSO);

    echo json_encode([
        'ok'            => true,
        'valorAplicado' => $resultado['valorAplicado'],
        'saldoRestante' => $resultado['saldoRestante'],
    ]);
} catch (Exception $e) {
    DiscordLogger::erro('💥 Falha ao receber fiado', $e);
    http_response_code(500);
    echo json_encode(['ok' => false, 'erro' => 'Erro ao confirmar o recebimento.']);
}

<?php
// fiado_criar.php
// Endpoint AJAX (POST) chamado pelo modal "+ Novo Registro" em
// Financeiro/paginas/financeiro_aReceber.php. Permite ao barbeiro
// registrar manualmente um fiado (dívida) de um cliente já cadastrado,
// sem precisar vincular a um agendamento. Cai na mesma "conta corrente"
// por cliente da tela de Fiados e segue o mesmo fluxo de recebimento já
// existente (fiado_receber.php) — nenhum comportamento é alterado.

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

$idBarbeiro = (int) $_SESSION['id'];
$idCliente  = (int) ($_POST['idCliente'] ?? 0);
$valorBruto = $_POST['valor'] ?? '';
$descricao  = trim((string) ($_POST['descricao'] ?? ''));

if ($idCliente <= 0) {
    echo json_encode(['ok' => false, 'erro' => 'Selecione um cliente.']);
    exit;
}

// Aceita tanto "30.00" quanto "30,00" vindo do input.
$valor = (float) str_replace(',', '.', (string) $valorBruto);
if ($valor <= 0) {
    echo json_encode(['ok' => false, 'erro' => 'Informe um valor válido, maior que zero.']);
    exit;
}

try {
    $resultado = FinanceiroService::criarFiadoManual($pdo, $idBarbeiro, $idCliente, $valor, $descricao);

    if (!$resultado['ok']) {
        echo json_encode(['ok' => false, 'erro' => $resultado['erro']]);
        exit;
    }

    DiscordLogger::financeiroLancamentos('📝 Fiado registrado manualmente', [
        ['name' => '🆔 Cliente', 'value' => '#' . $idCliente, 'inline' => true],
        ['name' => '💵 Valor devido', 'value' => number_format($valor, 2, ',', '.'), 'inline' => true],
    ], DiscordLogger::COR_SUCESSO);

    echo json_encode(['ok' => true, 'idLancamento' => $resultado['idLancamento']]);
} catch (Exception $e) {
    DiscordLogger::erro('💥 Falha ao registrar fiado manual', $e);
    http_response_code(500);
    echo json_encode(['ok' => false, 'erro' => 'Erro ao registrar o fiado.']);
}

<?php
// baixa_listar.php
// Endpoint AJAX (GET) usado por Financeiro/paginas/financeiro_baixa.php
// (Cadastrar Baixa / Extrato Financeiro) para listar os RECEBIMENTOS
// efetivamente ocorridos (ver FinanceiroRecebimentos) — um fiado pago em
// parcelas aparece como uma linha por parcela, cada uma na sua data real.

require_once __DIR__ . '/../../includes/session.php';
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/guard.php';
exigirSessao(['barbeiro'], json: true);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/FinanceiroService.php';

$idBarbeiro = (int) $_SESSION['id'];
$termo      = trim($_GET['termo'] ?? '');
$tipo       = $_GET['tipo'] ?? 'todos';

$lancamentos = FinanceiroService::listarExtrato($pdo, $idBarbeiro, $termo, $tipo);

$totalEntradas = 0.0;
$totalSaidas   = 0.0;
foreach ($lancamentos as $l) {
    if ($l['tipo'] === 'entrada') {
        $totalEntradas += (float) $l['valor'];
    } else {
        $totalSaidas += (float) $l['valor'];
    }
}

echo json_encode([
    'ok'            => true,
    'lancamentos'   => $lancamentos,
    'totalEntradas' => $totalEntradas,
    'totalSaidas'   => $totalSaidas,
]);

<?php
// dashboard_dados.php
// Endpoint AJAX (GET) usado por Financeiro/paginas/financeiro_dashboard.php.
// Substitui o gerador de números aleatórios (modo demonstrativo) por dados
// reais agregados de FinanceiroRecebimentos — cada recebimento entra na
// data em que foi de fato recebido (inclusive parcelas de fiado pagas aos
// poucos); fiado ainda em aberto nunca entra nas receitas/saldo.

require_once __DIR__ . '/../../includes/session.php';
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/guard.php';
exigirSessao(['barbeiro'], json: true);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/FinanceiroService.php';

$idBarbeiro = (int) $_SESSION['id'];
$periodo    = $_GET['periodo'] ?? 'diario';
$ano        = (int) ($_GET['ano'] ?? date('Y'));
$forma      = $_GET['forma'] ?? 'todas';

if (!in_array($periodo, ['diario', 'semanal', 'mensal', 'anual'], true)) {
    $periodo = 'diario';
}

$resumo = FinanceiroService::resumoDashboard($pdo, $idBarbeiro, $periodo, $ano, $forma);

$totalEntradas = 0.0;
$totalSaidas   = 0.0;
$qtd           = 0;
foreach ($resumo['serie'] as $ponto) {
    $totalEntradas += (float) $ponto['entradas'];
    $totalSaidas   += (float) $ponto['saidas'];
    $qtd           += (int) $ponto['qtd'];
}

echo json_encode([
    'ok'             => true,
    'serie'          => $resumo['serie'],
    'porForma'       => $resumo['porForma'],
    'totalEntradas'  => $totalEntradas,
    'totalSaidas'    => $totalSaidas,
    'saldo'          => $totalEntradas - $totalSaidas,
    'qtd'            => $qtd,
]);

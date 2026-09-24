<?php
// fiados_listar.php
// Endpoint AJAX (GET) usado por Financeiro/paginas/financeiro_fiados.php
// para listar os fiados em aberto agrupados por cliente (conta corrente:
// soma todos os cortes fiados pendentes de cada cliente num único saldo).
// Aceita ?termo= para buscar por nome/telefone do cliente.

require_once __DIR__ . '/../../includes/session.php';
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/guard.php';
exigirSessao(['barbeiro'], json: true);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/FinanceiroService.php';

$idBarbeiro = (int) $_SESSION['id'];
$termo      = trim((string) ($_GET['termo'] ?? ''));

echo json_encode(['ok' => true, 'fiados' => FinanceiroService::listarFiadosAgrupados($pdo, $idBarbeiro, $termo)]);

<?php
// relatorio_listar.php
// Endpoint AJAX (GET) usado por Financeiro/paginas/financeiro_relatorios.php
// para preencher a tabela com os relatórios já gerados pelo barbeiro.

require_once __DIR__ . '/../../includes/session.php';
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/guard.php';
// Financeiro geral (receitas, despesas, baixas, relatórios): só o Proprietário.
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/AcessoService.php';
AcessoService::exigirProprietario($pdo, json: true);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/RelatorioService.php';

$idBarbeiro = (int) $_SESSION['id'];
$relatorios = RelatorioService::listar($pdo, $idBarbeiro);

echo json_encode(['ok' => true, 'relatorios' => $relatorios]);

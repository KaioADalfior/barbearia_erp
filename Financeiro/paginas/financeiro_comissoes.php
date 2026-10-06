<?php
// Financeiro/paginas/financeiro_comissoes.php
// Visão do PROPRIETÁRIO: receitas, despesas, serviços realizados, total
// faturado, comissões (por funcionário e lançamento a lançamento) e baixa
// de comissões. Funcionário que abrir esta URL recebe ACESSO NEGADO (403).
// A tela em si fica em includes/comissao_view.php (compartilhada com
// "Meu Financeiro").

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/guard.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/AcessoService.php';
AcessoService::exigirProprietario($pdo);

require_once __DIR__ . '/../../includes/ComissaoService.php';

$paginaAtual = 'financeiro-comissoes';
$cmModo      = 'proprietario';
$filtros     = ComissaoService::filtrosAutorizados($pdo, $_GET, false);

require __DIR__ . '/../../includes/comissao_view.php';

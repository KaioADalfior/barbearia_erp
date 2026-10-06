<?php
// Financeiro/paginas/financeiro_meu.php
// "Meu Financeiro" do FUNCIONÁRIO: só as próprias comissões e totais.
// Pedir o financeiro de outro funcionário (?funcionario=ID diferente do
// próprio) => ACESSO NEGADO (403). Proprietário não tem comissão própria:
// é levado para a visão geral (Comissões).

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/guard.php';
exigirSessao(['barbeiro']);
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/AcessoService.php';
require_once __DIR__ . '/../../includes/ComissaoService.php';

if (AcessoService::ehProprietario($pdo)) {
    $qs = $_SERVER['QUERY_STRING'] ?? '';
    header('Location: /financeiro/comissoes' . ($qs !== '' ? '?' . $qs : ''));
    exit;
}

$paginaAtual = 'financeiro-meu';
$cmModo      = 'funcionario';
$filtros     = ComissaoService::filtrosAutorizados($pdo, $_GET, false); // trava no próprio id / 403 se for de outro

require __DIR__ . '/../../includes/comissao_view.php';

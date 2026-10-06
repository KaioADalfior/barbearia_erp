<?php
// Financeiro/scripts/comissao_listar.php
// Endpoint AJAX (GET) das comissões. Mesmas regras de permissão das telas
// (ver ComissaoService::filtrosAutorizados):
//   - Proprietário: qualquer funcionário (?funcionario=ID) ou todos;
//     filtros de data (de/ate, YYYY-MM-DD) e status (pendente|pago|cancelado).
//   - Funcionário: SOMENTE as próprias comissões. Pedir o id de outro
//     funcionário => 403 "Acesso negado".

require_once __DIR__ . '/../../includes/session.php';
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/guard.php';
exigirSessao(['barbeiro'], json: true);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/ComissaoService.php';

$filtros = ComissaoService::filtrosAutorizados($pdo, $_GET, true);
$ehProprietario = AcessoService::ehProprietario($pdo);

$resposta = [
    'ok'        => true,
    'filtros'   => $filtros,
    'totais'    => ComissaoService::totais($pdo, $filtros),
    'comissoes' => ComissaoService::listar($pdo, $filtros),
];

if ($ehProprietario) {
    $resposta['porFuncionario'] = ComissaoService::porFuncionario($pdo, $filtros);
    $resposta['visaoGeral']     = ComissaoService::visaoGeral($pdo, $filtros);
}

echo json_encode($resposta);

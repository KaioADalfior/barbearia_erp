<?php
// Publico/scripts/publico_info.php
// Endpoint AJAX (GET) usado por Publico/paginas/agendar.php ao carregar a
// página. Sem login — recebe o token do link público e devolve, em JSON,
// só o que a tela precisa mostrar: nome/foto do barbeiro e a lista de
// serviços ativos (nome, preço, duração). Nunca expõe id_barbeiro, dados
// de clientes ou qualquer outra informação interna.

require_once __DIR__ . '/../../includes/session.php';
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/PublicoTokenService.php';

$token = trim($_GET['t'] ?? '');
$barbeiro = PublicoTokenService::resolverBarbeiro($pdo, $token);

if ($barbeiro === null) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'erro' => 'Link inválido ou desativado.']);
    exit;
}

$stmt = $pdo->prepare('SELECT idServico, nome, duracao_minutos, valor FROM Servico WHERE ativo = 1 ORDER BY nome ASC');
$stmt->execute();

$servicos = array_map(function ($linha) {
    return [
        'idServico'       => (int) $linha['idServico'],
        'nome'            => $linha['nome'],
        'duracaoMinutos'  => (int) $linha['duracao_minutos'],
        'valor'           => (float) $linha['valor'],
    ];
}, $stmt->fetchAll());

echo json_encode([
    'ok'       => true,
    'barbeiro' => [
        'nome' => $barbeiro['nome'],
        'foto' => $barbeiro['foto'],
    ],
    'servicos' => $servicos,
]);

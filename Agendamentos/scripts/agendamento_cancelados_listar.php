<?php
// agendamento_cancelados_listar.php
// Endpoint AJAX (GET) usado pelo botão "Excluir Agendamentos cancelados"
// em Agendamentos/paginas/agendamento_listar.php. Lista TODOS os
// agendamentos com Status = 'cancelado' do barbeiro logado, pra exibir
// antes de excluir — o barbeiro confere se a lista bate com o que ele
// espera ver antes de confirmar a exclusão definitiva (ver
// agendamento_cancelados_excluir.php).

require_once __DIR__ . '/../../includes/session.php';
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/guard.php';
exigirSessao(['barbeiro'], json: true);

require_once __DIR__ . '/../../config/config.php';

$idBarbeiro = (int) $_SESSION['id'];

$stmt = $pdo->prepare(
    "SELECT a.idAgendamento, a.Data, h.hora, c.nome AS clienteNome, s.nome AS servicoNome
     FROM Agendamentos a
     INNER JOIN Horario h ON h.idHorario = a.idHorario
     INNER JOIN Cliente c ON c.idCliente = a.idCliente
     INNER JOIN Servico s ON s.idServico = a.idServico
     WHERE h.id_barbeiro = :b AND a.Status = 'cancelado'
     ORDER BY a.Data DESC, h.hora DESC"
);
$stmt->execute(['b' => $idBarbeiro]);
$cancelados = $stmt->fetchAll();

$itens = array_map(function ($c) {
    return [
        'idAgendamento' => (int) $c['idAgendamento'],
        'cliente'       => $c['clienteNome'],
        'servico'       => $c['servicoNome'],
        'data'          => (new DateTimeImmutable($c['Data']))->format('d/m/Y'),
        'hora'          => substr($c['hora'], 0, 5),
    ];
}, $cancelados);

echo json_encode([
    'ok'    => true,
    'total' => count($itens),
    'itens' => $itens,
]);

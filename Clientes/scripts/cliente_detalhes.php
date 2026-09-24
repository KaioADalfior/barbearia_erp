<?php
// cliente_detalhes.php
// Endpoint AJAX (GET) usado pelo modal de detalhes em Clientes/paginas/cliente_listar.php.
// Retorna, em JSON, os dados básicos do cliente + os agendamentos feitos por
// ele + o histórico livre (fiado, observações, atendimentos avulsos etc.)
// para popular as abas do modal sem precisar recarregar a página.

require_once __DIR__ . '/../../includes/session.php';
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/guard.php';
exigirSessao(['barbeiro'], json: true);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/FinanceiroService.php';

$idCliente = (int) ($_GET['id'] ?? 0);

if ($idCliente <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'erro' => 'Cliente inválido.']);
    exit;
}

$stmtCliente = $pdo->prepare(
    'SELECT idCliente, nome, telefone, email, ativo, criado_em
     FROM Cliente
     WHERE idCliente = :id
     LIMIT 1'
);
$stmtCliente->execute(['id' => $idCliente]);
$cliente = $stmtCliente->fetch();

if (!$cliente) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'erro' => 'Cliente não encontrado.']);
    exit;
}

// ---------- Agendamentos do cliente ----------
$stmtAgendamentos = $pdo->prepare(
    'SELECT a.idAgendamento, a.Data, a.Valor, a.IncluirBarba, a.Observacao, a.Status,
            s.nome AS servico_nome, h.hora
     FROM Agendamentos a
     JOIN Servico s ON s.idServico = a.idServico
     JOIN Horario h ON h.idHorario = a.idHorario
     WHERE a.idCliente = :id
     ORDER BY a.Data DESC, h.hora DESC'
);
$stmtAgendamentos->execute(['id' => $idCliente]);
$agendamentos = $stmtAgendamentos->fetchAll();

// ---------- Histórico livre (fiado / observação / atendimento / outro) ----------
$stmtHistorico = $pdo->prepare(
    'SELECT ch.idHistorico, ch.tipo, ch.descricao, ch.valor, ch.quitado, ch.criado_em,
            b.nome AS barbeiro_nome
     FROM ClienteHistorico ch
     LEFT JOIN Barbeiro b ON b.id_barbeiro = ch.id_barbeiro
     WHERE ch.idCliente = :id
     ORDER BY ch.criado_em DESC'
);
$stmtHistorico->execute(['id' => $idCliente]);
$historico = $stmtHistorico->fetchAll();

echo json_encode([
    'ok'              => true,
    'cliente'         => $cliente,
    'agendamentos'    => $agendamentos,
    'historico'       => $historico,
    'saldoFiadoPendente' => FinanceiroService::saldoFiadoPendente($pdo, $idCliente),
]);

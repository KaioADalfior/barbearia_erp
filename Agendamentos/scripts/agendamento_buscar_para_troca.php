<?php
// agendamento_buscar_para_troca.php
// Endpoint AJAX (GET) usado pelo submenu "Alterar agendamento" -> "Trocar
// cliente", em Agendamentos/paginas/agendar.php.
//
// Busca, por nome ou telefone, agendamentos ATIVOS (agendado/confirmado,
// hoje em diante) do barbeiro logado, pra o barbeiro escolher com qual
// deles o agendamento atual vai trocar de cliente (ver
// agendamento_trocar_cliente.php). Diferente de Clientes/scripts/clientes_buscar.php
// (que busca CLIENTES): aqui a busca é por AGENDAMENTOS, já que um mesmo
// cliente pode ter mais de um agendamento futuro (ex.: fidelidade) e o
// barbeiro precisa escolher exatamente qual ocorrência entra na troca.
//
// Agendamentos "duplicados" (grupo_agendamento preenchido) e o próprio
// agendamento em edição (parâmetro "excluir") nunca aparecem nos resultados.

require_once __DIR__ . '/../../includes/session.php';
header('Content-Type: application/json; charset=utf-8');

if (($_SESSION['tipo'] ?? null) !== 'barbeiro') {
    http_response_code(401);
    echo json_encode(['ok' => false, 'erro' => 'Não autenticado.']);
    exit;
}

require_once __DIR__ . '/../../config/config.php';

$idBarbeiro = (int) $_SESSION['id'];
$termo      = trim($_GET['termo'] ?? '');
$excluir    = (int) ($_GET['excluir'] ?? 0);

if ($termo === '') {
    echo json_encode(['ok' => true, 'agendamentos' => []]);
    exit;
}

$sql = "SELECT a.idAgendamento, a.Data, h.hora, a.idCliente,
               c.nome AS clienteNome, c.telefone AS clienteTelefone,
               s.nome AS servicoNome
        FROM Agendamentos a
        INNER JOIN Horario h ON h.idHorario = a.idHorario
        INNER JOIN Cliente c ON c.idCliente = a.idCliente
        INNER JOIN Servico s ON s.idServico = a.idServico
        WHERE h.id_barbeiro = :b
          AND a.Status IN ('agendado', 'confirmado')
          AND a.Data >= CURDATE()
          AND a.grupo_agendamento IS NULL
          AND a.idAgendamento <> :excluir
          AND (c.nome LIKE :termo OR c.telefone LIKE :termo)
        ORDER BY a.Data ASC, h.hora ASC
        LIMIT 8";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    'b'       => $idBarbeiro,
    'excluir' => $excluir,
    'termo'   => '%' . $termo . '%',
]);

$agendamentos = array_map(function ($linha) {
    return [
        'idAgendamento' => (int) $linha['idAgendamento'],
        'data'          => $linha['Data'],
        'hora'          => substr($linha['hora'], 0, 5),
        'idCliente'     => (int) $linha['idCliente'],
        'clienteNome'   => $linha['clienteNome'],
        'clienteTelefone' => $linha['clienteTelefone'],
        'servicoNome'   => $linha['servicoNome'],
    ];
}, $stmt->fetchAll());

echo json_encode(['ok' => true, 'agendamentos' => $agendamentos]);

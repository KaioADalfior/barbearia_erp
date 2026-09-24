<?php
// agendamento_trocar_cliente.php
// Endpoint AJAX (POST) chamado pelo submenu "Alterar agendamento" -> "Trocar
// cliente", em Agendamentos/paginas/agendar.php.
//
// Troca dois agendamentos ATIVOS entre si: cada cliente passa a ocupar o
// horário que era do outro. Diferente de "Alterar cliente" (substituição
// definitiva, sem contrapartida): aqui os dois agendamentos continuam
// existindo, só passam a apontar pro horário um do outro.
//
// Cada linha de Agendamentos mantém TODOS os seus próprios dados (Serviço,
// Valor, Observação, fidelidade, grupo_recorrencia) — só idHorario e Data
// são trocados entre as duas linhas. Isso é proposital: a fidelidade e o
// serviço contratado são do CLIENTE, não do horário, então cada cliente
// continua com a sua própria fidelidade/serviço/valor intactos, só mudando
// de horário (o mesmo efeito de rodar "Trocar horário" nos dois ao mesmo
// tempo, de forma atômica).
//
// A troca em si é feita em 3 passos dentro de uma transação, pra nunca
// violar a UNIQUE KEY uk_horario_ativo_unico (que impede dois agendamentos
// ATIVOS no mesmo horário):
//   1) O agendamento A é marcado 'cancelado' TEMPORARIAMENTE — isso libera,
//      só pro banco (idHorario_ativo é uma coluna gerada a partir do
//      Status), o horário que A ocupava.
//   2) O agendamento B passa a usar o horário (agora livre) que era do A.
//   3) O agendamento A passa a usar o horário (agora livre, já que B saiu
//      dele no passo 2) que era do B, e o Status original de A é restaurado.
// Como tudo roda dentro de uma única transação, esse estado intermediário
// nunca fica visível fora dela — se qualquer passo falhar, a transação
// inteira é desfeita (ROLLBACK) e nada muda.
//
// Não permitido para agendamentos "duplicados" (grupo_agendamento
// preenchido) — trocar só uma das duas linhas vinculadas quebraria o par.

require_once __DIR__ . '/../../includes/session.php';
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/guard.php';
exigirSessao(['barbeiro'], json: true);

require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'erro' => 'Método não permitido.']);
    exit;
}

$idBarbeiro     = (int) $_SESSION['id'];
$idAgendamentoA = (int) ($_POST['idAgendamentoA'] ?? 0);
$idAgendamentoB = (int) ($_POST['idAgendamentoB'] ?? 0);

if ($idAgendamentoA <= 0 || $idAgendamentoB <= 0) {
    echo json_encode(['ok' => false, 'erro' => 'Requisição inválida.']);
    exit;
}

if ($idAgendamentoA === $idAgendamentoB) {
    echo json_encode(['ok' => false, 'erro' => 'Selecione dois agendamentos diferentes.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Trava as duas linhas numa única consulta, sempre em ordem crescente
    // de idAgendamento — evita deadlock entre duas trocas concorrentes que
    // envolvam os mesmos agendamentos (mesmo padrão usado em
    // agendamento_salvar.php pro agendamento duplicado).
    $idsOrdenados = [$idAgendamentoA, $idAgendamentoB];
    sort($idsOrdenados);

    $stmt = $pdo->prepare(
        "SELECT a.idAgendamento, a.idHorario, a.Data, a.Status, a.idCliente, a.grupo_agendamento,
                c.nome AS clienteNome, c.ativo AS clienteAtivo, h.hora
         FROM Agendamentos a
         INNER JOIN Horario h ON h.idHorario = a.idHorario
         INNER JOIN Cliente c ON c.idCliente = a.idCliente
         WHERE a.idAgendamento IN (:id1, :id2) AND h.id_barbeiro = :b
         ORDER BY a.idAgendamento ASC
         FOR UPDATE"
    );
    $stmt->execute(['id1' => $idsOrdenados[0], 'id2' => $idsOrdenados[1], 'b' => $idBarbeiro]);
    $linhas = $stmt->fetchAll();

    $porId = [];
    foreach ($linhas as $linha) {
        $porId[(int) $linha['idAgendamento']] = $linha;
    }

    $rowA = $porId[$idAgendamentoA] ?? null;
    $rowB = $porId[$idAgendamentoB] ?? null;

    if (!$rowA || !$rowB) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'erro' => 'Um dos agendamentos não foi encontrado.']);
        exit;
    }

    foreach ([$rowA, $rowB] as $row) {
        if (!in_array($row['Status'], ['agendado', 'confirmado'], true)) {
            $pdo->rollBack();
            echo json_encode(['ok' => false, 'erro' => 'Só é possível trocar agendamentos ativos (agendado ou confirmado).']);
            exit;
        }
        if ($row['grupo_agendamento'] !== null) {
            $pdo->rollBack();
            echo json_encode(['ok' => false, 'erro' => 'Um dos agendamentos faz parte de um atendimento duplicado e não pode ser trocado por aqui.']);
            exit;
        }
        if (!(bool) $row['clienteAtivo']) {
            $pdo->rollBack();
            echo json_encode(['ok' => false, 'erro' => 'Um dos clientes está inativo.']);
            exit;
        }
    }

    // Regra "1 agendamento ativo por cliente por dia": depois da troca, o
    // cliente de A vai estar na Data de B (e vice-versa) — garante que
    // nenhum dos dois já tem OUTRO agendamento ativo nesse dia (fora os
    // dois que estão sendo trocados agora).
    $stmtConflito = $pdo->prepare(
        "SELECT 1 FROM Agendamentos
         WHERE idCliente = :c AND Data = :d AND Status IN ('agendado', 'confirmado')
           AND idAgendamento NOT IN (:idA, :idB)
         LIMIT 1"
    );
    $stmtConflito->execute(['c' => $rowA['idCliente'], 'd' => $rowB['Data'], 'idA' => $idAgendamentoA, 'idB' => $idAgendamentoB]);
    if ($stmtConflito->fetch()) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'erro' => $rowA['clienteNome'] . ' já tem outro agendamento no dia ' . $rowB['Data'] . '.']);
        exit;
    }
    $stmtConflito->execute(['c' => $rowB['idCliente'], 'd' => $rowA['Data'], 'idA' => $idAgendamentoA, 'idB' => $idAgendamentoB]);
    if ($stmtConflito->fetch()) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'erro' => $rowB['clienteNome'] . ' já tem outro agendamento no dia ' . $rowA['Data'] . '.']);
        exit;
    }

    // ---------- Troca em 3 passos (ver explicação no cabeçalho do arquivo) ----------
    $stmtLibera = $pdo->prepare("UPDATE Agendamentos SET Status = 'cancelado' WHERE idAgendamento = :id");
    $stmtLibera->execute(['id' => $idAgendamentoA]);

    $stmtMoveB = $pdo->prepare('UPDATE Agendamentos SET idHorario = :h, Data = :d WHERE idAgendamento = :id');
    $stmtMoveB->execute(['h' => $rowA['idHorario'], 'd' => $rowA['Data'], 'id' => $idAgendamentoB]);

    $stmtMoveA = $pdo->prepare('UPDATE Agendamentos SET idHorario = :h, Data = :d, Status = :status WHERE idAgendamento = :id');
    $stmtMoveA->execute(['h' => $rowB['idHorario'], 'd' => $rowB['Data'], 'status' => $rowA['Status'], 'id' => $idAgendamentoA]);

    $pdo->commit();

    DiscordLogger::agendamentos('🔀 Clientes trocados entre agendamentos', [
        ['name' => '👤 ' . $rowA['clienteNome'], 'value' => $rowA['Data'] . ' ' . substr($rowA['hora'], 0, 5) . ' → ' . $rowB['Data'] . ' ' . substr($rowB['hora'], 0, 5), 'inline' => false],
        ['name' => '👤 ' . $rowB['clienteNome'], 'value' => $rowB['Data'] . ' ' . substr($rowB['hora'], 0, 5) . ' → ' . $rowA['Data'] . ' ' . substr($rowA['hora'], 0, 5), 'inline' => false],
    ], DiscordLogger::COR_EDICAO);

    echo json_encode([
        'ok' => true,
        'clienteA' => ['nome' => $rowA['clienteNome'], 'novaData' => $rowB['Data'], 'novaHora' => substr($rowB['hora'], 0, 5)],
        'clienteB' => ['nome' => $rowB['clienteNome'], 'novaData' => $rowA['Data'], 'novaHora' => substr($rowA['hora'], 0, 5)],
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    DiscordLogger::erro('💥 Falha ao trocar clientes entre agendamentos', $e);
    http_response_code(500);
    echo json_encode(['ok' => false, 'erro' => 'Erro ao trocar os clientes.']);
}

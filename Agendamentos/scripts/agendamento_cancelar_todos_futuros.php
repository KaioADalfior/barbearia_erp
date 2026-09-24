<?php
// agendamento_cancelar_todos_futuros.php
// Endpoint AJAX (POST) chamado pelo modal de cancelamento em
// Agendamentos/paginas/agendar.php, opção "Cancelar todos os cortes".
//
// Diferente de agendamento_cancelar.php (que cancela SÓ o agendamento
// clicado — Status = 'cancelado' apenas naquela linha), este endpoint
// cancela TODOS os agendamentos futuros/pendentes ainda ativos
// (Status IN 'agendado','confirmado', Data >= hoje) do MESMO CLIENTE do
// agendamento clicado — cobrindo tanto ocorrências de uma mesma fidelidade
// (grupo_recorrencia) quanto agendamentos avulsos soltos, exatamente como
// o cliente pediu ("cancelar todos os cortes futuros deste cliente").
//
// É a mesma regra já usada em Clientes/scripts/cliente_status.php ao
// INATIVAR um cliente (ver bloco "if ($acao === 'inativar')"), só que aqui
// SEM mexer em Cliente.ativo — o cliente continua ativo e pode receber
// novos agendamentos normalmente depois.
//
// NUNCA toca em:
// - Agendamentos com Data < hoje (histórico, mesmo que ainda estivessem
//   'agendado'/'confirmado' por algum motivo — não é o caso normal, mas a
//   condição de data protege contra apagar o passado).
// - Agendamentos já 'concluido' (histórico de atendimentos realizados).
// - Agendamentos já 'cancelado' (nada a fazer).
// - Cadastro do cliente (Cliente.ativo, nome, telefone, etc.) — nunca é
//   alterado ou removido aqui.
//
// Segue o mesmo padrão de "duas linhas de um duplicado sempre juntas" que
// agendamento_cancelar.php já garante: como a busca abaixo é por
// idCliente (não por idAgendamento), qualquer duplicado (grupo_agendamento)
// do mesmo cliente já cai automaticamente nesta mesma leva, pois as duas
// linhas de um duplicado sempre compartilham o mesmo idCliente.

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

$idBarbeiro    = (int) $_SESSION['id'];
$idAgendamento = (int) ($_POST['idAgendamento'] ?? 0);

if ($idAgendamento <= 0) {
    echo json_encode(['ok' => false, 'erro' => 'Agendamento inválido.']);
    exit;
}

// ---------- Identifica o cliente a partir do agendamento clicado ----------
// Confirma que o agendamento clicado pertence ao barbeiro logado (mesma
// trava de segurança usada em agendamento_cancelar.php) antes de agir
// sobre TODOS os agendamentos futuros do cliente.
$stmt = $pdo->prepare(
    "SELECT a.idCliente, c.nome AS clienteNome
     FROM Agendamentos a
     INNER JOIN Horario h ON h.idHorario = a.idHorario
     INNER JOIN Cliente c ON c.idCliente = a.idCliente
     WHERE a.idAgendamento = :id AND h.id_barbeiro = :b AND a.Status IN ('agendado', 'confirmado')"
);
$stmt->execute(['id' => $idAgendamento, 'b' => $idBarbeiro]);
$base = $stmt->fetch();

if (!$base) {
    echo json_encode(['ok' => false, 'erro' => 'Agendamento não encontrado.']);
    exit;
}

$idCliente   = (int) $base['idCliente'];
$clienteNome = $base['clienteNome'];
$hoje        = date('Y-m-d');

try {
    $pdo->beginTransaction();

    // Trava e busca TODOS os agendamentos futuros ainda ativos do cliente
    // (não só desta fidelidade/grupo — qualquer agendamento futuro dele),
    // exatamente como Clientes/scripts/cliente_status.php já faz ao
    // inativar. Histórico (concluído) e o passado nunca entram aqui.
    $stmtFuturos = $pdo->prepare(
        "SELECT idAgendamento, idHorario
         FROM Agendamentos
         WHERE idCliente = :c AND Data >= :hoje AND Status IN ('agendado', 'confirmado')
         FOR UPDATE"
    );
    $stmtFuturos->execute(['c' => $idCliente, 'hoje' => $hoje]);
    $futuros = $stmtFuturos->fetchAll();

    $totalCancelados = 0;

    if (!empty($futuros)) {
        $idsAgendamentos = array_column($futuros, 'idAgendamento');
        $idsHorarios      = array_column($futuros, 'idHorario');

        $marcadoresAg = implode(',', array_fill(0, count($idsAgendamentos), '?'));
        $stmtCancela = $pdo->prepare("UPDATE Agendamentos SET Status = 'cancelado' WHERE idAgendamento IN ($marcadoresAg)");
        $stmtCancela->execute($idsAgendamentos);

        $marcadoresHor = implode(',', array_fill(0, count($idsHorarios), '?'));
        $stmtLibera = $pdo->prepare("UPDATE Horario SET disponivel = 1 WHERE idHorario IN ($marcadoresHor)");
        $stmtLibera->execute($idsHorarios);

        $totalCancelados = count($futuros);
    }

    $pdo->commit();

    DiscordLogger::agendamentos('🗑️ Todos os cortes futuros cancelados', [
        ['name' => '👤 Cliente', 'value' => $clienteNome, 'inline' => true],
        ['name' => '🗑️ Agendamentos cancelados', 'value' => (string) $totalCancelados, 'inline' => true],
        ['name' => '📌 Origem', 'value' => '#' . $idAgendamento, 'inline' => true],
    ], DiscordLogger::COR_ALERTA);

    echo json_encode([
        'ok'         => true,
        'cancelados' => $totalCancelados,
        'cliente'    => $clienteNome,
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    DiscordLogger::erro('💥 Falha ao cancelar todos os cortes futuros', $e);
    http_response_code(500);
    echo json_encode(['ok' => false, 'erro' => 'Erro ao cancelar os agendamentos futuros.']);
}
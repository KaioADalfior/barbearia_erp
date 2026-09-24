<?php
// agendamento_criar_para_cliente_existente.php
// Endpoint AJAX (POST) chamado pelo fluxo "Alterar cliente" -> "Sim,
// reagendar" em Agendamentos/paginas/agendar.php, quando o barbeiro escolhe
// um novo horário para o cliente que foi substituído.
//
// Cria um agendamento AVULSO (sem fidelidade, sem duplicar) pra um cliente
// já cadastrado, numa data/horário livre — a mesma validação de
// disponibilidade/dia bloqueado usada em todo o resto do sistema (ver
// includes/HorarioService.php), só que recebendo a data/horário como texto
// (igual agendamento_reagendar_unico.php), em vez de um idHorario já
// resolvido pelo grid — porque aqui o horário escolhido não vem de clicar
// numa célula da grade, vem de um seletor de data + horário dentro de um
// diálogo de confirmação.
//
// Não cria cliente novo (idCliente precisa já existir) e não mexe em mais
// nenhum outro agendamento — é só um agendamento novo e independente.

require_once __DIR__ . '/../../includes/session.php';
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/guard.php';
exigirSessao(['barbeiro'], json: true);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/FinanceiroService.php';
require_once __DIR__ . '/../../includes/HorarioService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'erro' => 'Método não permitido.']);
    exit;
}

$idBarbeiro  = (int) $_SESSION['id'];
$idCliente   = (int) ($_POST['idCliente'] ?? 0);
$idServico   = (int) ($_POST['idServico'] ?? 0);
$novaData    = trim($_POST['novaData'] ?? '');
$novaHoraRaw = trim($_POST['novaHora'] ?? '');
$observacao  = trim($_POST['observacao'] ?? '');

if ($idCliente <= 0 || $idServico <= 0) {
    echo json_encode(['ok' => false, 'erro' => 'Cliente ou serviço inválido.']);
    exit;
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $novaData)) {
    echo json_encode(['ok' => false, 'erro' => 'Selecione a data.']);
    exit;
}

if (!preg_match('/^\d{2}:\d{2}$/', $novaHoraRaw)) {
    echo json_encode(['ok' => false, 'erro' => 'Selecione o horário.']);
    exit;
}

$hoje = date('Y-m-d');
if ($novaData < $hoje) {
    echo json_encode(['ok' => false, 'erro' => 'A data não pode ser no passado.']);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmtCliente = $pdo->prepare('SELECT idCliente, nome FROM Cliente WHERE idCliente = :id AND ativo = 1');
    $stmtCliente->execute(['id' => $idCliente]);
    $cliente = $stmtCliente->fetch();

    if (!$cliente) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'erro' => 'Cliente não encontrado.']);
        exit;
    }

    $stmtServico = $pdo->prepare('SELECT idServico, nome, valor FROM Servico WHERE idServico = :s AND ativo = 1');
    $stmtServico->execute(['s' => $idServico]);
    $servico = $stmtServico->fetch();

    if (!$servico) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'erro' => 'Serviço inválido.']);
        exit;
    }

    // Dia bloqueado pelo barbeiro (ver DiaBloqueado): validado no backend.
    if (HorarioService::diaBloqueado($pdo, $idBarbeiro, $novaData)) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'erro' => 'Esse dia está bloqueado pelo barbeiro. Escolha outra data.']);
        exit;
    }

    $idHorario = HorarioService::obterOuCriarHorario($pdo, $idBarbeiro, $novaData, $novaHoraRaw);
    if ($idHorario === null) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'erro' => 'O barbeiro não atende nessa data.']);
        exit;
    }

    $stmtHorario = $pdo->prepare('SELECT idHorario, disponivel FROM Horario WHERE idHorario = :h FOR UPDATE');
    $stmtHorario->execute(['h' => $idHorario]);
    $horario = $stmtHorario->fetch();

    if (!$horario || !(bool) $horario['disponivel']) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'erro' => 'Esse horário não está disponível.']);
        exit;
    }

    $stmtOcupado = $pdo->prepare(
        "SELECT idAgendamento FROM Agendamentos WHERE idHorario = :h AND Status IN ('agendado', 'confirmado')"
    );
    $stmtOcupado->execute(['h' => $idHorario]);
    if ($stmtOcupado->fetch()) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'erro' => 'Esse horário já está ocupado por outro cliente.']);
        exit;
    }

    if (FinanceiroService::clienteJaTemAgendamentoNoDia($pdo, $idCliente, $novaData)) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'erro' => 'Este cliente já tem outro agendamento nesse dia.']);
        exit;
    }

    $stmtInsere = $pdo->prepare(
        'INSERT INTO Agendamentos (idCliente, idServico, idHorario, Data, Valor, IncluirBarba, Observacao, Status, fidelidade, grupo_recorrencia, gerado_automaticamente)
         VALUES (:idCliente, :idServico, :idHorario, :data, :valor, 0, :observacao, :statusAgendado, NULL, NULL, 0)'
    );
    $stmtInsere->execute([
        'idCliente'      => $idCliente,
        'idServico'      => $idServico,
        'idHorario'      => $idHorario,
        'data'           => $novaData,
        'valor'          => $servico['valor'],
        'observacao'     => $observacao !== '' ? $observacao : null,
        'statusAgendado' => 'agendado',
    ]);

    $stmtOcupa = $pdo->prepare('UPDATE Horario SET disponivel = 0 WHERE idHorario = :h');
    $stmtOcupa->execute(['h' => $idHorario]);

    $pdo->commit();

    DiscordLogger::agendamentos('🆕 Agendamento criado (reagendamento após troca de cliente)', [
        ['name' => '👤 Cliente', 'value' => $cliente['nome'], 'inline' => true],
        ['name' => '✂️ Serviço', 'value' => $servico['nome'], 'inline' => true],
        ['name' => '📅 Data', 'value' => $novaData . ' ' . $novaHoraRaw, 'inline' => true],
    ], DiscordLogger::COR_SUCESSO);

    echo json_encode(['ok' => true, 'novaData' => $novaData, 'novaHora' => $novaHoraRaw]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    DiscordLogger::erro('💥 Falha ao criar agendamento para cliente existente', $e);
    http_response_code(500);
    echo json_encode(['ok' => false, 'erro' => 'Erro ao criar o agendamento.']);
}

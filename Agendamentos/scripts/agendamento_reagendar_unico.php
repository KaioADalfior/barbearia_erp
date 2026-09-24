<?php
// agendamento_reagendar_unico.php
// Endpoint AJAX (POST) chamado pelo botão "Reagendar" em
// Agendamentos/paginas/agendar.php — tanto para clientes de FIDELIDADE
// ("Reagendar somente esta semana") quanto para clientes AVULSOS (único
// fluxo de reagendamento que eles têm).
//
// Move SOMENTE o agendamento clicado para uma nova data/horário. Quando o
// agendamento faz parte de uma fidelidade, não mexe em mais nenhuma outra
// ocorrência da mesma sequência — grupo_recorrencia e fidelidade continuam
// exatamente iguais nesta linha, só idHorario e Data mudam. Quando é
// avulso (grupo_recorrencia NULL), o reagendamento funciona do mesmo jeito,
// só que não existe nenhuma sequência para preservar.
//
// Não é permitido para agendamentos "duplicados" (dois horários vinculados
// por grupo_agendamento, ver agendamento_salvar.php) — mover só uma das
// duas linhas quebraria o vínculo entre elas; esse caso não tem botão
// "Reagendar" na interface.

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

$idBarbeiro    = (int) $_SESSION['id'];
$idAgendamento = (int) ($_POST['idAgendamento'] ?? 0);
$novaData      = trim($_POST['novaData'] ?? '');
$novaHoraRaw   = trim($_POST['novaHora'] ?? '');

if ($idAgendamento <= 0) {
    echo json_encode(['ok' => false, 'erro' => 'Agendamento inválido.']);
    exit;
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $novaData)) {
    echo json_encode(['ok' => false, 'erro' => 'Selecione a nova data.']);
    exit;
}

if (!preg_match('/^\d{2}:\d{2}$/', $novaHoraRaw)) {
    echo json_encode(['ok' => false, 'erro' => 'Selecione o novo horário.']);
    exit;
}

$hoje = date('Y-m-d');
if ($novaData < $hoje) {
    echo json_encode(['ok' => false, 'erro' => 'A nova data não pode ser no passado.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Carrega o agendamento atual — só permite reagendar quem faz parte de
    // uma fidelidade, e só o cliente ainda estando ativo.
    $stmt = $pdo->prepare(
        "SELECT a.idAgendamento, a.idHorario, a.idCliente, a.Data, a.fidelidade, a.grupo_recorrencia, a.grupo_agendamento,
                h.hora, c.nome AS clienteNome, c.ativo AS clienteAtivo, s.nome AS servicoNome
         FROM Agendamentos a
         INNER JOIN Horario h ON h.idHorario = a.idHorario
         INNER JOIN Cliente c ON c.idCliente = a.idCliente
         INNER JOIN Servico s ON s.idServico = a.idServico
         WHERE a.idAgendamento = :id AND h.id_barbeiro = :b AND a.Status IN ('agendado', 'confirmado')
         FOR UPDATE"
    );
    $stmt->execute(['id' => $idAgendamento, 'b' => $idBarbeiro]);
    $agendamento = $stmt->fetch();

    if (!$agendamento) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'erro' => 'Agendamento não encontrado.']);
        exit;
    }

    // Agendamento "duplicado" (dois horários vinculados por
    // grupo_agendamento, ver agendamento_salvar.php): mover só uma das duas
    // linhas quebraria o vínculo entre elas. Esse caso não tem botão
    // "Reagendar" na interface, mas o backend também bloqueia por segurança.
    if ($agendamento['grupo_agendamento'] !== null) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'erro' => 'Este agendamento faz parte de um atendimento duplicado e não pode ser reagendado por aqui.']);
        exit;
    }

    if (!(bool) $agendamento['clienteAtivo']) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'erro' => 'Este cliente está inativo.']);
        exit;
    }

    $horaAtualCurta = substr($agendamento['hora'], 0, 5);
    if ($novaData === $agendamento['Data'] && $novaHoraRaw === $horaAtualCurta) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'erro' => 'Escolha uma data ou horário diferente do atual.']);
        exit;
    }

    // Dia bloqueado pelo barbeiro (ver DiaBloqueado): validado no backend,
    // não só no frontend — mover um agendamento pra um dia bloqueado
    // equivale a criar um novo agendamento ali.
    if (HorarioService::diaBloqueado($pdo, $idBarbeiro, $novaData)) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'erro' => 'Esse dia está bloqueado pelo barbeiro. Escolha outra data.']);
        exit;
    }

    // Resolve (criando se preciso) o novo horário e trava a linha.
    $idHorarioNovo = HorarioService::obterOuCriarHorario($pdo, $idBarbeiro, $novaData, $novaHoraRaw);
    if ($idHorarioNovo === null) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'erro' => 'O barbeiro não atende nessa data.']);
        exit;
    }

    $stmtNovoHorario = $pdo->prepare('SELECT idHorario, disponivel FROM Horario WHERE idHorario = :h FOR UPDATE');
    $stmtNovoHorario->execute(['h' => $idHorarioNovo]);
    $novoHorario = $stmtNovoHorario->fetch();

    if (!$novoHorario || !(bool) $novoHorario['disponivel']) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'erro' => 'Esse horário não está disponível.']);
        exit;
    }

    $stmtOcupado = $pdo->prepare(
        "SELECT idAgendamento FROM Agendamentos WHERE idHorario = :h AND Status IN ('agendado', 'confirmado')"
    );
    $stmtOcupado->execute(['h' => $idHorarioNovo]);
    if ($stmtOcupado->fetch()) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'erro' => 'Esse horário já está ocupado por outro cliente.']);
        exit;
    }

    // Regra de 1 agendamento ativo por cliente por dia, ignorando este
    // próprio agendamento (que está apenas mudando de dia/horário).
    $stmtConflitoDia = $pdo->prepare(
        "SELECT 1 FROM Agendamentos
         WHERE idCliente = :c AND Data = :d AND Status IN ('agendado', 'confirmado') AND idAgendamento <> :id
         LIMIT 1"
    );
    $stmtConflitoDia->execute(['c' => $agendamento['idCliente'], 'd' => $novaData, 'id' => $idAgendamento]);
    if ($stmtConflitoDia->fetch()) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'erro' => 'Este cliente já tem outro agendamento nesse dia.']);
        exit;
    }

    // Libera o horário antigo, move o agendamento e ocupa o novo horário.
    // grupo_recorrencia e fidelidade NÃO mudam — a sequência original
    // continua intacta, só esta ocorrência troca de horário.
    $stmtLiberaAntigo = $pdo->prepare('UPDATE Horario SET disponivel = 1 WHERE idHorario = :h');
    $stmtLiberaAntigo->execute(['h' => $agendamento['idHorario']]);

    $stmtMove = $pdo->prepare('UPDATE Agendamentos SET idHorario = :h, Data = :d WHERE idAgendamento = :id');
    $stmtMove->execute(['h' => $idHorarioNovo, 'd' => $novaData, 'id' => $idAgendamento]);

    $stmtOcupaNovo = $pdo->prepare('UPDATE Horario SET disponivel = 0 WHERE idHorario = :h');
    $stmtOcupaNovo->execute(['h' => $idHorarioNovo]);

    $pdo->commit();

    DiscordLogger::agendamentos('🔁 Agendamento reagendado (só esta ocorrência)', [
        ['name' => '🆔 Agendamento', 'value' => '#' . $idAgendamento, 'inline' => true],
        ['name' => '👤 Cliente', 'value' => $agendamento['clienteNome'], 'inline' => true],
        ['name' => '✂️ Serviço', 'value' => $agendamento['servicoNome'], 'inline' => true],
        ['name' => '📅 De', 'value' => $agendamento['Data'] . ' ' . $horaAtualCurta, 'inline' => true],
        ['name' => '📅 Para', 'value' => $novaData . ' ' . $novaHoraRaw, 'inline' => true],
    ], DiscordLogger::COR_EDICAO);

    echo json_encode([
        'ok'       => true,
        'novaData' => $novaData,
        'novaHora' => $novaHoraRaw,
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    DiscordLogger::erro('💥 Falha ao reagendar (única ocorrência)', $e);
    http_response_code(500);
    echo json_encode(['ok' => false, 'erro' => 'Erro ao reagendar o agendamento.']);
}
<?php
// agendamento_reagendar_fidelidade.php
// Endpoint AJAX (POST) chamado pelo botão "REAGENDAR" -> "Reagendar
// fidelidade", em Agendamentos/paginas/agendar.php.
//
// Recalcula TODA a sequência de fidelidade a partir de uma nova data/horário
// base: cancela todas as ocorrências futuras ainda ativas (Data >= hoje) da
// mesma fidelidade (mesmo grupo_recorrencia), cria uma nova ocorrência
// "semente" na nova data/horário e gera as próximas ocorrências a partir
// dela, na mesma frequência, por até 1 ano — exatamente a mesma lógica de
// includes/FidelidadeService.php usada na criação original (Agendamentos/scripts/agendamento_salvar.php),
// só que reaproveitando o grupo_recorrencia já existente em vez de criar um novo.
//
// Ocorrências PASSADAS (Data < hoje) nunca são tocadas — o histórico do
// cliente permanece intacto.

require_once __DIR__ . '/../../includes/session.php';
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/guard.php';
exigirSessao(['barbeiro'], json: true);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/FinanceiroService.php';
require_once __DIR__ . '/../../includes/HorarioService.php';
require_once __DIR__ . '/../../includes/FidelidadeService.php';

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
    echo json_encode(['ok' => false, 'erro' => 'Selecione a nova data inicial.']);
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

    // Carrega o agendamento clicado só para identificar a fidelidade
    // (grupo_recorrencia + código) e os dados do cliente/serviço.
    $stmtBase = $pdo->prepare(
        "SELECT a.idAgendamento, a.idCliente, a.idServico, a.IncluirBarba, a.Observacao,
                a.fidelidade, a.grupo_recorrencia,
                c.nome AS clienteNome, c.ativo AS clienteAtivo, s.nome AS servicoNome
         FROM Agendamentos a
         INNER JOIN Horario h ON h.idHorario = a.idHorario
         INNER JOIN Cliente c ON c.idCliente = a.idCliente
         INNER JOIN Servico s ON s.idServico = a.idServico
         WHERE a.idAgendamento = :id AND h.id_barbeiro = :b AND a.Status IN ('agendado', 'confirmado')"
    );
    $stmtBase->execute(['id' => $idAgendamento, 'b' => $idBarbeiro]);
    $base = $stmtBase->fetch();

    if (!$base) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'erro' => 'Agendamento não encontrado.']);
        exit;
    }

    if ($base['grupo_recorrencia'] === null || !FidelidadeService::codigoValido($base['fidelidade'])) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'erro' => 'Este agendamento não faz parte de uma fidelidade.']);
        exit;
    }

    if (!(bool) $base['clienteAtivo']) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'erro' => 'Este cliente está inativo.']);
        exit;
    }

    $grupoRecorrencia = $base['grupo_recorrencia'];
    $codigoFidelidade = $base['fidelidade'];
    $idCliente         = (int) $base['idCliente'];
    $idServico         = (int) $base['idServico'];

    $stmtServico = $pdo->prepare('SELECT nome, valor FROM Servico WHERE idServico = :s AND ativo = 1');
    $stmtServico->execute(['s' => $idServico]);
    $servico = $stmtServico->fetch();

    if (!$servico) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'erro' => 'O serviço deste agendamento não está mais disponível.']);
        exit;
    }

    // ---------- Cancela TODAS as ocorrências futuras ainda ativas desta fidelidade ----------
    // "Futuras" = Data >= hoje. Ocorrências passadas nunca são tocadas —
    // preserva o histórico do cliente por completo.
    $stmtFuturas = $pdo->prepare(
        "SELECT a.idAgendamento, a.idHorario
         FROM Agendamentos a
         INNER JOIN Horario h ON h.idHorario = a.idHorario
         WHERE a.grupo_recorrencia = :g AND h.id_barbeiro = :b
           AND a.Status IN ('agendado', 'confirmado') AND a.Data >= :hoje
         FOR UPDATE"
    );
    $stmtFuturas->execute(['g' => $grupoRecorrencia, 'b' => $idBarbeiro, 'hoje' => $hoje]);
    $futuras = $stmtFuturas->fetchAll();

    if (!empty($futuras)) {
        $idsAgendamentos = array_column($futuras, 'idAgendamento');
        $idsHorarios      = array_column($futuras, 'idHorario');

        $marcadoresAg = implode(',', array_fill(0, count($idsAgendamentos), '?'));
        $stmtCancela = $pdo->prepare("UPDATE Agendamentos SET Status = 'cancelado' WHERE idAgendamento IN ($marcadoresAg)");
        $stmtCancela->execute($idsAgendamentos);

        $marcadoresHor = implode(',', array_fill(0, count($idsHorarios), '?'));
        $stmtLibera = $pdo->prepare("UPDATE Horario SET disponivel = 1 WHERE idHorario IN ($marcadoresHor)");
        $stmtLibera->execute($idsHorarios);
    }

    $totalCanceladas = count($futuras);

    // Dia bloqueado pelo barbeiro (ver DiaBloqueado): validado no backend,
    // não só no frontend.
    if (HorarioService::diaBloqueado($pdo, $idBarbeiro, $novaData)) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'erro' => 'Esse dia está bloqueado pelo barbeiro. Escolha outra data.']);
        exit;
    }

    // ---------- Cria a nova ocorrência "semente" na nova data/horário ----------
    $idHorarioSemente = HorarioService::obterOuCriarHorario($pdo, $idBarbeiro, $novaData, $novaHoraRaw);
    if ($idHorarioSemente === null) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'erro' => 'O barbeiro não atende nessa data.']);
        exit;
    }

    $stmtHorarioSemente = $pdo->prepare('SELECT idHorario, disponivel FROM Horario WHERE idHorario = :h FOR UPDATE');
    $stmtHorarioSemente->execute(['h' => $idHorarioSemente]);
    $horarioSemente = $stmtHorarioSemente->fetch();

    if (!$horarioSemente || !(bool) $horarioSemente['disponivel']) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'erro' => 'Esse horário não está disponível.']);
        exit;
    }

    $stmtOcupadoSemente = $pdo->prepare(
        "SELECT idAgendamento FROM Agendamentos WHERE idHorario = :h AND Status IN ('agendado', 'confirmado')"
    );
    $stmtOcupadoSemente->execute(['h' => $idHorarioSemente]);
    if ($stmtOcupadoSemente->fetch()) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'erro' => 'Esse horário já está ocupado por outro cliente.']);
        exit;
    }

    if (FinanceiroService::clienteJaTemAgendamentoNoDia($pdo, $idCliente, $novaData)) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'erro' => 'Este cliente já tem outro agendamento nesse dia.']);
        exit;
    }

    $stmtInsereSemente = $pdo->prepare(
        'INSERT INTO Agendamentos (idCliente, idServico, idHorario, Data, Valor, IncluirBarba, Observacao, Status, fidelidade, grupo_recorrencia, gerado_automaticamente)
         VALUES (:idCliente, :idServico, :idHorario, :data, :valor, :incluirBarba, :observacao, :statusAgendado, :fidelidade, :grupoRecorrencia, 0)'
    );
    $stmtInsereSemente->execute([
        'idCliente'        => $idCliente,
        'idServico'        => $idServico,
        'idHorario'        => $idHorarioSemente,
        'data'             => $novaData,
        'valor'            => $servico['valor'],
        'incluirBarba'     => (bool) $base['IncluirBarba'] ? 1 : 0,
        'observacao'       => $base['Observacao'],
        'statusAgendado'   => 'agendado',
        'fidelidade'       => $codigoFidelidade,
        'grupoRecorrencia' => $grupoRecorrencia,
    ]);

    $stmtOcupaSemente = $pdo->prepare('UPDATE Horario SET disponivel = 0 WHERE idHorario = :h');
    $stmtOcupaSemente->execute(['h' => $idHorarioSemente]);

    // ---------- Gera as próximas ocorrências a partir da nova data-base ----------
    // Mesma lógica de Agendamentos/scripts/agendamento_salvar.php: sempre em
    // SEMANAS (nunca dias corridos), até 1 ano a partir da nova data-base.
    $datasFuturas = FidelidadeService::gerarDatasFuturas($novaData, $codigoFidelidade);

    $stmtConflito = $pdo->prepare(
        "SELECT idAgendamento FROM Agendamentos WHERE idHorario = :h AND Status IN ('agendado', 'confirmado') LIMIT 1"
    );
    $stmtInsereFutura = $pdo->prepare(
        'INSERT INTO Agendamentos (idCliente, idServico, idHorario, Data, Valor, IncluirBarba, Observacao, Status, fidelidade, grupo_recorrencia, gerado_automaticamente)
         VALUES (:idCliente, :idServico, :idHorario, :data, :valor, :incluirBarba, :observacao, :statusAgendado, :fidelidade, :grupoRecorrencia, 1)'
    );
    $stmtOcupaHorarioFuturo = $pdo->prepare('UPDATE Horario SET disponivel = 0 WHERE idHorario = :h');

    $totalCriadas = 0;
    $ocorrenciasPuladas = [];

    foreach ($datasFuturas as $dataFutura) {
        if (HorarioService::diaBloqueado($pdo, $idBarbeiro, $dataFutura)) {
            $ocorrenciasPuladas[] = ['data' => $dataFutura, 'motivo' => 'Dia bloqueado pelo barbeiro'];
            continue;
        }

        $idHorarioFuturo = HorarioService::obterOuCriarHorario($pdo, $idBarbeiro, $dataFutura, $novaHoraRaw);

        if ($idHorarioFuturo === null) {
            $ocorrenciasPuladas[] = ['data' => $dataFutura, 'motivo' => 'Barbeiro não atende aos domingos'];
            continue;
        }

        $stmtConflito->execute(['h' => $idHorarioFuturo]);
        if ($stmtConflito->fetch()) {
            $ocorrenciasPuladas[] = ['data' => $dataFutura, 'motivo' => 'Horário já ocupado'];
            continue;
        }

        if (FinanceiroService::clienteJaTemAgendamentoNoDia($pdo, $idCliente, $dataFutura)) {
            $ocorrenciasPuladas[] = ['data' => $dataFutura, 'motivo' => 'Cliente já tem outro agendamento nesse dia'];
            continue;
        }

        $stmtInsereFutura->execute([
            'idCliente'        => $idCliente,
            'idServico'        => $idServico,
            'idHorario'        => $idHorarioFuturo,
            'data'             => $dataFutura,
            'valor'            => $servico['valor'],
            'incluirBarba'     => (bool) $base['IncluirBarba'] ? 1 : 0,
            'observacao'       => $base['Observacao'],
            'statusAgendado'   => 'agendado',
            'fidelidade'       => $codigoFidelidade,
            'grupoRecorrencia' => $grupoRecorrencia,
        ]);

        $stmtOcupaHorarioFuturo->execute(['h' => $idHorarioFuturo]);
        $totalCriadas++;
    }

    $pdo->commit();

    DiscordLogger::agendamentos('🔁 Fidelidade reagendada (série inteira recalculada)', [
        ['name' => '👤 Cliente', 'value' => $base['clienteNome'], 'inline' => true],
        ['name' => '✂️ Serviço', 'value' => $servico['nome'], 'inline' => true],
        ['name' => '🔁 Frequência', 'value' => FidelidadeService::rotulo($codigoFidelidade), 'inline' => true],
        ['name' => '📅 Nova data-base', 'value' => $novaData . ' ' . $novaHoraRaw, 'inline' => true],
        ['name' => '🗑️ Ocorrências antigas canceladas', 'value' => (string) $totalCanceladas, 'inline' => true],
        ['name' => '🆕 Ocorrências novas criadas', 'value' => (string) (1 + $totalCriadas), 'inline' => true],
    ], DiscordLogger::COR_EDICAO);

    echo json_encode([
        'ok'          => true,
        'canceladas'  => $totalCanceladas,
        'criadas'     => 1 + $totalCriadas,
        'puladas'     => $ocorrenciasPuladas,
        'rotulo'      => FidelidadeService::rotulo($codigoFidelidade),
        'novaData'    => $novaData,
        'novaHora'    => $novaHoraRaw,
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    DiscordLogger::erro('💥 Falha ao reagendar fidelidade', $e);
    http_response_code(500);
    echo json_encode(['ok' => false, 'erro' => 'Erro ao reagendar a fidelidade.']);
}
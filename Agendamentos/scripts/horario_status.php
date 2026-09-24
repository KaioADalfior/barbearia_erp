<?php
// horario_status.php
// Endpoint AJAX (POST) chamado pelo modal de novo agendamento e pela lista
// de horários em Agendamentos/paginas/agendar.php, para inativar ou
// reativar um horário específico (idHorario), afetando SOMENTE aquele
// registro — ou seja, somente aquele dia. Como a grade é gerada por dia
// (uma linha por id_barbeiro + data + hora, ver horarios_buscar.php), o
// horário volta a ficar ativo normalmente nos outros dias sem precisar de
// nenhuma lógica extra.
//
// Regra: só é permitido inativar um horário LIVRE (sem agendamento
// agendado/confirmado vinculado). Um horário ocupado precisa ser cancelado
// primeiro (agendamento_cancelar.php) antes de poder ser inativado.

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

$idBarbeiro = (int) $_SESSION['id'];
$idHorario  = (int) ($_POST['idHorario'] ?? 0);
$acao       = $_POST['acao'] ?? '';

if ($idHorario <= 0 || !in_array($acao, ['inativar', 'reativar'], true)) {
    echo json_encode(['ok' => false, 'erro' => 'Requisição inválida.']);
    exit;
}

// Garante que o horário pertence ao barbeiro logado.
$stmt = $pdo->prepare(
    'SELECT h.idHorario, h.data, h.hora, h.disponivel,
            a.idAgendamento
     FROM Horario h
     LEFT JOIN Agendamentos a ON a.idHorario = h.idHorario AND a.Status IN (\'agendado\', \'confirmado\')
     WHERE h.idHorario = :h AND h.id_barbeiro = :b'
);
$stmt->execute(['h' => $idHorario, 'b' => $idBarbeiro]);
$horario = $stmt->fetch();

if (!$horario) {
    echo json_encode(['ok' => false, 'erro' => 'Horário não encontrado.']);
    exit;
}

$ocupado = $horario['idAgendamento'] !== null;

if ($acao === 'inativar') {
    if ($ocupado) {
        echo json_encode(['ok' => false, 'erro' => 'Este horário já tem cliente agendado. Cancele o agendamento antes de inativar.']);
        exit;
    }

    $stmtUpdate = $pdo->prepare('UPDATE Horario SET disponivel = 0 WHERE idHorario = :h');
    $stmtUpdate->execute(['h' => $idHorario]);

    DiscordLogger::agendamentos('⛔ Horário inativado (somente neste dia)', [
        ['name' => '🆔 Horário',   'value' => '#' . $idHorario, 'inline' => true],
        ['name' => '📅 Data',      'value' => $horario['data'], 'inline' => true],
        ['name' => '🕐 Hora',      'value' => substr($horario['hora'], 0, 5), 'inline' => true],
    ], DiscordLogger::COR_ALERTA);

    echo json_encode(['ok' => true, 'disponivel' => false]);
    exit;
}

// acao === 'reativar'
$stmtUpdate = $pdo->prepare('UPDATE Horario SET disponivel = 1 WHERE idHorario = :h');
$stmtUpdate->execute(['h' => $idHorario]);

DiscordLogger::agendamentos('🟢 Horário reativado', [
    ['name' => '🆔 Horário',   'value' => '#' . $idHorario, 'inline' => true],
    ['name' => '📅 Data',      'value' => $horario['data'], 'inline' => true],
    ['name' => '🕐 Hora',      'value' => substr($horario['hora'], 0, 5), 'inline' => true],
], DiscordLogger::COR_SUCESSO);

echo json_encode(['ok' => true, 'disponivel' => true]);
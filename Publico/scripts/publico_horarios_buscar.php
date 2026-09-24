<?php
// Publico/scripts/publico_horarios_buscar.php
// Endpoint AJAX (GET) usado por Publico/paginas/agendar.php depois que o
// cliente escolhe um dia no calendário. Sem login — devolve só a lista de
// horários e se cada um está livre ou não. Diferente do equivalente
// interno (Agendamentos/scripts/horarios_buscar.php), NUNCA inclui nome,
// telefone, serviço ou qualquer outro dado de quem já ocupa um horário —
// isso é uma tela pública, sem autenticação.

require_once __DIR__ . '/../../includes/session.php';
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/PublicoTokenService.php';
require_once __DIR__ . '/../../includes/HorarioService.php';

$token = trim($_GET['t'] ?? '');
$barbeiro = PublicoTokenService::resolverBarbeiro($pdo, $token);

if ($barbeiro === null) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'erro' => 'Link inválido ou desativado.']);
    exit;
}

$idBarbeiro = $barbeiro['id_barbeiro'];
$data = $_GET['data'] ?? '';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'erro' => 'Data inválida.']);
    exit;
}

// Nunca deixa listar (nem agendar) num dia já passado.
if ($data < (new DateTimeImmutable('today'))->format('Y-m-d')) {
    echo json_encode(['ok' => true, 'data' => $data, 'horarios' => [], 'diaBloqueado' => false]);
    exit;
}

if (HorarioService::diaBloqueado($pdo, $idBarbeiro, $data)) {
    echo json_encode(['ok' => true, 'data' => $data, 'horarios' => [], 'diaBloqueado' => true]);
    exit;
}

HorarioService::garantirGradeDoDia($pdo, $idBarbeiro, $data);

$stmt = $pdo->prepare(
    "SELECT h.idHorario, h.hora, h.disponivel, a.idAgendamento
     FROM Horario h
     LEFT JOIN Agendamentos a ON a.idHorario = h.idHorario AND a.Status IN ('agendado', 'confirmado')
     WHERE h.id_barbeiro = :b AND h.data = :d
     ORDER BY h.hora ASC"
);
$stmt->execute(['b' => $idBarbeiro, 'd' => $data]);

// Se for hoje, não oferece horários que já passaram.
$agora = new DateTimeImmutable();
$ehHoje = $data === $agora->format('Y-m-d');
$minutoAgora = ((int) $agora->format('H')) * 60 + (int) $agora->format('i');

$horarios = [];
foreach ($stmt->fetchAll() as $linha) {
    $ocupado = $linha['idAgendamento'] !== null;
    $livre = !$ocupado && (bool) $linha['disponivel'];

    if ($livre && $ehHoje) {
        $horaMin = (int) substr($linha['hora'], 0, 2) * 60 + (int) substr($linha['hora'], 3, 2);
        if ($horaMin <= $minutoAgora) {
            $livre = false;
        }
    }

    $horarios[] = [
        'idHorario'  => (int) $linha['idHorario'],
        'hora'       => substr($linha['hora'], 0, 5),
        'disponivel' => $livre,
    ];
}

echo json_encode(['ok' => true, 'data' => $data, 'horarios' => $horarios, 'diaBloqueado' => false]);

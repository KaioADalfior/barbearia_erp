<?php
// dia_bloqueio_status.php
// Endpoint AJAX (POST) chamado pelo botão de bloqueio na barra de
// navegação de datas em Agendamentos/paginas/agendar.php, para
// bloquear/desbloquear um DIA INTEIRO do calendário do barbeiro logado.
//
// Diferente de horario_status.php (que inativa/reativa um HORÁRIO
// específico dentro de um dia): bloquear o dia NÃO apaga nem cancela
// nenhum horário/agendamento já existente — só passa a impedir a criação
// de NOVOS agendamentos naquele dia. Essa regra também é validada no
// backend (não só aqui): ver Agendamentos/scripts/agendamento_salvar.php,
// agendamento_reagendar_unico.php e agendamento_reagendar_fidelidade.php.

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
$data       = trim($_POST['data'] ?? '');
$acao       = $_POST['acao'] ?? '';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) || !in_array($acao, ['bloquear', 'desbloquear'], true)) {
    echo json_encode(['ok' => false, 'erro' => 'Requisição inválida.']);
    exit;
}

if ($acao === 'bloquear') {
    // INSERT IGNORE: bloquear um dia que já estava bloqueado é idempotente
    // (uk_barbeiro_dia_bloqueado evita duplicar a linha).
    $stmt = $pdo->prepare('INSERT IGNORE INTO DiaBloqueado (id_barbeiro, data) VALUES (:b, :d)');
    $stmt->execute(['b' => $idBarbeiro, 'd' => $data]);

    DiscordLogger::agendamentos('⛔ Dia bloqueado', [
        ['name' => '📅 Data', 'value' => $data, 'inline' => true],
    ], DiscordLogger::COR_ALERTA);

    echo json_encode(['ok' => true, 'bloqueado' => true]);
    exit;
}

// acao === 'desbloquear'
$stmt = $pdo->prepare('DELETE FROM DiaBloqueado WHERE id_barbeiro = :b AND data = :d');
$stmt->execute(['b' => $idBarbeiro, 'd' => $data]);

DiscordLogger::agendamentos('🟢 Dia desbloqueado', [
    ['name' => '📅 Data', 'value' => $data, 'inline' => true],
], DiscordLogger::COR_SUCESSO);

echo json_encode(['ok' => true, 'bloqueado' => false]);

<?php
// mes_disponibilidade.php
// Endpoint AJAX (GET) usado pelo modal de calendário em Agendamentos/paginas/agendar.php
// para desenhar o indicador de disponibilidade (bolinha colorida) ao lado de
// cada dia: vermelho = 0 vagas, amarelo = 1 a 4 vagas, verde = 5+ vagas.
//
// Uma única consulta agregada por mês visível (GROUP BY data), nunca uma
// consulta por dia/célula do calendário — evita até ~31 idas ao banco só
// para desenhar o mês. Dias que ainda não têm grade gerada (o barbeiro
// nunca abriu aquele dia no calendário) são tratados como totalmente livres
// (HorarioService::totalSlotsPadrao(), calculado dinamicamente a partir dos
// turnos/passo reais — nunca um valor fixo no código).

require_once __DIR__ . '/../../includes/session.php';
header('Content-Type: application/json; charset=utf-8');

if (($_SESSION['tipo'] ?? null) !== 'barbeiro') {
    http_response_code(401);
    echo json_encode(['ok' => false, 'erro' => 'Não autenticado.']);
    exit;
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/HorarioService.php';

$idBarbeiro = (int) $_SESSION['id'];
$mes = (int) ($_GET['mes'] ?? 0);
$ano = (int) ($_GET['ano'] ?? 0);

if ($mes < 1 || $mes > 12 || $ano < 2000 || $ano > 2100) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'erro' => 'Mês/ano inválido.']);
    exit;
}

$inicio = sprintf('%04d-%02d-01', $ano, $mes);
$fim = date('Y-m-t', strtotime($inicio));

// ---------- Uma única consulta agregada para o mês inteiro ----------
$sql = "SELECT
            h.data,
            SUM(CASE WHEN h.disponivel = 1 AND a.idAgendamento IS NULL THEN 1 ELSE 0 END) AS vagas
        FROM Horario h
        LEFT JOIN Agendamentos a ON a.idHorario = h.idHorario AND a.Status IN ('agendado', 'confirmado')
        WHERE h.id_barbeiro = :b AND h.data BETWEEN :inicio AND :fim
        GROUP BY h.data";

$stmt = $pdo->prepare($sql);
$stmt->execute(['b' => $idBarbeiro, 'inicio' => $inicio, 'fim' => $fim]);

$vagasPorDia = [];
foreach ($stmt->fetchAll() as $linha) {
    $vagasPorDia[$linha['data']] = (int) $linha['vagas'];
}

$totalPadrao = HorarioService::totalSlotsPadrao();

// ---------- Dias bloqueados do mês inteiro, também numa única consulta ----------
$diasBloqueados = HorarioService::diasBloqueadosNoIntervalo($pdo, $idBarbeiro, $inicio, $fim);

function statusDisponibilidade(int $vagas): string
{
    if ($vagas <= 0) return 'vermelho';
    if ($vagas <= 4) return 'amarelo';
    return 'verde';
}

$dias = [];
$totalDias = (int) date('t', strtotime($inicio));
for ($d = 1; $d <= $totalDias; $d++) {
    $dataChave = sprintf('%04d-%02d-%02d', $ano, $mes, $d);
    // Dia sem grade gerada ainda -> considera totalmente livre (todos os
    // slots padrão disponíveis), não zero.
    $vagas = array_key_exists($dataChave, $vagasPorDia) ? $vagasPorDia[$dataChave] : $totalPadrao;

    $dias[$dataChave] = [
        'vagas'     => $vagas,
        'status'    => statusDisponibilidade($vagas),
        // Dia bloqueado pelo barbeiro (ver DiaBloqueado / dia_bloqueio_status.php)
        // — o frontend desenha esse dia destacado (mesma cor do horário
        // inativado), independente da quantidade de vagas.
        'bloqueado' => isset($diasBloqueados[$dataChave]),
    ];
}

echo json_encode(['ok' => true, 'mes' => $mes, 'ano' => $ano, 'dias' => $dias]);
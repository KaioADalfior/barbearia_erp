<?php
// Publico/scripts/publico_mes_disponibilidade.php
// Endpoint AJAX (GET) usado pelo calendário de Publico/paginas/agendar.php
// para desenhar o indicador de disponibilidade (bolinha colorida) de cada
// dia do mês. Sem login — recebe o token do link público em vez do
// barbeiro logado, mas reaproveita exatamente a mesma lógica/consulta de
// Agendamentos/scripts/mes_disponibilidade.php (ver includes/HorarioService.php),
// pra nunca ter duas fontes de verdade sobre disponibilidade.

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
$mes = (int) ($_GET['mes'] ?? 0);
$ano = (int) ($_GET['ano'] ?? 0);

if ($mes < 1 || $mes > 12 || $ano < 2000 || $ano > 2100) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'erro' => 'Mês/ano inválido.']);
    exit;
}

// Não deixa consultar/agendar em meses já passados por essa via (a grade de
// um mês passado não faz sentido pro cliente final escolher).
$hoje = new DateTimeImmutable('today');
$primeiroDiaMesConsultado = DateTimeImmutable::createFromFormat('Y-m-d', sprintf('%04d-%02d-01', $ano, $mes));
if ($primeiroDiaMesConsultado < $hoje->modify('first day of this month')) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'erro' => 'Mês inválido.']);
    exit;
}

$inicio = sprintf('%04d-%02d-01', $ano, $mes);
$fim = date('Y-m-t', strtotime($inicio));

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
$diasBloqueados = HorarioService::diasBloqueadosNoIntervalo($pdo, $idBarbeiro, $inicio, $fim);

function publicoStatusDisponibilidade(int $vagas): string
{
    if ($vagas <= 0) return 'vermelho';
    if ($vagas <= 4) return 'amarelo';
    return 'verde';
}

$hojeStr = $hoje->format('Y-m-d');
$dias = [];
$totalDias = (int) date('t', strtotime($inicio));
for ($d = 1; $d <= $totalDias; $d++) {
    $dataChave = sprintf('%04d-%02d-%02d', $ano, $mes, $d);

    // Dias já passados nunca aparecem como disponíveis pro cliente final,
    // mesmo que ainda tenham slots livres na grade.
    if ($dataChave < $hojeStr) {
        $dias[$dataChave] = ['vagas' => 0, 'status' => 'passado', 'bloqueado' => false];
        continue;
    }

    $vagas = array_key_exists($dataChave, $vagasPorDia) ? $vagasPorDia[$dataChave] : $totalPadrao;

    $dias[$dataChave] = [
        'vagas'     => $vagas,
        'status'    => publicoStatusDisponibilidade($vagas),
        'bloqueado' => isset($diasBloqueados[$dataChave]),
    ];
}

echo json_encode(['ok' => true, 'mes' => $mes, 'ano' => $ano, 'dias' => $dias]);

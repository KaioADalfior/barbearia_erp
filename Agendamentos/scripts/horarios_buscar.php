<?php
// horarios_buscar.php
// Endpoint AJAX (GET) usado por Agendamentos/paginas/agendar.php.
// Recebe uma data e devolve, em JSON, a lista de horários daquele
// barbeiro (o barbeiro logado) para o dia, indicando quais já têm
// cliente agendado (vermelho) e quais estão livres (verde).
//
// Se ainda não existir nenhum horário cadastrado para aquele dia,
// a grade padrão da barbearia (08:00–11:20 e 13:00–19:40, a cada
// 40min, com intervalo de almoço entre 11:20 e 13:00) é criada
// automaticamente na tabela Horario — assim os horários sempre
// vêm do banco, sem precisar de cadastro manual prévio.

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
$data       = $_GET['data'] ?? '';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'erro' => 'Data inválida.']);
    exit;
}

// ---------- Garante que a grade do dia existe ----------
HorarioService::garantirGradeDoDia($pdo, $idBarbeiro, $data);

// ---------- Busca a grade do dia, já com dados do agendamento (se houver) ----------
// "ac" (agendamento concluído/ausente): traz, só informativamente, o
// cliente do ÚLTIMO agendamento CONCLUÍDO OU marcado como AUSENTE
// (Cliente Ausente) daquele horário/dia (se houver e não houver nenhum
// agendamento ATIVO ocupando o slot agora — ver mapeamento abaixo). Isso é
// o que permite a grade mostrar "Concluído — Nome" ou "Ausente — Nome" em
// vez de simplesmente "Vago" num horário que já foi atendido/tentado e
// liberado (ver scriptBD/atualizacao_liberar_horario_concluido.sql e
// scriptBD/atualizacao_status_ausente.sql), sem tirar a possibilidade de
// reservar um novo cliente nesse mesmo slot depois — LEFT JOIN puramente
// aditivo, não interfere em nada do que já existia. ac.Status decide, do
// lado do PHP, se o rótulo/cor exibido é de "concluído" ou de "ausente".
$sql = "SELECT
            h.idHorario,
            h.hora,
            h.disponivel,
            a.idAgendamento,
            a.idServico,
            a.Status,
            a.Observacao,
            a.IncluirBarba,
            a.Valor,
            a.grupo_agendamento,
            a.eh_principal_agendamento,
            a.cobranca_duplicado,
            a.fidelidade,
            a.grupo_recorrencia,
            c.idCliente,
            c.nome      AS cliente_nome,
            c.telefone  AS cliente_telefone,
            s.nome      AS servico_nome,
            cc.nome     AS concluido_cliente_nome,
            ac.Status   AS concluido_status
        FROM Horario h
        LEFT JOIN Agendamentos a ON a.idHorario = h.idHorario AND a.Status IN ('agendado', 'confirmado')
        LEFT JOIN Cliente c ON c.idCliente = a.idCliente
        LEFT JOIN Servico s ON s.idServico = a.idServico
        LEFT JOIN Agendamentos ac ON ac.idHorario = h.idHorario
            AND ac.Status IN ('concluido', 'ausente')
            AND ac.idAgendamento = (
                SELECT MAX(ac2.idAgendamento)
                FROM Agendamentos ac2
                WHERE ac2.idHorario = h.idHorario AND ac2.Status IN ('concluido', 'ausente')
            )
        LEFT JOIN Cliente cc ON cc.idCliente = ac.idCliente
        WHERE h.id_barbeiro = :b AND h.data = :d
        ORDER BY h.hora ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute(['b' => $idBarbeiro, 'd' => $data]);
$linhas = $stmt->fetchAll();

$horarios = array_map(function ($linha) {
    $ocupado = $linha['idAgendamento'] !== null;
    // Agendamento duplicado (dois horários consecutivos para um só
    // atendimento, ver includes/... e Agendamentos/scripts/agendamento_salvar.php):
    // qualquer uma das duas linhas tem grupo_agendamento preenchido.
    $duplicado = $ocupado && $linha['grupo_agendamento'] !== null;

    return [
        'idHorario'      => (int) $linha['idHorario'],
        'hora'           => substr($linha['hora'], 0, 5),
        'ocupado'        => $ocupado,
        // Inativado manualmente pelo barbeiro (só quando livre; um horário
        // ocupado é sempre considerado disponível=false por já ter cliente).
        'disponivel'     => $ocupado ? true : (bool) $linha['disponivel'],
        'idAgendamento'  => $ocupado ? (int) $linha['idAgendamento'] : null,
        'status'         => $ocupado ? $linha['Status'] : null,
        'cliente'        => $ocupado ? [
            'idCliente' => (int) $linha['idCliente'],
            'nome'      => $linha['cliente_nome'],
            'telefone'  => $linha['cliente_telefone'],
        ] : null,
        'servico'        => $ocupado ? $linha['servico_nome'] : null,
        // idServico do agendamento (o que foi ORIGINALMENTE agendado) — usado
        // pelo modal "Concluir Agendamento" pra pré-selecionar o serviço no
        // SELECT de "serviço realmente realizado" (ver agendamento_concluir.php).
        'idServico'      => $ocupado ? (int) $linha['idServico'] : null,
        'incluirBarba'   => $ocupado ? (bool) $linha['IncluirBarba'] : false,
        'observacao'     => $ocupado ? $linha['Observacao'] : null,
        'valor'          => $ocupado ? $linha['Valor'] : null,
        'duplicado'         => $duplicado,
        'grupoAgendamento'  => $duplicado ? $linha['grupo_agendamento'] : null,
        'ehPrincipal'       => $duplicado ? (bool) $linha['eh_principal_agendamento'] : null,
        'cobrancaDuplicado' => $duplicado ? $linha['cobranca_duplicado'] : null,
        // Agendamento de Fidelidade (ver includes/FidelidadeService.php):
        // toda ocorrência da série (a "semente" e as futuras geradas
        // automaticamente) carrega o mesmo código e grupo_recorrencia —
        // usado pelo frontend para mostrar o botão "Reagendar".
        'fidelidade'        => $ocupado ? $linha['fidelidade'] : null,
        'grupoRecorrencia'  => $ocupado ? $linha['grupo_recorrencia'] : null,
        // Nome do cliente do último atendimento CONCLUÍDO neste horário —
        // só preenchido quando o slot está livre agora (ninguém ativo
        // ocupando) E o último desfecho foi realmente uma conclusão (não
        // um "Cliente Ausente", ver ausenteNome abaixo). Usado pela grade
        // para mostrar "Concluído — Nome" em vez de "Vago" num horário que
        // já foi atendido (ver renderizarListaHorarios em
        // Agendamentos/paginas/agendar.php).
        'concluidoNome'     => (!$ocupado && $linha['concluido_cliente_nome'] && $linha['concluido_status'] === 'concluido') ? $linha['concluido_cliente_nome'] : null,
        // Mesma ideia, mas para "Cliente Ausente" — usado pela grade para
        // mostrar "Ausente — Nome" numa cor diferente de "Concluído".
        'ausenteNome'       => (!$ocupado && $linha['concluido_cliente_nome'] && $linha['concluido_status'] === 'ausente') ? $linha['concluido_cliente_nome'] : null,
    ];
}, $linhas);

// Dia bloqueado pelo barbeiro (ver DiaBloqueado / dia_bloqueio_status.php):
// não apaga nem esconde os horários/agendamentos já existentes — só
// informa o frontend pra impedir a criação de NOVOS agendamentos aqui
// (também validado de novo em agendamento_salvar.php, nunca só no front).
$diaBloqueado = HorarioService::diaBloqueado($pdo, $idBarbeiro, $data);

echo json_encode(['ok' => true, 'data' => $data, 'horarios' => $horarios, 'diaBloqueado' => $diaBloqueado]);
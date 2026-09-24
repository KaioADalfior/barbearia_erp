<?php
// agendamento_concluir.php
// Endpoint AJAX (POST) chamado pelo modal "Concluir Agendamento" em
// Agendamentos/paginas/agendar.php.
//
// Recebe, além do agendamento, a(s) forma(s) de pagamento (mesmo
// componente usado em Financeiro > Cadastrar Baixa) e a flag "fiado".
// Marca o agendamento como concluído (o registro nunca é apagado — fica
// no banco com Status = 'concluido' para histórico) e cria automaticamente
// o lançamento financeiro correspondente:
//   - fiado = 0 -> lançamento PAGO, entra normalmente nas receitas.
//   - fiado = 1 -> lançamento PENDENTE, fica fora do cálculo de receitas
//                  até ser recebido em Financeiro > Fiados.
// O horário volta a ficar disponível na grade, igual ao cancelamento.
//
// Agendamento duplicado (dois horários vinculados por grupo_agendamento):
// concluir QUALQUER UMA das duas linhas (a principal ou a secundária)
// conclui as DUAS automaticamente, de forma simétrica. O lançamento
// financeiro é criado UMA ÚNICA VEZ, sempre a partir da linha PRINCIPAL
// (que carrega o valor cobrado — 1x ou 2x o serviço); a linha secundária
// só é marcada como concluída e tem seu horário liberado, sem gerar
// nenhum lançamento próprio (evita duplicidade no financeiro). Pra
// concluir SÓ UM dos dois horários (deixando o outro em aberto), ver
// agendamento_concluir_individual.php.
//
// Serviço realmente realizado: o modal "Concluir Agendamento" deixa o
// barbeiro escolher, num SELECT alimentado pelos serviços cadastrados no
// banco, qual serviço foi de fato prestado — pode ser diferente do que foi
// originalmente agendado (ex.: agendou "Corte + Barba" mas só fez
// "Corte"). Isso vale TANTO pra agendamento avulso quanto pra duplicado:
//   - avulso: um único campo, idServicoRealizado.
//   - duplicado: DOIS campos independentes — idServicoRealizado (Horário
//     1/principal) e idServicoRealizadoSecundario (Horário 2/secundário)
//     — cobre o caso de "o pai vai com o filho e corta outro corte do já
//     agendado", em que cada pessoa pode ter feito um corte diferente do
//     que constava no agendamento original. O valor final cobrado (no
//     lançamento único, sempre pela linha principal) é recalculado a
//     partir dos serviços efetivamente escolhidos: só o do Horário 1
//     (cobranca_duplicado = 'um') ou a soma dos dois (cobranca_duplicado
//     = 'dois') — mesma regra de cobrança já definida na criação do
//     agendamento, só que agora aplicada aos serviços REALMENTE feitos.
// Quando informado, o Agendamento é atualizado (idServico + Valor) ANTES
// de gerar o lançamento financeiro, então tudo que lê a partir daí
// (financeiro, histórico do cliente, relatórios) já reflete só o que foi
// realmente cobrado.
//
// "Cliente Ausente" (ausente = 1): o cliente não veio. O horário é
// liberado exatamente como numa conclusão normal (grade mostra "Ausente -
// Nome do Cliente" em vez de "Concluído - Nome"), mas NÃO é gerado nenhum
// lançamento financeiro (não houve cobrança) — nem pago, nem fiado. Forma
// de pagamento e "fiado" são ignorados nesse caso. Agendamento duplicado
// segue a mesma regra simétrica: marcar qualquer uma das duas linhas como
// ausente marca as duas, sem gerar lançamento nenhum.

require_once __DIR__ . '/../../includes/session.php';
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/guard.php';
exigirSessao(['barbeiro'], json: true);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/FinanceiroService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'erro' => 'Método não permitido.']);
    exit;
}

$idBarbeiro                    = (int) $_SESSION['id'];
$idAgendamento                 = (int) ($_POST['idAgendamento'] ?? 0);
$ausente                       = !empty($_POST['ausente']);
$fiado                         = !$ausente && !empty($_POST['fiado']);
$formasBrutas                  = $_POST['formas'] ?? [];
$formasBrutas                  = is_array($formasBrutas) ? $formasBrutas : [];
$idServicoRealizado            = (int) ($_POST['idServicoRealizado'] ?? 0);
$idServicoRealizadoSecundario  = (int) ($_POST['idServicoRealizadoSecundario'] ?? 0);

if ($idAgendamento <= 0) {
    echo json_encode(['ok' => false, 'erro' => 'Agendamento inválido.']);
    exit;
}

$formas = FinanceiroService::normalizarFormasPagamento($formasBrutas);

// Forma de pagamento só é obrigatória quando NÃO é fiado e NÃO é "Cliente
// Ausente" — no fiado ela fica bloqueada no formulário e só é definida no
// recebimento (Financeiro > Fiados); no ausente não existe cobrança
// nenhuma, então não faz sentido pedir forma de pagamento.
if (!$ausente && !$fiado && empty($formas)) {
    echo json_encode(['ok' => false, 'erro' => 'Selecione ao menos uma forma de pagamento.']);
    exit;
}

$stmt = $pdo->prepare(
    "SELECT a.idAgendamento, a.idHorario, a.idCliente, a.idServico, a.Valor, a.Status,
            a.grupo_agendamento, a.eh_principal_agendamento, a.cobranca_duplicado,
            c.nome AS clienteNome, s.nome AS servicoNome
     FROM Agendamentos a
     INNER JOIN Horario h ON h.idHorario = a.idHorario
     INNER JOIN Cliente c ON c.idCliente = a.idCliente
     INNER JOIN Servico s ON s.idServico = a.idServico
     WHERE a.idAgendamento = :id AND h.id_barbeiro = :b AND a.Status <> 'cancelado'"
);
$stmt->execute(['id' => $idAgendamento, 'b' => $idBarbeiro]);
$agendamento = $stmt->fetch();

if (!$agendamento) {
    echo json_encode(['ok' => false, 'erro' => 'Agendamento não encontrado.']);
    exit;
}

if ($agendamento['Status'] === 'concluido') {
    echo json_encode(['ok' => false, 'erro' => 'Esse agendamento já está concluído.']);
    exit;
}

if ($agendamento['Status'] === 'ausente') {
    echo json_encode(['ok' => false, 'erro' => 'Esse agendamento já foi marcado como Cliente Ausente.']);
    exit;
}

/**
 * Busca um serviço ATIVO pelo id. Usada tanto pro serviço do Horário 1
 * quanto do Horário 2 — mesma validação nos dois casos.
 */
function buscarServicoAtivo(PDO $pdo, int $idServico): ?array
{
    if ($idServico <= 0) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT idServico, nome, valor FROM Servico WHERE idServico = :s AND ativo = 1');
    $stmt->execute(['s' => $idServico]);
    $servico = $stmt->fetch();
    return $servico ?: null;
}

try {
    $pdo->beginTransaction();

    // ---------- Agendamento duplicado: descobre a linha irmã (se houver) ----------
    // Sempre roteia o lançamento financeiro pela linha PRINCIPAL, não
    // importa qual das duas o barbeiro clicou.
    $agendamentoPrincipal  = $agendamento;
    $agendamentoSecundario = null;

    if ($agendamento['grupo_agendamento'] !== null) {
        $stmtIrmao = $pdo->prepare(
            "SELECT a.idAgendamento, a.idHorario, a.idCliente, a.idServico, a.Valor, a.Status,
                    a.eh_principal_agendamento, a.cobranca_duplicado, c.nome AS clienteNome, s.nome AS servicoNome
             FROM Agendamentos a
             INNER JOIN Horario h ON h.idHorario = a.idHorario
             INNER JOIN Cliente c ON c.idCliente = a.idCliente
             INNER JOIN Servico s ON s.idServico = a.idServico
             WHERE a.grupo_agendamento = :g AND a.idAgendamento <> :id AND h.id_barbeiro = :b AND a.Status <> 'cancelado'
             FOR UPDATE"
        );
        $stmtIrmao->execute(['g' => $agendamento['grupo_agendamento'], 'id' => $agendamento['idAgendamento'], 'b' => $idBarbeiro]);
        $irmao = $stmtIrmao->fetch();

        if ($irmao) {
            if ((bool) $agendamento['eh_principal_agendamento']) {
                $agendamentoPrincipal  = $agendamento;
                $agendamentoSecundario = $irmao;
            } else {
                $agendamentoPrincipal  = $irmao;
                $agendamentoSecundario = $agendamento;
            }
        }
    }

    // ---------- Serviço realmente realizado (SELECT do modal Concluir) ----------
    // Vale tanto pra agendamento avulso quanto pra duplicado (ver
    // cabeçalho do arquivo) — a única diferença é que o duplicado tem DOIS
    // campos independentes, um por horário.
    if (!$ausente) {
        if ($idServicoRealizado > 0) {
            $servicoRealizadoPrincipal = buscarServicoAtivo($pdo, $idServicoRealizado);
            if (!$servicoRealizadoPrincipal) {
                $pdo->rollBack();
                echo json_encode(['ok' => false, 'erro' => 'Serviço selecionado para o Horário 1 é inválido ou está inativo.']);
                exit;
            }

            $stmtAtualizaServico = $pdo->prepare('UPDATE Agendamentos SET idServico = :s, Valor = :v WHERE idAgendamento = :id');
            $stmtAtualizaServico->execute([
                's'  => $servicoRealizadoPrincipal['idServico'],
                'v'  => $servicoRealizadoPrincipal['valor'],
                'id' => $agendamentoPrincipal['idAgendamento'],
            ]);

            $agendamentoPrincipal['idServico']   = (int) $servicoRealizadoPrincipal['idServico'];
            $agendamentoPrincipal['servicoNome'] = $servicoRealizadoPrincipal['nome'];
            $agendamentoPrincipal['Valor']       = (float) $servicoRealizadoPrincipal['valor'];
        }

        if ($agendamentoSecundario && $idServicoRealizadoSecundario > 0) {
            $servicoRealizadoSecundario = buscarServicoAtivo($pdo, $idServicoRealizadoSecundario);
            if (!$servicoRealizadoSecundario) {
                $pdo->rollBack();
                echo json_encode(['ok' => false, 'erro' => 'Serviço selecionado para o Horário 2 é inválido ou está inativo.']);
                exit;
            }

            $stmtAtualizaServicoSecundario = $pdo->prepare('UPDATE Agendamentos SET idServico = :s WHERE idAgendamento = :id');
            $stmtAtualizaServicoSecundario->execute([
                's'  => $servicoRealizadoSecundario['idServico'],
                'id' => $agendamentoSecundario['idAgendamento'],
            ]);

            $agendamentoSecundario['idServico']   = (int) $servicoRealizadoSecundario['idServico'];
            $agendamentoSecundario['servicoNome'] = $servicoRealizadoSecundario['nome'];
            $agendamentoSecundario['Valor']       = (float) $servicoRealizadoSecundario['valor'];
        }

        // Recalcula o valor cobrado (sempre pela linha principal) a partir
        // dos serviços REALMENTE selecionados acima — nunca do que foi só
        // agendado. Mesma regra de cobranca_duplicado já definida na
        // criação: 'um' cobra só o Horário 1; 'dois' soma os dois.
        if ($agendamentoSecundario) {
            $novoValorPrincipal = $agendamentoPrincipal['Valor'];
            if (($agendamentoPrincipal['cobranca_duplicado'] ?? null) === 'dois') {
                $novoValorPrincipal += $agendamentoSecundario['Valor'];
            }

            if ($novoValorPrincipal !== $agendamentoPrincipal['Valor']) {
                $stmtAtualizaValorPrincipal = $pdo->prepare('UPDATE Agendamentos SET Valor = :v WHERE idAgendamento = :id');
                $stmtAtualizaValorPrincipal->execute(['v' => $novoValorPrincipal, 'id' => $agendamentoPrincipal['idAgendamento']]);
            }
            $agendamentoPrincipal['Valor'] = $novoValorPrincipal;
        }
    }

    if ($ausente) {
        // ---------- Cliente Ausente: libera o horário, NÃO gera lançamento ----------
        FinanceiroService::marcarAgendamentoAusente($pdo, $agendamentoPrincipal);

        if ($agendamentoSecundario) {
            FinanceiroService::marcarAgendamentoAusente($pdo, $agendamentoSecundario);
        }

        $idLancamento = null;
    } else {
        // Nome do serviço do horário secundário (se houver) — usado para o
        // lançamento/fiado exibir os DOIS cortes quando os dois foram cobrados
        // (cobranca_duplicado = 'dois'), e só o principal quando foi cobrado
        // apenas um (ver FinanceiroService::concluirAgendamentoComPagamento).
        $agendamentoPrincipal['servicoSecundarioNome'] = $agendamentoSecundario['servicoNome'] ?? null;

        // Único lançamento financeiro do atendimento, sempre com o valor da
        // linha principal (1x ou 2x o serviço, já recalculado acima a
        // partir do que foi realmente escolhido).
        $idLancamento = FinanceiroService::concluirAgendamentoComPagamento($pdo, $agendamentoPrincipal, $formas, $fiado, $idBarbeiro);

        // Conclui também a linha secundária (sem gerar nenhum lançamento
        // próprio) e libera o horário dela — mesmo comportamento simétrico
        // visto pelo barbeiro em qualquer uma das duas linhas.
        if ($agendamentoSecundario) {
            $stmtConcluiSecundario = $pdo->prepare("UPDATE Agendamentos SET Status = 'concluido' WHERE idAgendamento = :id");
            $stmtConcluiSecundario->execute(['id' => $agendamentoSecundario['idAgendamento']]);

            $stmtLiberaSecundario = $pdo->prepare('UPDATE Horario SET disponivel = 1 WHERE idHorario = :h');
            $stmtLiberaSecundario->execute(['h' => $agendamentoSecundario['idHorario']]);
        }
    }

    $pdo->commit();

    $camposLog = [
        ['name' => '?? Agendamento', 'value' => '#' . $agendamentoPrincipal['idAgendamento'], 'inline' => true],
        ['name' => '?? Cliente', 'value' => $agendamentoPrincipal['clienteNome'], 'inline' => true],
        ['name' => '?? Serviço', 'value' => $agendamentoPrincipal['servicoNome'], 'inline' => true],
        ['name' => '?? Status financeiro', 'value' => $ausente ? 'Cliente ausente (sem cobrança)' : ($fiado ? 'Fiado (pendente)' : 'Pago'), 'inline' => true],
    ];

    if ($agendamentoSecundario) {
        $camposLog[] = ['name' => '?? Duplicado', 'value' => ($ausente ? 'Marcado como ausente junto com #' : 'Concluído junto com #') . $agendamentoSecundario['idAgendamento'] . ' (' . $agendamentoSecundario['servicoNome'] . ')', 'inline' => true];
    }

    DiscordLogger::agendamentos(
        $ausente ? '?? Cliente ausente' : '? Agendamento concluído',
        $camposLog,
        $ausente ? DiscordLogger::COR_ALERTA : DiscordLogger::COR_SUCESSO
    );

    echo json_encode([
        'ok'                      => true,
        'idLancamento'            => $idLancamento,
        'fiado'                   => $fiado,
        'ausente'                 => $ausente,
        'idAgendamentoSecundario' => $agendamentoSecundario['idAgendamento'] ?? null,
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    DiscordLogger::erro('?? Falha ao concluir agendamento', $e);
    http_response_code(500);
    echo json_encode(['ok' => false, 'erro' => 'Erro ao concluir o agendamento.']);
}
<?php
// agendamento_liberar_secundario.php
// Endpoint AJAX (POST) chamado pelo modal de cancelamento em
// Agendamentos/paginas/agendar.php quando o agendamento é um "duplicado"
// (dois horários vinculados por grupo_agendamento) e o barbeiro escolhe
// cancelar SÓ UM dos dois horários, mantendo o outro ativo normalmente.
//
// Funciona pros dois lados do par, não só pro "Horário 2": cancela
// exatamente o idAgendamento recebido e libera só o Horario dele. O outro
// agendamento do par:
//   - Se o que foi cancelado era o SECUNDÁRIO (Valor 0.00, nunca gerou
//     lançamento próprio): o principal fica 100% intocado — mesmo Status,
//     mesmo Valor. É o caso comum ("terminei mais rápido, devolvo o
//     horário extra pra agenda"), o cliente continua pagando o combinado.
//   - Se o que foi cancelado era o PRINCIPAL (o que carregava o valor
//     combinado dos dois cortes): o secundário sobrevivente é promovido a
//     um agendamento AVULSO normal — grupo_agendamento e
//     cobranca_duplicado somem, eh_principal_agendamento vira 1, e o Valor
//     é recalculado pelo preço do PRÓPRIO serviço dele (não o valor
//     combinado que estava no principal cancelado). Sem isso, esse
//     sobrevivente ficaria com Valor 0.00 pra sempre — cobraria de graça
//     ao ser concluído.

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

$idBarbeiro    = (int) $_SESSION['id'];
$idAgendamento = (int) ($_POST['idAgendamento'] ?? 0);

if ($idAgendamento <= 0) {
    echo json_encode(['ok' => false, 'erro' => 'Agendamento inválido.']);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "SELECT a.idAgendamento, a.idHorario, a.Status, a.grupo_agendamento, a.eh_principal_agendamento, h.hora
         FROM Agendamentos a
         INNER JOIN Horario h ON h.idHorario = a.idHorario
         WHERE a.idAgendamento = :id AND h.id_barbeiro = :b"
    );
    $stmt->execute(['id' => $idAgendamento, 'b' => $idBarbeiro]);
    $agendamento = $stmt->fetch();

    if (!$agendamento) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'erro' => 'Agendamento não encontrado.']);
        exit;
    }

    if ($agendamento['Status'] === 'cancelado') {
        // Idempotente: já foi cancelado (ex.: clique duplo) — o resultado
        // pedido já é verdade, não é erro.
        $pdo->commit();
        echo json_encode(['ok' => true, 'hora' => substr($agendamento['hora'], 0, 5), 'jaCancelado' => true]);
        exit;
    }

    if (!in_array($agendamento['Status'], ['agendado', 'confirmado'], true)) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'erro' => 'Esse agendamento não pode mais ser cancelado.']);
        exit;
    }

    if ($agendamento['grupo_agendamento'] === null) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'erro' => 'Este agendamento não faz parte de um duplicado.']);
        exit;
    }

    // Trava este agendamento + o(s) outro(s) do mesmo grupo numa única
    // query, em ordem crescente de idAgendamento — mesma técnica usada em
    // agendamento_cancelar.php pra nunca correr risco de deadlock quando
    // os dois lados são mexidos quase ao mesmo tempo.
    $stmtGrupo = $pdo->prepare(
        "SELECT a.idAgendamento, a.idHorario, a.idServico, a.Valor, a.Status, a.eh_principal_agendamento, h.hora
         FROM Agendamentos a
         INNER JOIN Horario h ON h.idHorario = a.idHorario
         WHERE a.grupo_agendamento = :g AND h.id_barbeiro = :b
         ORDER BY a.idAgendamento ASC
         FOR UPDATE"
    );
    $stmtGrupo->execute(['g' => $agendamento['grupo_agendamento'], 'b' => $idBarbeiro]);
    $linhasGrupo = $stmtGrupo->fetchAll();

    $sobrevivente = null;
    foreach ($linhasGrupo as $linha) {
        if ((int) $linha['idAgendamento'] !== $idAgendamento
            && in_array($linha['Status'], ['agendado', 'confirmado'], true)
        ) {
            $sobrevivente = $linha;
            break;
        }
    }

    // Cancela SÓ o agendamento pedido e libera só o Horario dele.
    $stmtCancela = $pdo->prepare("UPDATE Agendamentos SET Status = 'cancelado' WHERE idAgendamento = :id");
    $stmtCancela->execute(['id' => $idAgendamento]);

    $stmtLibera = $pdo->prepare('UPDATE Horario SET disponivel = 1 WHERE idHorario = :h');
    $stmtLibera->execute(['h' => $agendamento['idHorario']]);

    $promovido = false;

    // Se o que foi cancelado era o PRINCIPAL (carregava o valor combinado),
    // o sobrevivente precisa ser promovido a agendamento avulso normal,
    // com o valor recalculado pelo PRÓPRIO serviço dele — senão ficaria
    // com Valor 0.00 pra sempre (ver cabeçalho do arquivo).
    if ($sobrevivente && (bool) $agendamento['eh_principal_agendamento']) {
        $stmtServico = $pdo->prepare('SELECT valor FROM Servico WHERE idServico = :s');
        $stmtServico->execute(['s' => $sobrevivente['idServico']]);
        $precoProprio = $stmtServico->fetchColumn();

        // Serviço pode ter sido excluído/inativado nesse meio-tempo — nesse
        // caso mantém o valor que já estava lá em vez de zerar o preço.
        $novoValor = $precoProprio !== false ? (float) $precoProprio : (float) $sobrevivente['Valor'];

        $stmtPromove = $pdo->prepare(
            'UPDATE Agendamentos
             SET grupo_agendamento = NULL, eh_principal_agendamento = 1, cobranca_duplicado = NULL, Valor = :valor
             WHERE idAgendamento = :id'
        );
        $stmtPromove->execute(['valor' => $novoValor, 'id' => $sobrevivente['idAgendamento']]);

        $promovido = true;
    }

    $pdo->commit();

    DiscordLogger::agendamentos('🗑️ Um horário do duplicado liberado', [
        ['name' => '🆔 Agendamento cancelado', 'value' => '#' . $idAgendamento, 'inline' => true],
        ['name' => '🕐 Horário liberado', 'value' => substr($agendamento['hora'], 0, 5), 'inline' => true],
        ['name' => '🟣 Outro horário do par', 'value' => $sobrevivente
            ? ('#' . $sobrevivente['idAgendamento'] . ($promovido ? ' (promovido a avulso)' : ' (mantido como estava)'))
            : 'nenhum ativo', 'inline' => true],
    ], DiscordLogger::COR_ALERTA);

    echo json_encode([
        'ok'        => true,
        'hora'      => substr($agendamento['hora'], 0, 5),
        'promovido' => $promovido,
    ]);
} catch (\Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    DiscordLogger::erro('💥 Falha ao liberar um horário do duplicado', $e);
    http_response_code(500);
    echo json_encode(['ok' => false, 'erro' => 'Não foi possível cancelar agora. Tente novamente em alguns segundos.']);
}

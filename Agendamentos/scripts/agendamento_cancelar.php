<?php
// agendamento_cancelar.php
// Endpoint AJAX (POST) chamado pelo modal de cancelamento em
// Agendamentos/paginas/agendar.php, opção "Cancelar este corte": cancela
// SOMENTE o agendamento clicado (Status = 'cancelado' só nesta linha) e
// libera o horário. Qualquer outra ocorrência futura da mesma fidelidade
// (mesmo grupo_recorrencia) permanece intacta — para cancelar a sequência
// inteira, ver agendamento_cancelar_todos_futuros.php ("Cancelar todos os
// cortes").
//
// Agendamento duplicado (dois horários vinculados por grupo_agendamento):
// cancelar QUALQUER UMA das duas linhas cancela as DUAS e libera os DOIS
// horários — nunca deixa uma linha "órfã" ainda marcada como duplicada
// enquanto a outra já foi cancelada.
//
// Só cancela agendamentos ainda ATIVOS (agendado/confirmado) — nunca um
// já 'concluido', para não corromper o histórico do cliente (relatórios,
// financeiro e fidelidade dependem dele continuar 'concluido' para sempre).
//
// Concorrência (bug corrigido): quando o barbeiro cancela um duplicado, as
// DUAS linhas já são canceladas nesta mesma chamada (ver acima). Se, por
// qualquer motivo, chegar uma segunda chamada quase ao mesmo tempo para a
// OUTRA metade do mesmo duplicado (ex.: duplo-clique, tela que ainda não
// recarregou), duas coisas precisam estar garantidas:
//   1) As duas transações nunca podem travar as duas linhas em ordem
//      invertida uma da outra — isso é deadlock clássico do MySQL, e um
//      deadlock não tratado aqui virava um erro fatal (HTML/texto puro em
//      vez de JSON), quebrando o parse no front e aparecendo pro usuário
//      como "Erro de Conexão". Por isso agora as linhas do grupo são
//      sempre travadas (FOR UPDATE) numa única query, em ordem crescente
//      de idAgendamento — nunca "primeiro a minha, depois a da outra"
//      como antes, que fazia cada lado travar em ordem oposta.
//   2) Se a linha pedida já estiver cancelada (porque a outra metade já a
//      cancelou junto, ver acima) isso NÃO é mais tratado como erro — o
//      resultado que o usuário queria (esse agendamento cancelado) já foi
//      alcançado, então respondemos ok:true normalmente (idempotente).

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

$idBarbeiro     = (int) $_SESSION['id'];
$idAgendamento  = (int) ($_POST['idAgendamento'] ?? 0);

if ($idAgendamento <= 0) {
    echo json_encode(['ok' => false, 'erro' => 'Agendamento inválido.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Confirma que o agendamento pedido existe e pertence ao barbeiro
    // logado, e já descobre o grupo_agendamento (se houver) — mas SEM
    // travar nada ainda, porque a linha ainda pode nem existir mais como
    // ativa (ver bloco de idempotência abaixo).
    $stmt = $pdo->prepare(
        "SELECT a.idAgendamento, a.idHorario, a.grupo_agendamento, a.Status, c.nome AS clienteNome, s.nome AS servicoNome
         FROM Agendamentos a
         INNER JOIN Horario h ON h.idHorario = a.idHorario
         INNER JOIN Cliente c ON c.idCliente = a.idCliente
         INNER JOIN Servico s ON s.idServico = a.idServico
         WHERE a.idAgendamento = :id AND h.id_barbeiro = :b"
    );
    $stmt->execute(['id' => $idAgendamento, 'b' => $idBarbeiro]);
    $agendamento = $stmt->fetch();

    if (!$agendamento) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'erro' => 'Agendamento não encontrado.']);
        exit;
    }

    // Idempotente: se este agendamento já está cancelado (ex.: a outra
    // metade de um duplicado foi cancelada segundos antes e já cancelou
    // este junto — ver cabeçalho), não é erro. O estado que o barbeiro
    // pediu (este agendamento cancelado) já é verdade.
    if ($agendamento['Status'] === 'cancelado') {
        $pdo->commit();
        echo json_encode(['ok' => true, 'idAgendamentoSecundario' => null, 'jaCancelado' => true]);
        exit;
    }

    if (!in_array($agendamento['Status'], ['agendado', 'confirmado'], true)) {
        // 'concluido' ou qualquer status futuro que não seja cancelável.
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'erro' => 'Esse agendamento não pode mais ser cancelado.']);
        exit;
    }

    // ---------- Trava TODAS as linhas envolvidas (esta + a irmã, se
    // houver) numa ÚNICA query, ordenadas por idAgendamento ----------
    // Isso é o que garante que duas chamadas concorrentes (uma pra cada
    // metade do mesmo duplicado) sempre tentam travar as linhas na MESMA
    // ordem (a de menor id primeiro) — nunca em ordem invertida uma da
    // outra, que é justamente o que causa deadlock no MySQL.
    if ($agendamento['grupo_agendamento'] !== null) {
        $stmtGrupo = $pdo->prepare(
            "SELECT a.idAgendamento, a.idHorario, a.Status
             FROM Agendamentos a
             INNER JOIN Horario h ON h.idHorario = a.idHorario
             WHERE a.grupo_agendamento = :g AND h.id_barbeiro = :b
             ORDER BY a.idAgendamento ASC
             FOR UPDATE"
        );
        $stmtGrupo->execute(['g' => $agendamento['grupo_agendamento'], 'b' => $idBarbeiro]);
        $linhasGrupo = $stmtGrupo->fetchAll();
    } else {
        $stmtSolo = $pdo->prepare('SELECT idAgendamento, idHorario, Status FROM Agendamentos WHERE idAgendamento = :id FOR UPDATE');
        $stmtSolo->execute(['id' => $agendamento['idAgendamento']]);
        $linhasGrupo = $stmtSolo->fetchAll();
    }

    $agendamentoSecundario = null;
    $stmtCancela = $pdo->prepare("UPDATE Agendamentos SET Status = 'cancelado' WHERE idAgendamento = :id");
    $stmtLibera  = $pdo->prepare('UPDATE Horario SET disponivel = 1 WHERE idHorario = :h');

    foreach ($linhasGrupo as $linha) {
        // Já pode ter sido cancelada entre a checagem lá em cima e agora
        // (outra transação venceu a corrida) — não faz nada com ela, só
        // não conta como "secundária cancelada agora".
        if ($linha['Status'] !== 'agendado' && $linha['Status'] !== 'confirmado') {
            continue;
        }

        $stmtCancela->execute(['id' => $linha['idAgendamento']]);
        $stmtLibera->execute(['h' => $linha['idHorario']]);

        if ((int) $linha['idAgendamento'] !== (int) $agendamento['idAgendamento']) {
            $agendamentoSecundario = $linha;
        }
    }

    $pdo->commit();

    $camposLog = [
        ['name' => '🆔 Agendamento', 'value' => '#' . $agendamento['idAgendamento'], 'inline' => true],
        ['name' => '👤 Cliente', 'value' => $agendamento['clienteNome'], 'inline' => true],
        ['name' => '✂️ Serviço', 'value' => $agendamento['servicoNome'], 'inline' => true],
    ];

    if ($agendamentoSecundario) {
        $camposLog[] = ['name' => '🟣 Duplicado', 'value' => 'Cancelado junto com #' . $agendamentoSecundario['idAgendamento'], 'inline' => true];
    }

    DiscordLogger::agendamentos('🗑️ Agendamento cancelado', $camposLog, DiscordLogger::COR_ALERTA);

    echo json_encode(['ok' => true, 'idAgendamentoSecundario' => $agendamentoSecundario['idAgendamento'] ?? null]);
} catch (\Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    DiscordLogger::erro('💥 Falha ao cancelar agendamento', $e);
    http_response_code(500);
    echo json_encode(['ok' => false, 'erro' => 'Não foi possível cancelar agora. Tente novamente em alguns segundos.']);
}

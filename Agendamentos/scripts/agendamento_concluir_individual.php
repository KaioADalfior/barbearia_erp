<?php
// agendamento_concluir_individual.php
// Endpoint AJAX (POST) chamado pelo botão "Concluir só este horário" no
// modal de Concluir Agendamento, em Agendamentos/paginas/agendar.php —
// só aparece quando o agendamento é um "duplicado" (dois horários
// vinculados por grupo_agendamento) e o horário irmão ainda está ativo.
//
// Diferente do Concluir normal (agendamento_concluir.php), que sempre
// conclui as DUAS linhas do duplicado juntas, este endpoint conclui SÓ o
// idAgendamento pedido — o outro fica exatamente como estava (ainda
// agendado/confirmado, esperando ser concluído/cancelado depois,
// separadamente). Cobre o caso de, por exemplo, pai e filho terem
// horários vinculados mas um sair antes do outro, ou terem feito cortes
// diferentes do que foi originalmente agendado.
//
// Como este horário passa a ser cobrado e concluído de forma
// independente, ele SEMPRE gera seu próprio lançamento financeiro, pelo
// preço do PRÓPRIO serviço selecionado (nunca o valor combinado que
// estava na linha principal do duplicado). Se o horário que sobra
// (irmão) ainda estiver ativo, ele é promovido a um agendamento AVULSO
// normal — grupo_agendamento e cobranca_duplicado somem,
// eh_principal_agendamento vira 1, e o Valor dele é recalculado pelo
// preço do seu PRÓPRIO serviço — exatamente a mesma promoção já usada em
// agendamento_liberar_secundario.php (cancelamento parcial de duplicado),
// pra ele não ficar com Valor 0.00 quando for concluído depois.

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

$idBarbeiro          = (int) $_SESSION['id'];
$idAgendamento       = (int) ($_POST['idAgendamento'] ?? 0);
$fiado               = !empty($_POST['fiado']);
$formasBrutas        = $_POST['formas'] ?? [];
$formasBrutas        = is_array($formasBrutas) ? $formasBrutas : [];
$idServicoRealizado  = (int) ($_POST['idServicoRealizado'] ?? 0);

if ($idAgendamento <= 0) {
    echo json_encode(['ok' => false, 'erro' => 'Agendamento inválido.']);
    exit;
}

$formas = FinanceiroService::normalizarFormasPagamento($formasBrutas);
if (!$fiado && empty($formas)) {
    echo json_encode(['ok' => false, 'erro' => 'Selecione ao menos uma forma de pagamento.']);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "SELECT a.idAgendamento, a.idHorario, a.idCliente, a.idServico, a.Valor, a.Status, a.grupo_agendamento,
                c.nome AS clienteNome, s.nome AS servicoNome
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

    if (!in_array($agendamento['Status'], ['agendado', 'confirmado'], true)) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'erro' => 'Esse agendamento não pode mais ser concluído.']);
        exit;
    }

    if ($agendamento['grupo_agendamento'] === null) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'erro' => 'Este agendamento não faz parte de um duplicado — use o Concluir normal.']);
        exit;
    }

    // Trava este agendamento + o(s) outro(s) do mesmo grupo numa única
    // query, em ordem crescente de idAgendamento — mesma técnica usada em
    // agendamento_cancelar.php e agendamento_liberar_secundario.php pra
    // nunca correr risco de deadlock.
    $stmtGrupo = $pdo->prepare(
        "SELECT a.idAgendamento, a.idHorario, a.idServico, a.Valor, a.Status
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

    // Serviço realmente realizado NESTE horário (se o barbeiro trocou) —
    // sempre recalcula o Valor cobrado a partir do preço do PRÓPRIO
    // serviço, nunca de um valor combinado herdado do duplicado.
    if ($idServicoRealizado > 0) {
        $stmtServico = $pdo->prepare('SELECT idServico, nome, valor FROM Servico WHERE idServico = :s AND ativo = 1');
        $stmtServico->execute(['s' => $idServicoRealizado]);
        $servicoRealizado = $stmtServico->fetch();

        if (!$servicoRealizado) {
            $pdo->rollBack();
            echo json_encode(['ok' => false, 'erro' => 'Serviço selecionado é inválido ou está inativo.']);
            exit;
        }

        $agendamento['idServico']   = (int) $servicoRealizado['idServico'];
        $agendamento['servicoNome'] = $servicoRealizado['nome'];
        $agendamento['Valor']       = (float) $servicoRealizado['valor'];
    } else {
        // Nenhuma troca: usa o preço ATUAL do próprio serviço já
        // agendado, buscado fresco (nunca o Valor salvo na linha, que
        // pode ainda estar refletindo o total combinado do duplicado).
        $stmtServicoAtual = $pdo->prepare('SELECT valor FROM Servico WHERE idServico = :s');
        $stmtServicoAtual->execute(['s' => $agendamento['idServico']]);
        $precoAtual = $stmtServicoAtual->fetchColumn();
        $agendamento['Valor'] = $precoAtual !== false ? (float) $precoAtual : (float) $agendamento['Valor'];
    }

    $agendamento['servicoSecundarioNome'] = null; // conclusão individual nunca soma um segundo corte

    $idLancamento = FinanceiroService::concluirAgendamentoComPagamento($pdo, $agendamento, $formas, $fiado, $idBarbeiro);

    $promovido = false;

    // O horário que sobra vira um agendamento avulso normal — sem isso,
    // ficaria "duplicado órfão" com Valor 0.00 (se era o secundário) ou
    // ainda carregando o valor combinado antigo (se era o principal).
    if ($sobrevivente) {
        $stmtServicoSobrevivente = $pdo->prepare('SELECT valor FROM Servico WHERE idServico = :s');
        $stmtServicoSobrevivente->execute(['s' => $sobrevivente['idServico']]);
        $precoProprio = $stmtServicoSobrevivente->fetchColumn();
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

    DiscordLogger::agendamentos('✅ Um horário do duplicado concluído individualmente', [
        ['name' => '🆔 Agendamento', 'value' => '#' . $agendamento['idAgendamento'], 'inline' => true],
        ['name' => '👤 Cliente', 'value' => $agendamento['clienteNome'], 'inline' => true],
        ['name' => '✂️ Serviço', 'value' => $agendamento['servicoNome'], 'inline' => true],
        ['name' => '💰 Status financeiro', 'value' => $fiado ? 'Fiado (pendente)' : 'Pago', 'inline' => true],
        ['name' => '🟣 Outro horário do par', 'value' => $sobrevivente
            ? ('#' . $sobrevivente['idAgendamento'] . ($promovido ? ' (promovido a avulso, continua aberto)' : ''))
            : 'nenhum ativo', 'inline' => true],
    ], DiscordLogger::COR_SUCESSO);

    echo json_encode([
        'ok'           => true,
        'idLancamento' => $idLancamento,
        'fiado'        => $fiado,
        'promovido'    => $promovido,
    ]);
} catch (\Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    DiscordLogger::erro('💥 Falha ao concluir horário individual do duplicado', $e);
    http_response_code(500);
    echo json_encode(['ok' => false, 'erro' => 'Não foi possível concluir agora. Tente novamente em alguns segundos.']);
}

<?php
// agendamento_alterar_cliente.php
// Endpoint AJAX (POST) chamado pelo submenu "Alterar agendamento" -> "Alterar
// cliente", em Agendamentos/paginas/agendar.php.
//
// Substitui o CLIENTE de um agendamento já existente, mantendo o mesmo
// idHorario/Data — ou seja, o horário continua sendo o mesmo, só o cliente
// muda. Não cria nenhum agendamento novo, não mexe na grade de horários
// (Horario.disponivel continua 0, o slot continua ocupado, só que agora
// por outro cliente).
//
// Se o agendamento fazia parte de uma sequência de FIDELIDADE
// (fidelidade/grupo_recorrencia preenchidos), essa ligação é removida desta
// linha: a partir de agora ela é uma reserva avulsa do novo cliente, e não
// deve mais ser afetada por operações feitas em cima da fidelidade original
// (ex.: "Reagendar fidelidade" cancela/recria por grupo_recorrencia, sem
// filtrar por cliente — manter o vínculo aqui faria essa operação, feita a
// partir de outra ocorrência do cliente antigo, mexer indevidamente neste
// agendamento que já não é mais dele).
//
// Não é permitido para agendamentos "duplicados" (grupo_agendamento
// preenchido, dois horários vinculados) — trocar o cliente de só uma das
// duas linhas quebraria a lógica de cobrança combinada.
//
// Retorna também os dados do cliente ANTIGO (e do serviço/observação que
// estavam no agendamento) para o frontend oferecer reagendá-lo em seguida
// (ver Agendamentos/scripts/agendamento_criar_para_cliente_existente.php).

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

$idBarbeiro    = (int) $_SESSION['id'];
$idAgendamento = (int) ($_POST['idAgendamento'] ?? 0);
$novoIdCliente = (int) ($_POST['novoIdCliente'] ?? 0);

if ($idAgendamento <= 0 || $novoIdCliente <= 0) {
    echo json_encode(['ok' => false, 'erro' => 'Requisição inválida.']);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "SELECT a.idAgendamento, a.idCliente, a.idServico, a.idHorario, a.Data, a.Valor,
                a.Observacao, a.fidelidade, a.grupo_recorrencia, a.grupo_agendamento,
                h.hora, c.nome AS clienteAtualNome, c.telefone AS clienteAtualTelefone,
                s.nome AS servicoNome, s.valor AS servicoValor
         FROM Agendamentos a
         INNER JOIN Horario h ON h.idHorario = a.idHorario
         INNER JOIN Cliente c ON c.idCliente = a.idCliente
         INNER JOIN Servico s ON s.idServico = a.idServico
         WHERE a.idAgendamento = :id AND h.id_barbeiro = :b AND a.Status IN ('agendado', 'confirmado')
         FOR UPDATE"
    );
    $stmt->execute(['id' => $idAgendamento, 'b' => $idBarbeiro]);
    $agendamento = $stmt->fetch();

    if (!$agendamento) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'erro' => 'Agendamento não encontrado.']);
        exit;
    }

    if ($agendamento['grupo_agendamento'] !== null) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'erro' => 'Este agendamento faz parte de um atendimento duplicado e não pode ter o cliente alterado por aqui.']);
        exit;
    }

    if ($novoIdCliente === (int) $agendamento['idCliente']) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'erro' => 'Selecione um cliente diferente do atual.']);
        exit;
    }

    $stmtNovoCliente = $pdo->prepare('SELECT idCliente, nome, telefone FROM Cliente WHERE idCliente = :id AND ativo = 1');
    $stmtNovoCliente->execute(['id' => $novoIdCliente]);
    $novoCliente = $stmtNovoCliente->fetch();

    if (!$novoCliente) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'erro' => 'Cliente selecionado não encontrado.']);
        exit;
    }

    // Regra: 1 agendamento ativo por cliente por dia — o novo cliente não
    // pode já ter outro agendamento nesta mesma data.
    if (FinanceiroService::clienteJaTemAgendamentoNoDia($pdo, $novoIdCliente, $agendamento['Data'])) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'erro' => 'Este cliente já possui outro agendamento nesse dia.']);
        exit;
    }

    // Guarda os dados do cliente ANTIGO antes de sobrescrever, pro frontend
    // poder oferecer reagendá-lo.
    $clienteAntigo = [
        'idCliente' => (int) $agendamento['idCliente'],
        'nome'      => $agendamento['clienteAtualNome'],
        'telefone'  => $agendamento['clienteAtualTelefone'],
    ];
    $tinhaFidelidade = $agendamento['fidelidade'] !== null;

    $stmtAtualiza = $pdo->prepare(
        'UPDATE Agendamentos SET idCliente = :novoCliente, fidelidade = NULL, grupo_recorrencia = NULL WHERE idAgendamento = :id'
    );
    $stmtAtualiza->execute(['novoCliente' => $novoIdCliente, 'id' => $idAgendamento]);

    $pdo->commit();

    DiscordLogger::agendamentos('👥 Cliente do agendamento alterado', [
        ['name' => '🆔 Agendamento', 'value' => '#' . $idAgendamento, 'inline' => true],
        ['name' => '📅 Data', 'value' => $agendamento['Data'] . ' ' . substr($agendamento['hora'], 0, 5), 'inline' => true],
        ['name' => '👤 Antes', 'value' => $clienteAntigo['nome'], 'inline' => true],
        ['name' => '👤 Depois', 'value' => $novoCliente['nome'], 'inline' => true],
    ], DiscordLogger::COR_EDICAO);

    echo json_encode([
        'ok'                 => true,
        'clienteAntigo'      => $clienteAntigo,
        'tinhaFidelidade'    => $tinhaFidelidade,
        'servicoOriginal'    => [
            'id'    => (int) $agendamento['idServico'],
            'nome'  => $agendamento['servicoNome'],
            'valor' => (float) $agendamento['servicoValor'],
        ],
        'observacaoOriginal' => $agendamento['Observacao'],
        'horario'            => [
            'data' => $agendamento['Data'],
            'hora' => substr($agendamento['hora'], 0, 5),
        ],
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    DiscordLogger::erro('💥 Falha ao alterar cliente do agendamento', $e);
    http_response_code(500);
    echo json_encode(['ok' => false, 'erro' => 'Erro ao alterar o cliente do agendamento.']);
}

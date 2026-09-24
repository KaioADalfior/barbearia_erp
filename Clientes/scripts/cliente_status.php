<?php
// cliente_status.php
// Alterna o status do cliente entre ativo/inativo. Nunca exclui o CADASTRO
// do cliente (Cliente.*) — isso nunca é tocado aqui.
//
// Ao INATIVAR: remove (DELETE) da tabela Agendamentos TODOS os agendamentos
// ainda ATIVOS desse cliente (Status IN 'agendado','confirmado') — não
// importa o tipo: fidelidade semanal/quinzenal/mensal/20 em 20 (qualquer
// fidelidade ENUM) ou avulso, cada ocorrência é removida individualmente —
// e libera (disponivel = 1) os respectivos Horario, deixando o slot vago
// de novo na agenda. Só permanecem no banco, para este cliente, os
// agendamentos com Status = 'concluido' (histórico real de atendimentos —
// nunca apagado) e os que já estavam 'cancelado' antes desta inativação
// (histórico de cancelamentos anteriores, também preservado). Nenhuma
// alteração de schema — só DELETE/UPDATE nas linhas já existentes de
// Agendamentos/Horario. Um cliente inativo já não aparece mais na busca de
// clientes (Clientes/scripts/clientes_buscar.php filtra ativo=1), então
// também não pode gerar novos agendamentos (avulsos ou de fidelidade).
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/guard.php';
exigirSessao(['barbeiro']);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/ClienteService.php';
require_once __DIR__ . '/../../includes/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /clientes');
    exit;
}

csrf_verificar(json: false, redirecionarPara: '/clientes');

$id    = (int) ($_POST['id'] ?? 0);
$acao  = $_POST['acao'] ?? '';

if ($id <= 0 || !in_array($acao, ['inativar', 'reativar'], true)) {
    header('Location: /clientes?status=status-erro');
    exit;
}

$novoStatus = $acao === 'inativar' ? 0 : 1;

$stmtNome = $pdo->prepare('SELECT nome FROM Cliente WHERE idCliente = :id');
$stmtNome->execute(['id' => $id]);
$nome = $stmtNome->fetchColumn();

$totalCanceladosFuturos = 0;

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('UPDATE Cliente SET ativo = :ativo WHERE idCliente = :id');
    $stmt->execute([
        'ativo' => $novoStatus,
        'id'    => $id,
    ]);

    if ($acao === 'inativar') {
        // Remove definitivamente da tabela Agendamentos todos os agendamentos
        // ainda ATIVOS deste cliente (agendado/confirmado) — sem filtro de
        // data, sem filtro de tipo: pega qualquer fidelidade (semanal,
        // quinzenal, mensal, 20 em 20 — ENUM '1'..'4') e também avulsos,
        // pois cada ocorrência de fidelidade é uma linha independente
        // (mesma coluna Status), então isso já corta a série inteira sem
        // nenhuma lógica extra por tipo.
        //
        // Diferente do cancelamento manual (agendamento_cancelar*.php, que
        // só marca Status='cancelado' e preserva a linha), aqui a linha é
        // APAGADA — a pedido explícito do usuário ("limpar do banco, deixar
        // só os concluídos"). Seguro porque nenhuma outra tabela referencia
        // um Agendamento agendado/confirmado: FinanceiroLancamentos.idAgendamento
        // só é preenchido quando o agendamento é CONCLUÍDO (ver
        // agendamento_concluir.php) — nunca para agendado/confirmado —,
        // então não há risco de linha órfã ou erro de FK.
        //
        // NUNCA remove: Status='concluido' (histórico real de atendimentos)
        // nem Status='cancelado' já existente (histórico de cancelamentos
        // anteriores a esta inativação). Lógica compartilhada com
        // cliente_atualizar.php via includes/ClienteService.php — nunca
        // duplicada.
        $totalCanceladosFuturos = ClienteService::cancelarAgendamentosAtivos($pdo, $id);
    }

    $pdo->commit();
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    DiscordLogger::erro('💥 Falha ao alterar status do cliente', $e);
    header('Location: /clientes?status=status-erro');
    exit;
}

$camposLog = [
    ['name' => '🆔 Cliente', 'value' => "#{$id}", 'inline' => true],
    ['name' => '👤 Nome', 'value' => $nome ?: '—', 'inline' => true],
];

if ($acao === 'inativar' && $totalCanceladosFuturos > 0) {
    $camposLog[] = ['name' => '🗑️ Agendamentos ativos removidos', 'value' => (string) $totalCanceladosFuturos, 'inline' => true];
}

DiscordLogger::clientes(
    $acao === 'inativar' ? '⛔ Cliente inativado' : '🟢 Cliente reativado',
    $camposLog,
    $acao === 'inativar' ? DiscordLogger::COR_ALERTA : DiscordLogger::COR_SUCESSO
);

$statusMsg = $acao === 'inativar' ? 'inativado-sucesso' : 'reativado-sucesso';
header('Location: /clientes?status=' . $statusMsg . '&nome=' . urlencode($nome ?: ''));
exit;
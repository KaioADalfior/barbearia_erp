<?php
// cliente_atualizar.php
//
// Este é o endpoint REAL chamado pela tela Clientes ao editar um cliente
// existente (ver cliPrepararEnvioForm() em Clientes/paginas/cliente_listar.php
// — o formulário de edição sempre envia para cá, inclusive quando o único
// campo alterado é o Status Ativo/Inativo).
//
// Por isso: sempre que esta tela detecta que o cliente estava ATIVO e está
// sendo salvo como INATIVO, também limpa automaticamente os agendamentos
// dele — mesma lógica usada em Clientes/scripts/cliente_status.php, via
// ClienteService::cancelarAgendamentosAtivos() (nunca duplicada em dois
// lugares). Remove (DELETE) da tabela Agendamentos todo agendamento ainda
// ativo (agendado/confirmado, qualquer fidelidade ou avulso) e libera o
// Horario correspondente — o quadro de horários fica vago de novo, igual
// ao cancelamento manual. Agendamentos 'concluido' e 'cancelado' nunca são
// tocados (histórico do cliente preservado).

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/guard.php';
exigirSessao(['barbeiro']);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/ClienteService.php';
require_once __DIR__ . '/../../includes/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../paginas/cliente_listar.php');
    exit;
}

csrf_verificar(json: false, redirecionarPara: '../paginas/cliente_listar.php');

$id        = (int) ($_POST['id'] ?? 0);
$nome      = trim($_POST['nome'] ?? '');
$telefone  = trim($_POST['telefone'] ?? '');
$email     = trim($_POST['email'] ?? '');
$ativo     = ($_POST['ativo'] ?? '1') === '0' ? 0 : 1;

$paramsVolta = http_build_query([
    'edit_id'   => $id,
    'nome'      => $nome,
    'telefone'  => $telefone,
    'email'     => $email,
    'ativo'     => $ativo,
]);

if ($id <= 0) {
    header('Location: ../paginas/cliente_listar.php?status=edicao-erro');
    exit;
}

if ($nome === '' || $telefone === '') {
    header('Location: ../paginas/cliente_listar.php?status=edicao-erro&' . $paramsVolta);
    exit;
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: ../paginas/cliente_listar.php?status=edicao-email-invalido&' . $paramsVolta);
    exit;
}

/*
|--------------------------------------------------------------------------
| Busca os dados atuais do cliente
|--------------------------------------------------------------------------
*/

$stmtAntigo = $pdo->prepare("
    SELECT *
    FROM Cliente
    WHERE idCliente = :id
");

$stmtAntigo->execute([
    'id' => $id
]);

$clienteAntigo = $stmtAntigo->fetch(PDO::FETCH_ASSOC);

if (!$clienteAntigo) {
    header('Location: ../paginas/cliente_listar.php?status=cliente-nao-encontrado');
    exit;
}

/*
|--------------------------------------------------------------------------
| Atualiza o cliente (+ limpa agendamentos se ele acabou de ser inativado)
|--------------------------------------------------------------------------
*/

$estavaAtivo   = (int) $clienteAntigo['ativo'] === 1;
$vaiInativar   = $estavaAtivo && $ativo === 0;
$totalRemovido = 0;

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        UPDATE Cliente
        SET
            nome = :nome,
            telefone = :telefone,
            email = :email,
            ativo = :ativo
        WHERE idCliente = :id
    ");

    $stmt->execute([
        'nome'      => $nome,
        'telefone'  => $telefone,
        'email'     => $email !== '' ? $email : null,
        'ativo'     => $ativo,
        'id'        => $id,
    ]);

    $linhasAlteradas = $stmt->rowCount();

    // Cliente estava ATIVO e está sendo salvo como INATIVO: limpa (DELETE)
    // todos os agendamentos ainda ativos dele e libera os horários — ver
    // includes/ClienteService.php.
    if ($vaiInativar) {
        $totalRemovido = ClienteService::cancelarAgendamentosAtivos($pdo, $id);
    }

    $pdo->commit();
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    DiscordLogger::erro('💥 Falha ao editar cliente', $e);
    header('Location: ../paginas/cliente_listar.php?status=edicao-erro&' . $paramsVolta);
    exit;
}

/*
|--------------------------------------------------------------------------
| Envia log para o Discord somente se houve alteração
|--------------------------------------------------------------------------
*/

if ($linhasAlteradas > 0 || $totalRemovido > 0) {
    $camposLog = [
        ['name' => '🆔 Cliente', 'value' => "#{$id}", 'inline' => true],
        ['name' => '👤 Nome', 'value' => "**Antes:** {$clienteAntigo['nome']}\n**Depois:** {$nome}", 'inline' => false],
        ['name' => '📞 Telefone', 'value' => "**Antes:** {$clienteAntigo['telefone']}\n**Depois:** {$telefone}", 'inline' => false],
        ['name' => '📧 E-mail', 'value' => "**Antes:** " . ($clienteAntigo['email'] ?: 'Não informado') . "\n**Depois:** " . ($email ?: 'Não informado'), 'inline' => false],
        ['name' => '📌 Status', 'value' => "**Antes:** " . ($clienteAntigo['ativo'] ? '🟢 Ativo' : '🔴 Inativo') . "\n**Depois:** " . ($ativo ? '🟢 Ativo' : '🔴 Inativo'), 'inline' => false],
    ];

    if ($vaiInativar && $totalRemovido > 0) {
        $camposLog[] = ['name' => '🗑️ Agendamentos ativos removidos', 'value' => (string) $totalRemovido, 'inline' => true];
    }

    DiscordLogger::clientes('✏️ Cliente editado', $camposLog, DiscordLogger::COR_EDICAO);
}

header('Location: ../paginas/cliente_listar.php?status=edicao-sucesso&nome=' . urlencode($nome));
exit;
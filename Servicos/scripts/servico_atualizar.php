<?php
// servico_atualizar.php

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/guard.php';
exigirSessao(['barbeiro']);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../paginas/servico_listar.php');
    exit;
}

csrf_verificar(json: false, redirecionarPara: '../paginas/servico_listar.php');

$id              = (int) ($_POST['id'] ?? 0);
$nome            = trim($_POST['nome'] ?? '');
$duracaoMinutos  = (int) ($_POST['duracao_minutos'] ?? 0);
$valorBruto      = trim($_POST['valor'] ?? '');
$valor           = (float) str_replace(',', '.', preg_replace('/[^\d,\.]/', '', $valorBruto));
$ativo           = ($_POST['ativo'] ?? '1') === '0' ? 0 : 1;

$paramsVolta = http_build_query([
    'edit_id'          => $id,
    'nome'             => $nome,
    'duracao_minutos'  => $duracaoMinutos,
    'valor'            => $valorBruto,
    'ativo'            => $ativo,
]);

if ($id <= 0) {
    header('Location: ../paginas/servico_listar.php?status=edicao-erro');
    exit;
}

if ($nome === '' || $duracaoMinutos <= 0 || $valor <= 0) {
    header('Location: ../paginas/servico_listar.php?status=edicao-erro&' . $paramsVolta);
    exit;
}

/*
|--------------------------------------------------------------------------
| Busca os dados atuais do serviço
|--------------------------------------------------------------------------
*/

$stmtAntigo = $pdo->prepare('SELECT * FROM Servico WHERE idServico = :id');
$stmtAntigo->execute(['id' => $id]);
$servicoAntigo = $stmtAntigo->fetch(PDO::FETCH_ASSOC);

if (!$servicoAntigo) {
    header('Location: ../paginas/servico_listar.php?status=servico-nao-encontrado');
    exit;
}

/*
|--------------------------------------------------------------------------
| Atualiza o serviço
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    'UPDATE Servico
     SET nome = :nome, duracao_minutos = :duracao_minutos, valor = :valor, ativo = :ativo
     WHERE idServico = :id'
);
$stmt->execute([
    'nome'            => $nome,
    'duracao_minutos' => $duracaoMinutos,
    'valor'           => $valor,
    'ativo'           => $ativo,
    'id'              => $id,
]);

/*
|--------------------------------------------------------------------------
| Envia log para o Discord somente se houve alteração
|--------------------------------------------------------------------------
*/

if ($stmt->rowCount() > 0) {
    $valorAntigo = number_format((float) $servicoAntigo['valor'], 2, ',', '.');
    $valorNovo   = number_format($valor, 2, ',', '.');

    DiscordLogger::servicos('✏️ Serviço editado', [
        ['name' => '🆔 Serviço', 'value' => "#{$id}", 'inline' => true],
        ['name' => '💈 Nome', 'value' => "**Antes:** {$servicoAntigo['nome']}\n**Depois:** {$nome}", 'inline' => false],
        ['name' => '⏱️ Duração', 'value' => "**Antes:** {$servicoAntigo['duracao_minutos']} min\n**Depois:** {$duracaoMinutos} min", 'inline' => false],
        ['name' => '💰 Valor', 'value' => "**Antes:** R$ {$valorAntigo}\n**Depois:** R$ {$valorNovo}", 'inline' => false],
        ['name' => '📌 Status', 'value' => "**Antes:** " . ($servicoAntigo['ativo'] ? '🟢 Ativo' : '🔴 Inativo') . "\n**Depois:** " . ($ativo ? '🟢 Ativo' : '🔴 Inativo'), 'inline' => false],
    ], DiscordLogger::COR_EDICAO);
}

header('Location: ../paginas/servico_listar.php?status=edicao-sucesso&nome=' . urlencode($nome));
exit;
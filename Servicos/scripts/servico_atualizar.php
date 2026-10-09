<?php
// servico_atualizar.php

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/guard.php';
exigirSessao(['barbeiro']);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/AcessoService.php';
require_once __DIR__ . '/../../includes/csrf.php';
// Preço/serviço/foto: só o proprietário altera (funcionário só consulta).
AcessoService::exigirProprietario($pdo);
require_once __DIR__ . '/../../includes/ServicoFoto.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /servicos');
    exit;
}

csrf_verificar(json: false, redirecionarPara: '/servicos');

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
    header('Location: /servicos?status=edicao-erro');
    exit;
}

if ($nome === '' || $duracaoMinutos <= 0 || $valor <= 0) {
    header('Location: /servicos?status=edicao-erro&' . $paramsVolta);
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
    header('Location: /servicos?status=servico-nao-encontrado');
    exit;
}

/*
|--------------------------------------------------------------------------
| Foto opcional: nova foto, remoção ou manter a atual
|--------------------------------------------------------------------------
*/

$enviouFoto   = isset($_FILES['foto']) && $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE;
$removerFoto  = ($_POST['remover_foto'] ?? '0') === '1';
$colunaFoto   = ServicoFoto::colunaExiste($pdo);
$fotoAntiga   = $colunaFoto ? ($servicoAntigo['foto'] ?? null) : null;
$fotoNova     = null;      // nome do arquivo recém-enviado
$mudouFoto    = null;      // 'adicionada' | 'trocada' | 'removida' | null

if ($colunaFoto && $enviouFoto) {
    $resultadoFoto = ServicoFoto::processarUpload($_FILES['foto']);

    if (!$resultadoFoto['ok']) {
        header('Location: /servicos?status=edicao-erro-foto&foto_erro=' . $resultadoFoto['erro'] . '&' . $paramsVolta);
        exit;
    }

    $fotoNova  = $resultadoFoto['nome'];
    $mudouFoto = $fotoAntiga ? 'trocada' : 'adicionada';
} elseif ($colunaFoto && $removerFoto && $fotoAntiga) {
    $mudouFoto = 'removida';
}

/*
|--------------------------------------------------------------------------
| Atualiza o serviço
|--------------------------------------------------------------------------
*/

$sql = 'UPDATE Servico
        SET nome = :nome, duracao_minutos = :duracao_minutos, valor = :valor, ativo = :ativo';
$parametros = [
    'nome'            => $nome,
    'duracao_minutos' => $duracaoMinutos,
    'valor'           => $valor,
    'ativo'           => $ativo,
    'id'              => $id,
];

if ($mudouFoto !== null) {
    $sql .= ', foto = :foto';
    $parametros['foto'] = $fotoNova; // NULL quando a foto foi removida
}

$sql .= ' WHERE idServico = :id';

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($parametros);
} catch (Throwable $e) {
    ServicoFoto::remover($fotoNova);
    throw $e;
}

// Só apaga o arquivo antigo depois que o banco já aponta para a foto nova.
if ($mudouFoto !== null) {
    ServicoFoto::remover($fotoAntiga);
}

/*
|--------------------------------------------------------------------------
| Envia log para o Discord somente se houve alteração
|--------------------------------------------------------------------------
*/

if ($stmt->rowCount() > 0) {
    $valorAntigo = number_format((float) $servicoAntigo['valor'], 2, ',', '.');
    $valorNovo   = number_format($valor, 2, ',', '.');

    $camposLog = [
        ['name' => '🆔 Serviço', 'value' => "#{$id}", 'inline' => true],
        ['name' => '💈 Nome', 'value' => "**Antes:** {$servicoAntigo['nome']}\n**Depois:** {$nome}", 'inline' => false],
        ['name' => '⏱️ Duração', 'value' => "**Antes:** {$servicoAntigo['duracao_minutos']} min\n**Depois:** {$duracaoMinutos} min", 'inline' => false],
        ['name' => '💰 Valor', 'value' => "**Antes:** R$ {$valorAntigo}\n**Depois:** R$ {$valorNovo}", 'inline' => false],
        ['name' => '📌 Status', 'value' => "**Antes:** " . ($servicoAntigo['ativo'] ? '🟢 Ativo' : '🔴 Inativo') . "\n**Depois:** " . ($ativo ? '🟢 Ativo' : '🔴 Inativo'), 'inline' => false],
    ];

    if ($mudouFoto !== null) {
        $camposLog[] = ['name' => '🖼️ Foto', 'value' => ucfirst($mudouFoto), 'inline' => false];
    }

    DiscordLogger::servicos('✏️ Serviço editado', $camposLog, DiscordLogger::COR_EDICAO);
}

// Mexeu na foto, mas o banco ainda não tem a coluna (e não foi possível criá-la).
$statusFinal = (!$colunaFoto && ($enviouFoto || $removerFoto)) ? 'edicao-sucesso-semfoto' : 'edicao-sucesso';

header('Location: /servicos?status=' . $statusFinal . '&nome=' . urlencode($nome));
exit;
<?php
// servico_salvar.php
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/guard.php';
exigirSessao(['barbeiro']);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/ServicoFoto.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /servicos');
    exit;
}

csrf_verificar(json: false, redirecionarPara: '/servicos');

$nome            = trim($_POST['nome'] ?? '');
$duracaoMinutos  = (int) ($_POST['duracao_minutos'] ?? 0);
$valorBruto      = trim($_POST['valor'] ?? '');
$valor           = (float) str_replace(',', '.', preg_replace('/[^\d,\.]/', '', $valorBruto));

// Query string usada para reabrir o modal de cadastro com os dados
// preenchidos, caso a validação falhe.
$paramsVolta = http_build_query([
    'nome'             => $nome,
    'duracao_minutos'  => $duracaoMinutos,
    'valor'            => $valorBruto,
]);

if ($nome === '' || $duracaoMinutos <= 0 || $valor <= 0) {
    header('Location: /servicos?status=cadastro-erro&' . $paramsVolta);
    exit;
}

/*
|--------------------------------------------------------------------------
| Foto opcional do serviço (ex.: foto de um degradê)
|--------------------------------------------------------------------------
*/

$enviouFoto = isset($_FILES['foto']) && $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE;
$colunaFoto = ServicoFoto::colunaExiste($pdo);
$fotoNome   = null;

if ($enviouFoto && $colunaFoto) {
    $resultadoFoto = ServicoFoto::processarUpload($_FILES['foto']);

    if (!$resultadoFoto['ok']) {
        header('Location: /servicos?status=cadastro-erro-foto&foto_erro=' . $resultadoFoto['erro'] . '&' . $paramsVolta);
        exit;
    }

    $fotoNome = $resultadoFoto['nome'];
}

try {
    if ($colunaFoto) {
        $stmt = $pdo->prepare(
            'INSERT INTO Servico (nome, duracao_minutos, valor, ativo, foto) VALUES (:nome, :duracao_minutos, :valor, 1, :foto)'
        );
        $stmt->execute([
            'nome'            => $nome,
            'duracao_minutos' => $duracaoMinutos,
            'valor'           => $valor,
            'foto'            => $fotoNome,
        ]);
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO Servico (nome, duracao_minutos, valor, ativo) VALUES (:nome, :duracao_minutos, :valor, 1)'
        );
        $stmt->execute([
            'nome'            => $nome,
            'duracao_minutos' => $duracaoMinutos,
            'valor'           => $valor,
        ]);
    }
} catch (Throwable $e) {
    // Não deixa uma foto órfã na pasta se o cadastro falhou.
    ServicoFoto::remover($fotoNome);
    throw $e;
}

DiscordLogger::servicos('🆕 Serviço cadastrado', [
    ['name' => '🆔 ID', 'value' => '#' . $pdo->lastInsertId(), 'inline' => true],
    ['name' => '💈 Nome', 'value' => $nome, 'inline' => true],
    ['name' => '⏱️ Duração', 'value' => $duracaoMinutos . ' min', 'inline' => true],
    ['name' => '💰 Valor', 'value' => 'R$ ' . number_format($valor, 2, ',', '.'), 'inline' => true],
    ['name' => '🖼️ Foto', 'value' => $fotoNome !== null ? 'Sim' : 'Não', 'inline' => true],
    ['name' => '👤 Cadastrado por', 'value' => $_SESSION['nome'] ?? ('#' . ($_SESSION['id'] ?? '—')), 'inline' => true],
]);

// Enviou foto, mas o banco ainda não tem a coluna (e não foi possível criá-la).
$statusFinal = ($enviouFoto && !$colunaFoto) ? 'cadastro-sucesso-semfoto' : 'cadastro-sucesso';

header('Location: /servicos?status=' . $statusFinal . '&nome=' . urlencode($nome));
exit;
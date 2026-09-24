<?php
// cliente_historico_salvar.php
// Endpoint AJAX (POST) usado pela aba "Histórico" do modal de detalhes em
// Clientes/paginas/cliente_listar.php. Permite ao barbeiro registrar uma
// anotação livre sobre o cliente (fiado, observação, atendimento avulso...).

require_once __DIR__ . '/../../includes/session.php';
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/guard.php';
exigirSessao(['barbeiro'], json: true);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'erro' => 'Método não permitido.']);
    exit;
}

csrf_verificar(json: true);

$idCliente = (int) ($_POST['idCliente'] ?? 0);
$tipo      = $_POST['tipo'] ?? '';
$descricao = trim($_POST['descricao'] ?? '');
$valorRaw  = trim($_POST['valor'] ?? '');

$tiposValidos = ['fiado', 'observacao', 'atendimento', 'outro'];

if ($idCliente <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'erro' => 'Cliente inválido.']);
    exit;
}

if (!in_array($tipo, $tiposValidos, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'erro' => 'Tipo de registro inválido.']);
    exit;
}

if ($descricao === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'erro' => 'Descreva o histórico antes de salvar.']);
    exit;
}

// Valor é obrigatório para "fiado" (é o valor em aberto); nos demais tipos é opcional.
$valor = null;
if ($valorRaw !== '') {
    $valorNormalizado = str_replace(['.', ','], ['', '.'], $valorRaw);
    if (!is_numeric($valorNormalizado)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'erro' => 'Informe um valor numérico válido.']);
        exit;
    }
    $valor = (float) $valorNormalizado;
}

if ($tipo === 'fiado' && $valor === null) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'erro' => 'Informe o valor do fiado.']);
    exit;
}

$stmtCliente = $pdo->prepare('SELECT nome FROM Cliente WHERE idCliente = :id');
$stmtCliente->execute(['id' => $idCliente]);
$nomeCliente = $stmtCliente->fetchColumn();

if ($nomeCliente === false) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'erro' => 'Cliente não encontrado.']);
    exit;
}

$stmt = $pdo->prepare(
    'INSERT INTO ClienteHistorico (idCliente, id_barbeiro, tipo, descricao, valor)
     VALUES (:idCliente, :idBarbeiro, :tipo, :descricao, :valor)'
);
$stmt->execute([
    'idCliente'  => $idCliente,
    'idBarbeiro' => $_SESSION['id'] ?? null,
    'tipo'       => $tipo,
    'descricao'  => $descricao,
    'valor'      => $valor,
]);

$idHistorico = (int) $pdo->lastInsertId();

$rotulos = [
    'fiado'       => '💰 Fiado',
    'observacao'  => '📝 Observação',
    'atendimento' => '✂️ Atendimento',
    'outro'       => 'ℹ️ Outro',
];

DiscordLogger::clientes(
    ($rotulos[$tipo] ?? 'ℹ️ Histórico') . ' adicionado ao cliente',
    [
        ['name' => '🆔 Cliente', 'value' => "#{$idCliente}", 'inline' => true],
        ['name' => '👤 Nome', 'value' => $nomeCliente ?: '—', 'inline' => true],
        ['name' => '👨‍🔧 Barbeiro', 'value' => $_SESSION['nome'] ?? '—', 'inline' => true],
        ['name' => '💬 Descrição', 'value' => $descricao, 'inline' => false],
        ['name' => '💵 Valor', 'value' => $valor !== null ? 'R$ ' . number_format($valor, 2, ',', '.') : 'Não informado', 'inline' => true],
    ]
);

echo json_encode([
    'ok' => true,
    'item' => [
        'idHistorico'   => $idHistorico,
        'tipo'          => $tipo,
        'descricao'     => $descricao,
        'valor'         => $valor,
        'quitado'       => 0,
        'criado_em'     => date('Y-m-d H:i:s'),
        'barbeiro_nome' => $_SESSION['nome'] ?? null,
    ],
]);

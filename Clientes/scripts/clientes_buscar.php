<?php
// clientes_buscar.php
// Endpoint AJAX (GET) usado pelo modal de agendamento em Agendamentos/paginas/agendar.php
// para buscar clientes já cadastrados, por nome ou telefone.

require_once __DIR__ . '/../../includes/session.php';
header('Content-Type: application/json; charset=utf-8');

if (($_SESSION['tipo'] ?? null) !== 'barbeiro') {
    http_response_code(401);
    echo json_encode(['ok' => false, 'erro' => 'Não autenticado.']);
    exit;
}

require_once __DIR__ . '/../../config/config.php';

$termo = trim($_GET['termo'] ?? '');

if ($termo === '') {
    echo json_encode(['ok' => true, 'clientes' => []]);
    exit;
}

$stmt = $pdo->prepare(
    'SELECT idCliente, nome, telefone, email
     FROM Cliente
     WHERE ativo = 1 AND (nome LIKE :termo OR telefone LIKE :termo)
     ORDER BY nome ASC
     LIMIT 8'
);
$stmt->execute(['termo' => '%' . $termo . '%']);

echo json_encode(['ok' => true, 'clientes' => $stmt->fetchAll()]);
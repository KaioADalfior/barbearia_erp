<?php
// listaespera_remover.php
// Endpoint AJAX (POST) usado pelo popup flutuante "Lista de Espera" em
// Agendamentos/paginas/agendar.php. Remove um cliente da lista de espera
// deste barbeiro.

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

$idBarbeiro = (int) $_SESSION['id'];
$idEspera   = (int) ($_POST['idEspera'] ?? 0);

if ($idEspera <= 0) {
    echo json_encode(['ok' => false, 'erro' => 'Requisição inválida.']);
    exit;
}

$stmt = $pdo->prepare(
    'SELECT le.idEspera, c.nome AS clienteNome
     FROM ListaEspera le
     INNER JOIN Cliente c ON c.idCliente = le.idCliente
     WHERE le.idEspera = :id AND le.id_barbeiro = :b'
);
$stmt->execute(['id' => $idEspera, 'b' => $idBarbeiro]);
$item = $stmt->fetch();

if (!$item) {
    echo json_encode(['ok' => false, 'erro' => 'Item não encontrado na lista de espera.']);
    exit;
}

$stmtRemove = $pdo->prepare('DELETE FROM ListaEspera WHERE idEspera = :id');
$stmtRemove->execute(['id' => $idEspera]);

DiscordLogger::agendamentos('🗑️ Cliente removido da lista de espera', [
    ['name' => '👤 Cliente', 'value' => $item['clienteNome'], 'inline' => true],
], DiscordLogger::COR_ALERTA);

echo json_encode(['ok' => true]);
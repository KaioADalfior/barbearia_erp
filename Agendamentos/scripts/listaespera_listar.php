<?php
// listaespera_listar.php
// Endpoint AJAX (GET) usado pelo popup flutuante "Lista de Espera" em
// Agendamentos/paginas/agendar.php. Devolve os clientes aguardando deste
// barbeiro, do mais antigo para o mais novo (ordem de chegada).

require_once __DIR__ . '/../../includes/session.php';
header('Content-Type: application/json; charset=utf-8');

if (($_SESSION['tipo'] ?? null) !== 'barbeiro') {
    http_response_code(401);
    echo json_encode(['ok' => false, 'erro' => 'Não autenticado.']);
    exit;
}

require_once __DIR__ . '/../../config/config.php';

$idBarbeiro = (int) $_SESSION['id'];

$stmt = $pdo->prepare(
    'SELECT le.idEspera, le.observacao, le.criado_em,
            c.idCliente, c.nome, c.telefone
     FROM ListaEspera le
     INNER JOIN Cliente c ON c.idCliente = le.idCliente
     WHERE le.id_barbeiro = :b
     ORDER BY le.criado_em ASC'
);
$stmt->execute(['b' => $idBarbeiro]);

$lista = array_map(function ($linha) {
    return [
        'idEspera'   => (int) $linha['idEspera'],
        'observacao' => $linha['observacao'],
        'criadoEm'   => $linha['criado_em'],
        'cliente'    => [
            'idCliente' => (int) $linha['idCliente'],
            'nome'      => $linha['nome'],
            'telefone'  => $linha['telefone'],
        ],
    ];
}, $stmt->fetchAll());

echo json_encode(['ok' => true, 'lista' => $lista]);
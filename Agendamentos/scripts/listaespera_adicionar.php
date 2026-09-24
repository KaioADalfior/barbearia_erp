<?php
// listaespera_adicionar.php
// Endpoint AJAX (POST) usado pelo popup flutuante "Lista de Espera" em
// Agendamentos/paginas/agendar.php. Adiciona um cliente (já cadastrado ou
// novo, mesmo padrão de busca/cadastro do modal de agendamento) à lista de
// espera deste barbeiro.
//
// Escopo desta etapa: só cadastra o cliente na lista. Encaixe automático,
// notificações e WhatsApp ficam para uma etapa futura.

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

$idBarbeiro   = (int) $_SESSION['id'];
$idClienteRaw = trim($_POST['idCliente'] ?? '');
$novoNome     = trim($_POST['novoNome'] ?? '');
$novoTelefone = trim($_POST['novoTelefone'] ?? '');
$novoEmail    = trim($_POST['novoEmail'] ?? '');
$observacao   = trim($_POST['observacao'] ?? '');

if ($idClienteRaw === '' && ($novoNome === '' || $novoTelefone === '')) {
    echo json_encode(['ok' => false, 'erro' => 'Busque um cliente existente ou informe nome e telefone para cadastrar um novo.']);
    exit;
}

if ($novoEmail !== '' && !filter_var($novoEmail, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'erro' => 'Informe um e-mail válido ou deixe o campo em branco.']);
    exit;
}

try {
    $pdo->beginTransaction();

    if ($idClienteRaw !== '') {
        $idCliente = (int) $idClienteRaw;
        $stmtCliente = $pdo->prepare('SELECT idCliente FROM Cliente WHERE idCliente = :id AND ativo = 1');
        $stmtCliente->execute(['id' => $idCliente]);
        if (!$stmtCliente->fetch()) {
            $pdo->rollBack();
            echo json_encode(['ok' => false, 'erro' => 'Cliente selecionado não encontrado.']);
            exit;
        }
    } else {
        $stmtNovoCliente = $pdo->prepare(
            'INSERT INTO Cliente (nome, telefone, email, ativo) VALUES (:nome, :telefone, :email, 1)'
        );
        $stmtNovoCliente->execute([
            'nome'     => $novoNome,
            'telefone' => $novoTelefone,
            'email'    => $novoEmail !== '' ? $novoEmail : null,
        ]);
        $idCliente = (int) $pdo->lastInsertId();
    }

    // Evita duplicar o mesmo cliente duas vezes na lista de espera deste barbeiro.
    $stmtJaNaLista = $pdo->prepare('SELECT idEspera FROM ListaEspera WHERE id_barbeiro = :b AND idCliente = :c');
    $stmtJaNaLista->execute(['b' => $idBarbeiro, 'c' => $idCliente]);
    if ($stmtJaNaLista->fetch()) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'erro' => 'Este cliente já está na lista de espera.']);
        exit;
    }

    $stmtInsere = $pdo->prepare(
        'INSERT INTO ListaEspera (id_barbeiro, idCliente, observacao) VALUES (:b, :c, :o)'
    );
    $stmtInsere->execute([
        'b' => $idBarbeiro,
        'c' => $idCliente,
        'o' => $observacao !== '' ? $observacao : null,
    ]);

    $pdo->commit();

    $stmtNome = $pdo->prepare('SELECT nome, telefone FROM Cliente WHERE idCliente = :id');
    $stmtNome->execute(['id' => $idCliente]);
    $cliente = $stmtNome->fetch();

    DiscordLogger::agendamentos('⏳ Cliente adicionado à lista de espera', [
        ['name' => '👤 Cliente', 'value' => ($cliente['nome'] ?? '—') . ' — ' . ($cliente['telefone'] ?? '—'), 'inline' => false],
        ['name' => '📝 Observação', 'value' => $observacao ?: '—', 'inline' => false],
    ]);

    echo json_encode(['ok' => true, 'idEspera' => (int) $pdo->lastInsertId()]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    DiscordLogger::erro('💥 Falha ao adicionar na lista de espera', $e);
    http_response_code(500);
    echo json_encode(['ok' => false, 'erro' => 'Erro ao adicionar na lista de espera.']);
}
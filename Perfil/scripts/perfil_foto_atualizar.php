<?php
// perfil_foto_atualizar.php
// Faz o upload (ou remoção) da foto de perfil do barbeiro logado.
// A foto é salva em assets/uploads/perfil/ com um nome único; o banco
// guarda somente o nome do arquivo (coluna Barbeiro.foto).

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/guard.php';
exigirSessao(['barbeiro'], json: true);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/csrf.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'erro' => 'Método não permitido.']);
    exit;
}

csrf_verificar(json: true);

$idBarbeiro = (int) $_SESSION['id'];
$acao       = $_POST['acao'] ?? 'enviar';

$pastaUploads = __DIR__ . '/../../assets/uploads/perfil/';

$stmtAtual = $pdo->prepare('SELECT foto FROM Barbeiro WHERE id_barbeiro = :id LIMIT 1');
$stmtAtual->execute(['id' => $idBarbeiro]);
$fotoAtual = $stmtAtual->fetchColumn();

// ---------- Remover foto ----------
if ($acao === 'remover') {
    if ($fotoAtual) {
        $caminhoAntigo = $pastaUploads . $fotoAtual;
        if (is_file($caminhoAntigo)) {
            @unlink($caminhoAntigo);
        }
    }

    $stmt = $pdo->prepare('UPDATE Barbeiro SET foto = NULL WHERE id_barbeiro = :id');
    $stmt->execute(['id' => $idBarbeiro]);

    unset($_SESSION['foto']);

    DiscordLogger::configuracoes('🖼️ Foto de perfil removida', [
        ['name' => '🆔 Barbeiro', 'value' => '#' . $idBarbeiro, 'inline' => true],
        ['name' => '👤 Nome', 'value' => $_SESSION['nome'] ?? '—', 'inline' => true],
    ]);

    echo json_encode(['ok' => true, 'foto' => null]);
    exit;
}

// ---------- Enviar nova foto ----------
if (!isset($_FILES['foto']) || $_FILES['foto']['error'] === UPLOAD_ERR_NO_FILE) {
    echo json_encode(['ok' => false, 'erro' => 'Selecione uma imagem.']);
    exit;
}

$arquivo = $_FILES['foto'];

if ($arquivo['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['ok' => false, 'erro' => 'Falha no envio do arquivo. Tente novamente.']);
    exit;
}

// Limite de 3MB
if ($arquivo['size'] > 3 * 1024 * 1024) {
    echo json_encode(['ok' => false, 'erro' => 'A imagem deve ter no máximo 3MB.']);
    exit;
}

$extensoesPermitidas = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];

$mimeReal = mime_content_type($arquivo['tmp_name']);

if (!isset($extensoesPermitidas[$mimeReal])) {
    echo json_encode(['ok' => false, 'erro' => 'Formato inválido. Envie uma imagem JPG, PNG ou WEBP.']);
    exit;
}

if (!is_dir($pastaUploads)) {
    mkdir($pastaUploads, 0755, true);
}

$extensao   = $extensoesPermitidas[$mimeReal];
$nomeNovo   = 'barbeiro_' . $idBarbeiro . '_' . bin2hex(random_bytes(6)) . '.' . $extensao;
$destino    = $pastaUploads . $nomeNovo;

if (!move_uploaded_file($arquivo['tmp_name'], $destino)) {
    echo json_encode(['ok' => false, 'erro' => 'Não foi possível salvar a imagem no servidor.']);
    exit;
}

$stmt = $pdo->prepare('UPDATE Barbeiro SET foto = :foto WHERE id_barbeiro = :id');
$stmt->execute(['foto' => $nomeNovo, 'id' => $idBarbeiro]);

// Remove a foto antiga, se havia uma
if ($fotoAtual) {
    $caminhoAntigo = $pastaUploads . $fotoAtual;
    if (is_file($caminhoAntigo)) {
        @unlink($caminhoAntigo);
    }
}

$_SESSION['foto'] = $nomeNovo;

DiscordLogger::configuracoes('🖼️ Foto de perfil atualizada', [
    ['name' => '🆔 Barbeiro', 'value' => '#' . $idBarbeiro, 'inline' => true],
    ['name' => '👤 Nome', 'value' => $_SESSION['nome'] ?? '—', 'inline' => true],
]);

echo json_encode(['ok' => true, 'foto' => $nomeNovo]);
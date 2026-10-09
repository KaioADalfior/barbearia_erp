<?php
// Catalogo/scripts/catalogo_imagem.php
// Envia ou remove o logo / a capa da vitrine pública (AJAX, JSON).
// POST: tipo=logo|capa ; acao=enviar (com arquivo "imagem") | remover

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/guard.php';
exigirSessao(['barbeiro'], json: true);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/AcessoService.php';
require_once __DIR__ . '/../../includes/csrf.php';
// Vitrine pública: só o proprietário configura.
AcessoService::exigirProprietario($pdo, true);
require_once __DIR__ . '/../../includes/CatalogoService.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'erro' => 'Método não permitido.']);
    exit;
}

csrf_verificar(json: true);

$tipo = $_POST['tipo'] ?? '';
$acao = $_POST['acao'] ?? 'enviar';

try {
    if ($acao === 'remover') {
        $resultado = CatalogoService::salvarImagem($pdo, $tipo, null);
    } else {
        if (!isset($_FILES['imagem']) || $_FILES['imagem']['error'] === UPLOAD_ERR_NO_FILE) {
            echo json_encode(['ok' => false, 'erro' => 'Selecione uma imagem.']);
            exit;
        }
        $resultado = CatalogoService::salvarImagem($pdo, $tipo, $_FILES['imagem']);
    }
} catch (Throwable $e) {
    DiscordLogger::erro('💥 Falha ao salvar imagem do catálogo', $e);
    http_response_code(500);
    echo json_encode(['ok' => false, 'erro' => 'Não foi possível salvar a imagem.']);
    exit;
}

if ($resultado['ok']) {
    DiscordLogger::configuracoes('🖼️ Imagem do catálogo ' . ($acao === 'remover' ? 'removida' : 'atualizada'), [
        ['name' => '🏷️ Imagem', 'value' => $tipo === 'logo' ? 'Logo' : 'Capa', 'inline' => true],
        ['name' => '👤 Por', 'value' => $_SESSION['nome'] ?? ('#' . ($_SESSION['id'] ?? '—')), 'inline' => true],
    ]);
}

echo json_encode($resultado);

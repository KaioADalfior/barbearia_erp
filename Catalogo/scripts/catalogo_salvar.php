<?php
// Catalogo/scripts/catalogo_salvar.php
// Salva Catálogo > Configurar (AJAX, JSON). Só apresentação da vitrine pública.

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

// Cria as tabelas (DDL faz commit implícito) ANTES de abrir a transação.
CatalogoService::disponivel($pdo);

try {
    $pdo->beginTransaction();
    $resultado = CatalogoService::salvarConfig($pdo, $_POST);
    if (!$resultado['ok']) {
        $pdo->rollBack();
        echo json_encode($resultado);
        exit;
    }
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    DiscordLogger::erro('💥 Falha ao salvar o catálogo', $e);
    http_response_code(500);
    echo json_encode(['ok' => false, 'erro' => 'Não foi possível salvar o catálogo. Tente novamente.']);
    exit;
}

DiscordLogger::configuracoes('🛍️ Catálogo atualizado', [
    ['name' => '👤 Por', 'value' => $_SESSION['nome'] ?? ('#' . ($_SESSION['id'] ?? '—')), 'inline' => true],
]);

echo json_encode(['ok' => true]);

// Avisa o catálogo central DAK Barber (se a barbearia participa). Depois de responder
// ao usuário e sem nunca atrapalhar: o catálogo também sincroniza periodicamente.
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}
require_once __DIR__ . '/../../includes/DakIntegracao.php';
DakIntegracao::notificarMudanca($pdo);

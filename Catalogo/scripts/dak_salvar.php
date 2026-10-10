<?php
// Catalogo/scripts/dak_salvar.php
// Salva "Participar do catálogo DAK Barber" (AJAX/JSON). Só o proprietário.
// Registra o consentimento, a localização aproximada e (re)registra a instalação no catálogo central.

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/guard.php';
exigirSessao(['barbeiro'], json: true);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/AcessoService.php';
require_once __DIR__ . '/../../includes/csrf.php';
AcessoService::exigirProprietario($pdo, true);
require_once __DIR__ . '/../../includes/DakIntegracao.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'erro' => 'Método não permitido.']);
    exit;
}
csrf_verificar(json: true);

try {
    $antes = DakIntegracao::config($pdo);
    $r = DakIntegracao::salvar($pdo, $_POST, (int) $_SESSION['id']);
    if (!$r['ok']) {
        echo json_encode($r);
        exit;
    }
    $dep = DakIntegracao::config($pdo);
    $aviso = null;
    if ($dep['participa']) {
        $reg = DakIntegracao::registrar($pdo);     // idempotente
        $aviso = $reg['msg'];
        $dep = DakIntegracao::config($pdo);
    } elseif ($antes['participa']) {
        DakIntegracao::notificarMudanca($pdo, 'participacao_revogada');
        $aviso = 'Participação encerrada. Sua barbearia será retirada do catálogo.';
    }
    DiscordLogger::configuracoes($dep['participa'] ? '🌐 Participação no catálogo DAK ativada' : '🌐 Participação no catálogo DAK encerrada', [
        ['name' => '👤 Por', 'value' => $_SESSION['nome'] ?? ('#' . ($_SESSION['id'] ?? '—')), 'inline' => true],
    ]);
    echo json_encode(['ok' => true, 'aviso' => $aviso, 'estado' => [
        'participa' => $dep['participa'], 'central_status' => $dep['central_status'], 'registro_msg' => $dep['registro_msg'],
    ]]);
} catch (Throwable $e) {
    DiscordLogger::erro('💥 Falha ao salvar integração DAK', $e);
    http_response_code(500);
    echo json_encode(['ok' => false, 'erro' => 'Não foi possível salvar. Tente novamente.']);
}

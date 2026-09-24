<?php
// Configuracoes/scripts/link_publico_gerar.php
// Endpoint AJAX (POST) chamado pelo botão "Gerar link" / "Gerar novo link"
// em Configuracoes/paginas/configuracoes.php (aba do barbeiro). Cria um
// novo link público de agendamento (ver includes/PublicoTokenService.php),
// substituindo o anterior — o link antigo, se existir, para de funcionar.

require_once __DIR__ . '/../../includes/session.php';
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/guard.php';
exigirSessao(['barbeiro'], json: true);

require_once __DIR__ . '/../../includes/csrf.php';
csrf_verificar(json: true);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/PublicoTokenService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'erro' => 'Método não permitido.']);
    exit;
}

$idBarbeiro = (int) $_SESSION['id'];
$nome = (string) ($_SESSION['nome'] ?? 'barbearia');

try {
    $token = PublicoTokenService::gerarNovoLink($pdo, $idBarbeiro, $nome);
    echo json_encode(['ok' => true, 'token' => $token]);
} catch (Exception $e) {
    DiscordLogger::erro('💥 Falha ao gerar link público de agendamento', $e);
    http_response_code(500);
    echo json_encode(['ok' => false, 'erro' => 'Não foi possível gerar o link agora.']);
}

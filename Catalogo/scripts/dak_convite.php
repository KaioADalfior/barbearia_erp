<?php
// Catalogo/scripts/dak_convite.php
// POST (AJAX/JSON) usado pelo botão "Convidar para avaliar" de Agendamentos > Lista:
// gera o convite de avaliação (token assinado, uso único) de um atendimento CONCLUÍDO
// e devolve o link do WhatsApp com a mensagem pronta. Funcionário só convida para os próprios atendimentos.

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/guard.php';
exigirSessao(['barbeiro'], json: true);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/AcessoService.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/DakIntegracao.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'erro' => 'Método não permitido.']);
    exit;
}
csrf_verificar(json: true);

$id = (int) ($_POST['idAgendamento'] ?? 0);
if ($id <= 0) {
    echo json_encode(['ok' => false, 'erro' => 'Atendimento inválido.']);
    exit;
}

try {
    DakIntegracao::estrutura($pdo);
    $r = DakIntegracao::emitirConvite($pdo, $id, (int) $_SESSION['id'], AcessoService::ehProprietario($pdo));
    echo json_encode($r);
} catch (Throwable $e) {
    DiscordLogger::erro('💥 Falha ao gerar convite de avaliação', $e);
    http_response_code(500);
    echo json_encode(['ok' => false, 'erro' => 'Não foi possível gerar o convite.']);
}

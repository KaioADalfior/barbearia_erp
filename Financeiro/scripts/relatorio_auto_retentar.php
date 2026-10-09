<?php
// relatorio_auto_retentar.php
// Endpoint AJAX (POST) do botão "Tentar novamente" de Financeiro/paginas/financeiro_relatorios.php:
// refaz uma geração automática que falhou. Só enxerga/refaz falhas do PRÓPRIO barbeiro logado.

require_once __DIR__ . '/../../includes/session.php';
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/guard.php';
exigirSessao(['barbeiro'], json: true);

require_once __DIR__ . '/../../includes/csrf.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar(json: true);
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/RelatorioAutomatico.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'erro' => 'Método não permitido.']);
    exit;
}

$idBarbeiro = (int) $_SESSION['id'];
$chave      = (string) ($_POST['chave'] ?? '');

// Formato fixo: b<id>|<tipo>|<Y-m-d>|<Y-m-d> — nada além disso chega ao banco.
if (!preg_match('/^b\d{1,10}\|(diario|semanal|mensal|anual)\|\d{4}-\d{2}-\d{2}\|\d{4}-\d{2}-\d{2}$/', $chave)) {
    echo json_encode(['ok' => false, 'erro' => 'Falha inválida.']);
    exit;
}

try {
    $r = RelatorioAutomatico::tentarNovamente($pdo, $idBarbeiro, $chave, new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')));
    if ($r['ok']) {
        DiscordLogger::financeiroRelatorios('🔁 Relatório automático regerado', [
            ['name' => '🆔 Profissional', 'value' => '#' . $idBarbeiro, 'inline' => true],
        ]);
    }
    echo json_encode($r);
} catch (Throwable $e) {
    DiscordLogger::erro('💥 Falha ao tentar novamente relatório automático', $e);
    http_response_code(500);
    echo json_encode(['ok' => false, 'erro' => 'Erro ao tentar novamente.']);
}

<?php
// relatorio_baixar.php
// Endpoint (GET) acionado pelo botão "Baixar" na tabela de
// Financeiro/paginas/financeiro_relatorios.php. Não retorna JSON — envia o
// PDF salvo no banco (coluna arquivo_pdf) diretamente como download.

require_once __DIR__ . '/../../includes/session.php';

require_once __DIR__ . '/../../includes/guard.php';
exigirSessao(['barbeiro']); // sem sessão válida, redireciona para o login normalmente

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/RelatorioService.php';

$idBarbeiro  = (int) $_SESSION['id'];
$idRelatorio = (int) ($_GET['id'] ?? 0);
$inline      = ($_GET['inline'] ?? '0') === '1';

$arquivo = $idRelatorio > 0 ? RelatorioService::buscarArquivo($pdo, $idBarbeiro, $idRelatorio) : null;

if (!$arquivo) {
    http_response_code(404);
    echo 'Relatório não encontrado.';
    exit;
}

// Limpa qualquer saída/buffer pendente para não corromper o PDF.
while (ob_get_level() > 0) {
    ob_end_clean();
}

$disposicao = $inline ? 'inline' : 'attachment';

header('Content-Type: application/pdf');
header('Content-Disposition: ' . $disposicao . '; filename="' . basename($arquivo['nome_arquivo']) . '"');
header('Content-Length: ' . strlen($arquivo['arquivo_pdf']));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

echo $arquivo['arquivo_pdf'];
exit;

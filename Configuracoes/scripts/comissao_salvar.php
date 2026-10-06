<?php
// Configuracoes/scripts/comissao_salvar.php
// Salva a porcentagem de comissão dos funcionários (Configurações > Comissão
// dos Barbeiros). Só Proprietário ou Administrador. A nova porcentagem vale
// para os PRÓXIMOS atendimentos concluídos — comissões já geradas guardam a
// porcentagem da época e nunca são recalculadas (ver ComissaoService).

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/guard.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/AcessoService.php';
AcessoService::exigirProprietarioOuAdmin($pdo);

require_once __DIR__ . '/../../includes/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /configuracoes');
    exit;
}

csrf_verificar(json: false, redirecionarPara: '/configuracoes');

$bruto = str_replace(',', '.', trim((string) ($_POST['comissao_percentual'] ?? '')));
if ($bruto === '' || !is_numeric($bruto) || (float) $bruto < 0 || (float) $bruto > 100) {
    header('Location: /configuracoes?comissao=erro');
    exit;
}

$novo   = round((float) $bruto, 2);
$antigo = ComissaoService::percentualAtual($pdo);

ComissaoService::salvarPercentual($pdo, $novo);

DiscordLogger::admin('💼 Comissão dos barbeiros alterada', [
    ['name' => '📉 Antes', 'value' => ComissaoService::fmtPct($antigo), 'inline' => true],
    ['name' => '📈 Agora', 'value' => ComissaoService::fmtPct($novo), 'inline' => true],
    ['name' => '👑 Alterado por', 'value' => $_SESSION['nome'] ?? ('#' . ($_SESSION['id'] ?? '—')), 'inline' => false],
]);

header('Location: /configuracoes?comissao=ok');
exit;

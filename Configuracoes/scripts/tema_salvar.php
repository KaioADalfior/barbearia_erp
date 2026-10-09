<?php
// Configuracoes/scripts/tema_salvar.php
// Salva (ou restaura o padrão de) a COR DE DESTAQUE do sistema interno da
// barbearia (Configurações > Cor da barbearia). Só Proprietário ou
// Administrador; funcionário não altera a identidade da barbearia.
// Armazenamento e regras de contraste: includes/TemaService.php.

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/guard.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/AcessoService.php';
AcessoService::exigirProprietarioOuAdmin($pdo);

require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/TemaService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /configuracoes');
    exit;
}

csrf_verificar(json: false, redirecionarPara: '/configuracoes');

$acao  = $_POST['acao'] ?? 'salvar';
$antes = TemaService::corAtual($pdo);

try {
    if ($acao === 'restaurar') {
        TemaService::restaurarPadrao($pdo);
        $depois = TemaService::COR_PADRAO;
        $status = 'restaurado';
    } else {
        $cor = TemaService::validar($_POST['cor'] ?? null);
        if ($cor === null) {
            header('Location: /configuracoes?tema=erro');
            exit;
        }
        // Salvar a cor padrão equivale a restaurar: não deixa linha à toa.
        if ($cor === TemaService::COR_PADRAO) {
            TemaService::restaurarPadrao($pdo);
        } else {
            TemaService::salvar($pdo, $cor);
        }
        $depois = $cor;
        $status = 'ok';
    }
} catch (Throwable $e) {
    DiscordLogger::erro('💥 Falha ao salvar a cor da barbearia', $e);
    header('Location: /configuracoes?tema=falha');
    exit;
}

DiscordLogger::admin('🎨 Cor de destaque da barbearia alterada', [
    ['name' => '⬅️ Antes', 'value' => $antes, 'inline' => true],
    ['name' => '➡️ Agora', 'value' => $depois, 'inline' => true],
    ['name' => '👑 Alterado por', 'value' => $_SESSION['nome'] ?? ('#' . ($_SESSION['id'] ?? '—')), 'inline' => false],
]);

header('Location: /configuracoes?tema=' . $status);
exit;

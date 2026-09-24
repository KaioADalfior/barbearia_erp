<?php
// baixa_salvar.php
// Endpoint AJAX (POST) chamado pelo modal "Nova Baixa" em
// Financeiro/paginas/financeiro_baixa.php. Cria um lançamento financeiro
// manual (entrada ou saída), sempre com status "pago".

require_once __DIR__ . '/../../includes/session.php';
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/guard.php';
exigirSessao(['barbeiro'], json: true);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/FinanceiroService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'erro' => 'Método não permitido.']);
    exit;
}

$idBarbeiro  = (int) $_SESSION['id'];
$tipo        = $_POST['tipo'] ?? '';
$titulo      = trim($_POST['titulo'] ?? '');
$descricao   = trim($_POST['descricao'] ?? '');
$quantidade  = ($_POST['quantidade'] ?? '') !== '' ? (int) $_POST['quantidade'] : null;
$data        = $_POST['data'] ?? '';
$valor       = (float) str_replace(',', '.', $_POST['valor'] ?? '0');
$formasBrutas = $_POST['formas'] ?? [];
$formasBrutas = is_array($formasBrutas) ? $formasBrutas : [];

if (!in_array($tipo, ['entrada', 'saida'], true)) {
    echo json_encode(['ok' => false, 'erro' => 'Tipo de lançamento inválido.']);
    exit;
}

if ($titulo === '') {
    echo json_encode(['ok' => false, 'erro' => 'Informe um título para o lançamento.']);
    exit;
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
    echo json_encode(['ok' => false, 'erro' => 'Informe uma data válida.']);
    exit;
}

if ($valor <= 0) {
    echo json_encode(['ok' => false, 'erro' => 'Informe um valor válido.']);
    exit;
}

$formas = FinanceiroService::normalizarFormasPagamento($formasBrutas);
if (empty($formas)) {
    echo json_encode(['ok' => false, 'erro' => 'Selecione ao menos uma forma de pagamento.']);
    exit;
}

try {
    $idLancamento = FinanceiroService::criarBaixaManual($pdo, $idBarbeiro, [
        'tipo'       => $tipo,
        'titulo'     => $titulo,
        'descricao'  => $descricao,
        'quantidade' => $quantidade,
        'data'       => $data,
        'valor'      => $valor,
        'formas'     => $formas,
    ]);

    DiscordLogger::financeiroLancamentos('💵 Baixa lançada', [
        ['name' => '📌 Título', 'value' => $titulo, 'inline' => true],
        ['name' => '💰 Valor', 'value' => 'R$ ' . number_format($valor, 2, ',', '.'), 'inline' => true],
        ['name' => '↕️ Tipo', 'value' => $tipo === 'entrada' ? 'Entrada' : 'Saída', 'inline' => true],
    ]);

    echo json_encode(['ok' => true, 'idLancamento' => $idLancamento]);
} catch (Exception $e) {
    DiscordLogger::erro('💥 Falha ao salvar baixa', $e);
    http_response_code(500);
    echo json_encode(['ok' => false, 'erro' => 'Erro ao salvar o lançamento.']);
}

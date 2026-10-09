<?php
// relatorio_gerar.php
// Endpoint AJAX (POST) usado por Financeiro/paginas/financeiro_relatorios.php.
// O barbeiro escolhe o tipo (diario/semanal/mensal/anual/periodo).
// Para diario/semanal/mensal/anual ele manda uma data de referência e o
// período é calculado a partir dela. Para "periodo" ele manda diretamente
// data_inicio e data_fim. Este script agrega os lançamentos, monta o PDF
// (RelatorioPdfBuilder) e salva tudo em FinanceiroRelatorios.

require_once __DIR__ . '/../../includes/session.php';
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/guard.php';
exigirSessao(['barbeiro'], json: true);

// CSRF: o header X-CSRF-Token é enviado sozinho por includes/sidebar-script.php.
require_once __DIR__ . '/../../includes/csrf.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar(json: true);
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/forma_pagamento.php';
require_once __DIR__ . '/../../includes/RelatorioService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'erro' => 'Método não permitido.']);
    exit;
}

$idBarbeiro   = (int) $_SESSION['id'];

$tipo = $_POST['tipo'] ?? '';

// 'periodo' (intervalo livre) é tratado abaixo e não tem entrada em TIPOS_LABELS.
$tiposValidos = array_merge(array_keys(RelatorioService::TIPOS_LABELS), ['periodo']);
if (!in_array($tipo, $tiposValidos, true)) {
    echo json_encode(['ok' => false, 'erro' => 'Tipo de relatório inválido.']);
    exit;
}

// Função auxiliar: valida uma string 'Y-m-d'.
function dataEhValida(string $data): bool
{
    if ($data === '') {
        return false;
    }
    $d = DateTime::createFromFormat('Y-m-d', $data);
    return $d && $d->format('Y-m-d') === $data;
}

if ($tipo === 'periodo') {
    // Tipo "período": o próprio intervalo já vem pronto do formulário.
    $dataInicio = $_POST['data_inicio'] ?? '';
    $dataFim    = $_POST['data_fim'] ?? '';

    if (!dataEhValida($dataInicio) || !dataEhValida($dataFim)) {
        echo json_encode(['ok' => false, 'erro' => 'Datas de início/fim inválidas.']);
        exit;
    }

    if ($dataInicio > $dataFim) {
        echo json_encode(['ok' => false, 'erro' => 'A data de início não pode ser depois da data de fim.']);
        exit;
    }

    // Limite de intervalo: evita relatórios gigantes (consumo de memória/CPU).
    $dias = (new DateTime($dataInicio))->diff(new DateTime($dataFim))->days;
    if ($dias > 366) {
        echo json_encode(['ok' => false, 'erro' => 'O período do relatório não pode passar de 1 ano.']);
        exit;
    }
} else {
    // Tipos diario/semanal/mensal/anual: calcula o intervalo a partir de
    // uma única data de referência.
    $dataRef = $_POST['data'] ?? '';

    if (!dataEhValida($dataRef)) {
        echo json_encode(['ok' => false, 'erro' => 'Data de referência inválida.']);
        exit;
    }

    [$dataInicio, $dataFim] = RelatorioService::calcularIntervalo($tipo, $dataRef);
}

try {
    // Mesmo caminho da geração automática (RelatorioService::emitir): dados,
    // logo/identidade da barbearia, PDF e gravação.
    $emitido     = RelatorioService::emitir($pdo, $idBarbeiro, $tipo, $dataInicio, $dataFim, RelatorioService::ORIGEM_MANUAL);
    $titulo      = $emitido['titulo'];
    $idRelatorio = $emitido['id'];

    DiscordLogger::financeiroRelatorios('📄 Relatório financeiro gerado', [
        ['name' => '🏷️ Tipo', 'value' => (RelatorioService::TIPOS_LABELS[$tipo] ?? 'Período'), 'inline' => true],
        ['name' => '📅 Período', 'value' => $titulo, 'inline' => true],
        ['name' => '🆔 Relatório', 'value' => '#' . $idRelatorio, 'inline' => true],
    ]);

    echo json_encode(['ok' => true, 'idRelatorio' => $idRelatorio]);
} catch (Throwable $e) {
    DiscordLogger::erro('💥 Falha ao gerar relatório financeiro', $e);
    http_response_code(500);
    echo json_encode(['ok' => false, 'erro' => 'Não foi possível gerar o relatório.']);
}
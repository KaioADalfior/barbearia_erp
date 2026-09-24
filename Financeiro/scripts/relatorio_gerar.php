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

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/forma_pagamento.php';
require_once __DIR__ . '/../../includes/RelatorioService.php';
require_once __DIR__ . '/../../includes/RelatorioPdfBuilder.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'erro' => 'Método não permitido.']);
    exit;
}

$idBarbeiro   = (int) $_SESSION['id'];
$nomeBarbeiro = $_SESSION['nome'] ?? 'Barbeiro';

$tipo = $_POST['tipo'] ?? '';

$tiposValidos = array_keys(RelatorioService::TIPOS_LABELS);
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
    $titulo = RelatorioService::tituloPeriodo($tipo, $dataInicio, $dataFim);
    $dados  = RelatorioService::coletarDados($pdo, $idBarbeiro, $dataInicio, $dataFim);

    $logoPath = __DIR__ . '/../../assets/img/logoRelatorio.png';

    $pdfBytes = construirRelatorioPdf(
        'Sistema de Gestão',
        $nomeBarbeiro,
        $tipo,
        $dataInicio,
        $dataFim,
        $titulo,
        $dados,
        $logoPath
    );

    $nomeArquivo = 'relatorio-' . $tipo . '-' . $dataInicio
        . ($dataInicio !== $dataFim ? '_a_' . $dataFim : '')
        . '.pdf';

    $idRelatorio = RelatorioService::salvar(
        $pdo,
        $idBarbeiro,
        $tipo,
        $dataInicio,
        $dataFim,
        $titulo,
        $dados,
        $pdfBytes,
        $nomeArquivo
    );

    DiscordLogger::financeiroRelatorios('📄 Relatório financeiro gerado', [
        ['name' => '🏷️ Tipo', 'value' => RelatorioService::TIPOS_LABELS[$tipo], 'inline' => true],
        ['name' => '📅 Período', 'value' => $titulo, 'inline' => true],
        ['name' => '🆔 Relatório', 'value' => '#' . $idRelatorio, 'inline' => true],
    ]);

    echo json_encode(['ok' => true, 'idRelatorio' => $idRelatorio]);
} catch (Exception $e) {
    DiscordLogger::erro('💥 Falha ao gerar relatório financeiro', $e);
    http_response_code(500);
    echo json_encode(['ok' => false, 'erro' => 'Não foi possível gerar o relatório.']);
}
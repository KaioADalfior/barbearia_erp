<?php
/**
 * includes/RelatorioPdfBuilder.php
 *
 * Gera o PDF do relatório financeiro (Financeiro > Relatórios) usando a
 * biblioteca FPDF (100% PHP puro, sem extensões além das já nativas do
 * PHP — compatível com hospedagens compartilhadas como o InfinityFree, que
 * não permitem instalar extensões nem rodar `composer install` via SSH).
 *
 * A logo do barbeiro é lida de assets/img/logoRelatorio.png. Se o arquivo
 * ainda não tiver sido enviado ao servidor, o cabeçalho é desenhado
 * normalmente, só sem a imagem (não quebra a geração do relatório).
 */

require_once __DIR__ . '/libs/fpdf/fpdf.php';
require_once __DIR__ . '/forma_pagamento.php'; // FORMAS_PAGAMENTO_LABELS

class RelatorioPdfBuilder extends FPDF
{
    // Paleta alinhada à identidade visual do painel (azul-marinho/charcoal).
    private const COR_DOURADO      = [61, 126, 201];
    private const COR_DOURADO_ESC  = [30, 74, 128];
    private const COR_CHARCOAL     = [22, 35, 58];
    private const COR_CINZA_TEXTO  = [90, 107, 140];
    private const COR_CINZA_CLARO  = [222, 230, 242];
    private const COR_LINHA        = [206, 217, 232];
    private const COR_VERDE        = [31, 107, 48];
    private const COR_VERDE_FUNDO  = [227, 241, 227];
    private const COR_VERMELHO     = [140, 31, 40];
    private const COR_VERMELHO_FDO = [248, 228, 227];
    private const COR_AMARELO_FDO  = [222, 234, 248];
    private const COR_AMARELO_TXT  = [30, 74, 128];

    private string $nomeBarbearia;
    private string $nomeBarbeiro;
    private string $tituloRelatorio;
    private string $subtituloRelatorio;
    private string $geradoEm;
    private ?string $logoPath;

    public function __construct(
        string $nomeBarbearia,
        string $nomeBarbeiro,
        string $tituloRelatorio,
        string $subtituloRelatorio,
        ?string $logoPath
    ) {
        parent::__construct('P', 'mm', 'A4');

        $this->nomeBarbearia     = $nomeBarbearia;
        $this->nomeBarbeiro      = $nomeBarbeiro;
        $this->tituloRelatorio   = $tituloRelatorio;
        $this->subtituloRelatorio = $subtituloRelatorio;
        $this->geradoEm          = (new DateTime())->format('d/m/Y \à\s H:i');
        $this->logoPath          = ($logoPath && is_file($logoPath)) ? $logoPath : null;

        $this->SetMargins(14, 32, 14);
        $this->SetAutoPageBreak(true, 20);
        $this->AliasNbPages();
        $this->SetTitle($this->conv($tituloRelatorio));
        $this->SetCreator('BarbERP');
    }

    /** Converte UTF-8 (padrão do resto do sistema) para o encoding das fontes core do FPDF. */
    private function conv(string $texto): string
    {
        $convertido = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $texto);
        return $convertido !== false ? $convertido : $texto;
    }

    private function moeda(float $valor): string
    {
        return 'R$ ' . number_format($valor, 2, ',', '.');
    }

    private function dataBr(string $dataIso): string
    {
        return (new DateTimeImmutable($dataIso))->format('d/m/Y');
    }

    // ==================== CABEÇALHO / RODAPÉ (repetem em toda página) ====================

    public function Header(): void
    {
        // Faixa azul no topo da página.
        $this->SetFillColor(...self::COR_DOURADO);
        $this->Rect(0, 0, 210, 2.2, 'F');

        $y = 9;
        $xTexto = 14;

        if ($this->logoPath) {
            // A logo já contém o nome do sistema na própria arte, então
            // o nome não é repetido em texto ao lado — evita redundância.
            $this->Image($this->logoPath, 14, $y, 26);
            $xTexto = 44;
            $this->SetXY($xTexto, $y + 6);
        } else {
            // Sem logo disponível: mantém o nome em texto para não deixar o
            // cabeçalho vazio.
            $this->SetXY($xTexto, $y);
            $this->SetTextColor(...self::COR_CHARCOAL);
            $this->SetFont('Helvetica', 'B', 15);
            $this->Cell(90, 7, $this->conv($this->nomeBarbearia), 0, 2, 'L');
            $this->SetX($xTexto);
        }

        $this->SetFont('Helvetica', '', 9);
        $this->SetTextColor(...self::COR_CINZA_TEXTO);
        $this->Cell(90, 5, $this->conv('Barbeiro responsavel: ' . $this->nomeBarbeiro), 0, 2, 'L');

        // Bloco de título do relatório, alinhado à direita.
        $this->SetXY(110, $y);
        $this->SetFont('Helvetica', 'B', 13);
        $this->SetTextColor(...self::COR_DOURADO_ESC);
        $this->Cell(86, 7, $this->conv($this->tituloRelatorio), 0, 2, 'R');

        $this->SetX(110);
        $this->SetFont('Helvetica', '', 9);
        $this->SetTextColor(...self::COR_CINZA_TEXTO);
        $this->Cell(86, 5, $this->conv($this->subtituloRelatorio), 0, 2, 'R');

        $this->SetDrawColor(...self::COR_DOURADO);
        $this->SetLineWidth(0.5);
        $this->Line(14, 28, 196, 28);
    }

    public function Footer(): void
    {
        $this->SetY(-16);
        $this->SetDrawColor(...self::COR_LINHA);
        $this->SetLineWidth(0.2);
        $this->Line(14, $this->GetY(), 196, $this->GetY());

        $this->SetY(-13);
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(...self::COR_CINZA_TEXTO);
        $this->Cell(91, 5, $this->conv('Gerado em ' . $this->geradoEm), 0, 0, 'L');
        $this->Cell(91, 5, $this->conv('Pagina ' . $this->PageNo() . ' de {nb}'), 0, 0, 'R');
    }

    // ==================== BLOCOS DE CONTEÚDO ====================

    private function kpiCard(float $x, float $y, float $w, string $label, string $valor, array $corTexto, array $corFundo): void
    {
        $h = 22;
        $this->SetFillColor(...$corFundo);
        $this->SetDrawColor(...self::COR_LINHA);
        $this->SetLineWidth(0.2);
        $this->Rect($x, $y, $w, $h, 'DF');

        // Barra de destaque à esquerda do card, na cor do indicador.
        $this->SetFillColor(...$corTexto);
        $this->Rect($x, $y, 1.2, $h, 'F');

        $this->SetXY($x + 4, $y + 3.5);
        $this->SetFont('Helvetica', '', 7.5);
        $this->SetTextColor(...self::COR_CINZA_TEXTO);
        $this->Cell($w - 8, 4, $this->conv(mb_strtoupper($label, 'UTF-8')), 0, 2, 'L');

        $this->SetX($x + 4);
        $this->SetFont('Helvetica', 'B', 14);
        $this->SetTextColor(...$corTexto);
        $this->Cell($w - 8, 9, $this->conv($valor), 0, 2, 'L');
    }

    private function cabecalhoTabelaLancamentos(): void
    {
        $this->SetFont('Helvetica', 'B', 8.5);
        $this->SetFillColor(...self::COR_CHARCOAL);
        $this->SetTextColor(255, 255, 255);
        $this->SetDrawColor(...self::COR_CHARCOAL);

        $this->Cell(22, 8, $this->conv('DATA'), 0, 0, 'L', true);
        $this->Cell(20, 8, $this->conv('TIPO'), 0, 0, 'L', true);
        $this->Cell(76, 8, $this->conv('DESCRICAO'), 0, 0, 'L', true);
        $this->Cell(34, 8, $this->conv('FORMA DE PAGTO'), 0, 0, 'L', true);
        $this->Cell(30, 8, $this->conv('VALOR'), 0, 1, 'R', true);
    }

    private function garantirEspacoTabela(): void
    {
        if ($this->GetY() > 262) {
            $this->AddPage();
            $this->cabecalhoTabelaLancamentos();
        }
    }

    /**
     * Monta o corpo completo do relatório: KPIs, aviso de fiados (se
     * houver), resumo por forma de pagamento e a tabela detalhada de
     * lançamentos do período.
     */
    public function montarConteudo(array $dados): void
    {
        $this->AddPage();

        // ---------- KPIs ----------
        $y = $this->GetY();
        $w = (182 - 3 * 4) / 4;

        $this->kpiCard(14, $y, $w, 'Entradas', $this->moeda($dados['totalEntradas']), self::COR_VERDE, self::COR_VERDE_FUNDO);
        $this->kpiCard(14 + ($w + 4) * 1, $y, $w, 'Saidas', $this->moeda($dados['totalSaidas']), self::COR_VERMELHO, self::COR_VERMELHO_FDO);

        $corSaldo   = $dados['saldo'] >= 0 ? self::COR_VERDE : self::COR_VERMELHO;
        $fundoSaldo = $dados['saldo'] >= 0 ? self::COR_VERDE_FUNDO : self::COR_VERMELHO_FDO;
        $this->kpiCard(14 + ($w + 4) * 2, $y, $w, 'Saldo', $this->moeda($dados['saldo']), $corSaldo, $fundoSaldo);
        $this->kpiCard(14 + ($w + 4) * 3, $y, $w, 'Movimentacoes', (string) $dados['qtd'], self::COR_CHARCOAL, self::COR_CINZA_CLARO);

        $this->SetY($y + 22 + 6);

        // ---------- Aviso de fiados em aberto no período ----------
        if ($dados['fiadosQtd'] > 0) {
            $yAviso = $this->GetY();
            $this->SetFillColor(...self::COR_AMARELO_FDO);
            $this->SetDrawColor(...self::COR_DOURADO);
            $this->SetLineWidth(0.2);
            $this->Rect(14, $yAviso, 182, 12, 'DF');
            $this->SetFillColor(...self::COR_DOURADO);
            $this->Rect(14, $yAviso, 1.2, 12, 'F');

            $this->SetXY(18, $yAviso + 3);
            $this->SetFont('Helvetica', 'B', 9);
            $this->SetTextColor(...self::COR_AMARELO_TXT);
            $texto = sprintf(
                'Fiados em aberto no periodo: %d lancamento(s), totalizando %s (fora do saldo acima).',
                $dados['fiadosQtd'],
                $this->moeda($dados['fiadosTotal'])
            );
            $this->Cell(174, 5, $this->conv($texto), 0, 1, 'L');

            $this->SetY($yAviso + 12 + 6);
        }

        // ---------- Resumo por forma de pagamento ----------
        $formasComValor = array_filter($dados['porForma'], fn($v) => $v > 0.0);
        if (!empty($formasComValor)) {
            $this->SetFont('Helvetica', 'B', 10.5);
            $this->SetTextColor(...self::COR_CHARCOAL);
            $this->Cell(0, 6, $this->conv('Resumo por forma de pagamento'), 0, 1, 'L');
            $this->Ln(1);

            $this->SetFont('Helvetica', '', 9);
            foreach ($formasComValor as $chave => $valor) {
                $label = FORMAS_PAGAMENTO_LABELS[$chave] ?? $chave;
                $pct = $dados['totalEntradas'] > 0 ? ($valor / $dados['totalEntradas']) * 100 : 0;

                $this->SetTextColor(...self::COR_CINZA_TEXTO);
                $this->Cell(60, 6, $this->conv($label), 0, 0, 'L');
                $this->SetTextColor(...self::COR_CHARCOAL);
                $this->Cell(60, 6, $this->conv($this->moeda($valor)), 0, 0, 'L');
                $this->SetTextColor(...self::COR_CINZA_TEXTO);
                $this->Cell(62, 6, $this->conv(number_format($pct, 1, ',', '.') . '% das entradas'), 0, 1, 'R');
            }
            $this->Ln(3);
        }

        // ---------- Tabela de lançamentos ----------
        $this->SetFont('Helvetica', 'B', 10.5);
        $this->SetTextColor(...self::COR_CHARCOAL);
        $this->Cell(0, 6, $this->conv('Lancamentos do periodo'), 0, 1, 'L');
        $this->Ln(1);

        if (empty($dados['lancamentos'])) {
            $this->SetFont('Helvetica', '', 9.5);
            $this->SetTextColor(...self::COR_CINZA_TEXTO);
            $this->Cell(0, 8, $this->conv('Nenhum lancamento pago registrado neste periodo.'), 0, 1, 'L');
            return;
        }

        $this->cabecalhoTabelaLancamentos();

        $labelTipo = ['entrada' => 'Entrada', 'saida' => 'Saida'];
        $linha = 0;

        foreach ($dados['lancamentos'] as $l) {
            $this->garantirEspacoTabela();

            $fundo = ($linha % 2 === 0) ? [255, 255, 255] : [246, 244, 238];
            $this->SetFillColor(...$fundo);
            $this->SetDrawColor(...self::COR_LINHA);
            $this->SetFont('Helvetica', '', 8.5);
            $this->SetTextColor(...self::COR_CHARCOAL);

            $formas = array_filter(explode(',', (string) $l['forma_pagamento']));
            $formaTexto = $formas
                ? implode(', ', array_map(fn($f) => FORMAS_PAGAMENTO_LABELS[$f] ?? $f, $formas))
                : '-';

            $descricao = (string) $l['titulo'];
            if (mb_strlen($descricao, 'UTF-8') > 46) {
                $descricao = mb_substr($descricao, 0, 43, 'UTF-8') . '...';
            }

            $this->Cell(22, 7, $this->conv($this->dataBr($l['data'])), 0, 0, 'L', true);
            $this->Cell(20, 7, $this->conv($labelTipo[$l['tipo']] ?? $l['tipo']), 0, 0, 'L', true);
            $this->Cell(76, 7, $this->conv($descricao), 0, 0, 'L', true);
            $this->Cell(34, 7, $this->conv($formaTexto), 0, 0, 'L', true);

            $this->SetTextColor(...($l['tipo'] === 'entrada' ? self::COR_VERDE : self::COR_VERMELHO));
            $this->Cell(30, 7, $this->conv(($l['tipo'] === 'entrada' ? '+ ' : '- ') . $this->moeda((float) $l['valor'])), 0, 1, 'R', true);

            $linha++;
        }

        // Linha de total.
        $this->SetFont('Helvetica', 'B', 9);
        $this->SetTextColor(...self::COR_CHARCOAL);
        $this->SetDrawColor(...self::COR_CHARCOAL);
        $this->SetLineWidth(0.3);
        $this->Line(14, $this->GetY(), 196, $this->GetY());
        $this->Ln(2);
        $this->Cell(152, 7, $this->conv('Saldo do periodo'), 0, 0, 'R');
        $this->SetTextColor(...$corSaldo);
        $this->Cell(30, 7, $this->conv($this->moeda($dados['saldo'])), 0, 1, 'R');
    }
}

/**
 * Função de conveniência: monta o PDF completo e retorna os bytes prontos
 * para salvar no banco (coluna arquivo_pdf) ou enviar ao navegador.
 */
function construirRelatorioPdf(
    string $nomeBarbearia,
    string $nomeBarbeiro,
    string $tipo,
    string $dataInicio,
    string $dataFim,
    string $titulo,
    array $dados,
    ?string $logoPath
): string {
    $di = (new DateTimeImmutable($dataInicio))->format('d/m/Y');
    $df = (new DateTimeImmutable($dataFim))->format('d/m/Y');
    $subtitulo = ($dataInicio === $dataFim) ? $di : "{$di}  a  {$df}";

    $pdf = new RelatorioPdfBuilder($nomeBarbearia, $nomeBarbeiro, $titulo, $subtitulo, $logoPath);
    $pdf->montarConteudo($dados);

    return $pdf->Output('S');
}

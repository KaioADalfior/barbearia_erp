<?php
/**
 * includes/RelatorioPdfBuilder.php
 *
 * Gera o PDF do relatório financeiro (Financeiro > Relatórios) usando a
 * biblioteca FPDF (100% PHP puro, sem extensões além das já nativas do
 * PHP — compatível com hospedagens compartilhadas e com a imagem do
 * EasyPanel/Nixpacks).
 *
 * Estrutura do documento:
 *   - cabeçalho (em toda página): logo da barbearia (ou monograma, quando não
 *     há logo), nome, endereço/contatos e o tipo + período do relatório;
 *   - página 1: dados do relatório (período, profissional, emissão), resumo
 *     com os indicadores (receitas, despesas, resultado, movimentações),
 *     fiados pendentes e comissões, receitas por forma de pagamento;
 *   - detalhamento: tabela de movimentações com descrição quebrada em
 *     várias linhas (nada é cortado), cabeçalho repetido a cada página e
 *     totais destacados no final;
 *   - rodapé (em toda página): data/hora de emissão, origem e "Página x de y".
 *
 * Nenhum valor é calculado aqui: tudo vem pronto de RelatorioService (mesmas
 * regras do Dashboard/Comissões). A logo vem de RelatorioIdentidade já
 * tratada (PNG sem transparência, proporção original preservada).
 */

require_once __DIR__ . '/libs/fpdf/fpdf.php';
require_once __DIR__ . '/forma_pagamento.php'; // FORMAS_PAGAMENTO_LABELS

class RelatorioPdfBuilder extends FPDF
{
    private const MARGEM  = 14.0;
    private const LARGURA = 182.0;

    private const TINTA        = [38, 31, 24];
    private const CINZA        = [104, 94, 80];
    private const CINZA_CLARO  = [236, 230, 218];
    private const LINHA        = [214, 205, 188];
    private const ZEBRA        = [249, 246, 239];
    private const VERDE        = [24, 110, 70];
    private const VERDE_FUNDO  = [226, 242, 233];
    private const VERMELHO     = [166, 40, 32];
    private const VERMELHO_FDO = [248, 229, 226];
    private const AMBAR        = [138, 90, 8];
    private const AMBAR_FUNDO  = [250, 240, 214];

    /** @var array{nome:string, endereco:string, contato:string, logo:?array} */
    private array $identidade;
    /** @var array{nome:string, tipo:string} */
    private array $profissional;
    private string $rotuloTipo;
    private string $periodoCurto;
    private string $geradoEm;
    private string $origemTexto;
    /** @var array{0:int,1:int,2:int} */
    private array $acento      = [136, 109, 50];
    private array $acentoFundo = [201, 161, 74];

    public function __construct(array $identidade, array $profissional, string $rotuloTipo, string $periodoCurto, array $meta)
    {
        parent::__construct('P', 'mm', 'A4');

        $this->identidade   = $identidade;
        $this->profissional = $profissional;
        $this->rotuloTipo   = $rotuloTipo;
        $this->periodoCurto = $periodoCurto;

        $quando = $meta['geradoEm'] ?? new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo'));
        $this->geradoEm    = $quando->format('d/m/Y \à\s H:i');
        $this->origemTexto = ($meta['origem'] ?? 'manual') === 'automatico' ? 'geração automática' : 'geração manual';

        if (!empty($meta['cor']['accent-strong']) && !empty($meta['cor']['accent'])) {
            $this->acento      = $this->hexParaRgb($meta['cor']['accent-strong']);
            $this->acentoFundo = $this->hexParaRgb($meta['cor']['accent']);
        }

        $this->SetMargins(self::MARGEM, 36, self::MARGEM);
        $this->SetAutoPageBreak(true, 20);
        $this->AliasNbPages();
        $this->SetTitle($this->conv('Relatório ' . $rotuloTipo . ' — ' . $periodoCurto));
        $this->SetAuthor($this->conv($identidade['nome']));
        $this->SetCreator('BarbERP');
    }

    // ------------------------------------------------------------ utilitários

    /** UTF-8 -> cp1252 (encoding das fontes core do FPDF; mantém acentos, travessão e bullet). */
    private function conv(string $texto): string
    {
        $r = @iconv('UTF-8', 'CP1252//TRANSLIT', $texto);
        return $r !== false ? $r : $texto;
    }

    private function hexParaRgb(string $hex): array
    {
        $h = ltrim($hex, '#');
        return [hexdec(substr($h, 0, 2)), hexdec(substr($h, 2, 2)), hexdec(substr($h, 4, 2))];
    }

    private function moeda(float $v): string
    {
        $txt = 'R$ ' . number_format(abs($v), 2, ',', '.');
        return $v < -0.004 ? '- ' . $txt : $txt;
    }

    private function dataBr(string $iso): string
    {
        return (new DateTimeImmutable($iso))->format('d/m/Y');
    }

    private function texto(float $x, float $y, float $w, float $h, string $t, string $alin = 'L'): void
    {
        $this->SetXY($x, $y);
        $this->Cell($w, $h, $this->conv($t), 0, 0, $alin);
    }

    /** Reduz $t com reticências até caber em $largura na fonte atual (só para rótulos curtos). */
    private function ajustar(string $t, float $largura): string
    {
        while (mb_strlen($t, 'UTF-8') > 6 && $this->GetStringWidth($this->conv($t)) > $largura) {
            $t = rtrim(mb_substr($t, 0, -2, 'UTF-8')) . '…';
        }
        return $t;
    }

    /** Quebra $texto em linhas que cabem em $largura (mm) na fonte atual; nada é descartado. */
    private function quebrar(string $texto, float $largura): array
    {
        $texto = trim(preg_replace('/\s+/u', ' ', $texto) ?? '');
        if ($texto === '') {
            return [''];
        }
        $linhas = [];
        $atual  = '';
        foreach (explode(' ', $texto) as $palavra) {
            $teste = $atual === '' ? $palavra : $atual . ' ' . $palavra;
            if ($this->GetStringWidth($this->conv($teste)) <= $largura) {
                $atual = $teste;
                continue;
            }
            if ($atual !== '') {
                $linhas[] = $atual;
                $atual = '';
            }
            // palavra sozinha maior que a coluna: parte em pedaços
            while ($this->GetStringWidth($this->conv($palavra)) > $largura && mb_strlen($palavra, 'UTF-8') > 1) {
                $n = mb_strlen($palavra, 'UTF-8');
                while ($n > 1 && $this->GetStringWidth($this->conv(mb_substr($palavra, 0, $n, 'UTF-8'))) > $largura) {
                    $n--;
                }
                $linhas[] = mb_substr($palavra, 0, $n, 'UTF-8');
                $palavra  = mb_substr($palavra, $n, null, 'UTF-8');
            }
            $atual = $palavra;
        }
        if ($atual !== '') {
            $linhas[] = $atual;
        }
        return $linhas ?: [''];
    }

    // -------------------------------------------------- cabeçalho / rodapé

    public function Header(): void
    {
        $m = self::MARGEM;

        $this->SetFillColor(...$this->acentoFundo);
        $this->Rect(0, 0, 210, 2.4, 'F');

        // ---- Logo (proporção original dentro de uma caixa 36 x 20 mm) ou monograma
        $caixaW = 36.0;
        $caixaH = 20.0;
        $y0     = 7.0;
        $xTexto = $m;
        $logo   = $this->identidade['logo'] ?? null;
        $desenhou = false;

        if (is_array($logo) && !empty($logo['arquivo']) && is_file($logo['arquivo'])) {
            $razao = $logo['largura'] / max(1, $logo['altura']);
            $w = $caixaW;
            $h = $w / $razao;
            if ($h > $caixaH) {
                $h = $caixaH;
                $w = $h * $razao;
            }
            try {
                $this->Image($logo['arquivo'], $m, $y0 + ($caixaH - $h) / 2, $w, $h, 'PNG');
                $xTexto   = $m + $w + 4;
                $desenhou = true;
            } catch (Throwable $e) {
                error_log('RelatorioPdfBuilder: logo não pôde ser desenhada: ' . $e->getMessage());
            }
        }
        if (!$desenhou) {
            // Sem logo: monograma com a inicial da barbearia (mesmo espaço, sem quebrar o layout).
            $lado = 16.0;
            $this->SetFillColor(...$this->acentoFundo);
            $this->Rect($m, $y0 + 2, $lado, $lado, 'F');
            $inicial = mb_strtoupper(mb_substr($this->identidade['nome'], 0, 1, 'UTF-8'), 'UTF-8');
            $this->SetFont('Helvetica', 'B', 20);
            $this->SetTextColor(...self::TINTA);
            $this->texto($m, $y0 + 2, $lado, $lado, $inicial, 'C');
            $xTexto = $m + $lado + 4;
        }

        // ---- Identificação da barbearia
        $larguraTexto = 108 - ($xTexto - $m);
        $this->SetTextColor(...self::TINTA);
        $this->SetFont('Helvetica', 'B', 13);
        $this->texto($xTexto, $y0 + 2.2, $larguraTexto, 6, $this->ajustar($this->identidade['nome'], $larguraTexto));

        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(...self::CINZA);
        $yLinha = $y0 + 8.6;
        $linhasId = [];
        if ($this->identidade['endereco'] !== '') {
            // Endereço em até 2 linhas (a 2ª com reticências só se ainda sobrar texto).
            $q = $this->quebrar($this->identidade['endereco'], $larguraTexto);
            $linhasId[] = $q[0];
            if (isset($q[1])) {
                $linhasId[] = isset($q[2]) ? $q[1] . '…' : $q[1];
            }
        }
        if ($this->identidade['contato'] !== '') {
            $linhasId[] = $this->identidade['contato'];
        }
        foreach ($linhasId as $linha) {
            $this->texto($xTexto, $yLinha, $larguraTexto, 3.8, $this->ajustar($linha, $larguraTexto));
            $yLinha += 3.8;
        }

        // ---- Tipo e período do relatório (direita)
        $this->SetFont('Helvetica', 'B', 8);
        $this->SetTextColor(...$this->acento);
        $this->texto(112, $y0 + 1.5, 84, 4, mb_strtoupper('Relatório financeiro ' . $this->rotuloTipo, 'UTF-8'), 'R');
        $this->SetFont('Helvetica', 'B', 12.5);
        $this->SetTextColor(...self::TINTA);
        $this->texto(112, $y0 + 6.5, 84, 6, $this->periodoCurto, 'R');
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(...self::CINZA);
        $this->texto(112, $y0 + 13, 84, 4, $this->ajustar('Profissional: ' . $this->profissional['nome'], 84), 'R');

        $this->SetDrawColor(...$this->acentoFundo);
        $this->SetLineWidth(0.5);
        $this->Line($m, 31.5, $m + self::LARGURA, 31.5);
        $this->SetLineWidth(0.2);
        $this->SetY(36);
    }

    public function Footer(): void
    {
        $m = self::MARGEM;
        $this->SetDrawColor(...self::LINHA);
        $this->SetLineWidth(0.2);
        $this->Line($m, 281, $m + self::LARGURA, 281);

        $this->SetFont('Helvetica', '', 7.5);
        $this->SetTextColor(...self::CINZA);
        $this->texto($m, 283, 140, 4, $this->ajustar('Emitido em ' . $this->geradoEm . ' (' . $this->origemTexto . ') · ' . $this->identidade['nome'] . ' · BarbERP', 140));
        $this->texto($m + 140, 283, 42, 4, 'Página ' . $this->PageNo() . ' de {nb}', 'R');
    }

    // --------------------------------------------------------- componentes

    private function secao(string $titulo, float $alturaMinima = 40): void
    {
        if ($this->GetY() + $alturaMinima > $this->PageBreakTrigger) {
            $this->AddPage();
        }
        $y = $this->GetY();
        $this->SetFillColor(...$this->acentoFundo);
        $this->Rect(self::MARGEM, $y + 0.6, 1.6, 5.2, 'F');
        $this->SetFont('Helvetica', 'B', 11);
        $this->SetTextColor(...self::TINTA);
        $this->texto(self::MARGEM + 4, $y, 170, 6.4, $titulo);
        $this->SetY($y + 9);
    }

    private function indicador(float $x, float $y, float $w, string $rotulo, string $valor, array $cor, array $fundo, string $nota = ''): void
    {
        $h = 24;
        $this->SetFillColor(...$fundo);
        $this->SetDrawColor(...self::LINHA);
        $this->SetLineWidth(0.2);
        $this->Rect($x, $y, $w, $h, 'DF');
        $this->SetFillColor(...$cor);
        $this->Rect($x, $y, 1.4, $h, 'F');

        $this->SetFont('Helvetica', 'B', 7.2);
        $this->SetTextColor(...self::CINZA);
        $this->texto($x + 4.5, $y + 3.2, $w - 6, 4, mb_strtoupper($rotulo, 'UTF-8'));

        $tam = 13.5;
        $this->SetFont('Helvetica', 'B', $tam);
        while ($tam > 8 && $this->GetStringWidth($this->conv($valor)) > $w - 7) {
            $tam -= 0.5;
            $this->SetFont('Helvetica', 'B', $tam);
        }
        $this->SetTextColor(...$cor);
        $this->texto($x + 4.5, $y + 9, $w - 6, 7, $valor);

        if ($nota !== '') {
            $this->SetFont('Helvetica', '', 7);
            $this->SetTextColor(...self::CINZA);
            $this->texto($x + 4.5, $y + 17.5, $w - 6, 4, $this->ajustar($nota, $w - 7));
        }
    }

    private function cabecalhoTabela(): void
    {
        $this->SetFont('Helvetica', 'B', 8);
        $this->SetFillColor(...self::TINTA);
        $this->SetTextColor(255, 255, 255);
        $y = $this->GetY();
        $cols = [['DATA', 22, 'L'], ['TIPO', 20, 'L'], ['DESCRIÇÃO', 74, 'L'], ['FORMA PGTO.', 34, 'L'], ['VALOR', 32, 'R']];
        $this->SetXY(self::MARGEM, $y);
        foreach ($cols as [$t, $w, $a]) {
            $this->Cell($w, 7.5, $this->conv(($a === 'L' ? ' ' : '') . $t . ($a === 'R' ? ' ' : '')), 0, 0, $a, true);
        }
        $this->SetY($y + 7.5);
    }

    // --------------------------------------------------------------- corpo

    public function montarConteudo(array $dados, array $periodo): void
    {
        $this->AddPage();
        $m = self::MARGEM;

        // ---------- Dados do relatório
        $y = $this->GetY();
        $this->SetFillColor(...self::ZEBRA);
        $this->SetDrawColor(...self::LINHA);
        $this->SetLineWidth(0.2);
        $this->Rect($m, $y, self::LARGURA, 19, 'DF');
        $colW = self::LARGURA / 3;
        $campos = [
            ['PERÍODO DE REFERÊNCIA', $periodo['texto'], $periodo['dias']],
            ['PROFISSIONAL', $this->profissional['nome'], $this->profissional['tipo']],
            ['EMISSÃO', $this->geradoEm, ucfirst($this->origemTexto)],
        ];
        foreach ($campos as $i => [$rot, $val, $sub]) {
            $x = $m + $colW * $i + 4;
            $this->SetFont('Helvetica', 'B', 7);
            $this->SetTextColor(...self::CINZA);
            $this->texto($x, $y + 2.6, $colW - 6, 4, $rot);
            $this->SetFont('Helvetica', 'B', 9.5);
            $this->SetTextColor(...self::TINTA);
            $this->texto($x, $y + 7.2, $colW - 6, 5, $this->ajustar($val, $colW - 7));
            $this->SetFont('Helvetica', '', 8);
            $this->SetTextColor(...self::CINZA);
            $this->texto($x, $y + 12.6, $colW - 6, 4, $sub);
            if ($i > 0) {
                $this->SetDrawColor(...self::LINHA);
                $this->Line($m + $colW * $i, $y + 3, $m + $colW * $i, $y + 16);
            }
        }
        $this->SetY($y + 19 + 7);

        // ---------- Resumo
        $this->secao('Resumo do período', 62);
        $y = $this->GetY();
        $w = (self::LARGURA - 3 * 4) / 4;
        $saldoPos = $dados['saldo'] >= 0;
        $this->indicador($m, $y, $w, 'Receitas', $this->moeda($dados['totalEntradas']), self::VERDE, self::VERDE_FUNDO, 'valores recebidos');
        $this->indicador($m + ($w + 4), $y, $w, 'Despesas', $this->moeda($dados['totalSaidas']), self::VERMELHO, self::VERMELHO_FDO, 'valores pagos');
        $this->indicador($m + ($w + 4) * 2, $y, $w, 'Resultado', $this->moeda($dados['saldo']), $saldoPos ? self::VERDE : self::VERMELHO, $saldoPos ? self::VERDE_FUNDO : self::VERMELHO_FDO, 'receitas - despesas');
        $this->indicador($m + ($w + 4) * 3, $y, $w, 'Movimentações', (string) $dados['qtd'], self::TINTA, self::CINZA_CLARO, 'lançamentos no período');
        $this->SetY($y + 24 + 4);

        $this->SetFont('Helvetica', 'I', 7.5);
        $this->SetTextColor(...self::CINZA);
        $this->texto($m, $this->GetY(), self::LARGURA, 4, 'Receitas e despesas consideram apenas valores efetivamente recebidos ou pagos no período; fiados em aberto ficam fora do resultado.');
        $this->SetY($this->GetY() + 7);

        // ---------- Fiados pendentes
        if ($dados['fiadosQtd'] > 0) {
            $yAviso = $this->GetY();
            $this->SetFillColor(...self::AMBAR_FUNDO);
            $this->SetDrawColor(...self::LINHA);
            $this->Rect($m, $yAviso, self::LARGURA, 11, 'DF');
            $this->SetFillColor(...self::AMBAR);
            $this->Rect($m, $yAviso, 1.4, 11, 'F');
            $this->SetFont('Helvetica', 'B', 8.8);
            $this->SetTextColor(...self::AMBAR);
            $this->texto($m + 5, $yAviso + 3, self::LARGURA - 8, 5, sprintf(
                'Valores pendentes (fiados em aberto): %d lançamento(s), %s — fora do resultado acima.',
                $dados['fiadosQtd'],
                $this->moeda($dados['fiadosTotal'])
            ));
            $this->SetY($yAviso + 11 + 6);
        }

        $this->blocoComissoes($dados['comissoes'] ?? null);
        $this->blocoFormas($dados);
        $this->blocoMovimentacoes($dados);
    }

    private function blocoComissoes(?array $c): void
    {
        if ($c === null || (($c['totais']['qtd_servicos'] ?? 0) === 0 && ($c['totais']['cancelados'] ?? 0) === 0)) {
            return;
        }
        $t = $c['totais'];
        $m = self::MARGEM;
        $ehFunc = $c['escopo'] === 'funcionario';
        $this->secao($ehFunc ? 'Minhas comissões' : 'Comissões dos funcionários', 50);

        $y = $this->GetY();
        $w = (self::LARGURA - 3 * 4) / 4;
        $this->indicador($m, $y, $w, 'Atendimentos', (string) $t['qtd_servicos'], self::TINTA, self::CINZA_CLARO, $this->moeda($t['total_servicos']) . ' em serviços');
        $this->indicador($m + ($w + 4), $y, $w, 'Comissão total', $this->moeda($t['total_comissao']), self::TINTA, self::CINZA_CLARO);
        $this->indicador($m + ($w + 4) * 2, $y, $w, 'Comissão paga', $this->moeda($t['total_pago']), self::VERDE, self::VERDE_FUNDO);
        $this->indicador($m + ($w + 4) * 3, $y, $w, 'Comissão pendente', $this->moeda($t['total_pendente']), self::AMBAR, self::AMBAR_FUNDO);
        $this->SetY($y + 24 + 3);

        $this->SetFont('Helvetica', 'I', 7.5);
        $this->SetTextColor(...self::CINZA);
        $this->texto($m, $this->GetY(), self::LARGURA, 4, 'Comissões dos atendimentos realizados no período (pela data do atendimento), independentemente de quando foram pagas.');
        $this->SetY($this->GetY() + 7);

        if (!$ehFunc && !empty($c['porFuncionario'])) {
            $cols = [['FUNCIONÁRIO', 62, 'L'], ['ATEND.', 18, 'R'], ['SERVIÇOS', 32, 'R'], ['COMISSÃO', 30, 'R'], ['PAGA', 20, 'R'], ['PENDENTE', 20, 'R']];
            $this->tabelaSimples($cols, array_map(fn($f) => [
                $f['nome'], (string) $f['qtd_servicos'], $this->moeda($f['total_servicos']),
                $this->moeda($f['total_comissao']), $this->moeda($f['total_pago']), $this->moeda($f['total_pendente']),
            ], $c['porFuncionario']));
            $this->SetY($this->GetY() + 6);
        }
    }

    private function blocoFormas(array $dados): void
    {
        $formas = array_filter($dados['porForma'], fn($v) => $v > 0.0);
        if (empty($formas)) {
            return;
        }
        arsort($formas);
        $this->secao('Receitas por forma de pagamento', 20 + count($formas) * 8);
        $m = self::MARGEM;
        $total = $dados['totalEntradas'];
        foreach ($formas as $chave => $valor) {
            $y = $this->GetY();
            $pct = $total > 0 ? ($valor / $total) * 100 : 0;
            $this->SetFont('Helvetica', '', 9);
            $this->SetTextColor(...self::TINTA);
            $this->texto($m, $y, 40, 6, FORMAS_PAGAMENTO_LABELS[$chave] ?? (string) $chave);
            $this->SetFillColor(...self::CINZA_CLARO);
            $this->Rect($m + 42, $y + 1.6, 80, 3, 'F');
            $this->SetFillColor(...$this->acentoFundo);
            $this->Rect($m + 42, $y + 1.6, max(0.6, 80 * min(1, $pct / 100)), 3, 'F');
            $this->SetFont('Helvetica', 'B', 9);
            $this->texto($m + 126, $y, 32, 6, $this->moeda($valor), 'R');
            $this->SetFont('Helvetica', '', 8.5);
            $this->SetTextColor(...self::CINZA);
            $this->texto($m + 158, $y, 24, 6, number_format($pct, 1, ',', '.') . '%', 'R');
            $this->SetY($y + 7.5);
        }
        $this->SetY($this->GetY() + 5);
    }

    /** Tabela compacta com cabeçalho repetido por página e linhas de uma linha só. */
    private function tabelaSimples(array $cols, array $linhas): void
    {
        $m = self::MARGEM;
        $cabecalho = function () use ($cols, $m): void {
            $y = $this->GetY();
            $this->SetFillColor(...self::TINTA);
            $this->SetTextColor(255, 255, 255);
            $this->SetFont('Helvetica', 'B', 7.5);
            $this->SetXY($m, $y);
            foreach ($cols as [$t, $w, $a]) {
                $this->Cell($w, 7, $this->conv(($a === 'L' ? ' ' : '') . $t . ($a === 'R' ? ' ' : '')), 0, 0, $a, true);
            }
            $this->SetY($y + 7);
        };
        if ($this->GetY() + 22 > $this->PageBreakTrigger) {
            $this->AddPage();
        }
        $cabecalho();
        $i = 0;
        foreach ($linhas as $l) {
            if ($this->GetY() + 6.5 > $this->PageBreakTrigger) {
                $this->AddPage();
                $cabecalho();
            }
            $y = $this->GetY();
            $this->SetFillColor(...($i % 2 === 0 ? [255, 255, 255] : self::ZEBRA));
            $this->Rect($m, $y, self::LARGURA, 6.5, 'F');
            $this->SetFont('Helvetica', '', 8.5);
            $this->SetTextColor(...self::TINTA);
            $x = $m;
            foreach ($cols as $k => [$t, $w, $a]) {
                $this->texto($x + ($a === 'L' ? 1 : -1), $y + 0.8, $w, 5, $this->ajustar((string) $l[$k], $w - 3), $a);
                $x += $w;
            }
            $this->SetY($y + 6.5);
            $i++;
        }
        $this->SetDrawColor(...self::LINHA);
        $this->Line($m, $this->GetY(), $m + self::LARGURA, $this->GetY());
    }

    private function blocoMovimentacoes(array $dados): void
    {
        $m = self::MARGEM;
        $this->secao('Movimentações do período', 40);

        if (empty($dados['lancamentos'])) {
            $this->SetFont('Helvetica', '', 9.5);
            $this->SetTextColor(...self::CINZA);
            $this->texto($m, $this->GetY(), self::LARGURA, 8, 'Nenhuma movimentação (recebimento ou pagamento) registrada neste período.');
            $this->SetY($this->GetY() + 10);
            return;
        }

        $this->cabecalhoTabela();
        $rotuloTipo = ['entrada' => 'Receita', 'saida' => 'Despesa'];
        $i = 0;
        $colDesc = 74.0;

        foreach ($dados['lancamentos'] as $l) {
            $this->SetFont('Helvetica', '', 8.5);
            $descricao = $this->quebrar((string) $l['titulo'], $colDesc - 3);
            $formas = array_filter(explode(',', (string) $l['forma_pagamento']));
            $formaTxt = $formas ? implode(', ', array_map(fn($f) => FORMAS_PAGAMENTO_LABELS[$f] ?? $f, $formas)) : '-';
            $formaL = $this->quebrar($formaTxt, 34 - 3);
            $n = max(count($descricao), count($formaL));
            $h = max(7.0, $n * 4.1 + 3.0);

            if ($this->GetY() + $h > $this->PageBreakTrigger) {
                $this->AddPage();
                $this->cabecalhoTabela();
            }

            $y = $this->GetY();
            $this->SetFillColor(...($i % 2 === 0 ? [255, 255, 255] : self::ZEBRA));
            $this->Rect($m, $y, self::LARGURA, $h, 'F');

            $this->SetFont('Helvetica', '', 8.5);
            $this->SetTextColor(...self::TINTA);
            $this->texto($m + 1, $y + 1.6, 21, 4.1, $this->dataBr($l['data']));

            $entrada = $l['tipo'] === 'entrada';
            $this->SetTextColor(...($entrada ? self::VERDE : self::VERMELHO));
            $this->SetFont('Helvetica', 'B', 8);
            $this->texto($m + 23, $y + 1.6, 19, 4.1, $rotuloTipo[$l['tipo']] ?? (string) $l['tipo']);

            $this->SetFont('Helvetica', '', 8.5);
            $this->SetTextColor(...self::TINTA);
            foreach ($descricao as $k => $linha) {
                $this->texto($m + 43, $y + 1.6 + $k * 4.1, $colDesc - 2, 4.1, $linha);
            }
            $this->SetTextColor(...self::CINZA);
            foreach ($formaL as $k => $linha) {
                $this->texto($m + 117, $y + 1.6 + $k * 4.1, 33, 4.1, $linha);
            }

            $this->SetFont('Helvetica', 'B', 8.5);
            $this->SetTextColor(...($entrada ? self::VERDE : self::VERMELHO));
            $this->texto($m + 150, $y + 1.6, 31, 4.1, ($entrada ? '+ ' : '- ') . $this->moeda((float) $l['valor']), 'R');

            $this->SetY($y + $h);
            $i++;
        }

        $this->SetDrawColor(...self::TINTA);
        $this->SetLineWidth(0.3);
        $this->Line($m, $this->GetY(), $m + self::LARGURA, $this->GetY());
        $this->SetLineWidth(0.2);
        $this->SetY($this->GetY() + 4);

        // ---------- Totais (mantidos juntos na mesma página)
        if ($this->GetY() + 30 > $this->PageBreakTrigger) {
            $this->AddPage();
        }
        $saldoPos = $dados['saldo'] >= 0;
        $linhasTotais = [
            ['Total de receitas', $this->moeda($dados['totalEntradas']), self::VERDE, false],
            ['Total de despesas', $this->moeda($dados['totalSaidas']), self::VERMELHO, false],
            ['Resultado do período', $this->moeda($dados['saldo']), $saldoPos ? self::VERDE : self::VERMELHO, true],
        ];
        $x0 = $m + 90;
        foreach ($linhasTotais as [$rot, $val, $cor, $destaque]) {
            $y = $this->GetY();
            if ($destaque) {
                $this->SetFillColor(...($saldoPos ? self::VERDE_FUNDO : self::VERMELHO_FDO));
                $this->Rect($x0, $y, 92, 9, 'F');
                $this->SetFont('Helvetica', 'B', 10.5);
            } else {
                $this->SetFont('Helvetica', '', 9.5);
            }
            $this->SetTextColor(...self::TINTA);
            $this->texto($x0 + 3, $y + ($destaque ? 1.8 : 0.8), 50, 6, $rot);
            $this->SetFont('Helvetica', 'B', $destaque ? 11 : 9.5);
            $this->SetTextColor(...$cor);
            $this->texto($x0 + 44, $y + ($destaque ? 1.8 : 0.8), 45, 6, $val, 'R');
            $this->SetY($y + ($destaque ? 9 : 6.5));
        }
    }
}

/**
 * Monta o PDF completo e devolve os bytes (para salvar no banco ou enviar ao
 * navegador).
 *
 * @param array $identidade   RelatorioIdentidade::carregar()
 * @param array $profissional ['nome' => ..., 'tipo' => 'Proprietário'|'Funcionário']
 * @param array $meta         ['origem' => 'manual'|'automatico', 'geradoEm' => DateTimeImmutable, 'cor' => TemaService::tokens(...)]
 */
function construirRelatorioPdf(
    array $identidade,
    array $profissional,
    string $tipo,
    string $dataInicio,
    string $dataFim,
    string $titulo,
    array $dados,
    array $meta = []
): string {
    $ini = new DateTimeImmutable($dataInicio);
    $fim = new DateTimeImmutable($dataFim);
    $di = $ini->format('d/m/Y');
    $df = $fim->format('d/m/Y');
    $dias = (int) $ini->diff($fim)->days + 1;

    $rotulo = ['diario' => 'diário', 'semanal' => 'semanal', 'mensal' => 'mensal', 'anual' => 'anual', 'periodo' => 'por período'][$tipo] ?? $tipo;
    $periodoCurto = $dataInicio === $dataFim ? $di : "{$di} a {$df}";

    $pdf = new RelatorioPdfBuilder($identidade, $profissional, $rotulo, $periodoCurto, $meta);
    $pdf->montarConteudo($dados, [
        'texto' => $periodoCurto,
        'dias'  => $dias === 1 ? '1 dia' : "{$dias} dias",
    ]);

    return $pdf->Output('S');
}

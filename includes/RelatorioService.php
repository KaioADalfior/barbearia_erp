<?php
/**
 * includes/RelatorioService.php
 *
 * Regras de negócio do módulo Financeiro > Relatórios: calcula o intervalo
 * de datas de cada tipo de relatório (diário/semanal/mensal/anual), agrega
 * os lançamentos do período (mesma base de FinanceiroLancamentos usada pelo
 * Dashboard) e persiste/consulta os relatórios já gerados (tabela
 * FinanceiroRelatorios — o PDF fica salvo como BLOB no próprio banco).
 */

require_once __DIR__ . '/forma_pagamento.php'; // FORMAS_PAGAMENTO_LABELS

class RelatorioService
{
    public const TIPOS_LABELS = [
        'diario'  => 'Diário',
        'semanal' => 'Semanal',
        'mensal'  => 'Mensal',
        'anual'   => 'Anual',
    ];

    /**
     * Calcula [dataInicio, dataFim] (Y-m-d) para o tipo de relatório
     * escolhido, a partir de uma data de referência informada pelo
     * barbeiro no filtro:
     *   - diario:  o próprio dia da data de referência;
     *   - semanal: semana (segunda a domingo) que contém a data;
     *   - mensal:  mês inteiro (dia 1 ao último dia) que contém a data;
     *   - anual:   ano inteiro (01/01 a 31/12) que contém a data.
     */
    public static function calcularIntervalo(string $tipo, string $dataRef): array
    {
        $ref = new DateTimeImmutable($dataRef);

        switch ($tipo) {
            case 'semanal':
                $diaSemanaIso = (int) $ref->format('N'); // 1 (segunda) .. 7 (domingo)
                $inicio = $ref->modify('-' . ($diaSemanaIso - 1) . ' days');
                $fim    = $inicio->modify('+6 days');
                break;

            case 'mensal':
                $inicio = $ref->modify('first day of this month');
                $fim    = $ref->modify('last day of this month');
                break;

            case 'anual':
                $inicio = new DateTimeImmutable($ref->format('Y') . '-01-01');
                $fim    = new DateTimeImmutable($ref->format('Y') . '-12-31');
                break;

            case 'diario':
            default:
                $inicio = $ref;
                $fim    = $ref;
                break;
        }

        return [$inicio->format('Y-m-d'), $fim->format('Y-m-d')];
    }

    /**
     * Monta o título já formatado do relatório (usado na listagem e no
     * cabeçalho do PDF), ex: "Relatório Diário — 07/08/2026" ou
     * "Relatório Mensal — 01/08/2026 a 31/08/2026".
     */
    public static function tituloPeriodo(string $tipo, string $dataInicio, string $dataFim): string
    {
        $label = self::TIPOS_LABELS[$tipo] ?? ucfirst($tipo);
        $di    = (new DateTimeImmutable($dataInicio))->format('d/m/Y');
        $df    = (new DateTimeImmutable($dataFim))->format('d/m/Y');

        if ($dataInicio === $dataFim) {
            return "Relatório {$label} — {$di}";
        }

        return "Relatório {$label} — {$di} a {$df}";
    }

    /**
     * Coleta e agrega os dados financeiros do período (mesma base do
     * Dashboard: FinanceiroRecebimentos, um recebimento por linha — cada
     * um contando na data em que foi efetivamente recebido, inclusive
     * parcelas de fiado). Retorna também um resumo dos fiados (pendentes)
     * em aberto no período, só para contexto informativo no relatório.
     */
    public static function coletarDados(PDO $pdo, int $idBarbeiro, string $dataInicio, string $dataFim): array
    {
        $stmt = $pdo->prepare(
            "SELECT r.idRecebimento, r.idLancamento, r.tipo, l.titulo, l.descricao, l.quantidade,
                    r.valor, r.forma_pagamento, l.origem, r.data
             FROM FinanceiroRecebimentos r
             INNER JOIN FinanceiroLancamentos l ON l.idLancamento = r.idLancamento
             WHERE r.id_barbeiro = :b AND r.data BETWEEN :ini AND :fim
             ORDER BY r.data ASC, r.idRecebimento ASC"
        );
        $stmt->execute(['b' => $idBarbeiro, 'ini' => $dataInicio, 'fim' => $dataFim]);
        $lancamentos = $stmt->fetchAll();

        $totalEntradas = 0.0;
        $totalSaidas   = 0.0;
        $porForma      = array_fill_keys(array_keys(FORMAS_PAGAMENTO_LABELS), 0.0);

        foreach ($lancamentos as $l) {
            if ($l['tipo'] === 'entrada') {
                $totalEntradas += (float) $l['valor'];

                $formasDoLancamento = array_filter(explode(',', (string) $l['forma_pagamento']));
                $qtdFormas = count($formasDoLancamento) ?: 1;
                foreach ($formasDoLancamento ?: [null] as $f) {
                    if (isset($porForma[$f])) {
                        $porForma[$f] += ((float) $l['valor']) / $qtdFormas;
                    }
                }
            } else {
                $totalSaidas += (float) $l['valor'];
            }
        }

        $stmtFiado = $pdo->prepare(
            "SELECT COUNT(*) AS qtd, COALESCE(SUM(valor - valor_pago), 0) AS total
             FROM FinanceiroLancamentos
             WHERE id_barbeiro = :b AND status = 'pendente' AND data BETWEEN :ini AND :fim"
        );
        $stmtFiado->execute(['b' => $idBarbeiro, 'ini' => $dataInicio, 'fim' => $dataFim]);
        $fiado = $stmtFiado->fetch();

        return [
            'lancamentos'   => $lancamentos,
            'totalEntradas' => $totalEntradas,
            'totalSaidas'   => $totalSaidas,
            'saldo'         => $totalEntradas - $totalSaidas,
            'qtd'           => count($lancamentos),
            'porForma'      => $porForma,
            'fiadosQtd'     => (int) $fiado['qtd'],
            'fiadosTotal'   => (float) $fiado['total'],
        ];
    }

    /**
     * Salva o relatório gerado (metadados + bytes do PDF) na tabela
     * FinanceiroRelatorios. Retorna o id do relatório criado.
     */
    public static function salvar(
        PDO $pdo,
        int $idBarbeiro,
        string $tipo,
        string $dataInicio,
        string $dataFim,
        string $titulo,
        array $dados,
        string $pdfBytes,
        string $nomeArquivo
    ): int {
        $stmt = $pdo->prepare(
            'INSERT INTO FinanceiroRelatorios
                (id_barbeiro, tipo, data_inicio, data_fim, titulo, total_entradas, total_saidas, saldo, qtd_lancamentos, nome_arquivo, tamanho_bytes, arquivo_pdf)
             VALUES
                (:id_barbeiro, :tipo, :data_inicio, :data_fim, :titulo, :total_entradas, :total_saidas, :saldo, :qtd, :nome_arquivo, :tamanho, :arquivo_pdf)'
        );

        $stmt->bindValue(':id_barbeiro', $idBarbeiro, PDO::PARAM_INT);
        $stmt->bindValue(':tipo', $tipo);
        $stmt->bindValue(':data_inicio', $dataInicio);
        $stmt->bindValue(':data_fim', $dataFim);
        $stmt->bindValue(':titulo', $titulo);
        $stmt->bindValue(':total_entradas', $dados['totalEntradas']);
        $stmt->bindValue(':total_saidas', $dados['totalSaidas']);
        $stmt->bindValue(':saldo', $dados['saldo']);
        $stmt->bindValue(':qtd', $dados['qtd'], PDO::PARAM_INT);
        $stmt->bindValue(':nome_arquivo', $nomeArquivo);
        $stmt->bindValue(':tamanho', strlen($pdfBytes), PDO::PARAM_INT);
        $stmt->bindValue(':arquivo_pdf', $pdfBytes, PDO::PARAM_LOB);
        $stmt->execute();

        return (int) $pdo->lastInsertId();
    }

    /**
     * Lista os relatórios já gerados pelo barbeiro (sem o BLOB do PDF, só
     * os metadados — usado para montar a tabela da tela de Relatórios).
     */
    public static function listar(PDO $pdo, int $idBarbeiro): array
    {
        $stmt = $pdo->prepare(
            'SELECT idRelatorio, tipo, data_inicio, data_fim, titulo,
                    total_entradas, total_saidas, saldo, qtd_lancamentos,
                    nome_arquivo, tamanho_bytes, gerado_em
             FROM FinanceiroRelatorios
             WHERE id_barbeiro = :b
             ORDER BY gerado_em DESC, idRelatorio DESC'
        );
        $stmt->execute(['b' => $idBarbeiro]);

        return $stmt->fetchAll();
    }

    /**
     * Busca o arquivo PDF (bytes + nome) de um relatório específico, já
     * restrito ao barbeiro dono da sessão. Usado pelo endpoint de download.
     */
    public static function buscarArquivo(PDO $pdo, int $idBarbeiro, int $idRelatorio): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT nome_arquivo, arquivo_pdf
             FROM FinanceiroRelatorios
             WHERE idRelatorio = :id AND id_barbeiro = :b'
        );
        $stmt->execute(['id' => $idRelatorio, 'b' => $idBarbeiro]);
        $linha = $stmt->fetch();

        return $linha ?: null;
    }

    /**
     * Exclui um relatório gerado (permite ao barbeiro limpar a listagem).
     */
    public static function excluir(PDO $pdo, int $idBarbeiro, int $idRelatorio): bool
    {
        $stmt = $pdo->prepare(
            'DELETE FROM FinanceiroRelatorios WHERE idRelatorio = :id AND id_barbeiro = :b'
        );
        $stmt->execute(['id' => $idRelatorio, 'b' => $idBarbeiro]);

        return $stmt->rowCount() > 0;
    }
}

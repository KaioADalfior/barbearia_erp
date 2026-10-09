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
require_once __DIR__ . '/FinanceiroService.php';
require_once __DIR__ . '/AcessoService.php';

class RelatorioService
{
    /** Origem do relatório: pedido por um usuário ou gerado pela rotina diária (00h). */
    public const ORIGEM_MANUAL     = 'manual';
    public const ORIGEM_AUTOMATICO = 'automatico';

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
        // Funcionário: o financeiro dele conta só a comissão (ver
        // FinanceiroService::fonteRecebimentos); proprietário: o dele + a sobra
        // dos atendimentos dos funcionários depois da comissão.
        $fonte  = FinanceiroService::fonteRecebimentos($pdo, $idBarbeiro);
        $titulo = FinanceiroService::exprTitulo($pdo, $idBarbeiro);
        $stmt = $pdo->prepare(
            "SELECT r.idRecebimento, r.idLancamento, r.tipo, {$titulo} AS titulo, l.descricao, l.quantidade,
                    r.valor, r.forma_pagamento, l.origem, r.data, l.id_barbeiro AS lanc_barbeiro
             FROM {$fonte} r
             INNER JOIN FinanceiroLancamentos l ON l.idLancamento = r.idLancamento
             WHERE r.id_barbeiro = :b AND r.data BETWEEN :ini AND :fim
             ORDER BY r.data ASC, r.idRecebimento ASC"
        );
        $stmt->execute(['b' => $idBarbeiro, 'ini' => $dataInicio, 'fim' => $dataFim]);
        $lancamentos = FinanceiroService::anotarComissao($pdo, $idBarbeiro, $stmt->fetchAll());

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
        string $nomeArquivo,
        string $origem = self::ORIGEM_MANUAL,
        ?string $chaveAuto = null
    ): int {
        self::garantirEstrutura($pdo);
        $stmt = $pdo->prepare(
            'INSERT INTO FinanceiroRelatorios
                (id_barbeiro, tipo, data_inicio, data_fim, titulo, total_entradas, total_saidas, saldo, qtd_lancamentos, nome_arquivo, tamanho_bytes, arquivo_pdf, origem, chave_auto)
             VALUES
                (:id_barbeiro, :tipo, :data_inicio, :data_fim, :titulo, :total_entradas, :total_saidas, :saldo, :qtd, :nome_arquivo, :tamanho, :arquivo_pdf, :origem, :chave_auto)'
        );
        $stmt->bindValue(':origem', $origem === self::ORIGEM_AUTOMATICO ? self::ORIGEM_AUTOMATICO : self::ORIGEM_MANUAL);
        $stmt->bindValue(':chave_auto', $chaveAuto, $chaveAuto === null ? PDO::PARAM_NULL : PDO::PARAM_STR);

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
    public static function listar(PDO $pdo, int $idBarbeiro, int $limite = 500): array
    {
        self::garantirEstrutura($pdo);
        $limite = max(1, min(2000, $limite));
        $stmt = $pdo->prepare(
            "SELECT idRelatorio, tipo, data_inicio, data_fim, titulo,
                    total_entradas, total_saidas, saldo, qtd_lancamentos,
                    nome_arquivo, tamanho_bytes, gerado_em, origem
             FROM FinanceiroRelatorios
             WHERE id_barbeiro = :b
             ORDER BY gerado_em DESC, idRelatorio DESC
             LIMIT {$limite}"
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
    // =====================================================================
    // Estrutura do banco para os relatórios automáticos (idempotente,
    // somente aditiva — espelha scriptBD/atualizacao_relatorios_automaticos.sql)
    // =====================================================================

    private static bool $estruturaOk = false;

    /**
     * Garante as colunas `origem` e `chave_auto` (com índice único) em
     * FinanceiroRelatorios e a tabela de controle FinanceiroRelatoriosAuto.
     * Nunca apaga nem altera dados existentes: relatórios antigos ficam como
     * origem 'manual' e chave NULL. Falhas de permissão de DDL são
     * registradas e NÃO derrubam a tela (a listagem antiga continua válida).
     */
    public static function garantirEstrutura(PDO $pdo): void
    {
        if (self::$estruturaOk) {
            return;
        }
        try {
            $tem = static function (string $col) use ($pdo): bool {
                $st = $pdo->prepare(
                    'SELECT COUNT(*) FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c'
                );
                $st->execute(['t' => 'FinanceiroRelatorios', 'c' => $col]);
                return (int) $st->fetchColumn() > 0;
            };

            if (!$tem('origem')) {
                $pdo->exec("ALTER TABLE FinanceiroRelatorios ADD COLUMN origem ENUM('manual','automatico') NOT NULL DEFAULT 'manual'");
            }
            if (!$tem('chave_auto')) {
                $pdo->exec('ALTER TABLE FinanceiroRelatorios ADD COLUMN chave_auto VARCHAR(80) NULL');
            }
            $idx = $pdo->prepare(
                "SELECT COUNT(*) FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'FinanceiroRelatorios' AND INDEX_NAME = 'uk_relatorio_auto'"
            );
            $idx->execute();
            if ((int) $idx->fetchColumn() === 0) {
                $pdo->exec('ALTER TABLE FinanceiroRelatorios ADD UNIQUE INDEX uk_relatorio_auto (chave_auto)');
            }

            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS FinanceiroRelatoriosAuto (
                    chave             VARCHAR(80) NOT NULL PRIMARY KEY,
                    id_barbeiro       INT NOT NULL,
                    tipo              VARCHAR(10) NOT NULL,
                    data_inicio       DATE NOT NULL,
                    data_fim          DATE NOT NULL,
                    status            ENUM('ok','erro') NOT NULL DEFAULT 'erro',
                    tentativas        INT NOT NULL DEFAULT 0,
                    ultima_tentativa  DATETIME NULL,
                    mensagem          VARCHAR(255) NULL,
                    idRelatorio       INT NULL,
                    atualizado_em     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_rel_auto_barbeiro (id_barbeiro, status, data_fim)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
            self::$estruturaOk = true;
        } catch (Throwable $e) {
            error_log('RelatorioService::garantirEstrutura: ' . $e->getMessage());
        }
    }

    /** Chave única de um relatório automático: barbeiro + tipo + período. */
    public static function chaveAuto(int $idBarbeiro, string $tipo, string $dataInicio, string $dataFim): string
    {
        return "b{$idBarbeiro}|{$tipo}|{$dataInicio}|{$dataFim}";
    }

    // =====================================================================
    // Emissão (usada pela tela e pela rotina diária)
    // =====================================================================

    /**
     * Comissões dos atendimentos do período, SEM recalcular nada: usa as
     * mesmas consultas da tela Financeiro > Comissões (ComissaoService), que
     * filtram pela data do atendimento. Proprietário vê o total da barbearia
     * e o detalhe por funcionário; funcionário vê só as dele. Se o módulo de
     * comissões não estiver disponível, devolve null e o relatório omite o bloco.
     */
    public static function coletarComissoes(PDO $pdo, int $idBarbeiro, string $dataInicio, string $dataFim): ?array
    {
        try {
            require_once __DIR__ . '/ComissaoService.php';
            $ehFuncionario = AcessoService::tipoDoBarbeiro($pdo, $idBarbeiro) === AcessoService::FUNCIONARIO;
            $filtros = ['de' => $dataInicio, 'ate' => $dataFim, 'funcionario' => $ehFuncionario ? $idBarbeiro : null, 'status' => null, 'tipo' => 'todos'];
            $totais = ComissaoService::totais($pdo, $filtros);

            return [
                'escopo'         => $ehFuncionario ? 'funcionario' : 'proprietario',
                'totais'         => $totais,
                'porFuncionario' => $ehFuncionario ? [] : ComissaoService::porFuncionario($pdo, $filtros),
            ];
        } catch (Throwable $e) {
            error_log('RelatorioService::coletarComissoes: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Monta e grava um relatório (dados + PDF). Mesmo caminho para o pedido
     * manual e para a rotina diária. Para origem automática, a chave única
     * impede duplicidade: se já existir, devolve o existente (duplicado=true).
     *
     * @return array{id:int, duplicado:bool, titulo:string}
     * @throws Throwable qualquer falha de dados/PDF/gravação (quem chama decide o que fazer)
     */
    public static function emitir(
        PDO $pdo,
        int $idBarbeiro,
        string $tipo,
        string $dataInicio,
        string $dataFim,
        string $origem = self::ORIGEM_MANUAL,
        ?DateTimeImmutable $geradoEm = null
    ): array {
        require_once __DIR__ . '/RelatorioPdfBuilder.php';
        require_once __DIR__ . '/RelatorioIdentidade.php';
        require_once __DIR__ . '/TemaService.php';
        self::garantirEstrutura($pdo);

        $automatico = $origem === self::ORIGEM_AUTOMATICO;
        $chave = $automatico ? self::chaveAuto($idBarbeiro, $tipo, $dataInicio, $dataFim) : null;

        if ($chave !== null) {
            $ja = $pdo->prepare('SELECT idRelatorio FROM FinanceiroRelatorios WHERE chave_auto = :c');
            $ja->execute(['c' => $chave]);
            $existente = $ja->fetchColumn();
            if ($existente !== false) {
                return ['id' => (int) $existente, 'duplicado' => true, 'titulo' => self::tituloPeriodo($tipo, $dataInicio, $dataFim)];
            }
        }

        $stmt = $pdo->prepare('SELECT nome, tipo_usuario FROM Barbeiro WHERE id_barbeiro = :b');
        $stmt->execute(['b' => $idBarbeiro]);
        $prof = $stmt->fetch();
        if (!$prof) {
            throw new RuntimeException('Profissional inexistente.');
        }

        $titulo = self::tituloPeriodo($tipo, $dataInicio, $dataFim);
        $dados  = self::coletarDados($pdo, $idBarbeiro, $dataInicio, $dataFim);
        $dados['comissoes'] = self::coletarComissoes($pdo, $idBarbeiro, $dataInicio, $dataFim);

        $identidade = RelatorioIdentidade::carregar($pdo);
        try {
            $pdfBytes = construirRelatorioPdf(
                $identidade,
                [
                    'nome' => (string) $prof['nome'],
                    'tipo' => (string) ($prof['tipo_usuario'] ?? 'proprietario') === 'funcionario' ? 'Funcionário' : 'Proprietário',
                ],
                $tipo,
                $dataInicio,
                $dataFim,
                $titulo,
                $dados,
                [
                    'origem'   => $origem,
                    'geradoEm' => $geradoEm ?? new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')),
                    'cor'      => TemaService::tokens(TemaService::corAtual($pdo), false),
                ]
            );
        } finally {
            RelatorioIdentidade::liberar($identidade);
        }

        $nomeArquivo = 'relatorio-' . $tipo . '-' . $dataInicio
            . ($dataInicio !== $dataFim ? '_a_' . $dataFim : '')
            . '.pdf';

        try {
            $id = self::salvar($pdo, $idBarbeiro, $tipo, $dataInicio, $dataFim, $titulo, $dados, $pdfBytes, $nomeArquivo, $origem, $chave);
        } catch (PDOException $e) {
            // Corrida entre duas execuções: a chave única barrou a segunda.
            if ($chave !== null && ($e->errorInfo[1] ?? 0) === 1062) {
                $ja = $pdo->prepare('SELECT idRelatorio FROM FinanceiroRelatorios WHERE chave_auto = :c');
                $ja->execute(['c' => $chave]);
                $existente = $ja->fetchColumn();
                if ($existente !== false) {
                    return ['id' => (int) $existente, 'duplicado' => true, 'titulo' => $titulo];
                }
            }
            throw $e;
        }

        return ['id' => $id, 'duplicado' => false, 'titulo' => $titulo];
    }

    /**
     * Falhas da geração automática ainda não resolvidas, só do próprio
     * barbeiro. A mensagem já vem sanitizada (sem dados) de quem gravou.
     */
    public static function falhasAutomaticas(PDO $pdo, int $idBarbeiro): array
    {
        self::garantirEstrutura($pdo);
        try {
            $stmt = $pdo->prepare(
                "SELECT chave, tipo, data_inicio, data_fim, tentativas, ultima_tentativa, mensagem
                 FROM FinanceiroRelatoriosAuto
                 WHERE id_barbeiro = :b AND status = 'erro'
                 ORDER BY data_fim DESC, chave ASC
                 LIMIT 50"
            );
            $stmt->execute(['b' => $idBarbeiro]);
            return $stmt->fetchAll();
        } catch (Throwable $e) {
            return [];
        }
    }
}

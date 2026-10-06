<?php
/**
 * includes/ComissaoService.php
 *
 * Regras de COMISSÃO dos funcionários (barbeiros com tipo de acesso
 * 'funcionario', ver AcessoService). Tudo que envolve comissão passa por
 * aqui — as telas e os endpoints só chamam estes métodos.
 *
 * Modelo (tabela Comissoes):
 *   - UMA comissão por atendimento: UNIQUE(idAgendamento). O id do
 *     agendamento é a referência (ATENDIMENTO -> COMISSÃO -> FUNCIONÁRIO);
 *     processar o mesmo atendimento duas vezes nunca duplica.
 *   - Guarda uma "foto" do momento da conclusão: valor do serviço,
 *     PORCENTAGEM aplicada, valor da comissão, cliente, serviço, forma de
 *     pagamento, data e horário. Mudar a porcentagem depois (Configurações)
 *     só vale para os próximos atendimentos — comissões antigas não mudam.
 *   - status: 'pendente' (a pagar ao funcionário) | 'pago' (baixa feita
 *     pelo proprietário) | 'cancelado' (atendimento estornado/excluído).
 *     Nada é apagado: cancelar/reabrir/pagar só muda o status e anota o
 *     histórico (coluna historico).
 *   - idLancamento liga a comissão ao lançamento de receita do atendimento
 *     (FinanceiroLancamentos); a receita continua sendo UMA só linha — a
 *     comissão não cria outro lançamento para não duplicar o faturamento
 *     nos relatórios/dashboard que já existem.
 *
 * Só atendimentos de FUNCIONÁRIO geram comissão: o que o proprietário
 * atende fica todo com a barbearia.
 *
 * A estrutura (coluna Barbeiro.tipo_usuario + tabelas ConfiguracaoSistema e
 * Comissoes) é criada automaticamente, uma vez por deploy, por iniciar()
 * (chamado em config/config.php) — mesmo padrão do catálogo/uploads. O SQL
 * equivalente está em scriptBD/atualizacao_comissoes.sql.
 */

require_once __DIR__ . '/AcessoService.php';

final class ComissaoService
{
    public const PERCENTUAL_PADRAO = 50.0;
    private const CHAVE_PERCENTUAL = 'comissao_percentual';

    // ----------------------------------------------------------- estrutura

    /** Chamado uma vez por requisição por config/config.php. Nunca derruba a página. */
    public static function iniciar(PDO $pdo): void
    {
        $marcador = sys_get_temp_dir() . '/barberp_comissoes_estrutura_v1';
        if (is_file($marcador)) {
            return;
        }
        try {
            self::garantirEstrutura($pdo);
            @touch($marcador);
        } catch (Throwable $e) {
            error_log('ComissaoService::iniciar: ' . $e->getMessage());
        }
    }

    /**
     * Cria o que falta. DDL faz commit implícito no MySQL — por isso roda
     * ANTES de qualquer transação (ver iniciar()), nunca no meio de uma.
     */
    public static function garantirEstrutura(PDO $pdo): void
    {
        $existe = $pdo->query("SHOW COLUMNS FROM Barbeiro LIKE 'tipo_usuario'")->fetch();
        if (!$existe) {
            try {
                // DEFAULT 'proprietario': quem já existe continua com acesso total.
                $pdo->exec("ALTER TABLE Barbeiro ADD COLUMN tipo_usuario VARCHAR(15) NOT NULL DEFAULT 'proprietario'");
            } catch (Throwable $e) {
                // Outra requisição criou a coluna no mesmo instante: tudo bem.
                $existe = $pdo->query("SHOW COLUMNS FROM Barbeiro LIKE 'tipo_usuario'")->fetch();
                if (!$existe) {
                    throw $e;
                }
            }
        }

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS ConfiguracaoSistema (
                chave VARCHAR(60) NOT NULL PRIMARY KEY,
                valor VARCHAR(255) NOT NULL,
                atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS Comissoes (
                idComissao      INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                idAgendamento   INT NOT NULL,
                idLancamento    INT NULL,
                id_barbeiro     INT NOT NULL,
                idCliente       INT NULL,
                cliente_nome    VARCHAR(120) NULL,
                idServico       INT NULL,
                servico_nome    VARCHAR(190) NULL,
                forma_pagamento VARCHAR(100) NULL,
                data_servico    DATE NOT NULL,
                hora_servico    TIME NULL,
                valor_servico   DECIMAL(10,2) NOT NULL,
                percentual      DECIMAL(5,2) NOT NULL,
                valor_comissao  DECIMAL(10,2) NOT NULL,
                status          VARCHAR(10) NOT NULL DEFAULT 'pendente',
                pago_em         DATETIME NULL,
                cancelado_em    DATETIME NULL,
                historico       TEXT NULL,
                criado_em       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                atualizado_em   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_comissao_agendamento (idAgendamento),
                KEY idx_comissao_barbeiro_status (id_barbeiro, status),
                KEY idx_comissao_data (data_servico),
                KEY idx_comissao_lancamento (idLancamento)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }

    // ------------------------------------------------------- configuração

    /** Porcentagem de comissão vigente (0–100). Usada nos PRÓXIMOS atendimentos. */
    public static function percentualAtual(PDO $pdo): float
    {
        try {
            $stmt = $pdo->prepare('SELECT valor FROM ConfiguracaoSistema WHERE chave = :c');
            $stmt->execute(['c' => self::CHAVE_PERCENTUAL]);
            $valor = $stmt->fetchColumn();
            if ($valor !== false && is_numeric($valor)) {
                return max(0.0, min(100.0, (float) $valor));
            }
        } catch (Throwable $e) {
            // tabela ainda não existe: usa o padrão
        }
        return self::PERCENTUAL_PADRAO;
    }

    public static function salvarPercentual(PDO $pdo, float $percentual): void
    {
        $percentual = round(max(0.0, min(100.0, $percentual)), 2);
        $stmt = $pdo->prepare(
            'INSERT INTO ConfiguracaoSistema (chave, valor) VALUES (:c, :v)
             ON DUPLICATE KEY UPDATE valor = VALUES(valor)'
        );
        $stmt->execute(['c' => self::CHAVE_PERCENTUAL, 'v' => number_format($percentual, 2, '.', '')]);
    }

    // -------------------------------------------------------- geração

    /**
     * Gera a comissão de um atendimento recém-concluído. Chamado por
     * FinanceiroService::concluirAgendamentoComPagamento, DENTRO da mesma
     * transação da conclusão (se algo falhar lá, a comissão some junto).
     *
     * - Só gera para barbeiro FUNCIONÁRIO; proprietário => retorna null.
     * - Idempotente: se o atendimento já tem comissão, devolve a existente
     *   sem tocar em nada (a porcentagem e o valor antigos ficam como estão).
     *
     * @param array{idAgendamento:int, idLancamento:?int, id_barbeiro:int, idCliente:?int, cliente:?string,
     *              idServico:?int, servico:?string, valor:float|string, forma:?string} $d
     * @return int|null idComissao
     */
    public static function registrarParaAtendimento(PDO $pdo, array $d): ?int
    {
        $idBarbeiro = (int) $d['id_barbeiro'];
        if (AcessoService::tipoDoBarbeiro($pdo, $idBarbeiro) !== AcessoService::FUNCIONARIO) {
            return null;
        }

        $idAgendamento = (int) $d['idAgendamento'];
        $existente = $pdo->prepare('SELECT idComissao FROM Comissoes WHERE idAgendamento = :a');
        $existente->execute(['a' => $idAgendamento]);
        $id = $existente->fetchColumn();
        if ($id !== false) {
            return (int) $id;
        }

        // Data e horário do atendimento (o que foi agendado/atendido).
        $stmtQuando = $pdo->prepare(
            'SELECT a.Data, h.hora FROM Agendamentos a INNER JOIN Horario h ON h.idHorario = a.idHorario WHERE a.idAgendamento = :a'
        );
        $stmtQuando->execute(['a' => $idAgendamento]);
        $quando = $stmtQuando->fetch() ?: ['Data' => date('Y-m-d'), 'hora' => null];

        $valorServico = round((float) $d['valor'], 2);
        $percentual   = self::percentualAtual($pdo);
        $valorComissao = round($valorServico * $percentual / 100, 2);

        // INSERT ... ON DUPLICATE: dois processamentos simultâneos do mesmo
        // atendimento nunca criam duas linhas (UNIQUE idAgendamento).
        $stmt = $pdo->prepare(
            'INSERT INTO Comissoes
                (idAgendamento, idLancamento, id_barbeiro, idCliente, cliente_nome, idServico, servico_nome, forma_pagamento,
                 data_servico, hora_servico, valor_servico, percentual, valor_comissao, status, historico)
             VALUES
                (:ag, :lan, :b, :cli, :clin, :srv, :srvn, :forma, :data, :hora, :vs, :perc, :vc, \'pendente\', :hist)
             ON DUPLICATE KEY UPDATE idComissao = idComissao'
        );
        $stmt->execute([
            'ag'    => $idAgendamento,
            'lan'   => $d['idLancamento'] ?? null,
            'b'     => $idBarbeiro,
            'cli'   => $d['idCliente'] ?? null,
            'clin'  => $d['cliente'] ?? null,
            'srv'   => $d['idServico'] ?? null,
            'srvn'  => $d['servico'] ?? null,
            'forma' => $d['forma'] ?? null,
            'data'  => $quando['Data'],
            'hora'  => $quando['hora'],
            'vs'    => number_format($valorServico, 2, '.', ''),
            'perc'  => number_format($percentual, 2, '.', ''),
            'vc'    => number_format($valorComissao, 2, '.', ''),
            'hist'  => date('d/m/Y H:i') . ' — gerada (' . self::fmtPct($percentual) . ' de ' . self::fmtMoeda($valorServico) . ')',
        ]);

        $existente->execute(['a' => $idAgendamento]);
        $id = $existente->fetchColumn();
        return $id !== false ? (int) $id : null;
    }

    // -------------------------------------------------- estorno / baixa

    /**
     * O lançamento de receita do atendimento foi excluído/estornado: a
     * comissão ligada a ele deixa de valer. NÃO apaga — vira 'cancelado'
     * (e continua no histórico; se já tinha sido paga, pago_em permanece
     * para o proprietário saber que houve pagamento a acertar).
     */
    public static function cancelarPorLancamento(PDO $pdo, int $idLancamento, string $motivo = 'lançamento excluído'): int
    {
        $stmt = $pdo->prepare(
            "UPDATE Comissoes
             SET status = 'cancelado', cancelado_em = NOW(),
                 historico = CONCAT(COALESCE(historico, ''), CHAR(10), :h)
             WHERE idLancamento = :l AND status <> 'cancelado'"
        );
        $stmt->execute(['l' => $idLancamento, 'h' => date('d/m/Y H:i') . ' — cancelada (' . $motivo . ')']);
        return $stmt->rowCount();
    }

    public static function cancelarPorAgendamento(PDO $pdo, int $idAgendamento, string $motivo = 'atendimento cancelado'): int
    {
        $stmt = $pdo->prepare(
            "UPDATE Comissoes
             SET status = 'cancelado', cancelado_em = NOW(),
                 historico = CONCAT(COALESCE(historico, ''), CHAR(10), :h)
             WHERE idAgendamento = :a AND status <> 'cancelado'"
        );
        $stmt->execute(['a' => $idAgendamento, 'h' => date('d/m/Y H:i') . ' — cancelada (' . $motivo . ')']);
        return $stmt->rowCount();
    }

    /**
     * Baixa: marca comissões PENDENTES como pagas (nunca mexe em
     * canceladas). Devolve quantas mudaram.
     *
     * @param int[] $ids
     */
    public static function marcarPagas(PDO $pdo, array $ids, string $quem): int
    {
        return self::mudarStatus($pdo, $ids, 'pendente', 'pago', 'pago_em = NOW()', 'paga por ' . $quem);
    }

    /** Desfaz uma baixa feita por engano (pago -> pendente), anotando no histórico. */
    public static function reabrir(PDO $pdo, array $ids, string $quem): int
    {
        return self::mudarStatus($pdo, $ids, 'pago', 'pendente', 'pago_em = NULL', 'baixa desfeita por ' . $quem);
    }

    private static function mudarStatus(PDO $pdo, array $ids, string $de, string $para, string $setExtra, string $nota): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($v) => $v > 0)));
        if (empty($ids)) {
            return 0;
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare(
            "UPDATE Comissoes
             SET status = ?, {$setExtra}, historico = CONCAT(COALESCE(historico, ''), CHAR(10), ?)
             WHERE status = ? AND idComissao IN ($in)"
        );
        $stmt->execute(array_merge([$para, date('d/m/Y H:i') . ' — ' . $nota, $de], $ids));
        return $stmt->rowCount();
    }

    // ------------------------------------------------------- consultas

    /**
     * Normaliza filtros vindos da URL. Datas inválidas viram null; status
     * desconhecido vira null; funcionario <= 0 vira null.
     *
     * @return array{de:?string, ate:?string, funcionario:?int, status:?string, tipo:string}
     */
    public static function normalizarFiltros(array $in): array
    {
        $data = function ($v) {
            $v = trim((string) $v);
            return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && strtotime($v) !== false ? $v : null;
        };
        $status = $in['status'] ?? '';
        $tipo   = $in['tipo'] ?? 'todos';
        $func   = (int) ($in['funcionario'] ?? 0);

        return [
            'de'          => $data($in['de'] ?? ''),
            'ate'         => $data($in['ate'] ?? ''),
            'funcionario' => $func > 0 ? $func : null,
            'status'      => in_array($status, ['pendente', 'pago', 'cancelado'], true) ? $status : null,
            'tipo'        => in_array($tipo, ['todos', 'receita', 'despesa', 'comissao'], true) ? $tipo : 'todos',
        ];
    }

    /** @return array{0:string,1:array} fragmento WHERE (sempre começa com 1=1) + parâmetros */
    private static function where(array $f, string $alias = 'c'): array
    {
        $w = ' WHERE 1=1';
        $p = [];
        if (!empty($f['funcionario'])) {
            $w .= " AND {$alias}.id_barbeiro = :func";
            $p['func'] = (int) $f['funcionario'];
        }
        if (!empty($f['de'])) {
            $w .= " AND {$alias}.data_servico >= :de";
            $p['de'] = $f['de'];
        }
        if (!empty($f['ate'])) {
            $w .= " AND {$alias}.data_servico <= :ate";
            $p['ate'] = $f['ate'];
        }
        if (!empty($f['status'])) {
            $w .= " AND {$alias}.status = :st";
            $p['st'] = $f['status'];
        }
        return [$w, $p];
    }

    /** Lista de comissões (mais recentes primeiro), já com o nome do funcionário. */
    public static function listar(PDO $pdo, array $filtros, int $limite = 500): array
    {
        [$w, $p] = self::where($filtros);
        $limite = max(1, min(2000, $limite));
        $stmt = $pdo->prepare(
            "SELECT c.idComissao, c.idAgendamento, c.idLancamento, c.id_barbeiro, b.nome AS funcionario_nome,
                    c.cliente_nome, c.servico_nome, c.forma_pagamento, c.data_servico, c.hora_servico,
                    c.valor_servico, c.percentual, c.valor_comissao, c.status, c.pago_em, c.cancelado_em, c.historico
             FROM Comissoes c
             LEFT JOIN Barbeiro b ON b.id_barbeiro = c.id_barbeiro
             {$w}
             ORDER BY c.data_servico DESC, c.hora_servico DESC, c.idComissao DESC
             LIMIT {$limite}"
        );
        $stmt->execute($p);
        return $stmt->fetchAll();
    }

    /**
     * Totais (comissões CANCELADAS ficam fora de todas as somas — só
     * aparecem na contagem `cancelados`).
     *
     * @return array{qtd_servicos:int, total_servicos:float, total_comissao:float, total_pago:float, total_pendente:float, cancelados:int}
     */
    public static function totais(PDO $pdo, array $filtros): array
    {
        [$w, $p] = self::where($filtros);
        $stmt = $pdo->prepare(
            "SELECT
                SUM(c.status <> 'cancelado') AS qtd,
                COALESCE(SUM(CASE WHEN c.status <> 'cancelado' THEN c.valor_servico END), 0) AS total_servicos,
                COALESCE(SUM(CASE WHEN c.status <> 'cancelado' THEN c.valor_comissao END), 0) AS total_comissao,
                COALESCE(SUM(CASE WHEN c.status = 'pago' THEN c.valor_comissao END), 0) AS total_pago,
                COALESCE(SUM(CASE WHEN c.status = 'pendente' THEN c.valor_comissao END), 0) AS total_pendente,
                COALESCE(SUM(c.status = 'cancelado'), 0) AS cancelados
             FROM Comissoes c {$w}"
        );
        $stmt->execute($p);
        $r = $stmt->fetch();

        return [
            'qtd_servicos'   => (int) ($r['qtd'] ?? 0),
            'total_servicos' => round((float) $r['total_servicos'], 2),
            'total_comissao' => round((float) $r['total_comissao'], 2),
            'total_pago'     => round((float) $r['total_pago'], 2),
            'total_pendente' => round((float) $r['total_pendente'], 2),
            'cancelados'     => (int) $r['cancelados'],
        ];
    }

    /** Totais por funcionário (ranking por comissão/serviços), só funcionários com algo no filtro. */
    public static function porFuncionario(PDO $pdo, array $filtros): array
    {
        [$w, $p] = self::where($filtros);
        $stmt = $pdo->prepare(
            "SELECT c.id_barbeiro, b.nome,
                    SUM(c.status <> 'cancelado') AS qtd,
                    COALESCE(SUM(CASE WHEN c.status <> 'cancelado' THEN c.valor_servico END), 0) AS total_servicos,
                    COALESCE(SUM(CASE WHEN c.status <> 'cancelado' THEN c.valor_comissao END), 0) AS total_comissao,
                    COALESCE(SUM(CASE WHEN c.status = 'pago' THEN c.valor_comissao END), 0) AS total_pago,
                    COALESCE(SUM(CASE WHEN c.status = 'pendente' THEN c.valor_comissao END), 0) AS total_pendente
             FROM Comissoes c
             LEFT JOIN Barbeiro b ON b.id_barbeiro = c.id_barbeiro
             {$w}
             GROUP BY c.id_barbeiro, b.nome
             ORDER BY total_comissao DESC, b.nome ASC"
        );
        $stmt->execute($p);

        return array_map(fn($r) => [
            'id_barbeiro'    => (int) $r['id_barbeiro'],
            'nome'           => $r['nome'] ?? ('#' . $r['id_barbeiro']),
            'qtd_servicos'   => (int) $r['qtd'],
            'total_servicos' => round((float) $r['total_servicos'], 2),
            'total_comissao' => round((float) $r['total_comissao'], 2),
            'total_pago'     => round((float) $r['total_pago'], 2),
            'total_pendente' => round((float) $r['total_pendente'], 2),
        ], $stmt->fetchAll());
    }

    /** Todos os funcionários cadastrados (para filtros e para listar quem ainda não tem comissão). */
    public static function funcionarios(PDO $pdo): array
    {
        $stmt = $pdo->query("SELECT id_barbeiro, nome FROM Barbeiro WHERE tipo_usuario = 'funcionario' ORDER BY nome ASC");
        return $stmt->fetchAll();
    }

    /**
     * Visão geral da barbearia para o PROPRIETÁRIO no período: receitas e
     * despesas efetivamente recebidas/pagas (FinanceiroRecebimentos, de
     * todos os barbeiros), serviços realizados e total faturado (lançamentos
     * de atendimento, inclusive fiado em aberto).
     *
     * @return array{receitas:float, despesas:float, servicos_realizados:int, total_faturado:float}
     */
    public static function visaoGeral(PDO $pdo, array $filtros): array
    {
        $p = [];
        $wR = ' WHERE 1=1';
        $wL = " WHERE l.origem = 'agendamento'";
        if (!empty($filtros['funcionario'])) {
            $wR .= ' AND r.id_barbeiro = :func';
            $wL .= ' AND l.id_barbeiro = :func';
            $p['func'] = (int) $filtros['funcionario'];
        }
        if (!empty($filtros['de'])) {
            $wR .= ' AND r.data >= :de';
            $wL .= ' AND l.data >= :de';
            $p['de'] = $filtros['de'];
        }
        if (!empty($filtros['ate'])) {
            $wR .= ' AND r.data <= :ate';
            $wL .= ' AND l.data <= :ate';
            $p['ate'] = $filtros['ate'];
        }

        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(CASE WHEN r.tipo = 'entrada' THEN r.valor END), 0) AS receitas,
                    COALESCE(SUM(CASE WHEN r.tipo = 'saida' THEN r.valor END), 0) AS despesas
             FROM FinanceiroRecebimentos r {$wR}"
        );
        $stmt->execute($p);
        $rd = $stmt->fetch();

        $stmt = $pdo->prepare(
            "SELECT COUNT(*) AS qtd, COALESCE(SUM(l.valor), 0) AS faturado
             FROM FinanceiroLancamentos l {$wL}"
        );
        $stmt->execute($p);
        $lf = $stmt->fetch();

        return [
            'receitas'            => round((float) $rd['receitas'], 2),
            'despesas'            => round((float) $rd['despesas'], 2),
            'servicos_realizados' => (int) $lf['qtd'],
            'total_faturado'      => round((float) $lf['faturado'], 2),
        ];
    }

    /** Extrato geral (receitas/despesas recebidas) de todos os barbeiros — visão do proprietário. */
    public static function extratoGeral(PDO $pdo, array $filtros, int $limite = 300): array
    {
        $p = [];
        $w = ' WHERE 1=1';
        if (!empty($filtros['funcionario'])) {
            $w .= ' AND r.id_barbeiro = :func';
            $p['func'] = (int) $filtros['funcionario'];
        }
        if (!empty($filtros['de'])) {
            $w .= ' AND r.data >= :de';
            $p['de'] = $filtros['de'];
        }
        if (!empty($filtros['ate'])) {
            $w .= ' AND r.data <= :ate';
            $p['ate'] = $filtros['ate'];
        }
        if (($filtros['tipo'] ?? '') === 'receita') {
            $w .= " AND r.tipo = 'entrada'";
        } elseif (($filtros['tipo'] ?? '') === 'despesa') {
            $w .= " AND r.tipo = 'saida'";
        }
        $limite = max(1, min(1000, $limite));
        $stmt = $pdo->prepare(
            "SELECT r.idRecebimento, r.data, r.tipo, r.valor, r.forma_pagamento, l.titulo, l.origem,
                    r.id_barbeiro, b.nome AS responsavel
             FROM FinanceiroRecebimentos r
             INNER JOIN FinanceiroLancamentos l ON l.idLancamento = r.idLancamento
             LEFT JOIN Barbeiro b ON b.id_barbeiro = r.id_barbeiro
             {$w}
             ORDER BY r.data DESC, r.idRecebimento DESC
             LIMIT {$limite}"
        );
        $stmt->execute($p);
        return $stmt->fetchAll();
    }

    /**
     * Filtros já AUTORIZADOS para quem está logado (chamar depois de
     * exigirSessao(['barbeiro'])).
     *   - Proprietário: usa os filtros pedidos (qualquer funcionário).
     *   - Funcionário: sempre trava no próprio id. Se pedir o financeiro de
     *     OUTRO funcionário (?funcionario=ID manipulado), responde
     *     ACESSO NEGADO (403) — nunca devolve dados alheios nem ignora
     *     silenciosamente.
     */
    public static function filtrosAutorizados(PDO $pdo, array $entrada, bool $json): array
    {
        $f = self::normalizarFiltros($entrada);

        if (!AcessoService::ehProprietario($pdo)) {
            $eu = (int) ($_SESSION['id'] ?? 0);
            if ($f['funcionario'] !== null && $f['funcionario'] !== $eu) {
                AcessoService::negar($json, 'Tentativa de ver o financeiro do funcionário #' . $f['funcionario']);
            }
            $f['funcionario'] = $eu;
            $f['tipo'] = 'comissao';
        }

        return $f;
    }

    // -------------------------------------------------------- formatação

    public static function fmtMoeda(float $v): string
    {
        return 'R$ ' . number_format($v, 2, ',', '.');
    }

    public static function fmtPct(float $v): string
    {
        return rtrim(rtrim(number_format($v, 2, ',', ''), '0'), ',') . '%';
    }
}

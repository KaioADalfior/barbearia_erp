<?php
/**
 * includes/FinanceiroService.php
 *
 * Regras de negócio do módulo Financeiro (Dashboard, Cadastrar Baixa/Extrato
 * e Fiados) e a regra de "1 agendamento ativo por cliente por dia". Centraliza
 * as queries usadas pelos endpoints em Financeiro/scripts e Agendamentos/scripts
 * para não duplicar SQL entre eles.
 */

require_once __DIR__ . '/forma_pagamento.php'; // FORMAS_PAGAMENTO_LABELS

class FinanceiroService
{
    /**
     * Soma o saldo devedor (valor - valor_pago) dos fiados em aberto de um
     * cliente. Usado para alertar o barbeiro com o VALOR em aberto (não a
     * quantidade de cortes) ao abrir os detalhes do cliente em
     * Clientes/paginas/cliente_listar.php.
     */
    public static function saldoFiadoPendente(PDO $pdo, int $idCliente): float
    {
        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(valor - valor_pago), 0) FROM FinanceiroLancamentos WHERE idCliente = :cliente AND status = 'pendente'"
        );
        $stmt->execute(['cliente' => $idCliente]);

        return (float) $stmt->fetchColumn();
    }

    /**
     * Regra: cada cliente pode ter apenas um agendamento ATIVO (agendado ou
     * confirmado) por dia. Usada antes de salvar um novo agendamento.
     */
    public static function clienteJaTemAgendamentoNoDia(PDO $pdo, int $idCliente, string $data): bool
    {
        $stmt = $pdo->prepare(
            "SELECT 1 FROM Agendamentos
             WHERE idCliente = :cliente AND Data = :data AND Status IN ('agendado', 'confirmado')
             LIMIT 1"
        );
        $stmt->execute(['cliente' => $idCliente, 'data' => $data]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Concilia forma(s) de pagamento vindas do POST (array) em uma string
     * única (CSV), validando contra as opções conhecidas do componente
     * reutilizável de Forma de pagamento.
     *
     * @return string[] formas válidas (chaves), na ordem recebida
     */
    public static function normalizarFormasPagamento(array $formasBrutas): array
    {
        $validas = array_keys(FORMAS_PAGAMENTO_LABELS);
        $formas = array_values(array_intersect($formasBrutas, $validas));

        return $formas;
    }

    /**
     * Concluir um agendamento: marca o agendamento como concluído, libera o
     * horário (mesmo comportamento já existente) e cria o lançamento
     * financeiro correspondente — pago (entra nas receitas) ou pendente
     * (fiado, fora do cálculo de receitas até ser recebido).
     *
     * @param array $agendamento linha de Agendamentos + joins (idAgendamento, idHorario, idCliente, idServico, Valor, clienteNome, servicoNome)
     * @param string[] $formasPagamento
     * @return int idLancamento criado
     */
    public static function concluirAgendamentoComPagamento(
        PDO $pdo,
        array $agendamento,
        array $formasPagamento,
        bool $fiado,
        int $idBarbeiro
    ): int {
        $stmtConclui = $pdo->prepare("UPDATE Agendamentos SET Status = 'concluido' WHERE idAgendamento = :id");
        $stmtConclui->execute(['id' => $agendamento['idAgendamento']]);

        $stmtLibera = $pdo->prepare('UPDATE Horario SET disponivel = 1 WHERE idHorario = :h');
        $stmtLibera->execute(['h' => $agendamento['idHorario']]);

        // Agendamento duplicado (dois horários vinculados, ver
        // grupo_agendamento) com os DOIS cortes efetivamente cobrados
        // (cobranca_duplicado = 'dois'): o nome do serviço passa a listar os
        // dois cortes (ex.: "Corte + Barba"), tanto no lançamento financeiro
        // quanto no fiado/histórico do cliente — refletindo o que foi
        // realmente cobrado. Se só um corte foi cobrado (ou não é
        // duplicado), continua mostrando só ele, como antes.
        $nomeServicoCompleto = $agendamento['servicoNome'];
        $qtdCortesCobrados   = 1;
        if (($agendamento['cobranca_duplicado'] ?? null) === 'dois' && !empty($agendamento['servicoSecundarioNome'])) {
            $nomeServicoCompleto .= ' + ' . $agendamento['servicoSecundarioNome'];
            $qtdCortesCobrados = 2;
        }

        $idLancamento = self::criarLancamento($pdo, [
            'id_barbeiro'     => $idBarbeiro,
            'idAgendamento'   => $agendamento['idAgendamento'],
            'idCliente'       => $agendamento['idCliente'],
            'origem'          => 'agendamento',
            'tipo'            => 'entrada',
            'titulo'          => $nomeServicoCompleto . ' - ' . $agendamento['clienteNome'],
            'descricao'       => null,
            'quantidade'      => $qtdCortesCobrados,
            'valor'           => $agendamento['Valor'],
            'forma_pagamento' => !empty($formasPagamento) ? implode(',', $formasPagamento) : null,
            'status'          => $fiado ? 'pendente' : 'pago',
            'data'            => date('Y-m-d'),
        ]);

        // Fiado: registra automaticamente no histórico do cliente (aba
        // "Histórico" em Clientes > detalhes), já ligado a este lançamento
        // para poder ser marcado como PAGO quando for recebido.
        if ($fiado) {
            self::registrarHistoricoFiado($pdo, $agendamento, $idLancamento, $idBarbeiro, $nomeServicoCompleto);
        }

        return $idLancamento;
    }

    /**
     * Marca um agendamento como "Cliente Ausente" (não veio, não foi
     * atendido) e NÃO cria nenhum lançamento financeiro — não houve
     * cobrança, então não deve entrar em receitas/relatórios. Usada por
     * agendamento_concluir.php quando o barbeiro marca a opção "Cliente
     * Ausente" em vez de concluir com pagamento normalmente.
     *
     * Diferente de uma conclusão normal, o horário NÃO fica livre pra um
     * novo agendamento — continua bloqueado (Horario.disponivel permanece
     * 0, exatamente como já estava enquanto o agendamento estava ativo),
     * até o barbeiro decidir manualmente reativá-lo (mesmo botão
     * "Reativar horário" usado pra um horário inativado à mão, ver
     * Agendamentos/scripts/horario_status.php). Isso evita que o slot
     * simplesmente reabra sozinho pra reserva logo depois de um não
     * comparecimento.
     *
     * @param array $agendamento linha de Agendamentos (idAgendamento, idHorario)
     */
    public static function marcarAgendamentoAusente(PDO $pdo, array $agendamento): void
    {
        $stmtMarca = $pdo->prepare("UPDATE Agendamentos SET Status = 'ausente' WHERE idAgendamento = :id");
        $stmtMarca->execute(['id' => $agendamento['idAgendamento']]);

        // Propositalmente NÃO libera o Horario aqui (ver docblock acima) —
        // ele já está com disponivel=0 desde que o agendamento foi criado
        // e continua assim até uma reativação manual.
    }

    /**
     * Cria a entrada de histórico (tipo "fiado", em aberto) para o cliente,
     * espelhando o lançamento financeiro pendente recém-criado.
     */
    private static function registrarHistoricoFiado(PDO $pdo, array $agendamento, int $idLancamento, int $idBarbeiro, string $nomeServicoCompleto): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO ClienteHistorico (idCliente, id_barbeiro, idLancamento, tipo, descricao, valor, quitado)
             VALUES (:idCliente, :idBarbeiro, :idLancamento, \'fiado\', :descricao, :valor, 0)'
        );
        $stmt->execute([
            'idCliente'    => $agendamento['idCliente'],
            'idBarbeiro'   => $idBarbeiro,
            'idLancamento' => $idLancamento,
            'descricao'    => 'Fiado - ' . $nomeServicoCompleto . ' - aguardando recebimento.',
            'valor'        => $agendamento['Valor'],
        ]);
    }

    /**
     * Lançamento manual (Financeiro > Cadastrar Baixa). Sempre entra
     * como pago — fiado só existe via conclusão de agendamento.
     */
    public static function criarBaixaManual(PDO $pdo, int $idBarbeiro, array $dados): int
    {
        return self::criarLancamento($pdo, [
            'id_barbeiro'     => $idBarbeiro,
            'idAgendamento'   => null,
            'idCliente'       => null,
            'origem'          => 'manual',
            'tipo'            => $dados['tipo'],
            'titulo'          => $dados['titulo'],
            'descricao'       => $dados['descricao'] !== '' ? $dados['descricao'] : null,
            'quantidade'      => $dados['quantidade'],
            'valor'           => $dados['valor'],
            'forma_pagamento' => implode(',', $dados['formas']),
            'status'          => 'pago',
            'data'            => $dados['data'],
        ]);
    }

    /**
     * Fiado manual (Financeiro > Fiados > "+ Novo Registro"). Permite ao
     * barbeiro registrar uma dívida de um cliente já cadastrado sem estar
     * vinculada a um agendamento (ex: fiado combinado presencialmente).
     * Cria o lançamento PENDENTE normalmente — cai na mesma "conta
     * corrente" que aparece em Fiados — e espelha no histórico do
     * cliente, exatamente como um fiado vindo de agendamento concluído.
     * O recebimento depois segue o mesmo fluxo já existente
     * (receberFiadoCliente), sem nenhuma diferença de comportamento.
     *
     * @return array{ok:bool, erro?:string, idLancamento?:int}
     */
    public static function criarFiadoManual(PDO $pdo, int $idBarbeiro, int $idCliente, float $valor, string $descricao = ''): array
    {
        if ($idCliente <= 0) {
            return ['ok' => false, 'erro' => 'Selecione um cliente.'];
        }

        if ($valor <= 0) {
            return ['ok' => false, 'erro' => 'Informe um valor maior que zero.'];
        }

        $stmtCliente = $pdo->prepare('SELECT nome FROM Cliente WHERE idCliente = :id AND ativo = 1');
        $stmtCliente->execute(['id' => $idCliente]);
        $nomeCliente = $stmtCliente->fetchColumn();

        if (!$nomeCliente) {
            return ['ok' => false, 'erro' => 'Cliente não encontrado.'];
        }

        $descricao = trim($descricao) !== '' ? trim($descricao) : 'Fiado registrado manualmente';

        $pdo->beginTransaction();

        try {
            $idLancamento = self::criarLancamento($pdo, [
                'id_barbeiro'     => $idBarbeiro,
                'idAgendamento'   => null,
                'idCliente'       => $idCliente,
                'origem'          => 'manual',
                'tipo'            => 'entrada',
                'titulo'          => $descricao . ' - ' . $nomeCliente,
                'descricao'       => null,
                'quantidade'      => 1,
                'valor'           => $valor,
                'forma_pagamento' => null,
                'status'          => 'pendente',
                'data'            => date('Y-m-d'),
            ]);

            $stmtHist = $pdo->prepare(
                'INSERT INTO ClienteHistorico (idCliente, id_barbeiro, idLancamento, tipo, descricao, valor, quitado)
                 VALUES (:idCliente, :idBarbeiro, :idLancamento, \'fiado\', :descricao, :valor, 0)'
            );
            $stmtHist->execute([
                'idCliente'    => $idCliente,
                'idBarbeiro'   => $idBarbeiro,
                'idLancamento' => $idLancamento,
                'descricao'    => 'Fiado - ' . $descricao . ' - aguardando recebimento.',
                'valor'        => $valor,
            ]);

            $pdo->commit();

            return ['ok' => true, 'idLancamento' => $idLancamento];
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    private static function criarLancamento(PDO $pdo, array $l): int
    {
        // valor_pago: lançamentos 'pago' nascem sempre quitados de uma vez
        // (valor_pago = valor). 'pendente' (fiado) nasce com valor_pago = 0
        // — o saldo em aberto (valor - valor_pago) é quem alimenta a tela
        // de Fiados e vai diminuindo a cada recebimento parcial.
        $valorPago = $l['status'] === 'pago' ? $l['valor'] : 0;

        $stmt = $pdo->prepare(
            'INSERT INTO FinanceiroLancamentos
                (id_barbeiro, idAgendamento, idCliente, origem, tipo, titulo, descricao, quantidade, valor, valor_pago, forma_pagamento, status, data, pago_em)
             VALUES
                (:id_barbeiro, :idAgendamento, :idCliente, :origem, :tipo, :titulo, :descricao, :quantidade, :valor, :valor_pago, :forma_pagamento, :status, :data, :pago_em)'
        );
        $stmt->execute([
            'id_barbeiro'     => $l['id_barbeiro'],
            'idAgendamento'   => $l['idAgendamento'],
            'idCliente'       => $l['idCliente'],
            'origem'          => $l['origem'],
            'tipo'            => $l['tipo'],
            'titulo'          => $l['titulo'],
            'descricao'       => $l['descricao'],
            'quantidade'      => $l['quantidade'],
            'valor'           => $l['valor'],
            'valor_pago'      => $valorPago,
            'forma_pagamento' => $l['forma_pagamento'],
            'status'          => $l['status'],
            'data'            => $l['data'],
            'pago_em'         => $l['status'] === 'pago' ? date('Y-m-d H:i:s') : null,
        ]);

        $idLancamento = (int) $pdo->lastInsertId();

        // Lançamento já nasce PAGO (baixa manual, ou agendamento concluído
        // sem fiado): o dinheiro já entrou de uma vez, na hora — registra
        // já aqui o recebimento correspondente (valor cheio), que é o que
        // o Dashboard/Extrato/Relatórios somam de fato (ver
        // FinanceiroRecebimentos e registrarRecebimento() logo abaixo).
        // Fiado ('pendente') NÃO gera recebimento nenhum ainda — só quando
        // for de fato recebido, em receberFiadoCliente().
        if ($l['status'] === 'pago') {
            self::registrarRecebimento($pdo, $idLancamento, $l['id_barbeiro'], $l['idCliente'], $l['tipo'], $l['valor'], $l['forma_pagamento'], $l['data']);
        }

        return $idLancamento;
    }

    /**
     * Registra UM pagamento efetivamente recebido — a unidade real que o
     * Dashboard, o Extrato e os Relatórios somam pra calcular receita
     * (ver FinanceiroService::resumoDashboard, listarExtrato e
     * RelatorioService::coletarDados). Diferente de FinanceiroLancamentos
     * (que representa o atendimento/dívida inteiro), aqui cada linha é um
     * evento de dinheiro entrando de verdade, na SUA própria data — por
     * isso um fiado pago em duas parcelas em dias diferentes gera DUAS
     * linhas aqui, cada uma contando como receita no dia em que aquela
     * parcela específica foi paga, em vez de tudo de uma vez só quando a
     * dívida é finalmente quitada.
     */
    private static function registrarRecebimento(
        PDO $pdo,
        int $idLancamento,
        int $idBarbeiro,
        ?int $idCliente,
        string $tipo,
        float $valor,
        ?string $formaPagamento,
        string $data
    ): void {
        $stmt = $pdo->prepare(
            'INSERT INTO FinanceiroRecebimentos (idLancamento, id_barbeiro, idCliente, tipo, valor, forma_pagamento, data)
             VALUES (:idLancamento, :id_barbeiro, :idCliente, :tipo, :valor, :forma_pagamento, :data)'
        );
        $stmt->execute([
            'idLancamento'    => $idLancamento,
            'id_barbeiro'     => $idBarbeiro,
            'idCliente'       => $idCliente,
            'tipo'            => $tipo,
            'valor'           => $valor,
            'forma_pagamento' => $formaPagamento,
            'data'            => $data,
        ]);
    }

    /**
     * Extrato / Cadastrar Baixa: um RECEBIMENTO por linha (não um
     * lançamento por linha) — ver FinanceiroRecebimentos. Isso é o que
     * garante que um fiado pago em parcelas apareça como uma linha por
     * parcela, cada uma na sua própria data, em vez de uma única linha só
     * no dia da quitação final.
     */
    public static function listarExtrato(PDO $pdo, int $idBarbeiro, string $termo = '', string $tipo = 'todos'): array
    {
        $sql = "SELECT r.idRecebimento, r.idLancamento, r.tipo, l.titulo, l.descricao, l.quantidade,
                       r.valor, r.forma_pagamento, r.data, l.origem
                FROM FinanceiroRecebimentos r
                INNER JOIN FinanceiroLancamentos l ON l.idLancamento = r.idLancamento
                WHERE r.id_barbeiro = :b";
        $params = ['b' => $idBarbeiro];

        if ($tipo === 'entrada' || $tipo === 'saida') {
            $sql .= ' AND r.tipo = :tipo';
            $params['tipo'] = $tipo;
        }

        if ($termo !== '') {
            $sql .= ' AND (l.titulo LIKE :termo OR l.descricao LIKE :termo)';
            $params['termo'] = '%' . $termo . '%';
        }

        $sql .= ' ORDER BY r.data DESC, r.idRecebimento DESC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Fiados: uma "conta corrente" por cliente, juntando todos os cortes
     * fiados (lançamentos PENDENTES, fora do cálculo de receitas) num único
     * saldo devedor — em vez de uma linha por corte. Cada cliente aparece
     * uma única vez na tela de Fiados, com os lançamentos individuais
     * aninhados em `itens` para quem quiser ver o detalhe.
     *
     * @param string $termo Busca opcional por nome ou telefone do cliente.
     * @return array<int, array{
     *   idCliente:int, clienteNome:?string, clienteTelefone:?string,
     *   saldoTotal:float, qtdCortes:int, itens:array
     * }>
     */
    public static function listarFiadosAgrupados(PDO $pdo, int $idBarbeiro, string $termo = ''): array
    {
        $sql = "SELECT f.idLancamento, f.idCliente, f.titulo, f.valor, f.valor_pago, f.quantidade,
                       (f.valor - f.valor_pago) AS saldo, f.data, f.forma_pagamento, f.criado_em,
                       c.nome AS clienteNome, c.telefone AS clienteTelefone
                FROM FinanceiroLancamentos f
                LEFT JOIN Cliente c ON c.idCliente = f.idCliente
                WHERE f.id_barbeiro = :b AND f.status = 'pendente'";
        $params = ['b' => $idBarbeiro];

        if ($termo !== '') {
            $sql .= ' AND (c.nome LIKE :termo OR c.telefone LIKE :termo)';
            $params['termo'] = '%' . $termo . '%';
        }

        $sql .= ' ORDER BY f.data ASC, f.idLancamento ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $lancamentos = $stmt->fetchAll();

        $porCliente = [];
        foreach ($lancamentos as $l) {
            // idCliente pode ser NULL (fiado sem cliente vinculado, caso raro);
            // usa o próprio idLancamento como chave para não misturar contas.
            $chave = $l['idCliente'] !== null ? 'c' . $l['idCliente'] : 'l' . $l['idLancamento'];

            if (!isset($porCliente[$chave])) {
                $porCliente[$chave] = [
                    'idCliente'       => $l['idCliente'] !== null ? (int) $l['idCliente'] : null,
                    'clienteNome'     => $l['clienteNome'],
                    'clienteTelefone' => $l['clienteTelefone'],
                    'saldoTotal'      => 0.0,
                    'qtdCortes'       => 0,
                    'itens'           => [],
                ];
            }

            // qtdCortes soma a "quantidade" real de cortes de cada lançamento
            // (1 normalmente; 2 quando é um duplicado com os dois cortes
            // cobrados — ver FinanceiroService::concluirAgendamentoComPagamento),
            // não a quantidade de linhas/lançamentos.
            $porCliente[$chave]['saldoTotal'] += (float) $l['saldo'];
            $porCliente[$chave]['qtdCortes']  += (int) $l['quantidade'];
            $porCliente[$chave]['itens'][] = [
                'idLancamento'    => (int) $l['idLancamento'],
                'titulo'          => $l['titulo'],
                'valor'           => (float) $l['valor'],
                'valorPago'       => (float) $l['valor_pago'],
                'saldo'           => (float) $l['saldo'],
                'quantidade'      => (int) $l['quantidade'],
                'data'            => $l['data'],
                'forma_pagamento' => $l['forma_pagamento'],
            ];
        }

        // Mais recente primeiro (por data do fiado mais recente do cliente).
        $agrupado = array_values($porCliente);
        usort($agrupado, function ($a, $b) {
            $dataA = end($a['itens'])['data'] ?? '';
            $dataB = end($b['itens'])['data'] ?? '';
            return $dataB <=> $dataA;
        });

        return $agrupado;
    }

    /**
     * Recebe um pagamento de fiado de um cliente: um valor livre (que pode
     * ser menor que o total devido), abatido dos lançamentos pendentes mais
     * antigos primeiro (FIFO). Cada lançamento só é marcado como PAGO (sai
     * da tela de Fiados) quando seu valor_pago atinge o valor total; senão
     * fica pendente com o novo saldo reduzido. Sempre anexa uma entrada no
     * histórico do cliente dizendo quanto foi pago e quanto ainda falta.
     *
     * IMPORTANTE: cada abatimento aplicado aqui — parcial ou não — gera
     * imediatamente uma linha em FinanceiroRecebimentos com a data de HOJE
     * (ver registrarRecebimento()), que é o que o Dashboard/Extrato/
     * Relatórios somam. Ou seja, o dinheiro conta no financeiro no dia em
     * que CADA parcela é de fato recebida — não é preciso esperar o
     * lançamento inteiro ficar 100% quitado pra essa parte já entrar nas
     * receitas. Isso vale mesmo que o lançamento em si continue com
     * status 'pendente' (saldo residual) depois deste recebimento.
     *
     * Ex: cliente deve 50 (dois cortes fiados), paga 30 hoje -> abate o
     * corte mais antigo primeiro; se sobrar valor, começa a abater o
     * próximo. Continua devendo 20 (registrado no histórico dele), mas os
     * 30 recebidos hoje já entram na receita de hoje. Se ele pagar os 20
     * restantes daqui a 5 dias, esses 20 entram na receita daqui a 5 dias.
     *
     * @return array{ok:bool, erro?:string, valorAplicado?:float, saldoRestante?:float}
     */
    public static function receberFiadoCliente(
        PDO $pdo,
        int $idBarbeiro,
        int $idCliente,
        float $valor,
        array $formasPagamento
    ): array {
        if ($valor <= 0) {
            return ['ok' => false, 'erro' => 'Informe um valor maior que zero.'];
        }

        $pdo->beginTransaction();

        try {
            // Trava as linhas do cliente para evitar corrida entre dois
            // recebimentos simultâneos (ex: duas abas abertas).
            $stmt = $pdo->prepare(
                "SELECT idLancamento, valor, valor_pago, (valor - valor_pago) AS saldo
                 FROM FinanceiroLancamentos
                 WHERE id_barbeiro = :b AND idCliente = :c AND status = 'pendente'
                 ORDER BY data ASC, idLancamento ASC
                 FOR UPDATE"
            );
            $stmt->execute(['b' => $idBarbeiro, 'c' => $idCliente]);
            $lancamentos = $stmt->fetchAll();

            $saldoTotal = array_sum(array_column($lancamentos, 'saldo'));

            if ($saldoTotal <= 0) {
                $pdo->rollBack();
                return ['ok' => false, 'erro' => 'Este cliente não tem fiado em aberto.'];
            }

            // Não deixa registrar um pagamento maior que a dívida total —
            // trava no saldo devedor (o barbeiro pode digitar o valor certo
            // e tentar de novo).
            if ($valor > $saldoTotal + 0.001) {
                $pdo->rollBack();
                return [
                    'ok'    => false,
                    'erro'  => 'Valor maior que o saldo devedor (' . number_format($saldoTotal, 2, ',', '.') . ').',
                ];
            }

            $forma = implode(',', $formasPagamento);
            $restante = $valor;
            $quitadosCompletamente = [];
            $hoje = date('Y-m-d');

            foreach ($lancamentos as $l) {
                if ($restante <= 0.001) {
                    break;
                }

                $saldoItem = (float) $l['saldo'];
                $abatimento = min($restante, $saldoItem);
                $novoValorPago = round((float) $l['valor_pago'] + $abatimento, 2);
                $quitado = $novoValorPago >= ((float) $l['valor'] - 0.001);

                // Este abatimento (parcial OU o que fecha a conta) É a
                // parte que efetivamente entrou no bolso agora — conta como
                // receita HOJE, seja o lançamento fechado por completo
                // nesta parcela ou não. É isso que corrige o comportamento
                // antigo, em que um pagamento parcial só reduzia o saldo
                // devedor e ficava invisível pro financeiro até quitar tudo.
                self::registrarRecebimento($pdo, (int) $l['idLancamento'], $idBarbeiro, $idCliente, 'entrada', $abatimento, $forma, $hoje);

                if ($quitado) {
                    // Só a "etiqueta" do lançamento muda pra 'pago' (sai da
                    // tela de Fiados) — a `data` original do lançamento NÃO
                    // é mais sobrescrita aqui: quem passou a carregar a data
                    // de cada entrada de dinheiro é FinanceiroRecebimentos
                    // (ver acima), inclusive quando o valor foi recebido aos
                    // poucos em datas diferentes.
                    $stmtUpd = $pdo->prepare(
                        "UPDATE FinanceiroLancamentos
                         SET valor_pago = :vp, status = 'pago', forma_pagamento = :forma, pago_em = NOW()
                         WHERE idLancamento = :id"
                    );
                    $stmtUpd->execute(['vp' => $l['valor'], 'forma' => $forma, 'id' => $l['idLancamento']]);
                    $quitadosCompletamente[] = (int) $l['idLancamento'];
                } else {
                    $stmtUpd = $pdo->prepare(
                        "UPDATE FinanceiroLancamentos SET valor_pago = :vp WHERE idLancamento = :id"
                    );
                    $stmtUpd->execute(['vp' => $novoValorPago, 'id' => $l['idLancamento']]);
                }

                $restante = round($restante - $abatimento, 2);
            }

            // Reflete no histórico do cliente as entradas de fiado dos
            // lançamentos que foram totalmente quitados nesse recebimento.
            if (!empty($quitadosCompletamente)) {
                $in = implode(',', array_fill(0, count($quitadosCompletamente), '?'));
                $stmtHist = $pdo->prepare(
                    "UPDATE ClienteHistorico SET quitado = 1 WHERE idLancamento IN ($in)"
                );
                $stmtHist->execute($quitadosCompletamente);
            }

            $saldoRestante = round($saldoTotal - $valor, 2);

            // Sempre anexa uma entrada de histórico com o resumo do
            // recebimento (total ou parcial), independente de quantos
            // lançamentos ele tocou.
            $descricao = $saldoRestante > 0.001
                ? sprintf(
                    'Pagamento de fiado - recebido %s, ainda deve %s.',
                    self::formatarMoeda($valor),
                    self::formatarMoeda($saldoRestante)
                )
                : sprintf('Pagamento de fiado - recebido %s. Fiado quitado.', self::formatarMoeda($valor));

            $stmtHistPagamento = $pdo->prepare(
                'INSERT INTO ClienteHistorico (idCliente, id_barbeiro, idLancamento, tipo, descricao, valor, quitado)
                 VALUES (:idCliente, :idBarbeiro, NULL, \'pagamento\', :descricao, :valor, 1)'
            );
            $stmtHistPagamento->execute([
                'idCliente'  => $idCliente,
                'idBarbeiro' => $idBarbeiro,
                'descricao'  => $descricao,
                'valor'      => $valor,
            ]);

            $pdo->commit();

            return ['ok' => true, 'valorAplicado' => $valor, 'saldoRestante' => $saldoRestante];
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    private static function formatarMoeda(float $valor): string
    {
        return 'R$ ' . number_format($valor, 2, ',', '.');
    }

    /**
     * Exclui um lançamento (Financeiro > Cadastrar Baixa), para o barbeiro
     * corrigir um lançamento errado. Só permite excluir lançamentos "pago"
     * (os "pendente"/fiado têm sua própria tela — Financeiro > Fiados — e
     * não são excluídos por aqui) e sempre restritos ao próprio barbeiro.
     * Remove também a entrada de histórico do cliente ligada a ele (se
     * houver) e TODOS os recebimentos ligados a ele em
     * FinanceiroRecebimentos — inclusive quando o lançamento foi um fiado
     * quitado em mais de uma parcela, cada uma virou uma linha separada lá
     * (ver registrarRecebimento()), e todas precisam sumir junto.
     */
    public static function excluirLancamento(PDO $pdo, int $idBarbeiro, int $idLancamento): bool
    {
        $pdo->beginTransaction();

        try {
            $stmtHistorico = $pdo->prepare('DELETE FROM ClienteHistorico WHERE idLancamento = :id');
            $stmtHistorico->execute(['id' => $idLancamento]);

            // Explícito aqui (além do ON DELETE CASCADE da FK, ver migração
            // atualizacao_financeiro_recebimentos.sql) pra não depender de
            // FK estar de fato ativa no servidor de banco.
            $stmtRecebimentos = $pdo->prepare('DELETE FROM FinanceiroRecebimentos WHERE idLancamento = :id AND id_barbeiro = :b');
            $stmtRecebimentos->execute(['id' => $idLancamento, 'b' => $idBarbeiro]);

            $stmtLancamento = $pdo->prepare(
                "DELETE FROM FinanceiroLancamentos WHERE idLancamento = :id AND id_barbeiro = :b AND status = 'pago'"
            );
            $stmtLancamento->execute(['id' => $idLancamento, 'b' => $idBarbeiro]);
            $excluido = $stmtLancamento->rowCount() > 0;

            $pdo->commit();

            return $excluido;
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Resumo do Dashboard Financeiro: totais e série agrupada pelo período
     * escolhido (diario/semanal/mensal/anual), somando a partir de
     * FinanceiroRecebimentos — cada recebimento conta na data em que
     * efetivamente entrou (inclusive parcelas de fiado recebidas aos
     * poucos), não na data do lançamento/atendimento original.
     */
    public static function resumoDashboard(PDO $pdo, int $idBarbeiro, string $periodo, int $ano, string $forma = 'todas'): array
    {
        [$formatoAgrupamento, $inicio, $fim] = self::intervaloPeriodo($periodo, $ano);

        $sql = "SELECT DATE_FORMAT(data, :formato) AS chave,
                       SUM(CASE WHEN tipo = 'entrada' THEN valor ELSE 0 END) AS entradas,
                       SUM(CASE WHEN tipo = 'saida' THEN valor ELSE 0 END) AS saidas,
                       COUNT(*) AS qtd
                FROM FinanceiroRecebimentos
                WHERE id_barbeiro = :b AND data BETWEEN :inicio AND :fim";
        $params = ['formato' => $formatoAgrupamento, 'b' => $idBarbeiro, 'inicio' => $inicio, 'fim' => $fim];

        if ($forma !== 'todas') {
            $sql .= " AND FIND_IN_SET(:forma, forma_pagamento) > 0";
            $params['forma'] = $forma;
        }

        $sql .= ' GROUP BY chave ORDER BY chave ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $serie = $stmt->fetchAll();

        $sqlFormas = "SELECT forma_pagamento, valor FROM FinanceiroRecebimentos
                      WHERE id_barbeiro = :b AND tipo = 'entrada' AND data BETWEEN :inicio AND :fim";
        $stmtFormas = $pdo->prepare($sqlFormas);
        $stmtFormas->execute(['b' => $idBarbeiro, 'inicio' => $inicio, 'fim' => $fim]);

        $porForma = array_fill_keys(array_keys(FORMAS_PAGAMENTO_LABELS), 0.0);
        foreach ($stmtFormas->fetchAll() as $linha) {
            $formasDoLancamento = array_filter(explode(',', (string) $linha['forma_pagamento']));
            $qtdFormas = count($formasDoLancamento) ?: 1;
            foreach ($formasDoLancamento ?: [null] as $f) {
                if (isset($porForma[$f])) {
                    $porForma[$f] += ((float) $linha['valor']) / $qtdFormas;
                }
            }
        }

        return [
            'serie'    => $serie,
            'porForma' => $porForma,
        ];
    }

    /**
     * Traduz o período escolhido no Dashboard em (formato do DATE_FORMAT,
     * data inicial, data final) para a consulta agregada.
     *
     * Sempre o período ATUAL (nunca uma janela deslizante de dias/semanas/
     * anos passados) — é isso que faz o Dashboard "zerar" sozinho na
     * virada de cada período, sem precisar de nenhuma rotina de limpeza:
     *   - diario:  só HOJE (00:00 a 23:59:59) — vira o dia, some da tela.
     *   - semanal: só a semana atual (segunda a domingo que contém hoje)
     *              — vira a semana (domingo -> segunda), some da tela.
     *   - mensal:  só o mês atual inteiro (dia 1 ao último dia).
     *   - anual:   o ano escolhido no filtro (o único período em que o
     *              filtro "Ano" faz sentido escolher um ano passado).
     * O que "some" de uma tela nunca é perdido: como cada período é
     * calculado direto do banco (nunca a partir de um total acumulado
     * salvo em algum lugar), o dia que zerou à meia-noite continua
     * contando normalmente dentro da semana, do mês e do ano — a soma
     * "sobe" pros períodos maiores automaticamente, só por já fazer parte
     * do intervalo de datas deles.
     */
    private static function intervaloPeriodo(string $periodo, int $ano): array
    {
        // America/Sao_Paulo, já garantido pelo date_default_timezone_set()
        // em includes/session.php — esta função nunca deve rodar sem essa
        // configuração já aplicada (session.php é sempre o primeiro
        // arquivo incluído em toda página/script do sistema).
        $hoje = new DateTimeImmutable();

        switch ($periodo) {
            case 'semanal':
                $diaSemanaIso = (int) $hoje->format('N'); // 1 (segunda) .. 7 (domingo)
                $inicioSemana = $hoje->modify('-' . ($diaSemanaIso - 1) . ' days');
                $fimSemana    = $inicioSemana->modify('+6 days');
                return ['%Y-%m-%d', $inicioSemana->format('Y-m-d'), $fimSemana->format('Y-m-d')];

            case 'mensal':
                $inicioMes = $hoje->modify('first day of this month');
                $fimMes    = $hoje->modify('last day of this month');
                return ['%Y-%m-%d', $inicioMes->format('Y-m-d'), $fimMes->format('Y-m-d')];

            case 'anual':
                return ['%Y-%m', "$ano-01-01", "$ano-12-31"];

            case 'diario':
            default:
                return ['%Y-%m-%d', $hoje->format('Y-m-d'), $hoje->format('Y-m-d')];
        }
    }
}
<?php
// agendamento_salvar.php
// Endpoint AJAX (POST) chamado pelo modal de horário vago em Agendamentos/paginas/agendar.php.
// Recebe o horário escolhido e, ou o id de um cliente já cadastrado, ou os
// dados para cadastrar um cliente novo na hora — e cria o agendamento.

require_once __DIR__ . '/../../includes/session.php';
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/guard.php';
exigirSessao(['barbeiro'], json: true);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/FinanceiroService.php';
require_once __DIR__ . '/../../includes/HorarioService.php';
require_once __DIR__ . '/../../includes/FidelidadeService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'erro' => 'Método não permitido.']);
    exit;
}

$idBarbeiro   = (int) $_SESSION['id'];
$idHorario    = (int) ($_POST['idHorario'] ?? 0);
$idServico    = (int) ($_POST['idServico'] ?? 0);
$idClienteRaw = trim($_POST['idCliente'] ?? '');
$novoNome     = trim($_POST['novoNome'] ?? '');
$novoTelefone = trim($_POST['novoTelefone'] ?? '');
$novoEmail    = trim($_POST['novoEmail'] ?? '');
// A opção "Incluir barba" foi removida do modal de agendamento — barba
// agora é representada só pela escolha do Serviço (ex.: "Barba" ou
// "Corte + Barba"). A coluna Agendamentos.IncluirBarba continua existindo
// no banco apenas por compatibilidade com agendamentos antigos já
// concluídos antes dessa mudança (ver det-barba em agendar.php); novos
// agendamentos sempre gravam 0 aqui, sem depender mais de nenhum campo do
// formulário.
$incluirBarba = 0;
$observacao   = trim($_POST['observacao'] ?? '');

// Agendamento de Fidelidade: '1'..'4' = número de SEMANAS entre ocorrências
// (nunca dias corridos — ver includes/FidelidadeService.php), '' = sem fidelidade.
$fidelidadeRaw = trim($_POST['fidelidade'] ?? '');
$fidelidade    = FidelidadeService::codigoValido($fidelidadeRaw) ? $fidelidadeRaw : null;

// Agendamento duplicado (dois horários consecutivos para um só atendimento,
// ex.: pai + filho — cada um pode fazer um corte diferente, com valores
// diferentes). "Duplicar horário" e "Agendamento de Fidelidade" não se
// combinam nesta primeira versão — se duplicar estiver ligado, a fidelidade
// é ignorada (o formulário já esconde uma opção quando a outra está ativa).
//
// "Selecione os Cortes": idServico (acima) é o corte do 1º horário, sempre
// obrigatório. idServicoSecundario é o corte do 2º horário, OPCIONAL — se
// não for escolhido, o 2º horário continua sendo reservado, só que sem
// cobrança própria (usa o mesmo serviço do 1º horário só para exibição,
// com Valor 0.00).
$duplicar               = !empty($_POST['duplicar']);
$idHorarioSecundario     = (int) ($_POST['idHorarioSecundario'] ?? 0);
$idServicoSecundarioRaw  = (int) ($_POST['idServicoSecundario'] ?? 0);

if ($duplicar) {
    $fidelidade = null;
}

if ($idHorario <= 0 || $idServico <= 0) {
    echo json_encode(['ok' => false, 'erro' => 'Selecione o horário e o serviço.']);
    exit;
}

if ($idClienteRaw === '' && ($novoNome === '' || $novoTelefone === '')) {
    echo json_encode(['ok' => false, 'erro' => 'Busque um cliente existente ou informe nome e telefone para cadastrar um novo.']);
    exit;
}

if ($novoEmail !== '' && !filter_var($novoEmail, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'erro' => 'Informe um e-mail válido ou deixe o campo em branco.']);
    exit;
}

if ($duplicar && ($idHorarioSecundario <= 0 || $idHorarioSecundario === $idHorario)) {
    echo json_encode(['ok' => false, 'erro' => 'Selecione o segundo horário para o agendamento duplicado.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Confirma que o(s) horário(s) são deste barbeiro e trava a(s) linha(s)
    // para evitar que dois cliques simultâneos agendem o mesmo horário.
    // Quando duplicado, trava as duas linhas numa única consulta, sempre em
    // ordem crescente de idHorario, para nunca gerar deadlock entre dois
    // agendamentos duplicados concorrentes que envolvam os mesmos horários.
    $idsParaTravar = $duplicar ? [$idHorario, $idHorarioSecundario] : [$idHorario];
    sort($idsParaTravar);
    $marcadores = implode(',', array_fill(0, count($idsParaTravar), '?'));
    $stmtHorario = $pdo->prepare(
        "SELECT idHorario, data, hora FROM Horario WHERE idHorario IN ($marcadores) AND id_barbeiro = ? ORDER BY idHorario FOR UPDATE"
    );
    $stmtHorario->execute([...$idsParaTravar, $idBarbeiro]);
    $horariosTravados = [];
    foreach ($stmtHorario->fetchAll() as $linha) {
        $horariosTravados[(int) $linha['idHorario']] = $linha;
    }

    $horario = $horariosTravados[$idHorario] ?? null;
    if (!$horario) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'erro' => 'Horário não encontrado.']);
        exit;
    }

    // ---------- Dia bloqueado: validado no backend, não só no frontend ----------
    // Bloquear um dia (ver DiaBloqueado / dia_bloqueio_status.php) não apaga
    // nada, só impede novos agendamentos. O horário secundário (duplicar)
    // é sempre no mesmo dia do principal, então uma única checagem cobre os dois.
    if (HorarioService::diaBloqueado($pdo, $idBarbeiro, $horario['data'])) {
        $pdo->rollBack();
        echo json_encode([
            'ok'     => false,
            'codigo' => 'dia_bloqueado',
            'erro'   => 'Este dia está bloqueado pelo barbeiro. Desbloqueie o dia para criar novos agendamentos.',
        ]);
        exit;
    }

    $horarioSecundario = null;
    if ($duplicar) {
        $horarioSecundario = $horariosTravados[$idHorarioSecundario] ?? null;
        if (!$horarioSecundario) {
            $pdo->rollBack();
            echo json_encode(['ok' => false, 'erro' => 'Segundo horário não encontrado.']);
            exit;
        }
        if ($horarioSecundario['data'] !== $horario['data']) {
            $pdo->rollBack();
            echo json_encode(['ok' => false, 'erro' => 'Os dois horários do agendamento duplicado precisam ser no mesmo dia.']);
            exit;
        }

        // Só permite duplicar para o slot IMEDIATAMENTE seguinte (exatamente
        // 40min depois, o passo padrão da grade — ver includes/HorarioService.php),
        // pra nunca "pular" o intervalo de almoço nem horários distantes.
        $minutosPrincipal   = (int) substr($horario['hora'], 0, 2) * 60 + (int) substr($horario['hora'], 3, 2);
        $minutosSecundario  = (int) substr($horarioSecundario['hora'], 0, 2) * 60 + (int) substr($horarioSecundario['hora'], 3, 2);
        if ($minutosSecundario - $minutosPrincipal !== 40) {
            $pdo->rollBack();
            echo json_encode(['ok' => false, 'erro' => 'O segundo horário precisa ser o próximo horário consecutivo da grade.']);
            exit;
        }
    }

    $stmtOcupado = $pdo->prepare(
        "SELECT idHorario FROM Agendamentos WHERE idHorario IN ($marcadores) AND Status IN ('agendado', 'confirmado')"
    );
    $stmtOcupado->execute($idsParaTravar);
    if ($stmtOcupado->fetch()) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'erro' => 'Esse horário acabou de ser ocupado. Escolha outro.']);
        exit;
    }

    $stmtServico = $pdo->prepare('SELECT nome, valor FROM Servico WHERE idServico = :s AND ativo = 1');
    $stmtServico->execute(['s' => $idServico]);
    $servico = $stmtServico->fetch();

    if (!$servico) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'erro' => 'Serviço inválido.']);
        exit;
    }

    // ---------- "Selecione os Cortes": corte do 2º horário (opcional) ----------
    // Só é buscado quando o barbeiro realmente escolheu um serviço diferente
    // para o segundo horário. Se não escolheu, o 2º horário é reservado
    // mesmo assim, só que sem cobrança própria (ver cálculo de valores abaixo).
    $servicoSecundario = null;
    if ($duplicar && $idServicoSecundarioRaw > 0) {
        $stmtServicoSecundario = $pdo->prepare('SELECT nome, valor FROM Servico WHERE idServico = :s AND ativo = 1');
        $stmtServicoSecundario->execute(['s' => $idServicoSecundarioRaw]);
        $servicoSecundario = $stmtServicoSecundario->fetch();

        if (!$servicoSecundario) {
            $pdo->rollBack();
            echo json_encode(['ok' => false, 'erro' => 'Segundo serviço inválido.']);
            exit;
        }
    }

    // ---------- Cliente: usa o existente ou cadastra um novo ----------
    if ($idClienteRaw !== '') {
        $idCliente = (int) $idClienteRaw;
        $stmtCliente = $pdo->prepare('SELECT idCliente FROM Cliente WHERE idCliente = :id AND ativo = 1');
        $stmtCliente->execute(['id' => $idCliente]);
        if (!$stmtCliente->fetch()) {
            $pdo->rollBack();
            echo json_encode(['ok' => false, 'erro' => 'Cliente selecionado não encontrado.']);
            exit;
        }
    } else {
        $stmtNovoCliente = $pdo->prepare(
            'INSERT INTO Cliente (nome, telefone, email, ativo) VALUES (:nome, :telefone, :email, 1)'
        );
        $stmtNovoCliente->execute([
            'nome'     => $novoNome,
            'telefone' => $novoTelefone,
            'email'    => $novoEmail !== '' ? $novoEmail : null,
        ]);
        $idCliente = (int) $pdo->lastInsertId();
    }

    // ---------- Regra: 1 agendamento ativo por cliente por dia ----------
    if (FinanceiroService::clienteJaTemAgendamentoNoDia($pdo, $idCliente, $horario['data'])) {
        $pdo->rollBack();
        echo json_encode([
            'ok'     => false,
            'codigo' => 'agendamento_duplicado',
            'erro'   => 'Este cliente já possui um agendamento hoje.',
        ]);
        exit;
    }

    $stmtAgenda = $pdo->prepare(
        'INSERT INTO Agendamentos (idCliente, idServico, idHorario, Data, Valor, IncluirBarba, Observacao, Status, fidelidade, grupo_recorrencia, gerado_automaticamente, grupo_agendamento, eh_principal_agendamento, cobranca_duplicado)
         VALUES (:idCliente, :idServico, :idHorario, :data, :valor, :incluirBarba, :observacao, :statusAgendado, :fidelidade, :grupoRecorrencia, 0, :grupoAgendamento, :ehPrincipal, :cobrancaDuplicado)'
    );

    // Agendamento de Fidelidade: todas as ocorrências da sequência (essa
    // primeira incluída) compartilham o mesmo grupo_recorrencia, o que
    // permite no futuro cancelar/alterar uma ocorrência isolada ou a
    // sequência inteira sem precisar de nenhuma mudança de schema.
    $grupoRecorrencia = $fidelidade !== null ? FidelidadeService::novoGrupoRecorrencia() : null;

    // Agendamento duplicado: as duas linhas (principal + secundária)
    // compartilham "grupo_agendamento". A PRINCIPAL carrega o valor total
    // cobrado (corte do 1º horário + corte do 2º horário, quando houver) e
    // é a única que gera lançamento financeiro; a SECUNDÁRIA nasce com
    // Valor 0.00 — nunca é cobrada de novo, evitando duplicidade no
    // financeiro, mesmo tendo um serviço próprio (ex.: pai faz barba, filho
    // faz corte — cada um com seu serviço, mas uma única cobrança).
    $grupoAgendamento = $duplicar ? bin2hex(random_bytes(16)) : null;
    $valorServico = (float) $servico['valor'];
    $valorPrincipal = $duplicar && $servicoSecundario
        ? round($valorServico + (float) $servicoSecundario['valor'], 2)
        : $valorServico;

    // "cobranca_duplicado" passa a ser só um resumo informativo: quantos
    // cortes distintos foram cobrados nesse atendimento duplicado.
    $cobranca = $duplicar ? ($servicoSecundario ? 'dois' : 'um') : null;

    $stmtAgenda->execute([
        'idCliente'          => $idCliente,
        'idServico'          => $idServico,
        'idHorario'          => $idHorario,
        'data'               => $horario['data'],
        'valor'              => $valorPrincipal,
        'incluirBarba'       => $incluirBarba,
        'observacao'         => $observacao !== '' ? $observacao : null,
        'statusAgendado'     => 'agendado',
        'fidelidade'         => $fidelidade,
        'grupoRecorrencia'   => $grupoRecorrencia,
        'grupoAgendamento'   => $grupoAgendamento,
        'ehPrincipal'        => 1,
        'cobrancaDuplicado'  => $cobranca,
    ]);

    $stmtHorarioIndisponivel = $pdo->prepare('UPDATE Horario SET disponivel = 0 WHERE idHorario = :h');
    $stmtHorarioIndisponivel->execute(['h' => $idHorario]);

    // ---------- Agendamento duplicado: cria a linha secundária no segundo horário ----------
    if ($duplicar) {
        $stmtAgenda->execute([
            'idCliente'          => $idCliente,
            // Se o barbeiro escolheu um corte específico pro 2º horário, usa
            // ele (ex.: "Barba"); senão, só reaproveita o corte do 1º
            // horário para exibição — o horário fica reservado mesmo assim,
            // mas sem cobrança própria (Valor 0.00 abaixo).
            'idServico'          => $servicoSecundario ? $idServicoSecundarioRaw : $idServico,
            'idHorario'          => $idHorarioSecundario,
            'data'               => $horarioSecundario['data'],
            'valor'              => 0.00,
            'incluirBarba'       => $incluirBarba,
            'observacao'         => $observacao !== '' ? $observacao : null,
            'statusAgendado'     => 'agendado',
            'fidelidade'         => null,
            'grupoRecorrencia'   => null,
            'grupoAgendamento'   => $grupoAgendamento,
            'ehPrincipal'        => 0,
            'cobrancaDuplicado'  => null,
        ]);

        $stmtHorarioIndisponivel->execute(['h' => $idHorarioSecundario]);
    }

    // ---------- Agendamento de Fidelidade: gera as ocorrências futuras ----------
    // Só a partir daqui até o fim do bloco: mesmo horário (hora) do primeiro
    // agendamento, a cada N dias, limitado a 1 ano — nunca indefinidamente.
    $totalCriadas = 0;
    $ocorrenciasPuladas = [];

    if ($fidelidade !== null) {
        $datasFuturas = FidelidadeService::gerarDatasFuturas($horario['data'], $fidelidade);

        // Prepara as queries uma única vez fora do loop (evita recompilar o
        // SQL a cada ocorrência — só os parâmetros mudam a cada iteração).
        $stmtConflito = $pdo->prepare(
            "SELECT idAgendamento FROM Agendamentos WHERE idHorario = :h AND Status IN ('agendado', 'confirmado') LIMIT 1"
        );
        $stmtInsereFutura = $pdo->prepare(
            'INSERT INTO Agendamentos (idCliente, idServico, idHorario, Data, Valor, IncluirBarba, Observacao, Status, fidelidade, grupo_recorrencia, gerado_automaticamente)
             VALUES (:idCliente, :idServico, :idHorario, :data, :valor, :incluirBarba, :observacao, :statusAgendado, :fidelidade, :grupoRecorrencia, 1)'
        );
        $stmtOcupaHorarioFuturo = $pdo->prepare('UPDATE Horario SET disponivel = 0 WHERE idHorario = :h');

        foreach ($datasFuturas as $dataFutura) {
            // Dia bloqueado pelo barbeiro (ver DiaBloqueado): pula essa
            // ocorrência da fidelidade, sem interromper as demais.
            if (HorarioService::diaBloqueado($pdo, $idBarbeiro, $dataFutura)) {
                $ocorrenciasPuladas[] = ['data' => $dataFutura, 'motivo' => 'Dia bloqueado pelo barbeiro'];
                continue;
            }

            // Garante (criando se preciso) a linha de Horario dessa data
            // futura, no mesmo horário do primeiro agendamento. Retorna
            // null se cair num domingo que o barbeiro não atende (ver
            // Configurações > Trabalhar aos domingos) — nesse caso não há
            // horário possível, então a ocorrência é pulada.
            $idHorarioFuturo = HorarioService::obterOuCriarHorario($pdo, $idBarbeiro, $dataFutura, $horario['hora']);

            if ($idHorarioFuturo === null) {
                $ocorrenciasPuladas[] = ['data' => $dataFutura, 'motivo' => 'Barbeiro não atende aos domingos'];
                continue;
            }

            $stmtConflito->execute(['h' => $idHorarioFuturo]);
            if ($stmtConflito->fetch()) {
                $ocorrenciasPuladas[] = ['data' => $dataFutura, 'motivo' => 'Horário já ocupado'];
                continue;
            }

            if (FinanceiroService::clienteJaTemAgendamentoNoDia($pdo, $idCliente, $dataFutura)) {
                $ocorrenciasPuladas[] = ['data' => $dataFutura, 'motivo' => 'Cliente já tem outro agendamento nesse dia'];
                continue;
            }

            $stmtInsereFutura->execute([
                'idCliente'        => $idCliente,
                'idServico'        => $idServico,
                'idHorario'        => $idHorarioFuturo,
                'data'             => $dataFutura,
                'valor'            => $servico['valor'],
                'incluirBarba'     => $incluirBarba,
                'observacao'       => $observacao !== '' ? $observacao : null,
                'statusAgendado'   => 'agendado',
                'fidelidade'       => $fidelidade,
                'grupoRecorrencia' => $grupoRecorrencia,
            ]);

            $stmtOcupaHorarioFuturo->execute(['h' => $idHorarioFuturo]);
            $totalCriadas++;
        }
    }

    $pdo->commit();

    $stmtNomeCliente = $pdo->prepare('SELECT nome, telefone FROM Cliente WHERE idCliente = :id');
    $stmtNomeCliente->execute(['id' => $idCliente]);
    $clienteAgendado = $stmtNomeCliente->fetch();

    $camposLog = [
        ['name' => '👤 Cliente', 'value' => ($clienteAgendado['nome'] ?? '—') . ' — ' . ($clienteAgendado['telefone'] ?? '—'), 'inline' => false],
        ['name' => '✂️ Serviço', 'value' => $servico['nome'], 'inline' => true],
        ['name' => '📅 Data', 'value' => $horario['data'], 'inline' => true],
        ['name' => '📝 Observação', 'value' => $observacao ?: '—', 'inline' => false],
    ];

    if ($fidelidade !== null) {
        $camposLog[] = ['name' => '🔁 Fidelidade', 'value' => FidelidadeService::rotulo($fidelidade) . " ({$totalCriadas} ocorrência(s) futura(s) criada(s), " . count($ocorrenciasPuladas) . ' pulada(s))', 'inline' => false];
    }

    if ($duplicar) {
        $resumoCortes = $servico['nome'] . ' (R$ ' . number_format($valorServico, 2, ',', '.') . ')';
        if ($servicoSecundario) {
            $resumoCortes .= ' + ' . $servicoSecundario['nome'] . ' (R$ ' . number_format((float) $servicoSecundario['valor'], 2, ',', '.') . ')';
        }
        $camposLog[] = [
            'name'   => '🟣 Duplicado',
            'value'  => substr($horario['hora'], 0, 5) . ' + ' . substr($horarioSecundario['hora'], 0, 5)
                . ' — ' . $resumoCortes . ' = R$ ' . number_format($valorPrincipal, 2, ',', '.'),
            'inline' => false,
        ];
    }

    DiscordLogger::agendamentos('🆕 Agendamento criado', $camposLog);

    $resposta = ['ok' => true];

    if ($fidelidade !== null) {
        $resposta['fidelidade'] = [
            'codigo'   => $fidelidade,
            'rotulo'   => FidelidadeService::rotulo($fidelidade),
            'criadas'  => $totalCriadas,
            'puladas'  => $ocorrenciasPuladas,
        ];
    }

    if ($duplicar) {
        $resposta['duplicado'] = [
            'grupoAgendamento'  => $grupoAgendamento,
            'cobranca'          => $cobranca,
            'horaPrincipal'     => substr($horario['hora'], 0, 5),
            'horaSecundaria'    => substr($horarioSecundario['hora'], 0, 5),
            'servicoPrincipal'  => $servico['nome'],
            'servicoSecundario' => $servicoSecundario ? $servicoSecundario['nome'] : null,
            'valorTotal'        => $valorPrincipal,
        ];
    }

    echo json_encode($resposta);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    DiscordLogger::erro('💥 Falha ao salvar agendamento', $e);
    http_response_code(500);
    echo json_encode(['ok' => false, 'erro' => 'Erro ao salvar o agendamento.']);
}
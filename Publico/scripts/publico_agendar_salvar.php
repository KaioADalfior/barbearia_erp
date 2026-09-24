<?php
// Publico/scripts/publico_agendar_salvar.php
// Endpoint AJAX (POST) chamado pela etapa final de Publico/paginas/agendar.php.
// Sem login — recebe o token do link público, o horário/serviço escolhidos
// e os dados de contato do cliente, casa ou cadastra o Cliente pelo
// telefone, e cria o Agendamento. Reaproveita as mesmas regras já
// validadas no fluxo interno (Agendamentos/scripts/agendamento_salvar.php):
// trava do horário, dia bloqueado, horário já ocupado, e "1 agendamento
// ativo por cliente por dia" — só que sem as opções internas de
// duplicado/fidelidade, que não fazem sentido nesta tela simplificada.

require_once __DIR__ . '/../../includes/session.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'erro' => 'Método não permitido.']);
    exit;
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/PublicoTokenService.php';
require_once __DIR__ . '/../../includes/HorarioService.php';
require_once __DIR__ . '/../../includes/FinanceiroService.php';
require_once __DIR__ . '/../../includes/AgendamentoPublicoThrottle.php';

csrf_verificar(json: true);

$ip = $_SERVER['REMOTE_ADDR'] ?? 'desconhecido';

if (AgendamentoPublicoThrottle::bloqueado($pdo, $ip)) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'erro' => 'Muitas tentativas. Aguarde um pouco e tente novamente.']);
    exit;
}

// ---------- Campo-armadilha (honeypot) contra bots ----------
// Campo escondido via CSS que uma pessoa real nunca vê nem preenche; um
// bot que preenche todo formulário automaticamente cai aqui. Responde
// "sucesso" falso (sem criar nada) pra não ensinar o bot a se adaptar —
// só a tentativa é registrada no throttle, como qualquer outra.
if (trim($_POST['site'] ?? '') !== '') {
    AgendamentoPublicoThrottle::registrarTentativa($pdo, $ip);
    echo json_encode(['ok' => true]);
    exit;
}

$token        = trim($_POST['t'] ?? '');
$idHorario    = (int) ($_POST['idHorario'] ?? 0);
$idServico    = (int) ($_POST['idServico'] ?? 0);
$nome         = trim($_POST['nome'] ?? '');
$telefone     = trim($_POST['telefone'] ?? '');
$email        = trim($_POST['email'] ?? '');
$observacao   = trim($_POST['observacao'] ?? '');

$barbeiro = PublicoTokenService::resolverBarbeiro($pdo, $token);
if ($barbeiro === null) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'erro' => 'Link inválido ou desativado.']);
    exit;
}
$idBarbeiro = $barbeiro['id_barbeiro'];

AgendamentoPublicoThrottle::registrarTentativa($pdo, $ip);

if ($idHorario <= 0 || $idServico <= 0) {
    echo json_encode(['ok' => false, 'erro' => 'Selecione o horário e o serviço.']);
    exit;
}

if ($nome === '' || mb_strlen($nome) < 3) {
    echo json_encode(['ok' => false, 'erro' => 'Informe seu nome completo.']);
    exit;
}

$telefoneDigitos = preg_replace('/\D+/', '', $telefone);
if ($telefoneDigitos === null || strlen($telefoneDigitos) < 10) {
    echo json_encode(['ok' => false, 'erro' => 'Informe um telefone/WhatsApp válido, com DDD.']);
    exit;
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'erro' => 'Informe um e-mail válido ou deixe o campo em branco.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Trava a linha do horário para evitar que dois cliques simultâneos
    // (ou o painel interno do barbeiro, ao mesmo tempo) agendem o mesmo slot.
    $stmtHorario = $pdo->prepare(
        'SELECT idHorario, data, hora FROM Horario WHERE idHorario = :h AND id_barbeiro = :b FOR UPDATE'
    );
    $stmtHorario->execute(['h' => $idHorario, 'b' => $idBarbeiro]);
    $horario = $stmtHorario->fetch();

    if (!$horario) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'erro' => 'Horário não encontrado.']);
        exit;
    }

    if ($horario['data'] < (new DateTimeImmutable('today'))->format('Y-m-d')) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'erro' => 'Esse horário já passou. Escolha outro.']);
        exit;
    }

    if (HorarioService::diaBloqueado($pdo, $idBarbeiro, $horario['data'])) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'erro' => 'Esse dia não está mais disponível. Escolha outro.']);
        exit;
    }

    $stmtOcupado = $pdo->prepare(
        "SELECT idHorario FROM Agendamentos WHERE idHorario = :h AND Status IN ('agendado', 'confirmado')"
    );
    $stmtOcupado->execute(['h' => $idHorario]);
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

    // ---------- Cliente: casa pelo telefone, ou cadastra um novo ----------
    // Compara só pelos dígitos do telefone (ignora formatação), pra achar o
    // mesmo cliente independente de como o número foi digitado da vez
    // passada. Se achar mais de um cadastro ativo com o mesmo telefone
    // (dado antigo duplicado), usa o mais recente.
    $stmtClienteExistente = $pdo->prepare(
        "SELECT idCliente FROM Cliente
         WHERE ativo = 1 AND REPLACE(REPLACE(REPLACE(REPLACE(telefone, ' ', ''), '-', ''), '(', ''), ')', '') LIKE :telefone
         ORDER BY idCliente DESC LIMIT 1"
    );
    $stmtClienteExistente->execute(['telefone' => '%' . $telefoneDigitos]);
    $clienteExistente = $stmtClienteExistente->fetch();

    if ($clienteExistente) {
        $idCliente = (int) $clienteExistente['idCliente'];
    } else {
        $stmtNovoCliente = $pdo->prepare(
            'INSERT INTO Cliente (nome, telefone, email, ativo) VALUES (:nome, :telefone, :email, 1)'
        );
        $stmtNovoCliente->execute([
            'nome'     => $nome,
            'telefone' => $telefone,
            'email'    => $email !== '' ? $email : null,
        ]);
        $idCliente = (int) $pdo->lastInsertId();
    }

    if (FinanceiroService::clienteJaTemAgendamentoNoDia($pdo, $idCliente, $horario['data'])) {
        $pdo->rollBack();
        echo json_encode([
            'ok'     => false,
            'codigo' => 'agendamento_duplicado',
            'erro'   => 'Você já tem um agendamento nesse dia.',
        ]);
        exit;
    }

    // origem_publica = 1 marca que este agendamento nasceu aqui (link
    // público), não no painel interno — só usado pra pintar a grade de
    // horários numa cor diferente pro barbeiro (ver horarios_buscar.php e
    // Agendamentos/paginas/agendar.php > status-dot-publico), sem afetar
    // nenhuma regra de negócio.
    $stmtAgenda = $pdo->prepare(
        'INSERT INTO Agendamentos (idCliente, idServico, idHorario, Data, Valor, IncluirBarba, Observacao, Status, fidelidade, grupo_recorrencia, gerado_automaticamente, origem_publica, grupo_agendamento, eh_principal_agendamento, cobranca_duplicado)
         VALUES (:idCliente, :idServico, :idHorario, :data, :valor, 0, :observacao, :statusAgendado, NULL, NULL, 0, 1, NULL, 1, NULL)'
    );
    $stmtAgenda->execute([
        'idCliente'      => $idCliente,
        'idServico'      => $idServico,
        'idHorario'      => $idHorario,
        'data'           => $horario['data'],
        'valor'          => (float) $servico['valor'],
        'observacao'     => $observacao !== '' ? $observacao : null,
        'statusAgendado' => 'agendado',
    ]);

    $pdo->prepare('UPDATE Horario SET disponivel = 0 WHERE idHorario = :h')->execute(['h' => $idHorario]);

    $pdo->commit();

    DiscordLogger::agendamentos('🌐 Agendamento criado pelo link público', [
        ['name' => '👤 Cliente',  'value' => $nome . ' — ' . $telefone, 'inline' => false],
        ['name' => '✂️ Serviço',  'value' => $servico['nome'], 'inline' => true],
        ['name' => '📅 Data',     'value' => $horario['data'] . ' às ' . substr($horario['hora'], 0, 5), 'inline' => true],
        ['name' => '📝 Observação', 'value' => $observacao ?: '—', 'inline' => false],
    ]);

    echo json_encode([
        'ok' => true,
        'confirmacao' => [
            'servico' => $servico['nome'],
            'data'    => $horario['data'],
            'hora'    => substr($horario['hora'], 0, 5),
        ],
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    DiscordLogger::erro('💥 Falha ao salvar agendamento público', $e);
    http_response_code(500);
    echo json_encode(['ok' => false, 'erro' => 'Erro ao salvar o agendamento.']);
}

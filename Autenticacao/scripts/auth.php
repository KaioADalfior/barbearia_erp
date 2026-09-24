<?php
// auth.php
// Processa o login: verifica primeiro em Administrador, depois em Barbeiro

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/LoginThrottle.php';

// Aceita apenas requisições POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../paginas/login.php');
    exit;
}

// Token CSRF do form de login (proteção contra login CSRF / forçar a
// vítima a logar numa conta escolhida pelo atacante).
csrf_verificar(json: false, redirecionarPara: '../paginas/login.php');

$login = trim($_POST['login'] ?? '');
$senha = trim($_POST['senha'] ?? '');

if ($login === '' || $senha === '') {
    header('Location: ../paginas/login.php?erro=1');
    exit;
}

// ---------- Proteção contra força bruta ----------
// Bloqueia por IP + login (ver includes/LoginThrottle.php) depois de
// MAX_LOGIN_ATTEMPTS tentativas incorretas seguidas.
$statusBloqueio = LoginThrottle::verificarBloqueio($pdo, $login);
if ($statusBloqueio['bloqueado']) {
    DiscordLogger::alerta('🔒 Login bloqueado por excesso de tentativas', [
        ['name' => '🔑 Login tentado', 'value' => $login, 'inline' => true],
        ['name' => '🌐 IP', 'value' => $_SERVER['REMOTE_ADDR'] ?? '—', 'inline' => true],
        ['name' => '⏳ Minutos restantes', 'value' => (string) $statusBloqueio['minutosRestantes'], 'inline' => true],
    ]);
    header('Location: ../paginas/login.php?erro=bloqueado');
    exit;
}

/*
 * Compatibilidade: contas antigas ainda podem estar com a senha em texto
 * puro no banco (dado de teste). Continuamos aceitando esse caso para não
 * travar o acesso de ninguém, mas assim que o login der certo com a senha
 * em texto puro, ela é IMEDIATAMENTE re-salva como hash (password_hash) —
 * ver upgradeSenhaSeNecessario() abaixo. Depois da primeira vez que cada
 * conta loga, ela nunca mais fica em texto puro no banco.
 */
function upgradeSenhaSeNecessario(PDO $pdo, string $tabela, string $colId, $id, string $senhaDigitada, string $senhaArmazenada): void
{
    if (password_verify($senhaDigitada, $senhaArmazenada)) {
        return; // já é hash válido, nada a fazer
    }
    // Chegou até aqui e a senha bateu (ver chamada abaixo) então
    // $senhaArmazenada estava em texto puro — regrava como hash.
    $novoHash = password_hash($senhaDigitada, PASSWORD_DEFAULT);
    $upd = $pdo->prepare("UPDATE {$tabela} SET senha = :senha WHERE {$colId} = :id");
    $upd->execute(['senha' => $novoHash, 'id' => $id]);
}

// 1) Tenta autenticar como Administrador
$stmt = $pdo->prepare('SELECT id_Admin, nome, login, senha FROM Administrador WHERE login = :login LIMIT 1');
$stmt->execute(['login' => $login]);
$admin = $stmt->fetch();

$senhaAdminOk = $admin && (password_verify($senha, $admin['senha']) || hash_equals((string) $admin['senha'], $senha));

if ($senhaAdminOk) {
    upgradeSenhaSeNecessario($pdo, 'Administrador', 'id_Admin', $admin['id_Admin'], $senha, $admin['senha']);
    LoginThrottle::limparFalhas($pdo, $login);

    // Regenera o ID de sessão no login (evita Session Fixation: um ID de
    // sessão obtido/fixado antes do login nunca fica autenticado).
    session_regenerate_id(true);

    $_SESSION['tipo']      = 'admin';
    $_SESSION['id']        = $admin['id_Admin'];
    $_SESSION['nome']      = $admin['nome'];
    $_SESSION['login']     = $admin['login'];
    $_SESSION['ultima_atividade'] = time();

    DiscordLogger::login(true, '✅ Login de Administrador', [
        ['name' => '👤 Nome', 'value' => $admin['nome'], 'inline' => true],
        ['name' => '🔑 Login', 'value' => $admin['login'], 'inline' => true],
    ]);

    header('Location: /painel?login=sucesso');
    exit;
}

// 2) Se não encontrou como Administrador, tenta como Barbeiro
$stmt = $pdo->prepare('SELECT id_barbeiro, nome, login, senha, foto FROM Barbeiro WHERE login = :login LIMIT 1');
$stmt->execute(['login' => $login]);
$barbeiro = $stmt->fetch();

$senhaBarbeiroOk = $barbeiro && (password_verify($senha, $barbeiro['senha']) || hash_equals((string) $barbeiro['senha'], $senha));

if ($senhaBarbeiroOk) {
    upgradeSenhaSeNecessario($pdo, 'Barbeiro', 'id_barbeiro', $barbeiro['id_barbeiro'], $senha, $barbeiro['senha']);
    LoginThrottle::limparFalhas($pdo, $login);

    session_regenerate_id(true);

    $_SESSION['tipo']  = 'barbeiro';
    $_SESSION['id']    = $barbeiro['id_barbeiro'];
    $_SESSION['nome']  = $barbeiro['nome'];
    $_SESSION['login'] = $barbeiro['login'];
    $_SESSION['foto']  = $barbeiro['foto'] ?? null;
    $_SESSION['ultima_atividade'] = time();

    DiscordLogger::login(true, '✅ Login de Barbeiro', [
        ['name' => '👤 Nome', 'value' => $barbeiro['nome'], 'inline' => true],
        ['name' => '🔑 Login', 'value' => $barbeiro['login'], 'inline' => true],
    ]);

    header('Location: /inicio?login=sucesso');
    exit;
}

// 3) Não encontrado em nenhuma das duas tabelas (ou senha errada)
LoginThrottle::registrarFalha($pdo, $login);

DiscordLogger::login(false, '❌ Tentativa de login falhou', [
    ['name' => '🔑 Login tentado', 'value' => $login, 'inline' => true],
    ['name' => '🌐 IP', 'value' => $_SERVER['REMOTE_ADDR'] ?? '—', 'inline' => true],
]);

header('Location: ../paginas/login.php?erro=1');
exit;
<?php
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/guard.php';
exigirSessao(['admin']);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/security.php';
require_once __DIR__ . '/../../includes/csrf.php';

$paginaAtual = 'login-tentativas';

// ---------- Busca as combinações IP+login atualmente bloqueadas ----------
// Mesma regra usada de verdade pelo LoginThrottle: só entra aqui quem tem
// MAX_LOGIN_ATTEMPTS (ou mais) tentativas erradas dentro da janela de
// LOGIN_LOCKOUT_MINUTES. Se a janela já passou, some da lista sozinho.
$stmt = $pdo->prepare(
    'SELECT chave, COUNT(*) AS tentativas, MAX(criado_em) AS ultima_tentativa
     FROM LoginTentativas
     WHERE sucesso = 0
       AND criado_em >= DATE_SUB(NOW(), INTERVAL :janela MINUTE)
     GROUP BY chave
     HAVING tentativas >= :maxTentativas
     ORDER BY ultima_tentativa DESC'
);
$stmt->execute([
    'janela'        => LOGIN_LOCKOUT_MINUTES,
    'maxTentativas' => MAX_LOGIN_ATTEMPTS,
]);
$bloqueios = $stmt->fetchAll();

// separa "ip:login" pro final (login nunca tem ":", IP pode ter no IPv6)
foreach ($bloqueios as &$b) {
    $pos = strrpos($b['chave'], ':');
    $b['ip']    = $pos !== false ? substr($b['chave'], 0, $pos) : $b['chave'];
    $b['login'] = $pos !== false ? substr($b['chave'], $pos + 1) : '—';

    $liberaEm = (new DateTimeImmutable($b['ultima_tentativa']))->modify('+' . LOGIN_LOCKOUT_MINUTES . ' minutes');
    $b['minutos_restantes'] = max(1, (int) ceil((strtotime($liberaEm->format('Y-m-d H:i:s')) - time()) / 60));
}
unset($b);

$statusGet = $_GET['status'] ?? '';
$mensagens = [
    'desbloqueado-sucesso' => ['ok', 'Login desbloqueado com sucesso.'],
    'desbloqueado-todos'   => ['ok', 'Todos os bloqueios foram removidos.'],
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<?php include __DIR__ . '/../../includes/theme-init.php'; ?>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tentativas de Login — Sistema de Gestão</title>

<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../../assets/css/admin-theme.css">

<style>
    table tbody tr{ border-top:1px solid rgba(255,255,255,0.05); }
    .badge{
        display:inline-flex; align-items:center; gap:0.35rem;
        font-size:11px; font-weight:600; letter-spacing:0.04em;
        padding:0.28rem 0.65rem; border-radius:999px;
        color:#c9a8ab; background:rgba(140,31,40,0.12); border:1px solid rgba(140,31,40,0.4);
    }
</style>
</head>
<body class="flex">

<?php include __DIR__ . '/../../includes/sidebar.php'; ?>
<?php include __DIR__ . '/../../includes/toast.php'; ?>

<main class="flex-1 min-w-0">

    <header class="topbar px-5 sm:px-8 py-5 sm:py-6 flex items-center gap-4">
        <button type="button" onclick="abrirMenuMobile()" class="menu-toggle-btn lg:hidden" aria-label="Abrir menu">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M4 6h16M4 12h16M4 18h16" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
            </svg>
        </button>
        <div class="min-w-0 flex-1">
            <p class="eyebrow uppercase mb-1" style="color:var(--gold-light); opacity:.75">Configurações</p>
            <h1 class="display text-3xl sm:text-4xl text-[color:var(--cream)] truncate">Logins Bloqueados</h1>
        </div>
        <?php if (!empty($bloqueios)): ?>
        <form action="../scripts/login_desbloquear.php" method="POST" autocomplete="off" onsubmit="return confirm('Desbloquear TODOS os logins da lista?')">
            <?= csrf_field() ?>
            <input type="hidden" name="acao" value="desbloquear-todos">
            <button type="submit" class="btn-secondary h-11 px-5 rounded-xl text-sm shrink-0">
                Desbloquear todos
            </button>
        </form>
        <?php endif; ?>
    </header>

    <section class="p-5 sm:p-8">

        <?php if (isset($mensagens[$statusGet])): [$tipoMsg, $textoMsg] = $mensagens[$statusGet]; ?>
            <script>toast(<?= json_encode($textoMsg) ?>, <?= json_encode($tipoMsg === 'ok' ? 'sucesso' : 'erro') ?>);</script>
        <?php endif; ?>

        <p class="text-sm text-zinc-400 mb-6 max-w-2xl">
            Depois de <?= MAX_LOGIN_ATTEMPTS ?> tentativas de login erradas seguidas (mesmo IP + mesmo login),
            o sistema bloqueia novas tentativas por <?= LOGIN_LOCKOUT_MINUTES ?> minutos — é a proteção contra
            força bruta. Se alguém precisar entrar antes desse tempo passar, desbloqueie aqui.
        </p>

        <?php if (empty($bloqueios)): ?>

            <div class="panel-card rounded-2xl p-10 text-center">
                <p class="text-sm text-zinc-400">Nenhum login bloqueado no momento.</p>
            </div>

        <?php else: ?>

            <div class="panel-card rounded-2xl overflow-hidden">
                <div class="barber-stripe-thin"></div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left">
                                <th class="px-5 py-4 text-xs uppercase tracking-wide text-zinc-500 font-medium">Login tentado</th>
                                <th class="px-5 py-4 text-xs uppercase tracking-wide text-zinc-500 font-medium">IP</th>
                                <th class="px-5 py-4 text-xs uppercase tracking-wide text-zinc-500 font-medium">Tentativas</th>
                                <th class="px-5 py-4 text-xs uppercase tracking-wide text-zinc-500 font-medium">Libera em</th>
                                <th class="px-5 py-4 text-xs uppercase tracking-wide text-zinc-500 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bloqueios as $b): ?>
                            <tr>
                                <td class="px-5 py-3 text-[color:var(--cream)]"><?= htmlspecialchars($b['login']) ?></td>
                                <td class="px-5 py-3 text-zinc-400 font-mono text-xs"><?= htmlspecialchars($b['ip']) ?></td>
                                <td class="px-5 py-3"><span class="badge"><?= (int) $b['tentativas'] ?> erradas</span></td>
                                <td class="px-5 py-3 text-zinc-400">~<?= (int) $b['minutos_restantes'] ?> min</td>
                                <td class="px-5 py-3 text-right">
                                    <form action="../scripts/login_desbloquear.php" method="POST" autocomplete="off">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="acao" value="desbloquear-um">
                                        <input type="hidden" name="chave" value="<?= htmlspecialchars($b['chave']) ?>">
                                        <button type="submit" class="btn-primary h-9 px-4 rounded-lg text-xs">
                                            Desbloquear
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="barber-stripe-thin"></div>
            </div>

        <?php endif; ?>

    </section>

</main>

</body>
</html>

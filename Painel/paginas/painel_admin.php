<?php
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/guard.php';
exigirSessao(['admin']);
$paginaAtual = 'dashboard';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<?php include __DIR__ . '/../../includes/theme-init.php'; ?>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>BarbERP — Painel</title>

<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../../assets/css/admin-theme.css?v=2">
</head>
<body class="flex">

<?php include __DIR__ . '/../../includes/sidebar.php'; ?>
<?php include __DIR__ . '/../../includes/toast.php'; ?>

<main class="flex-1 min-w-0">

    <header class="topbar px-8 py-6">
        <p class="eyebrow uppercase mb-1" style="color:var(--gold-light); opacity:.75">Painel Administrativo</p>
        <h1 class="display text-4xl text-[color:var(--cream)]">Bem-vindo, <?= htmlspecialchars($_SESSION['nome'] ?? 'Admin') ?></h1>
    </header>

    <section class="p-8">
        <a href="../../Barbeiros/paginas/barbeiro_cadastrar.php" class="panel-card block rounded-2xl p-6 max-w-sm hover:border-yellow-600/40 transition-colors">
            <div class="w-11 h-11 rounded-xl bg-yellow-500/10 flex items-center justify-center mb-4">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M6.5 5.5a2.5 2.5 0 1 1 3.4 3.4L18 17.5" stroke="#6fa8ea" stroke-width="1.5" stroke-linecap="round"/>
                    <path d="M6.5 18.5a2.5 2.5 0 1 0 3.4-3.4L18 6.5" stroke="#6fa8ea" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
            </div>
            <p class="text-sm font-medium text-[color:var(--cream)] mb-1">Cadastrar Barbeiro</p>
            <p class="text-xs text-zinc-500">Adicionar um novo profissional ao sistema</p>
        </a>
    </section>

</main>

<?php if (($_GET['login'] ?? '') === 'sucesso'): ?>
<script>toast('Login realizado com sucesso!');</script>
<?php endif; ?>

</body>
</html>
<?php
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/guard.php';
exigirSessao(['admin']);
require_once __DIR__ . '/../../includes/csrf.php';
$paginaAtual = 'barbeiro-cadastrar';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<?php include __DIR__ . '/../../includes/theme-init.php'; ?>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cadastrar Barbeiro — BarbERP</title>

<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../../assets/css/admin-theme.css?v=2">
</head>
<body class="flex">

<?php include __DIR__ . '/../../includes/sidebar.php'; ?>

<main class="flex-1 min-w-0">

    <header class="topbar px-5 sm:px-8 py-5 sm:py-6 flex items-center gap-4">
        <button type="button" onclick="abrirMenuMobile()" class="menu-toggle-btn lg:hidden" aria-label="Abrir menu">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M4 6h16M4 12h16M4 18h16" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
            </svg>
        </button>
        <div class="min-w-0">
            <p class="eyebrow uppercase mb-1" style="color:var(--gold-light); opacity:.75">Barbeiro</p>
            <h1 class="display text-3xl sm:text-4xl text-[color:var(--cream)] truncate">Cadastrar Barbeiro</h1>
        </div>
    </header>

    <section class="p-5 sm:p-8">
        <div class="panel-card rounded-2xl overflow-hidden max-w-xl">

            <div class="barber-stripe-thin"></div>

            <div class="p-7">

                <?php if (($_GET['status'] ?? '') === 'sucesso'): ?>
                    <div class="alert-success rounded-xl px-4 py-3 text-sm mb-6">
                        Barbeiro cadastrado com sucesso.
                    </div>
                <?php elseif (($_GET['status'] ?? '') === 'login-existe'): ?>
                    <div class="alert-error rounded-xl px-4 py-3 text-sm mb-6">
                        Já existe um barbeiro com esse login. Escolha outro.
                    </div>
                <?php elseif (($_GET['status'] ?? '') === 'erro'): ?>
                    <div class="alert-error rounded-xl px-4 py-3 text-sm mb-6">
                        Preencha todos os campos corretamente.
                    </div>
                <?php endif; ?>

                <form action="../scripts/barbeiro_salvar.php" method="POST" autocomplete="off">
                    <?= csrf_field() ?>

                    <div class="mb-5">
                        <label class="field-label block mb-2 uppercase" for="nome">Nome</label>
                        <input id="nome" name="nome" type="text" placeholder="Nome completo" required
                               class="field w-full h-12 px-4 rounded-xl text-sm">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-5">
                        <div>
                            <label class="field-label block mb-2 uppercase" for="login">Login</label>
                            <input id="login" name="login" type="text" placeholder="usuario123" required
                                   class="field w-full h-12 px-4 rounded-xl text-sm">
                        </div>
                        <div>
                            <label class="field-label block mb-2 uppercase" for="telefone">Telefone</label>
                            <input id="telefone" name="telefone" type="text" placeholder="(27) 90000-0000" required
                                   class="field w-full h-12 px-4 rounded-xl text-sm">
                        </div>
                    </div>

                    <div class="mb-7">
                        <label class="field-label block mb-2 uppercase" for="senha">Senha</label>
                        <input id="senha" name="senha" type="password" placeholder="Senha de acesso" required minlength="6"
                               class="field w-full h-12 px-4 rounded-xl text-sm">
                    </div>

                    <div class="flex gap-3">
                        <button type="submit" class="btn-primary h-12 px-6 rounded-xl text-sm flex-1">
                            Cadastrar Barbeiro
                        </button>
                        <a href="../../Painel/paginas/painel_admin.php" class="btn-secondary h-12 px-6 rounded-xl text-sm flex items-center justify-center">
                            Cancelar
                        </a>
                    </div>

                </form>

            </div>

            <div class="barber-stripe-thin"></div>

        </div>
    </section>

</main>

</body>
</html>
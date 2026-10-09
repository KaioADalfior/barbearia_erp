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
<link rel="stylesheet" href="/assets/css/admin-theme.css?v=3">
<style>
    .tipo-opcoes{ display:grid; grid-template-columns:1fr; gap:.75rem; }
    @media (min-width:520px){ .tipo-opcoes{ grid-template-columns:1fr 1fr; } }
    .tipo-opcao{ position:relative; display:block; cursor:pointer; }
    .tipo-opcao input{ position:absolute; opacity:0; inset:0; width:100%; height:100%; cursor:pointer; margin:0; }
    .tipo-opcao__card{
        display:flex; gap:.75rem; align-items:flex-start; padding:.9rem 1rem; border-radius:.9rem;
        background:rgba(255,255,255,0.03); border:1px solid var(--line);
        transition:border-color .15s, background .15s;
    }
    .tipo-opcao__dot{
        flex:0 0 auto; width:18px; height:18px; margin-top:2px; border-radius:9999px;
        border:2px solid rgba(255,255,255,0.35); display:flex; align-items:center; justify-content:center;
    }
    .tipo-opcao__dot::after{ content:''; width:8px; height:8px; border-radius:9999px; background:transparent; transition:background .15s; }
    .tipo-opcao input:checked + .tipo-opcao__card{ border-color:rgba(var(--accent-rgb),0.75); background:rgba(var(--accent-rgb),0.12); }
    .tipo-opcao input:checked + .tipo-opcao__card .tipo-opcao__dot{ border-color:var(--accent-strong); }
    .tipo-opcao input:checked + .tipo-opcao__card .tipo-opcao__dot::after{ background:var(--accent-strong); }
    .tipo-opcao input:focus-visible + .tipo-opcao__card{ outline:2px solid var(--accent-strong); outline-offset:2px; }
    .tipo-opcao__titulo{ font-size:14px; font-weight:600; color:var(--cream); }
    .tipo-opcao__desc{ font-size:12px; line-height:1.45; color:var(--text-muted); margin-top:2px; }
</style>
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
            <p class="eyebrow uppercase mb-1" style="color:var(--accent-strong); opacity:.75">Barbeiro</p>
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
                <?php elseif (($_GET['status'] ?? '') === 'tipo'): ?>
                    <div class="alert-error rounded-xl px-4 py-3 text-sm mb-6">
                        Escolha o tipo de acesso: Proprietário ou Funcionário.
                    </div>
                <?php elseif (($_GET['status'] ?? '') === 'erro'): ?>
                    <div class="alert-error rounded-xl px-4 py-3 text-sm mb-6">
                        Preencha todos os campos corretamente.
                    </div>
                <?php endif; ?>

                <form action="/Barbeiros/scripts/barbeiro_salvar.php" method="POST" autocomplete="off">
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

                    <div class="mb-5">
                        <span class="field-label block mb-2 uppercase">Tipo de acesso</span>
                        <div class="tipo-opcoes" role="radiogroup" aria-label="Tipo de acesso">
                            <label class="tipo-opcao">
                                <input type="radio" name="tipo_usuario" value="proprietario" required>
                                <span class="tipo-opcao__card">
                                    <span class="tipo-opcao__dot"></span>
                                    <span>
                                        <span class="tipo-opcao__titulo block">Proprietário</span>
                                        <span class="tipo-opcao__desc block">Acesso total: todos os barbeiros, agendamentos, financeiro e comissões.</span>
                                    </span>
                                </span>
                            </label>
                            <label class="tipo-opcao">
                                <input type="radio" name="tipo_usuario" value="funcionario" required>
                                <span class="tipo-opcao__card">
                                    <span class="tipo-opcao__dot"></span>
                                    <span>
                                        <span class="tipo-opcao__titulo block">Funcionário</span>
                                        <span class="tipo-opcao__desc block">Vê só os próprios atendimentos, comissão e financeiro. Recebe comissão por serviço.</span>
                                    </span>
                                </span>
                            </label>
                        </div>
                    </div>

                    <div class="mb-7">
                        <label class="field-label block mb-2 uppercase" for="senha">Senha</label>
                        <input id="senha" name="senha" type="password" placeholder="Senha de acesso" required minlength="8"
                               class="field w-full h-12 px-4 rounded-xl text-sm">
                    </div>

                    <div class="flex gap-3">
                        <button type="submit" class="btn-primary h-12 px-6 rounded-xl text-sm flex-1">
                            Cadastrar Barbeiro
                        </button>
                        <a href="/painel" class="btn-secondary h-12 px-6 rounded-xl text-sm flex items-center justify-center">
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
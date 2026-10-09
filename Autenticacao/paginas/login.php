<?php
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/csrf.php';

if (!empty($_SESSION['tipo'])) {
    if ($_SESSION['tipo'] === 'barbeiro') {
        header('Location: /inicio?login=sucesso');
        exit;
    }
    if ($_SESSION['tipo'] === 'admin') {
        header('Location: /painel?login=sucesso');
        exit;
    }
}
?>


<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BarbERP — Acesso</title>

    <?php require_once __DIR__ . '/../../includes/theme-init.php'; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Poppins:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/admin-theme.css?v=3">

    <style>
        /* Tokens, tipografia (.display, .eyebrow), campos (.field, .field-label)
           e tema claro/escuro vêm de assets/css/admin-theme.css. Aqui ficam
           só as peças exclusivas da tela de acesso. */
        body.lg-body {
            background-image:
                radial-gradient(ellipse at 50% 16%, rgba(var(--accent-rgb), 0.11), transparent 55%),
                radial-gradient(ellipse at 50% 100%, rgba(0, 0, 0, 0.35), transparent 60%),
                repeating-linear-gradient(45deg, rgba(255, 236, 205, 0.014) 0, rgba(255, 236, 205, 0.014) 1px, transparent 1px, transparent 7px);
        }
        html[data-theme="light"] body.lg-body {
            background-image:
                radial-gradient(ellipse at 50% 14%, rgba(var(--accent-rgb), 0.16), transparent 55%);
        }

        .lg-emblem {
            background:
                radial-gradient(circle at 35% 30%, rgba(255, 255, 255, 0.10), transparent 45%),
                linear-gradient(145deg, var(--surface-3), var(--bg));
            border: 1px solid rgba(var(--accent-rgb), 0.55);
            box-shadow:
                0 0 0 4px rgba(var(--accent-rgb), 0.08),
                var(--shadow-1),
                inset 0 1px 1px rgba(255, 255, 255, 0.05);
        }

        .lg-stripe {
            height: 5px;
            width: 100%;
            background: linear-gradient(90deg, var(--accent-lo), var(--accent), var(--accent-hi), var(--accent), var(--accent-lo));
        }

        .lg-card {
            background: linear-gradient(180deg, var(--surface-2), var(--surface));
            border: 1px solid var(--line-strong);
            box-shadow: var(--shadow-2);
        }

        .lg-btn {
            background: linear-gradient(180deg, var(--accent-hi), var(--accent));
            color: var(--accent-on);
            font-weight: 600;
            letter-spacing: 0.03em;
            transition: filter .2s, transform .15s, box-shadow .2s;
            box-shadow: 0 8px 20px -8px rgba(var(--accent-rgb), 0.55);
        }
        .lg-btn:hover { filter: brightness(1.06); box-shadow: 0 10px 26px -6px rgba(var(--accent-rgb), 0.65); }
        .lg-btn:active { transform: scale(0.98); }
        .lg-btn:focus-visible { outline: 2px solid var(--accent-strong); outline-offset: 3px; }

        .lg-tick {
            width: 1px;
            height: 14px;
            background: rgba(var(--accent-rgb), 0.5);
        }
    </style>
</head>

<body class="lg-body flex items-center justify-center px-6 py-10">

    <div class="w-full max-w-md">

        <!-- Emblema -->
        <div class="flex flex-col items-center mb-7">
            <div class="lg-emblem w-20 h-20 rounded-full flex items-center justify-center mb-4">
                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="5" y="11" width="14" height="9" rx="2" stroke="var(--accent-strong)" stroke-width="1.4" />
                    <path d="M8 11V7a4 4 0 0 1 8 0v4" stroke="var(--accent-strong)" stroke-width="1.4" stroke-linecap="round" />
                    <circle cx="12" cy="15.4" r="1.3" fill="var(--accent-strong)" />
                </svg>
            </div>

            <p class="eyebrow uppercase mb-1" style="color:var(--accent-strong); opacity:.75">Plataforma de Gestão</p>
            <h1 class="display text-5xl text-[color:var(--cream)] leading-none">
                BARB<span style="color:var(--accent-strong)">ERP</span>
            </h1>
            <div class="flex items-center gap-3 mt-3 text-[11px] tracking-wider" style="color:var(--text-muted)">
                <span>PAINEL INTERNO</span>
                <span class="lg-tick"></span>
                <span>ACESSO RESTRITO</span>
            </div>
        </div>

        <!-- Card -->
        <div class="lg-card rounded-3xl shadow-2xl overflow-hidden">

            <div class="lg-stripe"></div>

            <div class="p-8">

                <?php /* Erro de login agora aparece como modal SweetAlert2 — ver script no fim da página */ ?>

                <form action="/Autenticacao/scripts/auth.php" method="POST" autocomplete="off">
                    <?= csrf_field() ?>

                    <div class="mb-5">
                        <label class="field-label block mb-2 uppercase" for="login">Usuário</label>
                        <input
                            id="login"
                            name="login"
                            type="text"
                            placeholder="Seu login"
                            required
                            class="field w-full h-12 px-4 rounded-xl text-sm">
                    </div>

                    <div class="mb-7">
                        <label class="field-label block mb-2 uppercase" for="senha">Senha</label>
                        <input
                            id="senha"
                            name="senha"
                            type="password"
                            placeholder="Sua senha"
                            required
                            class="field w-full h-12 px-4 rounded-xl text-sm">
                    </div>

                    <button type="submit" class="lg-btn w-full h-12 rounded-xl text-sm">
                        Entrar no sistema
                    </button>

                </form>

            </div>

            <div class="lg-stripe"></div>
        </div>

        <p class="text-center text-xs mt-8 font-light" style="color:var(--text-muted)">
            © 2026 DAK Soluções Digitais — Todos os direitos reservados.
        </p>

    </div>

    <script>
        <?php if (isset($_GET['erro'])): ?>
            Swal.fire({
                icon: '<?= $_GET['erro'] === 'bloqueado' ? 'warning' : 'error' ?>',
                title: '<?= $_GET['erro'] === 'bloqueado' ? 'Muitas tentativas' : 'Acesso negado' ?>',
                text: '<?= $_GET['erro'] === 'bloqueado'
                            ? 'Muitas tentativas de acesso. Tente novamente mais tarde.'
                            : 'Usuário ou senha inválidos. Confira os dados e tente novamente.' ?>',
                background: 'var(--surface-2)',
                color: 'var(--cream)',
                confirmButtonColor: 'var(--accent)',
                confirmButtonText: 'Tentar novamente',
                iconColor: 'var(--danger)'
            }).then(function() {
                // Remove ?erro=1 da URL para não reabrir o modal num refresh
                var url = new URL(window.location.href);
                url.searchParams.delete('erro');
                window.history.replaceState({}, document.title, url.pathname + url.search);
                document.getElementById('senha').focus();
            });
        <?php endif; ?>
    </script>

</body>

</html>

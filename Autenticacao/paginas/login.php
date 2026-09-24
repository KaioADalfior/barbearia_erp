<?php
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/csrf.php';

if (!empty($_SESSION['tipo'])) {
    if ($_SESSION['tipo'] === 'barbeiro') {
        header('Location: ../../Painel/paginas/painel_barbeiro.php?login=sucesso');
        exit;
    }
    if ($_SESSION['tipo'] === 'admin') {
        header('Location: ../../Painel/paginas/painel_admin.php?login=sucesso');
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

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">

    <style>
        :root {
            --onyx: #0b0f17;
            --charcoal: #111827;
            --charcoal-2: #141b2b;
            --gold: #2f6fed;
            --gold-light: #5b93f7;
            --cream: #e7ebf3;
            --pole-red: #64748b;
            --pole-blue: #94a3b8;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--onyx);
            background-image:
                radial-gradient(ellipse at 50% 18%, rgba(47, 111, 237, 0.10), transparent 55%),
                radial-gradient(ellipse at 50% 100%, rgba(0, 0, 0, 0.65), transparent 60%),
                repeating-linear-gradient(45deg, rgba(255, 255, 255, 0.015) 0, rgba(255, 255, 255, 0.015) 1px, transparent 1px, transparent 6px);
            min-height: 100vh;
        }

        .display {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            letter-spacing: -0.01em;
        }

        .eyebrow {
            font-family: 'Poppins', sans-serif;
            letter-spacing: 0.35em;
            font-size: 11px;
            font-weight: 500;
        }

        /* Emblema em relevo */
        .emblem {
            background:
                radial-gradient(circle at 35% 30%, rgba(255, 255, 255, 0.10), transparent 45%),
                linear-gradient(145deg, #17233a, #0b0f17);
            border: 1px solid rgba(47, 111, 237, 0.55);
            box-shadow:
                0 0 0 4px rgba(47, 111, 237, 0.08),
                0 12px 30px -8px rgba(0, 0, 0, 0.7),
                inset 0 1px 1px rgba(255, 255, 255, 0.05);
        }

        /* Faixa de destaque neutra — barra sólida na cor de destaque do sistema,
       sem referência temática a barbearia, para servir de base genérica a
       qualquer cliente (ver mesmo tratamento em assets/css/admin-theme.css). */
        .barber-stripe {
            height: 5px;
            width: 100%;
            background: linear-gradient(90deg, var(--gold), var(--gold-light));
        }

        .card {
            background: linear-gradient(180deg, var(--charcoal), var(--charcoal-2));
            border: 1px solid rgba(47, 111, 237, 0.14);
        }

        .field-label {
            font-size: 12px;
            letter-spacing: 0.12em;
            font-weight: 500;
            color: #aebdd6;
        }

        .field {
            background: rgba(0, 0, 0, 0.35);
            border: 1px solid rgba(255, 255, 255, 0.08);
            color: var(--cream);
            transition: border-color .2s, box-shadow .2s, background-color .2s;
        }

        .field::placeholder {
            color: #5b6f8c;
        }

        .field:focus {
            outline: none;
            border-color: var(--gold);
            background: rgba(0, 0, 0, 0.5);
            box-shadow: 0 0 0 4px rgba(47, 111, 237, 0.12);
        }

        .btn-entrar {
            background: linear-gradient(180deg, var(--gold-light), var(--gold));
            color: #ffffff;
            font-weight: 600;
            letter-spacing: 0.03em;
            transition: filter .2s, transform .15s, box-shadow .2s;
            box-shadow: 0 8px 20px -8px rgba(47, 111, 237, 0.55);
        }

        .btn-entrar:hover {
            filter: brightness(1.08);
            box-shadow: 0 10px 26px -6px rgba(47, 111, 237, 0.65);
        }

        .btn-entrar:active {
            transform: scale(0.98);
        }

        .divider-tick {
            width: 1px;
            height: 14px;
            background: rgba(47, 111, 237, 0.4);
        }

        .error-box {
            border: 1px solid rgba(239, 68, 68, 0.55);
            background: rgba(239, 68, 68, 0.12);
            color: #f0c9cc;
        }
    </style>
</head>

<body class="flex items-center justify-center px-6 py-10">

    <div class="w-full max-w-md">

        <!-- Emblema -->
        <div class="flex flex-col items-center mb-7">
            <div class="emblem w-20 h-20 rounded-full flex items-center justify-center mb-4">
                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="5" y="11" width="14" height="9" rx="2" stroke="var(--gold-light)" stroke-width="1.4" />
                    <path d="M8 11V7a4 4 0 0 1 8 0v4" stroke="var(--gold-light)" stroke-width="1.4" stroke-linecap="round" />
                    <circle cx="12" cy="15.4" r="1.3" fill="var(--gold-light)" />
                </svg>
            </div>

            <p class="eyebrow uppercase mb-1" style="color:var(--gold-light); opacity:.75">Plataforma de Gestão</p>
            <h1 class="display text-5xl text-[color:var(--cream)] leading-none">
                BARB<span style="color:var(--gold-light)">ERP</span>
            </h1>
            <div class="flex items-center gap-3 mt-3 text-[11px] text-zinc-500 tracking-wider">
                <span>PAINEL INTERNO</span>
                <span class="divider-tick"></span>
                <span>ACESSO RESTRITO</span>
            </div>
        </div>

        <!-- Card -->
        <div class="card rounded-3xl shadow-2xl overflow-hidden">

            <div class="barber-stripe"></div>

            <div class="p-8">

                <?php /* Erro de login agora aparece como modal SweetAlert2 — ver script no fim da página */ ?>

                <form action="../scripts/auth.php" method="POST" autocomplete="off">
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

                    <button type="submit" class="btn-entrar w-full h-12 rounded-xl text-sm">
                        Entrar no sistema
                    </button>

                </form>

            </div>

            <div class="barber-stripe"></div>
        </div>

        <p class="text-center text-xs text-zinc-600 mt-8 font-light">
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
                background: '#141b2b',
                color: '#e7ebf3',
                confirmButtonColor: '#2f6fed',
                confirmButtonText: 'Tentar novamente',
                iconColor: '#ef4444'
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
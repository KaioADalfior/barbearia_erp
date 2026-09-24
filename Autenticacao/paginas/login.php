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
<title>Alex Barbearia — Acesso</title>

<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Poppins:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">

<style>
    :root{
        --onyx:#0a0e1a;
        --charcoal:#101828;
        --charcoal-2:#182338;
        --gold:#3d7ec9;
        --gold-light:#6fa8ea;
        --cream:#e9eef6;
        --pole-red:#1e4d80;
        --pole-blue:#5b9bd8;
    }

    *{ box-sizing:border-box; }

    body{
        font-family:'Poppins', sans-serif;
        background-color:var(--onyx);
        background-image:
            radial-gradient(ellipse at 50% 18%, rgba(61,126,201,0.10), transparent 55%),
            radial-gradient(ellipse at 50% 100%, rgba(0,0,0,0.65), transparent 60%),
            repeating-linear-gradient(45deg, rgba(255,255,255,0.015) 0, rgba(255,255,255,0.015) 1px, transparent 1px, transparent 6px);
        min-height:100vh;
    }

    .display{
        font-family:'Bebas Neue', sans-serif;
        letter-spacing:0.06em;
    }

    .eyebrow{
        font-family:'Poppins', sans-serif;
        letter-spacing:0.35em;
        font-size:11px;
        font-weight:500;
    }

    /* Emblema em relevo dourado */
    .emblem{
        background:
            radial-gradient(circle at 35% 30%, rgba(255,255,255,0.10), transparent 45%),
            linear-gradient(145deg, #142238, #0a0e1a);
        border:1px solid rgba(61,126,201,0.55);
        box-shadow:
            0 0 0 4px rgba(61,126,201,0.08),
            0 12px 30px -8px rgba(0,0,0,0.7),
            inset 0 1px 1px rgba(255,255,255,0.05);
    }

    /* Faixa de poste de barbeiro — o elemento de assinatura */
    .barber-stripe{
        height:8px;
        width:100%;
        background:repeating-linear-gradient(
            -45deg,
            var(--pole-red) 0 14px,
            var(--cream) 14px 28px,
            var(--pole-blue) 28px 42px,
            var(--cream) 42px 56px
        );
        background-size:200% 100%;
        animation:pole-scroll 7s linear infinite;
    }
    @keyframes pole-scroll{
        from{ background-position:0 0; }
        to{ background-position:-79px 0; }
    }
    @media (prefers-reduced-motion: reduce){
        .barber-stripe{ animation:none; }
    }

    .card{
        background:linear-gradient(180deg, var(--charcoal), var(--charcoal-2));
        border:1px solid rgba(61,126,201,0.14);
    }

    .field-label{
        font-size:12px;
        letter-spacing:0.12em;
        font-weight:500;
        color:#aebdd6;
    }

    .field{
        background:rgba(0,0,0,0.35);
        border:1px solid rgba(255,255,255,0.08);
        color:var(--cream);
        transition:border-color .2s, box-shadow .2s, background-color .2s;
    }
    .field::placeholder{ color:#5b6f8c; }
    .field:focus{
        outline:none;
        border-color:var(--gold);
        background:rgba(0,0,0,0.5);
        box-shadow:0 0 0 4px rgba(61,126,201,0.12);
    }

    .btn-entrar{
        background:linear-gradient(180deg, var(--gold-light), var(--gold));
        color:#ffffff;
        font-weight:600;
        letter-spacing:0.03em;
        transition:filter .2s, transform .15s, box-shadow .2s;
        box-shadow:0 8px 20px -8px rgba(61,126,201,0.55);
    }
    .btn-entrar:hover{ filter:brightness(1.08); box-shadow:0 10px 26px -6px rgba(61,126,201,0.65); }
    .btn-entrar:active{ transform:scale(0.98); }

    .divider-tick{
        width:1px;
        height:14px;
        background:rgba(61,126,201,0.4);
    }

    .error-box{
        border:1px solid rgba(140,31,40,0.55);
        background:rgba(140,31,40,0.12);
        color:#f0c9cc;
    }
</style>
</head>

<body class="flex items-center justify-center px-6 py-10">

<div class="w-full max-w-md">

    <!-- Emblema -->
    <div class="flex flex-col items-center mb-7">
        <div class="emblem w-20 h-20 rounded-full flex items-center justify-center mb-4">
            <svg width="34" height="34" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M6.5 5.5a2.5 2.5 0 1 1 3.4 3.4L18 17.5" stroke="#6fa8ea" stroke-width="1.4" stroke-linecap="round"/>
                <path d="M6.5 18.5a2.5 2.5 0 1 0 3.4-3.4L18 6.5" stroke="#6fa8ea" stroke-width="1.4" stroke-linecap="round"/>
                <circle cx="6.2" cy="6.2" r="1.6" stroke="#6fa8ea" stroke-width="1.2"/>
                <circle cx="6.2" cy="17.8" r="1.6" stroke="#6fa8ea" stroke-width="1.2"/>
            </svg>
        </div>

        <p class="eyebrow uppercase mb-1" style="color:var(--gold-light); opacity:.75">Est. 2026 · Espírito Santo</p>
        <h1 class="display text-5xl text-[color:var(--cream)] leading-none">
            ALEX <span style="color:var(--gold-light)">BARBEARIA</span>
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
                        class="field w-full h-12 px-4 rounded-xl text-sm"
                    >
                </div>

                <div class="mb-7">
                    <label class="field-label block mb-2 uppercase" for="senha">Senha</label>
                    <input
                        id="senha"
                        name="senha"
                        type="password"
                        placeholder="Sua senha"
                        required
                        class="field w-full h-12 px-4 rounded-xl text-sm"
                    >
                </div>

                <button type="submit" class="btn-entrar w-full h-12 rounded-xl text-sm">
                    Entrar no sistema
                </button>

            </form>

        </div>

        <div class="barber-stripe"></div>
    </div>

    <p class="text-center text-xs text-zinc-600 mt-8 font-light">
        © 2026 DAK ChatBots e Soluções Digitais — Todos os direitos reservados.
    </p>
    <p class="text-center text-xs text-zinc-600 mt-8 font-light">
        <a href="../../Uploads/paginas/uploads.php">Ver Uploads e Versões</a>
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
    background: '#101828',
    color: '#e9eef6',
    confirmButtonColor: '#3d7ec9',
    confirmButtonText: 'Tentar novamente',
    iconColor: '#8c1f28'
}).then(function () {
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
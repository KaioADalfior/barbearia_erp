<?php
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/guard.php';
exigirSessao(['barbeiro']);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/csrf.php';

$paginaAtual = 'perfil';
$idBarbeiro  = (int) $_SESSION['id'];

$stmt = $pdo->prepare('SELECT nome, login, telefone, foto FROM Barbeiro WHERE id_barbeiro = :id LIMIT 1');
$stmt->execute(['id' => $idBarbeiro]);
$barbeiro = $stmt->fetch();

if (!$barbeiro) {
    header('Location: /Autenticacao/paginas/login.php');
    exit;
}

$temFoto     = !empty($barbeiro['foto']) && is_file(__DIR__ . '/../../assets/uploads/perfil/' . $barbeiro['foto']);
$fotoUrl     = $temFoto ? '/assets/uploads/perfil/' . rawurlencode($barbeiro['foto']) . '?v=' . filemtime(__DIR__ . '/../../assets/uploads/perfil/' . $barbeiro['foto']) : null;
$inicial     = strtoupper(substr($barbeiro['nome'] ?? 'B', 0, 1));
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
<?php include __DIR__ . '/../../includes/theme-init.php'; ?>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Perfil — BarbERP</title>

<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/admin-theme.css?v=2">

<style>
    .perfil-avatar-wrap{
        position:relative;
        width:96px;
        height:96px;
        flex-shrink:0;
    }
    .perfil-avatar{
        width:96px;
        height:96px;
        border-radius:999px;
        object-fit:cover;
        display:block;
        background:rgba(255,255,255,0.05);
        border:2px solid rgba(61,126,201,0.35);
    }
    .perfil-avatar-letra{
        width:96px;
        height:96px;
        border-radius:999px;
        display:flex;
        align-items:center;
        justify-content:center;
        background:linear-gradient(180deg, rgba(61,126,201,0.22), rgba(61,126,201,0.08));
        border:2px solid rgba(61,126,201,0.35);
        color:var(--gold-light);
        font-family:'Bebas Neue', sans-serif;
        font-size:36px;
        letter-spacing:0.03em;
    }
    .perfil-avatar-editar{
        position:absolute;
        bottom:-2px;
        right:-2px;
        width:32px;
        height:32px;
        border-radius:999px;
        display:flex;
        align-items:center;
        justify-content:center;
        background:linear-gradient(180deg, var(--gold-light), var(--gold));
        border:2px solid var(--charcoal-2);
        color:#ffffff;
        cursor:pointer;
        transition:filter .15s;
    }
    .perfil-avatar-editar:hover{ filter:brightness(1.1); }
    .perfil-avatar-editar input[type="file"]{
        position:absolute;
        inset:0;
        opacity:0;
        cursor:pointer;
    }
    .perfil-remover-foto{
        font-size:12px;
        color:#8fa0bd;
        text-decoration:underline;
        text-underline-offset:2px;
        cursor:pointer;
        transition:color .15s;
    }
    .perfil-remover-foto:hover{ color:#e0a2a8; }
    .badge-usuario{
        display:inline-flex;
        align-items:center;
        gap:0.35rem;
        font-size:11px;
        font-weight:600;
        letter-spacing:0.03em;
        padding:0.28rem 0.65rem;
        border-radius:999px;
        color:var(--gold-light);
        background:rgba(61,126,201,0.12);
        border:1px solid rgba(61,126,201,0.4);
    }
</style>
</head>
<body class="flex">

<?php include __DIR__ . '/../../includes/sidebar_barbeiro.php'; ?>
<?php include __DIR__ . '/../../includes/toast.php'; ?>

<main class="flex-1 min-w-0">

    <header class="topbar px-5 sm:px-8 py-5 sm:py-6 flex items-center gap-4">
        <button type="button" onclick="abrirMenuMobile()" class="menu-toggle-btn lg:hidden" aria-label="Abrir menu">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M4 6h16M4 12h16M4 18h16" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
            </svg>
        </button>
        <div class="min-w-0">
            <p class="eyebrow uppercase mb-1" style="color:var(--gold-light); opacity:.75">Minha conta</p>
            <h1 class="display text-3xl sm:text-4xl text-[color:var(--cream)] truncate">Perfil</h1>
        </div>
    </header>

    <section class="p-5 sm:p-8 max-w-5xl grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">

        <div class="flex flex-col gap-6 min-w-0">

            <!-- Foto + dados da conta -->
            <div class="panel-card rounded-2xl p-5 sm:p-6">
                <p class="text-sm font-semibold text-[color:var(--cream)] mb-1">Foto de perfil</p>
                <p class="settings-desc mb-4">Aparece no menu lateral e na identificação da sua conta.</p>

                <div class="flex items-center gap-5">
                    <div class="perfil-avatar-wrap">
                        <?php if ($fotoUrl): ?>
                            <img id="perfil-avatar-img" src="<?= htmlspecialchars($fotoUrl) ?>" alt="Foto de perfil" class="perfil-avatar">
                        <?php else: ?>
                            <div id="perfil-avatar-letra" class="perfil-avatar-letra"><?= htmlspecialchars($inicial) ?></div>
                            <img id="perfil-avatar-img" src="" alt="Foto de perfil" class="perfil-avatar hidden">
                        <?php endif; ?>

                        <label class="perfil-avatar-editar" title="Trocar foto">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M4 13.5V18a2 2 0 0 0 2 2h4.5M20 10.5V6a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v9" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                                <circle cx="9" cy="9" r="1.6" stroke="currentColor" stroke-width="1.4"/>
                                <path d="M4 16l4.2-4.2a1.8 1.8 0 0 1 2.5 0L14 15" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <input type="file" id="perfil-foto-input" accept="image/png, image/jpeg, image/webp">
                        </label>
                    </div>

                    <div class="min-w-0">
                        <p class="text-sm text-[color:var(--cream)] font-medium truncate"><?= htmlspecialchars($barbeiro['nome']) ?></p>
                        <p class="badge-usuario mt-1.5">@<?= htmlspecialchars($barbeiro['login']) ?></p>
                        <p class="mt-3">
                            <span id="perfil-remover-foto" class="perfil-remover-foto <?= $fotoUrl ? '' : 'hidden' ?>">Remover foto</span>
                        </p>
                        <p class="settings-desc mt-2">JPG, PNG ou WEBP · até 3MB</p>
                    </div>
                </div>
            </div>

            <!-- Dados da conta -->
            <div class="panel-card rounded-2xl p-5 sm:p-6 min-w-0">
                <p class="text-sm font-semibold text-[color:var(--cream)] mb-1">Dados da conta</p>
                <p class="settings-desc mb-5">Seu nome completo e usuário de acesso.</p>

                <form action="/Perfil/scripts/perfil_atualizar.php" method="POST" autocomplete="off" class="flex flex-col gap-4">
                    <?= csrf_field() ?>
                    <div>
                        <label class="field-label block mb-2 uppercase" for="nome">Nome completo</label>
                        <input id="nome" name="nome" type="text" required maxlength="100"
                               value="<?= htmlspecialchars($barbeiro['nome']) ?>"
                               class="field w-full h-12 px-4 rounded-xl text-sm">
                    </div>

                    <div>
                        <label class="field-label block mb-2 uppercase" for="login">Usuário</label>
                        <input id="login" name="login" type="text" required maxlength="50"
                               value="<?= htmlspecialchars($barbeiro['login']) ?>"
                               class="field w-full h-12 px-4 rounded-xl text-sm">
                        <p class="settings-desc mt-2">Usado para entrar no painel. Só letras, números, ponto, traço ou underline.</p>
                    </div>

                    <button type="submit" class="btn-primary h-12 rounded-xl text-sm mt-2">
                        Salvar dados
                    </button>
                </form>
            </div>

        </div>

        <!-- Segurança / Senha -->
        <div class="panel-card rounded-2xl p-5 sm:p-6 min-w-0">
            <p class="text-sm font-semibold text-[color:var(--cream)] mb-1">Segurança</p>
            <p class="settings-desc mb-5">Altere a senha da sua conta.</p>

            <form action="/Configuracoes/scripts/senha_atualizar.php" method="POST" autocomplete="off" class="flex flex-col gap-4">
                <?= csrf_field() ?>
                <input type="hidden" name="voltar" value="perfil">

                <div>
                    <label class="field-label block mb-2 uppercase" for="senha_atual">Senha atual</label>
                    <input id="senha_atual" name="senha_atual" type="password" required
                           class="field w-full h-12 px-4 rounded-xl text-sm">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="field-label block mb-2 uppercase" for="senha_nova">Nova senha</label>
                        <input id="senha_nova" name="senha_nova" type="password" required minlength="6"
                               class="field w-full h-12 px-4 rounded-xl text-sm">
                    </div>

                    <div>
                        <label class="field-label block mb-2 uppercase" for="senha_confirma">Confirmar nova senha</label>
                        <input id="senha_confirma" name="senha_confirma" type="password" required minlength="6"
                               class="field w-full h-12 px-4 rounded-xl text-sm">
                    </div>
                </div>

                <button type="submit" class="btn-primary h-12 rounded-xl text-sm mt-2">
                    Salvar nova senha
                </button>
            </form>
        </div>

    </section>

</main>

<script>
    document.getElementById('perfil-foto-input').addEventListener('change', async function () {
        const arquivo = this.files[0];
        if (!arquivo) return;

        const formData = new FormData();
        formData.set('foto', arquivo);
        formData.set('_csrf', document.querySelector('meta[name="csrf-token"]').content);

        try {
            const resposta = await fetch('/Perfil/scripts/perfil_foto_atualizar.php', { method: 'POST', body: formData });
            const dados = await resposta.json();

            if (!dados.ok) {
                toast(dados.erro || 'Não foi possível enviar a foto.', 'erro');
                this.value = '';
                return;
            }

            const url = URL.createObjectURL(arquivo);
            const img = document.getElementById('perfil-avatar-img');
            const letra = document.getElementById('perfil-avatar-letra');
            img.src = url;
            img.classList.remove('hidden');
            if (letra) letra.classList.add('hidden');
            document.getElementById('perfil-remover-foto').classList.remove('hidden');

            toast('Foto atualizada com sucesso.', 'sucesso');
        } catch (e) {
            toast('Erro de conexão. Tente novamente.', 'erro');
        }

        this.value = '';
    });

    document.getElementById('perfil-remover-foto').addEventListener('click', async function () {
        const confirmacao = await Swal.fire({
            title: 'Remover foto de perfil?',
            text: 'Você pode enviar outra foto a qualquer momento.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sim, remover',
            cancelButtonText: 'Cancelar'
        });
        if (!confirmacao.isConfirmed) return;

        try {
            const formData = new FormData();
            formData.set('acao', 'remover');
            formData.set('_csrf', document.querySelector('meta[name="csrf-token"]').content);
            const resposta = await fetch('/Perfil/scripts/perfil_foto_atualizar.php', { method: 'POST', body: formData });
            const dados = await resposta.json();

            if (!dados.ok) {
                toast(dados.erro || 'Não foi possível remover a foto.', 'erro');
                return;
            }

            const img = document.getElementById('perfil-avatar-img');
            const letra = document.getElementById('perfil-avatar-letra');
            img.classList.add('hidden');
            img.src = '';
            if (letra) letra.classList.remove('hidden');
            this.classList.add('hidden');

            toast('Foto removida.', 'sucesso');
        } catch (e) {
            toast('Erro de conexão. Tente novamente.', 'erro');
        }
    });

    <?php if (isset($_GET['perfil_erro'])): ?>
    Swal.fire({
        icon: 'error',
        title: 'Não foi possível salvar',
        text: <?= json_encode($_GET['perfil_erro'], JSON_UNESCAPED_UNICODE) ?>,
        background: '#101828',
        color: '#e9eef6',
        confirmButtonColor: '#3d7ec9',
        confirmButtonText: 'Entendi',
        iconColor: '#8c1f28'
    }).then(function () {
        var url = new URL(window.location.href);
        url.searchParams.delete('perfil_erro');
        window.history.replaceState({}, document.title, url.pathname + url.search);
    });
    <?php endif; ?>

    <?php if (isset($_GET['perfil_sucesso'])): ?>
    toast('Dados atualizados com sucesso!');
    <?php endif; ?>

    <?php if (isset($_GET['senha_erro'])): ?>
    Swal.fire({
        icon: 'error',
        title: 'Senha incorreta',
        text: <?= json_encode($_GET['senha_erro'], JSON_UNESCAPED_UNICODE) ?>,
        background: '#101828',
        color: '#e9eef6',
        confirmButtonColor: '#3d7ec9',
        confirmButtonText: 'Entendi',
        iconColor: '#8c1f28'
    }).then(function () {
        var url = new URL(window.location.href);
        url.searchParams.delete('senha_erro');
        window.history.replaceState({}, document.title, url.pathname + url.search);
    });
    <?php endif; ?>

    <?php if (isset($_GET['senha_sucesso'])): ?>
    toast('Senha atualizada com sucesso!');
    <?php endif; ?>
</script>

</body>
</html>
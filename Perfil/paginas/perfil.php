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
    header('Location: /login');
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
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.css">
<script src="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.js"></script>
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
    .recorte-overlay{
        position:fixed; inset:0; z-index:200;
        display:none; align-items:center; justify-content:center;
        padding:16px; background:rgba(5,8,14,0.82);
    }
    .recorte-overlay.aberto{ display:flex; }
    .recorte-modal{
        width:100%; max-width:440px;
        background:linear-gradient(180deg, var(--charcoal), var(--charcoal-2));
        border:1px solid rgba(61,126,201,0.25);
        border-radius:20px; padding:20px;
        box-shadow:0 24px 60px -12px rgba(0,0,0,0.8);
    }
    .recorte-area{
        width:100%; height:min(60vw, 320px);
        background:#05080e; border-radius:12px; overflow:hidden;
    }
    .recorte-area img{ display:block; max-width:100%; }
    .recorte-modal .cropper-view-box, .recorte-modal .cropper-face{ border-radius:50%; }
    .recorte-modal .cropper-view-box{ outline:2px solid rgba(91,147,247,0.9); }
    .recorte-zoom{ width:100%; accent-color:var(--gold-light); }
    .recorte-btn{
        height:42px; padding:0 18px; border-radius:12px;
        font-size:14px; font-weight:600; cursor:pointer; transition:filter .15s;
    }
    .recorte-btn:hover{ filter:brightness(1.1); }
    .recorte-btn-sec{ background:rgba(255,255,255,0.06); color:#c9d3e6; border:1px solid rgba(255,255,255,0.1); }
    .recorte-btn-pri{ background:linear-gradient(180deg, var(--gold-light), var(--gold)); color:#fff; border:none; }
    .recorte-btn[disabled]{ opacity:.6; cursor:wait; }
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

<div id="recorte-overlay" class="recorte-overlay" role="dialog" aria-modal="true" aria-labelledby="recorte-titulo">
    <div class="recorte-modal">
        <p id="recorte-titulo" class="text-sm font-semibold text-[color:var(--cream)] mb-1">Ajustar foto</p>
        <p class="text-xs mb-3" style="color:#8fa0bd">Arraste para posicionar e use o controle para aproximar ou afastar.</p>
        <div class="recorte-area"><img id="recorte-img" alt="Pré-visualização"></div>
        <div class="flex items-center gap-3 mt-4">
            <span class="text-xs" style="color:#8fa0bd">&minus;</span>
            <input type="range" id="recorte-zoom" class="recorte-zoom" min="0" max="1" step="0.01" value="0" aria-label="Zoom">
            <span class="text-xs" style="color:#8fa0bd">+</span>
        </div>
        <div class="flex justify-end gap-3 mt-5">
            <button type="button" id="recorte-cancelar" class="recorte-btn recorte-btn-sec">Cancelar</button>
            <button type="button" id="recorte-salvar" class="recorte-btn recorte-btn-pri">Salvar foto</button>
        </div>
    </div>
</div>

<script>
    // ---------- Foto de perfil: escolher -> ajustar/redimensionar -> enviar ----------
    (function () {
        const input    = document.getElementById('perfil-foto-input');
        const overlay  = document.getElementById('recorte-overlay');
        const imgEl    = document.getElementById('recorte-img');
        const zoomEl   = document.getElementById('recorte-zoom');
        const btnSalvar   = document.getElementById('recorte-salvar');
        const btnCancelar = document.getElementById('recorte-cancelar');
        const TAMANHO_FINAL = 512; // px (quadrado)
        let cropper = null;
        let urlTemp = null;
        let baseRatio = 0;

        function fechar() {
            overlay.classList.remove('aberto');
            if (cropper) { cropper.destroy(); cropper = null; }
            baseRatio = 0;
            if (urlTemp) { URL.revokeObjectURL(urlTemp); urlTemp = null; }
            imgEl.removeAttribute('src');
            input.value = '';
            btnSalvar.disabled = false;
            btnSalvar.textContent = 'Salvar foto';
        }

        input.addEventListener('change', function () {
            const arquivo = this.files[0];
            if (!arquivo) return;

            if (!/^image\/(jpeg|png|webp)$/.test(arquivo.type)) {
                toast('Formato inválido. Envie uma imagem JPG, PNG ou WEBP.', 'erro');
                this.value = '';
                return;
            }
            if (typeof Cropper === 'undefined') {
                toast('Não foi possível carregar o editor de imagem. Recarregue a página.', 'erro');
                this.value = '';
                return;
            }

            urlTemp = URL.createObjectURL(arquivo);
            imgEl.src = urlTemp;
            overlay.classList.add('aberto');

            if (cropper) cropper.destroy();
            cropper = new Cropper(imgEl, {
                aspectRatio: 1,
                viewMode: 1,
                dragMode: 'move',
                autoCropArea: 0.9,
                background: false,
                guides: false,
                center: false,
                highlight: false,
                cropBoxMovable: false,
                cropBoxResizable: false,
                toggleDragModeOnDblclick: false,
                ready: function () {
                    zoomEl.value = 0;
                    baseRatio = cropper.getImageData().width / cropper.getImageData().naturalWidth;
                },
                zoom: function (e) {
                    // mantém o controle em sincronia com zoom por scroll/pinça
                    if (!baseRatio) return;
                    const v = (e.detail.ratio / baseRatio - 1) / 3;
                    zoomEl.value = Math.min(1, Math.max(0, v));
                }
            });
        });

        zoomEl.addEventListener('input', function () {
            if (!cropper || !baseRatio) return;
            // 0 => encaixe inicial; 1 => 4x mais aproximado
            cropper.zoomTo(baseRatio * (1 + parseFloat(this.value) * 3));
        });

        btnCancelar.addEventListener('click', fechar);
        overlay.addEventListener('click', function (e) { if (e.target === overlay) fechar(); });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && overlay.classList.contains('aberto')) fechar();
        });

        btnSalvar.addEventListener('click', function () {
            if (!cropper) return;
            btnSalvar.disabled = true;
            btnSalvar.textContent = 'Salvando...';

            const canvas = cropper.getCroppedCanvas({
                width: TAMANHO_FINAL,
                height: TAMANHO_FINAL,
                fillColor: '#ffffff',
                imageSmoothingQuality: 'high'
            });

            canvas.toBlob(async function (blob) {
                if (!blob) {
                    toast('Não foi possível processar a imagem.', 'erro');
                    btnSalvar.disabled = false;
                    btnSalvar.textContent = 'Salvar foto';
                    return;
                }

                const formData = new FormData();
                formData.set('foto', blob, 'foto.jpg');
                formData.set('_csrf', document.querySelector('meta[name="csrf-token"]').content);

                try {
                    const resposta = await fetch('/Perfil/scripts/perfil_foto_atualizar.php', { method: 'POST', body: formData });
                    const dados = await resposta.json();

                    if (!dados.ok) {
                        toast(dados.erro || 'Não foi possível enviar a foto.', 'erro');
                        btnSalvar.disabled = false;
                        btnSalvar.textContent = 'Salvar foto';
                        return;
                    }

                    const img = document.getElementById('perfil-avatar-img');
                    const letra = document.getElementById('perfil-avatar-letra');
                    img.src = canvas.toDataURL('image/jpeg', 0.9);
                    img.classList.remove('hidden');
                    if (letra) letra.classList.add('hidden');
                    document.getElementById('perfil-remover-foto').classList.remove('hidden');

                    fechar();
                    toast('Foto atualizada com sucesso.', 'sucesso');
                } catch (e) {
                    toast('Erro de conexão. Tente novamente.', 'erro');
                    btnSalvar.disabled = false;
                    btnSalvar.textContent = 'Salvar foto';
                }
            }, 'image/jpeg', 0.9);
        });
    })();

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
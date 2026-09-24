<?php
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/guard.php';
exigirSessao(['admin', 'barbeiro']);
require_once __DIR__ . '/../../includes/csrf.php';
$tipo = $_SESSION['tipo'] ?? null;
$paginaAtual = 'configuracoes';

// ---------- Lista de versões/uploads (somente Admin) ----------
$uploads = [];
if ($tipo === 'admin') {
    require_once __DIR__ . '/../../config/config.php';
    $stmtUploads = $pdo->query(
        'SELECT idUpload, versao, descricao, data_hora
         FROM UploadVersao
         ORDER BY data_hora DESC, idUpload DESC'
    );
    $uploads = $stmtUploads->fetchAll();
}

// ---------- Link público de agendamento (somente Barbeiro) ----------
// Cada barbeiro tem sua própria agenda (Horario.id_barbeiro) — o link é por
// barbeiro, não por conta de admin. Ver includes/PublicoTokenService.php e
// Publico/paginas/agendar.php.
$linkPublicoToken = null;
if ($tipo === 'barbeiro') {
    require_once __DIR__ . '/../../config/config.php';
    $stmtLink = $pdo->prepare('SELECT link_publico FROM Barbeiro WHERE id_barbeiro = :id');
    $stmtLink->execute(['id' => (int) $_SESSION['id']]);
    $linkPublicoToken = $stmtLink->fetchColumn() ?: null;
}
$linkPublicoUrl = null;
$linkGeralUrl = null;
if ($tipo === 'barbeiro') {
    $esquema = (!empty($_SERVER['HTTPS']) || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')) ? 'https' : 'http';
    if ($linkPublicoToken !== null) {
        $linkPublicoUrl = $esquema . '://' . ($_SERVER['HTTP_HOST'] ?? '') . '/c/agendar/' . $linkPublicoToken;
    }
    // Link GERAL da barbearia (sem token) — ver Publico/paginas/escolher_barbeiro.php.
    // Útil quando a barbearia tem mais de um barbeiro: lista todos pra o
    // cliente escolher. Não depende de nenhum link pessoal ter sido gerado.
    $linkGeralUrl = $esquema . '://' . ($_SERVER['HTTP_HOST'] ?? '') . '/c/agendar';
}

// ---------- Reabertura do modal de upload em caso de erro ----------
$uploadStatusGet   = $_GET['upload_status'] ?? '';
$reabrirUpload      = in_array($uploadStatusGet, ['erro', 'duplicada'], true);
$voltaUpVersao      = $_GET['up_versao'] ?? '';
$voltaUpDescricao   = $_GET['up_descricao'] ?? '';
$voltaUpData        = $_GET['up_data'] ?? '';
$voltaUpHora        = $_GET['up_hora'] ?? '';

$mensagensUpload = [
    'sucesso'   => ['ok',   $voltaUpVersao !== '' ? "Upload da versão $voltaUpVersao registrado." : 'Upload registrado com sucesso.'],
    'erro'      => ['erro', 'Preencha a descrição, a data e a hora do upload.'],
    'duplicada' => ['erro', 'Já existe um upload cadastrado com essa versão.'],
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<?php include __DIR__ . '/../../includes/theme-init.php'; ?>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Configurações — BarbERP</title>

<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/admin-theme.css?v=2">

<style>
    .modal-overlay{
        background:rgba(0,0,0,0.7);
        backdrop-filter:blur(3px);
    }
    .modal-card{
        background:linear-gradient(180deg, var(--charcoal-2), var(--charcoal-3));
        border:1px solid rgba(61,126,201,0.16);
    }
    .badge{
        display:inline-flex;
        align-items:center;
        gap:0.35rem;
        font-size:11px;
        font-weight:600;
        letter-spacing:0.04em;
        padding:0.28rem 0.65rem;
        border-radius:999px;
        color:var(--gold-light);
        background:rgba(61,126,201,0.12);
        border:1px solid rgba(61,126,201,0.4);
    }
    table tbody tr{
        border-top:1px solid rgba(255,255,255,0.05);
    }
</style>
</head>
<body class="flex">

<?php
if ($tipo === 'admin') {
    include __DIR__ . '/../../includes/sidebar.php';
} else {
    include __DIR__ . '/../../includes/sidebar_barbeiro.php';
}
include __DIR__ . '/../../includes/toast.php';
?>

<main class="flex-1 min-w-0">

    <header class="topbar px-5 sm:px-8 py-5 sm:py-6 flex items-center gap-4">
        <button type="button" onclick="abrirMenuMobile()" class="menu-toggle-btn lg:hidden" aria-label="Abrir menu">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M4 6h16M4 12h16M4 18h16" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
            </svg>
        </button>
        <div class="min-w-0">
            <p class="eyebrow uppercase mb-1" style="color:var(--gold-light); opacity:.75">Preferências</p>
            <h1 class="display text-3xl sm:text-4xl text-[color:var(--cream)] truncate">Configurações</h1>
        </div>
    </header>

    <?php if (isset($mensagensUpload[$uploadStatusGet])): [$tipoUpMsg, $textoUpMsg] = $mensagensUpload[$uploadStatusGet]; ?>
        <script>document.addEventListener('DOMContentLoaded', function () { toast(<?= json_encode($textoUpMsg) ?>, <?= json_encode($tipoUpMsg === 'ok' ? 'sucesso' : 'erro') ?>); }); </script>
    <?php endif; ?>

    <section class="p-5 sm:p-8 max-w-5xl grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">

        <!-- Coluna esquerda: Aparência + Notificações -->
        <div class="flex flex-col gap-6 min-w-0">

            <!-- Aparência -->
            <div class="panel-card rounded-2xl p-5 sm:p-6">
                <p class="text-sm font-semibold text-[color:var(--cream)] mb-1">Aparência</p>
                <p class="settings-desc mb-4">Escolha como o painel deve ser exibido.</p>

                <div class="grid grid-cols-2 gap-3 sm:gap-4">
                    <button type="button" id="opcao-tema-escuro" onclick="definirTema('dark')"
                            class="theme-option text-left">
                        <div class="theme-swatch mb-3" style="background:linear-gradient(180deg,#101828,#0a0e1a);"></div>
                        <p class="settings-label">Escuro</p>
                        <p class="settings-desc">Padrão do painel</p>
                    </button>

                    <button type="button" id="opcao-tema-claro" onclick="definirTema('light')"
                            class="theme-option text-left">
                        <div class="theme-swatch mb-3" style="background:linear-gradient(180deg,#ffffff,#eef2f8);"></div>
                        <p class="settings-label">Claro</p>
                        <p class="settings-desc">Fundo claro, texto escuro</p>
                    </button>
                </div>
            </div>

            <!-- Notificações -->
            <div class="panel-card rounded-2xl p-5 sm:p-6">
                <p class="text-sm font-semibold text-[color:var(--cream)] mb-1">Notificações</p>
                <p class="settings-desc mb-2">Preferências para os avisos exibidos no painel.</p>

                <div class="settings-row">
                    <div class="min-w-0">
                        <p class="settings-label">Som ao receber notificações</p>
                        <p class="settings-desc">Toca um som curto quando um toast aparece na tela</p>
                    </div>
                    <label class="switch">
                        <input type="checkbox" id="switch-som" onchange="alternarSom(this.checked)">
                        <span class="switch-track"></span>
                    </label>
                </div>
            </div>

        </div>

        <!-- Coluna direita: Segurança / Senha -->
        <div class="panel-card rounded-2xl p-5 sm:p-6 min-w-0">
            <p class="text-sm font-semibold text-[color:var(--cream)] mb-1">Segurança</p>
            <p class="settings-desc mb-5">Altere a senha da sua conta.</p>

            <?php /* Erro de senha agora aparece como modal SweetAlert2 — ver script no fim da página */ ?>

            <form action="/Configuracoes/scripts/senha_atualizar.php" method="POST" autocomplete="off" class="flex flex-col gap-4">
                <?= csrf_field() ?>

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

    <?php if ($tipo === 'barbeiro'): ?>
    <section class="p-5 sm:p-8 max-w-5xl">
        <div class="panel-card rounded-2xl p-5 sm:p-6">
            <p class="text-sm font-semibold text-[color:var(--cream)] mb-1">Agendamento online</p>
            <p class="settings-desc mb-5">Link público para seus clientes agendarem sozinhos, sem precisar entrar no sistema.</p>

            <div id="link-publico-vazio" class="<?= $linkPublicoUrl ? 'hidden' : '' ?>">
                <button type="button" id="btn-gerar-link" class="btn-primary h-11 px-5 rounded-xl text-sm">
                    Gerar meu link de agendamento
                </button>
            </div>

            <div id="link-publico-preenchido" class="<?= $linkPublicoUrl ? '' : 'hidden' ?>">
                <div class="flex flex-col sm:flex-row gap-3">
                    <input type="text" id="input-link-publico" readonly
                           value="<?= htmlspecialchars($linkPublicoUrl ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           class="field w-full h-12 px-4 rounded-xl text-sm" onclick="this.select()">
                    <button type="button" id="btn-copiar-link" class="btn-secondary h-12 px-5 rounded-xl text-sm shrink-0">
                        Copiar
                    </button>
                </div>
                <button type="button" id="btn-gerar-novo-link" class="text-xs mt-3" style="color:#7f8fac; text-decoration:underline;">
                    Gerar um novo link (o link atual deixa de funcionar)
                </button>
            </div>

            <div class="mt-5 pt-5" style="border-top:1px solid rgba(255,255,255,0.08);">
                <p class="text-sm font-semibold text-[color:var(--cream)] mb-1">Link geral da barbearia</p>
                <p class="settings-desc mb-3">Se a barbearia tem mais de um barbeiro, use este link em vez do seu pessoal — o cliente escolhe com quem quer agendar antes de continuar.</p>
                <div class="flex flex-col sm:flex-row gap-3">
                    <input type="text" readonly value="<?= htmlspecialchars($linkGeralUrl, ENT_QUOTES, 'UTF-8') ?>"
                           class="field w-full h-12 px-4 rounded-xl text-sm" onclick="this.select()">
                    <button type="button" id="btn-copiar-link-geral" class="btn-secondary h-12 px-5 rounded-xl text-sm shrink-0">
                        Copiar
                    </button>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($tipo === 'admin'): ?>
    <section class="p-5 sm:p-8 max-w-5xl">
        <div class="panel-card rounded-2xl overflow-hidden">
            <div class="barber-stripe-thin"></div>

            <div class="p-5 sm:p-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold text-[color:var(--cream)] mb-1">Versões &amp; Uploads</p>
                    <p class="settings-desc">Histórico de versões lançadas pelo desenvolvedor. Visível para os barbeiros em "Ver Uploads e Versões".</p>
                </div>
                <button type="button" onclick="openModal('modal-upload')" class="btn-primary h-11 px-5 rounded-xl text-sm flex items-center justify-center gap-2 shrink-0">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 5v14M5 12h14" stroke="#ffffff" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    Inserir Upload
                </button>
            </div>

            <?php if (empty($uploads)): ?>
                <div class="px-5 sm:px-6 pb-8 text-center">
                    <p class="text-sm text-zinc-400 mb-2">Nenhum upload cadastrado ainda.</p>
                    <button type="button" onclick="openModal('modal-upload')" class="btn-secondary h-10 px-5 rounded-xl text-sm inline-flex items-center">
                        Inserir o primeiro upload
                    </button>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-[11px] uppercase tracking-wider text-zinc-500">
                                <th class="px-5 sm:px-6 py-3 font-medium">Versão</th>
                                <th class="px-5 sm:px-6 py-3 font-medium">Descrição</th>
                                <th class="px-5 sm:px-6 py-3 font-medium">Data / Hora</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($uploads as $u): ?>
                            <tr>
                                <td class="px-5 sm:px-6 py-3.5"><span class="badge"><?= htmlspecialchars($u['versao']) ?></span></td>
                                <td class="px-5 sm:px-6 py-3.5 text-zinc-300 max-w-md"><?= nl2br(htmlspecialchars($u['descricao'])) ?></td>
                                <td class="px-5 sm:px-6 py-3.5 text-zinc-500 whitespace-nowrap"><?= date('d/m/Y \à\s H:i', strtotime($u['data_hora'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <div class="barber-stripe-thin"></div>
        </div>
    </section>
    <?php endif; ?>

</main>

<?php if ($tipo === 'admin'): ?>
<!-- ==================== MODAL: INSERIR UPLOAD ==================== -->
<div id="modal-upload" class="modal-overlay fixed inset-0 z-50 hidden flex items-center justify-center p-4" onclick="fecharAoClicarFora(event, 'modal-upload')">
    <div class="modal-card rounded-3xl shadow-2xl overflow-hidden w-full max-w-md">
        <div class="barber-stripe-thin"></div>
        <div class="p-7">
            <div class="flex items-start justify-between mb-6">
                <h2 class="display text-2xl text-[color:var(--cream)]">Inserir Upload</h2>
                <button type="button" onclick="closeModal('modal-upload')" class="icon-btn shrink-0">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>

            <form action="/Configuracoes/scripts/upload_salvar.php" method="POST" autocomplete="off">
                <?= csrf_field() ?>

                <div class="mb-5">
                    <label class="field-label block mb-2 uppercase" for="versao">Número da versão</label>
                    <input id="versao" name="versao" type="text" placeholder="Ex: v1.0.0 (deixe em branco para gerar automaticamente)"
                           value="<?= $reabrirUpload ? htmlspecialchars($voltaUpVersao) : '' ?>"
                           class="field w-full h-12 px-4 rounded-xl text-sm">
                    <p class="settings-desc mt-2">Se deixado em branco, a próxima versão é gerada automaticamente (ex: v1.0.0 → v1.0.0.1 → v1.0.0.2...).</p>
                </div>

                <div class="mb-5">
                    <label class="field-label block mb-2 uppercase" for="descricao">Descrição do upload</label>
                    <textarea id="descricao" name="descricao" rows="3" placeholder="O que mudou nessa versão?" required
                              class="field w-full px-4 py-3 rounded-xl text-sm resize-none"><?= $reabrirUpload ? htmlspecialchars($voltaUpDescricao) : '' ?></textarea>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-7">
                    <div>
                        <label class="field-label block mb-2 uppercase" for="data">Data</label>
                        <input id="data" name="data" type="date" required
                               value="<?= $reabrirUpload && $voltaUpData !== '' ? htmlspecialchars($voltaUpData) : date('Y-m-d') ?>"
                               class="field w-full h-12 px-4 rounded-xl text-sm">
                    </div>
                    <div>
                        <label class="field-label block mb-2 uppercase" for="hora">Hora</label>
                        <input id="hora" name="hora" type="time" required
                               value="<?= $reabrirUpload && $voltaUpHora !== '' ? htmlspecialchars($voltaUpHora) : date('H:i') ?>"
                               class="field w-full h-12 px-4 rounded-xl text-sm">
                    </div>
                </div>

                <div class="flex gap-3">
                    <button type="submit" class="btn-primary h-12 px-6 rounded-xl text-sm flex-1">
                        Salvar Upload
                    </button>
                    <button type="button" onclick="closeModal('modal-upload')" class="btn-secondary h-12 px-6 rounded-xl text-sm">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
        <div class="barber-stripe-thin"></div>
    </div>
</div>
<?php endif; ?>

<script>
function openModal(id) {
    document.getElementById(id).classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
}

function closeModal(id) {
    document.getElementById(id).classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
}

function fecharAoClicarFora(evento, id) {
    if (evento.target.id === id) {
        closeModal(id);
    }
}

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        var modalUpload = document.getElementById('modal-upload');
        if (modalUpload) closeModal('modal-upload');
    }
});

function definirTema(tema) {
    if (tema === 'light') {
        document.documentElement.setAttribute('data-theme', 'light');
        localStorage.setItem('ab_theme', 'light');
    } else {
        document.documentElement.removeAttribute('data-theme');
        localStorage.setItem('ab_theme', 'dark');
    }
    atualizarSelecaoTema();
}

function atualizarSelecaoTema() {
    var atual = document.documentElement.getAttribute('data-theme') === 'light' ? 'light' : 'dark';
    document.getElementById('opcao-tema-escuro').classList.toggle('theme-option-active', atual === 'dark');
    document.getElementById('opcao-tema-claro').classList.toggle('theme-option-active', atual === 'light');
}

function alternarSom(ativado) {
    localStorage.setItem('ab_som', ativado ? '1' : '0');
}

document.addEventListener('DOMContentLoaded', function () {
    atualizarSelecaoTema();

    var somAtivo = localStorage.getItem('ab_som');
    // Som ativado por padrão, a menos que o usuário já tenha desativado antes
    document.getElementById('switch-som').checked = somAtivo !== '0';
});

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

<?php if ($reabrirUpload): ?>
openModal('modal-upload');
<?php endif; ?>

<?php if ($tipo === 'barbeiro'): ?>
function gerarLinkPublico(mensagemConfirmacao) {
    if (mensagemConfirmacao && !confirm(mensagemConfirmacao)) {
        return;
    }
    var formData = new URLSearchParams();
    formData.set('_csrf', <?= json_encode(csrf_token()) ?>);

    fetch('/Configuracoes/scripts/link_publico_gerar.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: formData.toString(),
    })
        .then(function (r) { return r.json(); })
        .then(function (resposta) {
            if (!resposta.ok) {
                toast(resposta.erro || 'Não foi possível gerar o link agora.', 'erro');
                return;
            }
            var url = window.location.protocol + '//' + window.location.host + '/c/agendar/' + resposta.token;
            document.getElementById('input-link-publico').value = url;
            document.getElementById('link-publico-vazio').classList.add('hidden');
            document.getElementById('link-publico-preenchido').classList.remove('hidden');
            toast('Link de agendamento gerado com sucesso!');
        })
        .catch(function () {
            toast('Erro de conexão. Tente novamente.', 'erro');
        });
}

document.getElementById('btn-gerar-link').addEventListener('click', function () {
    gerarLinkPublico(null);
});
document.getElementById('btn-gerar-novo-link').addEventListener('click', function () {
    gerarLinkPublico('O link atual vai parar de funcionar. Gerar um novo mesmo assim?');
});
document.getElementById('btn-copiar-link').addEventListener('click', function () {
    var campo = document.getElementById('input-link-publico');
    campo.select();
    navigator.clipboard.writeText(campo.value).then(function () {
        toast('Link copiado!');
    }).catch(function () {
        document.execCommand('copy');
        toast('Link copiado!');
    });
});
document.getElementById('btn-copiar-link-geral').addEventListener('click', function () {
    var campo = this.parentElement.querySelector('input');
    campo.select();
    navigator.clipboard.writeText(campo.value).then(function () {
        toast('Link copiado!');
    }).catch(function () {
        document.execCommand('copy');
        toast('Link copiado!');
    });
});
<?php endif; ?>
</script>

</body>
</html>
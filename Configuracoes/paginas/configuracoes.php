<?php
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/guard.php';
exigirSessao(['admin', 'barbeiro']);
require_once __DIR__ . '/../../includes/csrf.php';
$tipo = $_SESSION['tipo'] ?? null;
$paginaAtual = 'configuracoes';

// ---------- Comissão dos Barbeiros (Proprietário e Administrador) ----------
// Porcentagem que cada FUNCIONÁRIO recebe por serviço concluído a partir de
// agora (comissões já geradas nunca mudam). Ver includes/ComissaoService.php.
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/AcessoService.php';
$podeConfigurarComissao = $tipo === 'admin' || AcessoService::ehProprietario($pdo);
$comissaoPercentual = $podeConfigurarComissao ? ComissaoService::percentualAtual($pdo) : null;
$comissaoStatusGet = $_GET['comissao'] ?? '';

// ---------- Cor da barbearia (Proprietário e Administrador) ----------
// Cor de destaque do sistema interno, por barbearia (ver includes/TemaService.php).
require_once __DIR__ . '/../../includes/TemaService.php';
require_once __DIR__ . '/../../includes/CatalogoService.php';
$podePersonalizarCor = $podeConfigurarComissao;
$corTema        = TemaService::corAtual($pdo);
$corTemaPadrao  = TemaService::COR_PADRAO;
$temaStatusGet  = $_GET['tema'] ?? '';

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
<link rel="stylesheet" href="/assets/css/admin-theme.css?v=3">

<style>
    .modal-overlay{
        background:rgba(0,0,0,0.7);
        backdrop-filter:blur(3px);
    }
    .modal-card{
        background:linear-gradient(180deg, var(--charcoal-2), var(--charcoal-3));
        border:1px solid rgba(var(--accent-rgb),0.16);
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
        color:var(--accent-strong);
        background:rgba(var(--accent-rgb),0.12);
        border:1px solid rgba(var(--accent-rgb),0.4);
    }
    table tbody tr{
        border-top:1px solid var(--line);
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
            <p class="eyebrow uppercase mb-1" style="color:var(--accent-strong); opacity:.75">Preferências</p>
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
                        <div class="theme-swatch mb-3" style="background:linear-gradient(180deg,var(--surface-2),#0a0e1a);"></div>
                        <p class="settings-label">Escuro</p>
                        <p class="settings-desc">Padrão do painel</p>
                    </button>

                    <button type="button" id="opcao-tema-claro" onclick="definirTema('light')"
                            class="theme-option text-left">
                        <div class="theme-swatch mb-3" style="background:linear-gradient(180deg,#ffffff,var(--text-soft));"></div>
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
                        <input id="senha_nova" name="senha_nova" type="password" required minlength="8"
                               class="field w-full h-12 px-4 rounded-xl text-sm">
                    </div>

                    <div>
                        <label class="field-label block mb-2 uppercase" for="senha_confirma">Confirmar nova senha</label>
                        <input id="senha_confirma" name="senha_confirma" type="password" required minlength="8"
                               class="field w-full h-12 px-4 rounded-xl text-sm">
                    </div>
                </div>

                <button type="submit" class="btn-primary h-12 rounded-xl text-sm mt-2">
                    Salvar nova senha
                </button>

            </form>
        </div>

    </section>

    <?php if ($podeConfigurarComissao): ?>
    <?php if ($comissaoStatusGet === 'ok' || $comissaoStatusGet === 'erro'): ?>
        <script>document.addEventListener('DOMContentLoaded', function () { toast(<?= json_encode($comissaoStatusGet === 'ok' ? 'Comissão dos barbeiros atualizada. Vale para os próximos atendimentos.' : 'Informe uma porcentagem entre 0 e 100.') ?>, <?= json_encode($comissaoStatusGet === 'ok' ? 'sucesso' : 'erro') ?>); }); </script>
    <?php endif; ?>
    <section class="p-5 sm:p-8 pb-0 sm:pb-0 max-w-5xl">
        <div class="panel-card rounded-2xl p-5 sm:p-6">
            <p class="text-sm font-semibold text-[color:var(--cream)] mb-1">Comissão dos Barbeiros</p>
            <p class="settings-desc mb-5">Porcentagem do valor de cada serviço que o funcionário recebe. Calculada automaticamente quando o atendimento é concluído. Mudar aqui vale só para os próximos atendimentos — o que já foi lançado continua com a porcentagem da época.</p>

            <form action="/Configuracoes/scripts/comissao_salvar.php" method="POST" autocomplete="off" class="grid grid-cols-1 sm:grid-cols-[minmax(0,220px)_1fr] gap-5 items-start">
                <?= csrf_field() ?>
                <div>
                    <label class="field-label block mb-2 uppercase" for="comissao_percentual">Comissão (%)</label>
                    <div class="relative">
                        <input id="comissao_percentual" name="comissao_percentual" type="number" inputmode="decimal"
                               min="0" max="100" step="0.01" required
                               value="<?= htmlspecialchars(rtrim(rtrim(number_format((float) $comissaoPercentual, 2, '.', ''), '0'), '.'), ENT_QUOTES, 'UTF-8') ?>"
                               class="field w-full h-12 pl-4 pr-10 rounded-xl text-sm">
                        <span class="absolute right-4 top-1/2 -translate-y-1/2 text-sm text-zinc-500 pointer-events-none">%</span>
                    </div>
                </div>
                <div class="rounded-xl p-4 text-sm" style="background:rgba(var(--accent-rgb),0.08); border:1px solid rgba(var(--accent-rgb),0.22);">
                    <p class="settings-label mb-1">Exemplo com um serviço de R$ 55,00</p>
                    <p class="settings-desc">Funcionário: <strong id="comissao-ex-func" class="text-[color:var(--cream)]">—</strong> &nbsp;·&nbsp; Barbearia: <strong id="comissao-ex-casa" class="text-[color:var(--cream)]">—</strong></p>
                    <p class="settings-desc mt-2">Atendimentos do proprietário não geram comissão.</p>
                </div>
                <div class="sm:col-span-2">
                    <button type="submit" class="btn-primary h-12 px-6 rounded-xl text-sm">Salvar comissão</button>
                </div>
            </form>
        </div>
    </section>
    <script>
        (function () {
            var campo = document.getElementById('comissao_percentual');
            function moeda(v) { return 'R$ ' + v.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.'); }
            function atualizar() {
                var p = parseFloat(String(campo.value).replace(',', '.'));
                if (isNaN(p) || p < 0 || p > 100) { document.getElementById('comissao-ex-func').textContent = '—'; document.getElementById('comissao-ex-casa').textContent = '—'; return; }
                var f = Math.round(55 * p) / 100;
                document.getElementById('comissao-ex-func').textContent = moeda(f);
                document.getElementById('comissao-ex-casa').textContent = moeda(55 - f);
            }
            campo.addEventListener('input', atualizar);
            atualizar();
        })();
    </script>
    <?php endif; ?>

    <?php if ($podePersonalizarCor): ?>
    <?php
        $mensagensTema = [
            'ok'         => ['sucesso', 'Cor da barbearia atualizada para todos os usuários.'],
            'restaurado' => ['sucesso', 'Cor padrão restaurada.'],
            'erro'       => ['erro', 'Cor inválida. Escolha uma cor no formato #RRGGBB.'],
            'falha'      => ['erro', 'Não foi possível salvar a cor agora. Tente novamente.'],
        ];
        if (isset($mensagensTema[$temaStatusGet])):
    ?>
        <script>document.addEventListener('DOMContentLoaded', function () { toast(<?= json_encode($mensagensTema[$temaStatusGet][1]) ?>, <?= json_encode($mensagensTema[$temaStatusGet][0]) ?>); }); </script>
    <?php endif; ?>
    <section class="p-5 sm:p-8 pb-0 sm:pb-0 max-w-5xl" id="cor-barbearia">
        <div class="panel-card rounded-2xl p-5 sm:p-6">
            <p class="text-sm font-semibold text-[color:var(--cream)] mb-1">Cor da barbearia</p>
            <p class="settings-desc mb-5">Escolha a cor de destaque do sistema (botões, menu, indicadores). Vale para todos os usuários desta barbearia e fica salva — o escuro/claro de cada pessoa continua separado. O contraste dos textos é ajustado automaticamente para manter a leitura.</p>

            <form action="/Configuracoes/scripts/tema_salvar.php" method="POST" autocomplete="off" id="form-cor-barbearia" class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_minmax(0,320px)] gap-6 items-start">
                <?= csrf_field() ?>
                <input type="hidden" name="acao" value="salvar">
                <div>
                    <span class="field-label block mb-2 uppercase">Cores prontas</span>
                    <div class="cor-presets" role="radiogroup" aria-label="Cores prontas">
                        <?php foreach (CatalogoService::CORES_PRESET as $hex => $nomeCor): ?>
                            <button type="button" class="cor-preset" role="radio" aria-checked="false" data-cor="<?= htmlspecialchars($hex, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($nomeCor, ENT_QUOTES, 'UTF-8') ?>">
                                <span class="cor-preset__bolinha" style="background:<?= htmlspecialchars($hex, ENT_QUOTES, 'UTF-8') ?>"></span>
                                <span class="cor-preset__nome"><?= htmlspecialchars($nomeCor, ENT_QUOTES, 'UTF-8') ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <label class="field-label block mt-5 mb-2 uppercase" for="cor-hex">Outra cor</label>
                    <div class="flex items-center gap-3">
                        <input type="color" id="cor-seletor" value="<?= htmlspecialchars($corTema, ENT_QUOTES, 'UTF-8') ?>" class="cor-seletor" aria-label="Escolher cor">
                        <input type="text" id="cor-hex" name="cor" value="<?= htmlspecialchars($corTema, ENT_QUOTES, 'UTF-8') ?>" maxlength="7" inputmode="text" spellcheck="false" autocapitalize="off"
                               pattern="#[0-9A-Fa-f]{6}" class="field h-12 w-40 px-4 rounded-xl text-sm uppercase" aria-describedby="cor-ajuda">
                        <span id="cor-ajuda" class="settings-desc">Formato #RRGGBB</span>
                    </div>
                    <p id="cor-erro" class="cor-erro hidden" role="alert">Cor inválida. Use o formato #RRGGBB (ex.: #C9A14A).</p>
                </div>

                <div class="cor-previa" aria-live="polite">
                    <p class="settings-label mb-3">Prévia</p>
                    <div class="flex flex-wrap items-center gap-3 mb-4">
                        <button type="button" class="btn-primary h-10 px-4 rounded-xl text-sm" tabindex="-1">Botão principal</button>
                        <button type="button" class="btn-secondary h-10 px-4 rounded-xl text-sm" tabindex="-1">Secundário</button>
                    </div>
                    <div class="flex flex-wrap items-center gap-3 mb-4">
                        <span class="badge badge-info">Destaque</span>
                        <a href="#cor-barbearia" class="cor-previa__link" tabindex="-1">Link de exemplo</a>
                    </div>
                    <div class="cor-previa__barra"><span></span></div>
                    <p class="settings-desc mt-3" id="cor-contraste">—</p>
                </div>

                <div class="lg:col-span-2 flex flex-wrap items-center gap-3">
                    <button type="submit" class="btn-primary h-12 px-6 rounded-xl text-sm" id="cor-salvar">Salvar cor</button>
                    <button type="button" class="btn-secondary h-12 px-5 rounded-xl text-sm" id="cor-desfazer">Desfazer prévia</button>
                    <button type="submit" form="form-cor-restaurar" class="btn-secondary h-12 px-5 rounded-xl text-sm" id="cor-restaurar" <?= $corTema === $corTemaPadrao ? 'disabled' : '' ?>>Restaurar cor padrão</button>
                </div>
            </form>
            <form action="/Configuracoes/scripts/tema_salvar.php" method="POST" id="form-cor-restaurar" class="hidden">
                <?= csrf_field() ?>
                <input type="hidden" name="acao" value="restaurar">
            </form>
        </div>
    </section>
    <script src="/assets/js/tema-cor.js?v=2"></script>
    <script>
        (function () {
            var salva = <?= json_encode($corTema) ?>;
            var padrao = <?= json_encode($corTemaPadrao) ?>;
            var hex = document.getElementById('cor-hex');
            var seletor = document.getElementById('cor-seletor');
            var erro = document.getElementById('cor-erro');
            var contraste = document.getElementById('cor-contraste');
            var salvar = document.getElementById('cor-salvar');
            var presets = Array.prototype.slice.call(document.querySelectorAll('.cor-preset'));

            function marcarPresets(cor) {
                presets.forEach(function (b) {
                    var on = b.getAttribute('data-cor').toLowerCase() === cor;
                    b.classList.toggle('is-ativo', on);
                    b.setAttribute('aria-checked', on ? 'true' : 'false');
                });
            }
            function atualizar(cor, origem) {
                var v = TemaCor.validar(cor);
                if (v === null) {
                    erro.classList.remove('hidden');
                    hex.setAttribute('aria-invalid', 'true');
                    salvar.disabled = true;
                    return;
                }
                erro.classList.add('hidden');
                hex.removeAttribute('aria-invalid');
                salvar.disabled = false;
                if (origem !== 'hex') hex.value = v;
                if (origem !== 'seletor') seletor.value = v;
                TemaCor.aplicarPrevia(v);
                marcarPresets(v);
                var escuro = document.documentElement.getAttribute('data-theme') !== 'light';
                var t = TemaCor.tokens(v, escuro);
                var c = TemaCor.contraste(t['accent-on'], t['accent']);
                contraste.textContent = 'Texto sobre a cor: contraste ' + c.toFixed(1).replace('.', ',') + ':1' + (c >= 4.5 ? ' (ótimo)' : c >= 3 ? ' (bom para textos em negrito)' : ' (baixo)');
            }
            presets.forEach(function (b) { b.addEventListener('click', function () { atualizar(b.getAttribute('data-cor'), 'preset'); }); });
            seletor.addEventListener('input', function () { atualizar(seletor.value, 'seletor'); });
            hex.addEventListener('input', function () { atualizar(hex.value, 'hex'); });
            document.getElementById('cor-desfazer').addEventListener('click', function () {
                TemaCor.limparPrevia();
                hex.value = salva; seletor.value = salva;
                erro.classList.add('hidden'); salvar.disabled = false;
                atualizar(salva, 'desfazer');
                if (salva === padrao) { TemaCor.limparPrevia(); }
            });
            // Estado inicial: sem alterar a página (já vem com a cor salva do servidor).
            marcarPresets(salva);
            var t0 = TemaCor.tokens(salva, document.documentElement.getAttribute('data-theme') !== 'light');
            var c0 = TemaCor.contraste(t0['accent-on'], t0['accent']);
            contraste.textContent = 'Texto sobre a cor: contraste ' + c0.toFixed(1).replace('.', ',') + ':1' + (c0 >= 4.5 ? ' (ótimo)' : c0 >= 3 ? ' (bom para textos em negrito)' : ' (baixo)');
        })();
    </script>
    <?php endif; ?>

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
                <button type="button" id="btn-gerar-novo-link" class="text-xs mt-3" style="color:var(--text-muted); text-decoration:underline;">
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
    background: 'var(--surface-2)',
    color: 'var(--cream)',
    confirmButtonColor: 'var(--accent)',
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
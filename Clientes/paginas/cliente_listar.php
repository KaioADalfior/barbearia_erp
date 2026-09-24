<?php
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/guard.php';
exigirSessao(['barbeiro']);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/csrf.php';

$paginaAtual = 'cliente-listar';

// ---------- Busca todos os clientes ----------
$stmt = $pdo->query('SELECT idCliente, nome, telefone, email, ativo, criado_em FROM Cliente ORDER BY nome ASC');
$clientes = $stmt->fetchAll();

// ---------- Mensagens de status (retorno dos scripts) ----------
$statusGet = $_GET['status'] ?? '';

// ---------- Reabertura automática de modal em caso de erro de validação ----------
$reabrirCadastro = in_array($statusGet, ['cadastro-erro', 'cadastro-email-invalido'], true);
$reabrirEdicao   = in_array($statusGet, ['edicao-erro', 'edicao-email-invalido'], true);

$voltaNome     = $_GET['nome'] ?? '';
$voltaTelefone = $_GET['telefone'] ?? '';
$voltaEmail    = $_GET['email'] ?? '';
$voltaEditId   = $_GET['edit_id'] ?? '';
$voltaAtivo    = $_GET['ativo'] ?? '1';

$mensagens = [
    'cadastro-sucesso'        => ['ok',   $voltaNome !== '' ? "Cliente $voltaNome cadastrado." : 'Cliente cadastrado com sucesso.'],
    'cadastro-erro'           => ['erro', 'Preencha nome e telefone corretamente.'],
    'cadastro-email-invalido' => ['erro', 'Informe um e-mail válido ou deixe o campo em branco.'],
    'edicao-sucesso'          => ['ok',   $voltaNome !== '' ? "Cliente $voltaNome atualizado." : 'Cliente atualizado com sucesso.'],
    'edicao-erro'             => ['erro', 'Preencha nome e telefone corretamente.'],
    'edicao-email-invalido'   => ['erro', 'Informe um e-mail válido ou deixe o campo em branco.'],
    'inativado-sucesso'       => ['ok',   $voltaNome !== '' ? "Cliente $voltaNome inativado." : 'Cliente inativado com sucesso.'],
    'reativado-sucesso'       => ['ok',   $voltaNome !== '' ? "Cliente $voltaNome ativado." : 'Cliente reativado com sucesso.'],
    'status-erro'             => ['erro', 'Não foi possível atualizar o status do cliente.'],
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<?php include __DIR__ . '/../../includes/theme-init.php'; ?>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Clientes — Alex Barbearia</title>

<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../../assets/css/admin-theme.css">

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
    }
    .badge-ativo{
        color:#bfe6c7;
        background:rgba(66,140,82,0.14);
        border:1px solid rgba(66,140,82,0.4);
    }
    .badge-inativo{
        color:#c9a8ab;
        background:rgba(140,31,40,0.12);
        border:1px solid rgba(140,31,40,0.4);
    }
    .icon-btn{
        display:inline-flex;
        align-items:center;
        justify-content:center;
        width:34px;
        height:34px;
        border-radius:0.65rem;
        border:1px solid rgba(255,255,255,0.08);
        background:rgba(255,255,255,0.03);
        color:#8fa0bd;
        transition:color .15s, border-color .15s, background-color .15s;
    }
    .icon-btn:hover{
        color:var(--gold-light);
        border-color:rgba(61,126,201,0.4);
        background:rgba(61,126,201,0.08);
    }
    .icon-btn-danger:hover{
        color:#f0a2a8;
        border-color:rgba(140,31,40,0.5);
        background:rgba(140,31,40,0.1);
    }
    table tbody tr{
        border-top:1px solid rgba(255,255,255,0.05);
    }

    /* Tabela exibida em ~80% de escala (mesmo efeito de zoom do navegador),
       deixando a listagem mais compacta sem perder a proporção do layout. */
    .table-80-wrap{
        zoom: 0.8;
    }
    @supports not (zoom: 1) {
        /* Fallback para navegadores sem suporte a "zoom" (ex: Firefox antigo) */
        .table-80-wrap{
            transform: scale(0.8);
            transform-origin: top left;
            width: 125%;
        }
    }
    .view-row{
        display:flex;
        justify-content:space-between;
        gap:1rem;
        padding:0.85rem 0;
        border-top:1px solid rgba(255,255,255,0.06);
    }
    .view-row:first-child{ border-top:none; }
    .view-label{
        font-size:11px;
        letter-spacing:0.1em;
        text-transform:uppercase;
        color:#7f8fac;
    }
    .view-value{
        font-size:14px;
        color:var(--cream);
        text-align:right;
    }

    /* ---------- Modal de detalhes: tamanho fixo (não acompanha o conteúdo da aba) ---------- */
    .modal-card-detalhes{
        height:min(680px, 90vh);
    }
    @media (max-width:640px){
        .modal-card-detalhes{
            height:min(600px, 88vh);
        }
    }

    /* ---------- Modal de detalhes: abas ---------- */
    .cli-tabs{
        display:flex;
        gap:0.25rem;
        overflow-x:auto;
        border-bottom:1px solid rgba(255,255,255,0.08);
        margin:0 -1.75rem 1.25rem;
        padding:0 1.75rem;
    }
    .cli-tab{
        flex-shrink:0;
        display:inline-flex;
        align-items:center;
        gap:0.4rem;
        font-size:12.5px;
        font-weight:600;
        letter-spacing:0.02em;
        color:#7f8fac;
        padding:0.7rem 0.15rem;
        border-bottom:2px solid transparent;
        margin-right:1.1rem;
        cursor:pointer;
        background:none;
        border-top:none;
        border-left:none;
        border-right:none;
        white-space:nowrap;
        transition:color .15s, border-color .15s;
    }
    .cli-tab:hover{ color:var(--cream); }
    .cli-tab.is-active{
        color:var(--gold-light);
        border-bottom-color:var(--gold);
    }
    .cli-tab:disabled{
        opacity:0.35;
        cursor:not-allowed;
    }
    .cli-tab:disabled:hover{ color:#7f8fac; }
    .cli-tab-badge{
        display:inline-flex;
        align-items:center;
        justify-content:center;
        min-width:17px;
        height:17px;
        padding:0 5px;
        border-radius:999px;
        background:rgba(61,126,201,0.16);
        color:var(--gold-light);
        font-size:10px;
        font-weight:700;
    }
    .cli-tab-panel{ display:none; }
    .cli-tab-panel.is-active{ display:block; }
    .cli-scroll{
        max-height:340px;
        overflow-y:auto;
        overflow-x:hidden;
        padding-right:2px;
    }
    .cli-item{
        background:rgba(255,255,255,0.025);
        border:1px solid rgba(255,255,255,0.06);
        border-radius:0.85rem;
        padding:0.85rem 1rem;
        margin-bottom:0.6rem;
        max-width:100%;
        overflow-wrap:anywhere;
    }
    .cli-item:last-child{ margin-bottom:0; }
    .cli-item-top{
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:0.5rem;
        margin-bottom:0.3rem;
    }
    .cli-item-title{
        font-size:13.5px;
        font-weight:600;
        color:var(--cream);
        min-width:0;
        overflow-wrap:anywhere;
    }
    .cli-item-meta{
        font-size:11px;
        color:#7f8fac;
    }
    .cli-item-desc{
        font-size:13px;
        color:#aebdd6;
        line-height:1.5;
        overflow-wrap:anywhere;
        word-break:break-word;
        white-space:pre-wrap;
    }
    .cli-badge-tipo{
        display:inline-flex;
        align-items:center;
        font-size:10px;
        font-weight:700;
        letter-spacing:0.04em;
        text-transform:uppercase;
        padding:0.2rem 0.55rem;
        border-radius:999px;
        white-space:nowrap;
    }
    .cli-badge-fiado{ color:#b8b3f0; background:rgba(99,91,220,0.16); border:1px solid rgba(99,91,220,0.4); }
    .cli-badge-pagamento{ color:#bfe6c7; background:rgba(66,140,82,0.14); border:1px solid rgba(66,140,82,0.4); }
    .cli-badge-observacao{ color:#7fd0d9; background:rgba(15,92,102,0.28); border:1px solid rgba(15,92,102,0.6); }
    .cli-badge-atendimento{ color:#bfe6c7; background:rgba(66,140,82,0.14); border:1px solid rgba(66,140,82,0.4); }
    .cli-badge-outro{ color:#c3cee0; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.12); }
    .cli-badge-quitado-pendente{ color:#9dc4f0; background:rgba(61,126,201,0.14); border:1px solid rgba(61,126,201,0.4); }
    .cli-badge-quitado-pago{ color:#bfe6c7; background:rgba(66,140,82,0.14); border:1px solid rgba(66,140,82,0.4); }
    .cli-badge-status-agendado   { color:#7fd0d9; background:rgba(15,92,102,0.28);  border:1px solid rgba(15,92,102,0.6); }
    .cli-badge-status-confirmado { color:#7fd0d9; background:rgba(15,92,102,0.16); border:1px solid rgba(15,92,102,0.4); }
    .cli-badge-status-concluido  { color:#bfe6c7; background:rgba(66,140,82,0.14); border:1px solid rgba(66,140,82,0.4); }
    .cli-badge-status-cancelado  { color:#c9a8ab; background:rgba(140,31,40,0.12); border:1px solid rgba(140,31,40,0.4); }
    .cli-vazio{
        text-align:center;
        padding:2rem 1rem;
        color:#7f8fac;
        font-size:13px;
    }
    .cli-em-breve{
        text-align:center;
        padding:3rem 1.5rem;
        color:#7f8fac;
        font-size:13px;
    }

    /* ---------- Tema claro: as cores acima foram pensadas para o fundo
       escuro original (tons pastéis/claros) e ficam com contraste muito
       baixo sobre fundo branco/creme. Reforçamos com tons mais escuros
       quando o tema claro está ativo. ---------- */
    html[data-theme="light"] .badge-ativo{ color:#1f6b30; }
    html[data-theme="light"] .badge-inativo{ color:#8c1f28; }
    html[data-theme="light"] .icon-btn{ color:#475569; }
    html[data-theme="light"] .icon-btn-danger:hover{ color:#8c1f28; }
    html[data-theme="light"] .view-label{ color:#475569; }
    html[data-theme="light"] .view-value{ color:var(--cream); }
    html[data-theme="light"] .cli-tab{ color:#475569; }
    html[data-theme="light"] .cli-tab:disabled:hover{ color:#475569; }
    html[data-theme="light"] .cli-item-meta{ color:#475569; }
    html[data-theme="light"] .cli-item-desc{ color:#334155; }
    html[data-theme="light"] .cli-badge-fiado{ color:#4b3fb0; }
    html[data-theme="light"] .cli-badge-pagamento{ color:#1f6b30; }
    html[data-theme="light"] .cli-badge-observacao{ color:#0d6b74; }
    html[data-theme="light"] .cli-badge-atendimento{ color:#1f6b30; }
    html[data-theme="light"] .cli-badge-outro{ color:#2c3e56; }
    html[data-theme="light"] .cli-badge-status-agendado{ color:#0d6b74; }
    html[data-theme="light"] .cli-badge-status-confirmado{ color:#1c3a5e; }
    html[data-theme="light"] .cli-badge-status-concluido{ color:#1f6b30; }
    html[data-theme="light"] .cli-badge-status-cancelado{ color:#8c1f28; }
    html[data-theme="light"] .cli-vazio{ color:#475569; }
    html[data-theme="light"] .cli-em-breve{ color:#475569; }
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
            <p class="eyebrow uppercase mb-1" style="color:var(--gold-light); opacity:.75">Clientes</p>
            <h1 class="display text-3xl sm:text-4xl text-[color:var(--cream)] truncate">Gerenciar Clientes</h1>
        </div>
    </header>

    <section class="p-5 sm:p-8">

        <?php if (isset($mensagens[$statusGet])): [$tipoMsg, $textoMsg] = $mensagens[$statusGet]; ?>
            <script>toast(<?= json_encode($textoMsg) ?>, <?= json_encode($tipoMsg === 'ok' ? 'sucesso' : 'erro') ?>);</script>
        <?php endif; ?>

        <!-- Barra de ferramentas: busca + cadastrar -->
        <div class="panel-card rounded-2xl p-4 mb-6 flex flex-col sm:flex-row gap-3 sm:items-center sm:justify-between">

            <div class="relative w-full sm:max-w-sm">
                <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="11" cy="11" r="6.5" stroke="#7f8fac" stroke-width="1.5"/>
                    <path d="M20 20l-4.3-4.3" stroke="#7f8fac" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
                <input
                    id="busca-cliente"
                    type="text"
                    placeholder="Buscar por nome, telefone, e-mail ou status..."
                    autocomplete="off"
                    class="field w-full h-11 pl-10 pr-4 rounded-xl text-sm"
                    oninput="filtrarClientes(this.value)"
                >
            </div>

            <button type="button" onclick="abrirClienteModalNovo()" class="btn-primary h-11 px-5 rounded-xl text-sm flex items-center justify-center gap-2 shrink-0">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 5v14M5 12h14" stroke="#ffffff" stroke-width="2" stroke-linecap="round"/>
                </svg>
                Cadastrar Cliente
            </button>
        </div>

        <?php if (empty($clientes)): ?>

            <div class="panel-card rounded-2xl p-10 text-center">
                <p class="text-sm text-zinc-400">Nenhum cliente cadastrado ainda.</p>
                <button type="button" onclick="abrirClienteModalNovo()" class="btn-secondary h-10 px-5 rounded-xl text-sm mt-4 inline-flex items-center">
                    Cadastrar o primeiro cliente
                </button>
            </div>

        <?php else: ?>

            <div class="panel-card rounded-2xl overflow-hidden">
                <div class="barber-stripe-thin"></div>

                <div class="overflow-x-auto">
                    <div class="table-80-wrap">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-[11px] uppercase tracking-wider text-zinc-500">
                                <th class="px-6 py-4 font-medium">Nome</th>
                                <th class="px-6 py-4 font-medium">Contato</th>
                                <th class="px-6 py-4 font-medium">Status</th>
                                <th class="px-6 py-4 font-medium">Cadastrado em</th>
                                <th class="px-6 py-4 font-medium text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-clientes">
                            <?php foreach ($clientes as $c):
                                $ativo   = (int) $c['ativo'] === 1;
                                $nome    = $c['nome'];
                                $tel     = $c['telefone'];
                                $email   = $c['email'] ?? '';
                                $criado  = $c['criado_em'] ? date('d/m/Y', strtotime($c['criado_em'])) : '—';
                                $statusTxt = $ativo ? 'ativo' : 'inativo';
                                $textoBusca = $c['idCliente'] . ' ' . $nome . ' ' . $tel . ' ' . $email . ' ' . $statusTxt . ' ' . $criado;
                                $busca = function_exists('mb_strtolower')
                                    ? mb_strtolower($textoBusca, 'UTF-8')
                                    : strtolower($textoBusca);
                            ?>
                            <tr data-busca="<?= htmlspecialchars($busca) ?>">
                                <td class="px-6 py-4 text-[color:var(--cream)] font-medium"><?= htmlspecialchars($nome) ?></td>
                                <td class="px-6 py-4 text-zinc-400">
                                    <div><?= htmlspecialchars($tel) ?></div>
                                    <?php if ($email !== ''): ?>
                                        <div class="text-xs text-zinc-500"><?= htmlspecialchars($email) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if ($ativo): ?>
                                        <span class="badge badge-ativo">Ativo</span>
                                    <?php else: ?>
                                        <span class="badge badge-inativo">Inativo</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-zinc-500"><?= htmlspecialchars($criado) ?></td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center justify-end gap-2">

                                        <button type="button" title="Detalhes" class="icon-btn"
                                            data-id="<?= (int) $c['idCliente'] ?>"
                                            data-nome="<?= htmlspecialchars($nome) ?>"
                                            data-telefone="<?= htmlspecialchars($tel) ?>"
                                            data-email="<?= htmlspecialchars($email) ?>"
                                            data-ativo="<?= $ativo ? '1' : '0' ?>"
                                            data-criado="<?= htmlspecialchars($criado) ?>"
                                            onclick="abrirClienteModal(this)">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/>
                                                <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.4"/>
                                            </svg>
                                        </button>

                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>

                            <tr id="linha-sem-resultado" class="hidden">
                                <td colspan="5" class="px-6 py-10 text-center text-zinc-500 text-sm">
                                    Nenhum cliente encontrado para essa busca.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    </div>
                </div>

                <div class="barber-stripe-thin"></div>
            </div>

        <?php endif; ?>

    </section>

</main>


<!-- ==================== MODAL: CLIENTE (novo / detalhes / edição, com abas) ==================== -->
<div id="modal-visualizar" class="modal-overlay fixed inset-0 z-50 hidden flex items-center justify-center p-4" onclick="fecharAoClicarFora(event, 'modal-visualizar')">
    <div class="modal-card modal-card-detalhes rounded-3xl shadow-2xl overflow-hidden w-full max-w-2xl flex flex-col">
        <div class="barber-stripe-thin shrink-0"></div>

        <div class="p-7 pb-0 shrink-0">
            <div class="flex items-start justify-between mb-4">
                <div class="min-w-0">
                    <h2 class="display text-2xl text-[color:var(--cream)] truncate" id="cliente-modal-titulo">Novo Cliente</h2>
                    <p class="text-xs text-zinc-500 mt-1" id="cliente-modal-subtitulo">Preencha os dados abaixo</p>
                </div>
                <button type="button" onclick="closeModal('modal-visualizar')" class="icon-btn shrink-0">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>

            <!-- Barra de ações -->
            <div class="flex gap-2 mb-5">
                <button type="button" onclick="abrirClienteModalNovo()" class="btn-secondary h-10 px-4 rounded-xl text-sm">
                    Novo
                </button>
                <button type="submit" form="form-cliente" class="btn-primary h-10 px-4 rounded-xl text-sm">
                    Salvar
                </button>
                <button type="button" onclick="closeModal('modal-visualizar')" class="btn-secondary h-10 px-4 rounded-xl text-sm">
                    Cancelar
                </button>
            </div>

            <!-- Abas -->
            <div class="cli-tabs">
                <button type="button" class="cli-tab is-active" data-tab="cli-tab-dados" onclick="cliTrocarAba('cli-tab-dados', this)">
                    Cliente
                </button>
                <button type="button" class="cli-tab" data-tab="cli-tab-agendamentos" id="cli-tab-btn-agendamentos" onclick="cliTrocarAba('cli-tab-agendamentos', this)">
                    Agendamentos
                    <span class="cli-tab-badge" id="cli-badge-agendamentos">0</span>
                </button>
                <button type="button" class="cli-tab" data-tab="cli-tab-historico" id="cli-tab-btn-historico" onclick="cliTrocarAba('cli-tab-historico', this)">
                    Histórico
                    <span class="cli-tab-badge" id="cli-badge-historico">0</span>
                </button>
                <button type="button" class="cli-tab" data-tab="cli-tab-fidelidade" onclick="cliTrocarAba('cli-tab-fidelidade', this)">
                    Fidelidade
                </button>
            </div>
        </div>

        <div class="px-7 pb-7 overflow-y-auto flex-1 min-h-0">

            <!-- ---- Aba: Cliente ---- -->
            <div id="cli-tab-dados" class="cli-tab-panel is-active">
                <form id="form-cliente" action="../scripts/cliente_salvar.php" method="POST" autocomplete="off" onsubmit="return cliPrepararEnvioForm()">
                    <?= csrf_field() ?>
                    <input type="hidden" id="cliente-id" name="id" value="<?= $reabrirEdicao ? htmlspecialchars($voltaEditId) : '' ?>">

                    <div class="mb-5">
                        <label class="field-label block mb-2 uppercase" for="cliente-nome">Nome</label>
                        <input id="cliente-nome" name="nome" type="text" placeholder="Nome completo" required
                               value="<?= ($reabrirCadastro || $reabrirEdicao) ? htmlspecialchars($voltaNome) : '' ?>"
                               class="field w-full h-12 px-4 rounded-xl text-sm">
                    </div>

                    <div class="mb-5">
                        <label class="field-label block mb-2 uppercase" for="cliente-telefone">Telefone</label>
                        <input id="cliente-telefone" name="telefone" type="text" placeholder="(27) 90000-0000" required
                               value="<?= ($reabrirCadastro || $reabrirEdicao) ? htmlspecialchars($voltaTelefone) : '' ?>"
                               class="field w-full h-12 px-4 rounded-xl text-sm">
                    </div>

                    <div class="mb-5">
                        <label class="field-label block mb-2 uppercase" for="cliente-email">E-mail <span class="normal-case text-zinc-500">(opcional)</span></label>
                        <input id="cliente-email" name="email" type="email" placeholder="cliente@email.com"
                               value="<?= ($reabrirCadastro || $reabrirEdicao) ? htmlspecialchars($voltaEmail) : '' ?>"
                               class="field w-full h-12 px-4 rounded-xl text-sm">
                    </div>

                    <div class="mb-5" id="cliente-campo-status">
                        <label class="field-label block mb-2 uppercase">Status</label>
                        <div class="flex gap-5">
                            <label class="flex items-center gap-2 text-sm text-zinc-300 cursor-pointer">
                                <input type="radio" id="cliente-ativo-sim" name="ativo" value="1"
                                       <?= (!($reabrirCadastro || $reabrirEdicao) || $voltaAtivo === '1') ? 'checked' : '' ?>
                                       style="accent-color: var(--gold);" class="w-4 h-4">
                                Ativo
                            </label>
                            <label class="flex items-center gap-2 text-sm text-zinc-300 cursor-pointer">
                                <input type="radio" id="cliente-ativo-nao" name="ativo" value="0"
                                       <?= (($reabrirCadastro || $reabrirEdicao) && $voltaAtivo === '0') ? 'checked' : '' ?>
                                       style="accent-color: var(--gold);" class="w-4 h-4">
                                Inativo
                            </label>
                        </div>
                    </div>

                    <div class="view-row" id="cliente-campo-criado" style="display:none;">
                        <span class="view-label">Cadastrado em</span>
                        <span class="view-value" id="view-criado">—</span>
                    </div>
                </form>
            </div>

            <!-- ---- Aba: Agendamentos ---- -->
            <div id="cli-tab-agendamentos" class="cli-tab-panel">
                <div class="cli-scroll" id="cli-lista-agendamentos">
                    <p class="cli-vazio">Carregando agendamentos...</p>
                </div>
            </div>

            <!-- ---- Aba: Histórico ---- -->
            <div id="cli-tab-historico" class="cli-tab-panel">

                <form id="form-historico" class="cli-item mb-5" onsubmit="cliSalvarHistorico(event)">
                    <?= csrf_field() ?>
                    <input type="hidden" id="historico-id-cliente" name="idCliente" value="">

                    <div class="mb-4">
                        <label class="field-label block mb-2 uppercase" for="historico-tipo">Tipo</label>
                        <select id="historico-tipo" name="tipo" class="field w-full h-11 px-3 rounded-xl text-sm" onchange="cliAtualizarCampoValor()">
                            <option value="fiado">Fiado</option>
                            <option value="observacao" selected>Observação</option>
                            <option value="atendimento">Atendimento</option>
                            <option value="outro">Outro</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="field-label block mb-2 uppercase" for="historico-descricao">Descrição</label>
                        <textarea id="historico-descricao" name="descricao" rows="2" required
                                  placeholder="Ex: Ficou de pagar o corte na próxima visita"
                                  class="field w-full px-4 py-3 rounded-xl text-sm resize-none"></textarea>
                    </div>

                    <div class="mb-4" id="historico-campo-valor">
                        <label class="field-label block mb-2 uppercase" for="historico-valor">Valor (R$)</label>
                        <input id="historico-valor" name="valor" type="text" inputmode="decimal" placeholder="0,00"
                               class="field w-full h-11 px-4 rounded-xl text-sm">
                    </div>

                    <button type="submit" id="historico-botao-salvar" class="btn-primary h-11 px-5 rounded-xl text-sm w-full">
                        Adicionar ao histórico
                    </button>
                </form>

                <div class="cli-scroll" id="cli-lista-historico">
                    <p class="cli-vazio">Carregando histórico...</p>
                </div>
            </div>

            <!-- ---- Aba: Fidelidade ---- -->
            <div id="cli-tab-fidelidade" class="cli-tab-panel">
                <div class="cli-em-breve">
                    <svg class="mx-auto mb-3" width="30" height="30" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 2l2.9 6.3 6.9.7-5.2 4.6 1.6 6.8L12 16.9l-6.2 3.5 1.6-6.8L2.2 9l6.9-.7L12 2Z" stroke="#7f8fac" stroke-width="1.4" stroke-linejoin="round"/>
                    </svg>
                    Programa de fidelidade em breve.<br>
                    Aqui o cliente vai poder acumular pontos e resgatar recompensas.
                </div>
            </div>

        </div>

        <div class="barber-stripe-thin shrink-0"></div>
    </div>
</div>

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
            closeModal('modal-visualizar');
        }
    });

    /* ===================== Modal do cliente (novo / edição, com abas) ===================== */

    function cliHabilitarAbasExtras(habilitar) {
        ['cli-tab-btn-agendamentos', 'cli-tab-btn-historico'].forEach(function (id) {
            document.getElementById(id).disabled = !habilitar;
        });
    }

    function escapeHtml(texto) {
        const div = document.createElement('div');
        div.textContent = texto ?? '';
        return div.innerHTML;
    }

    function formatarMoeda(valor) {
        if (valor === null || valor === undefined || valor === '') return null;
        return 'R$ ' + Number(valor).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function formatarDataHora(isoString) {
        if (!isoString) return '—';
        const [dataParte, horaParte] = isoString.split(' ');
        const [ano, mes, dia] = dataParte.split('-');
        const hora = horaParte ? horaParte.slice(0, 5) : '';
        return `${dia}/${mes}/${ano}${hora ? ' às ' + hora : ''}`;
    }

    function formatarData(dataIso) {
        if (!dataIso) return '—';
        const [ano, mes, dia] = dataIso.split('-');
        return `${dia}/${mes}/${ano}`;
    }

    function cliTrocarAba(idAba, botao) {
        document.querySelectorAll('.cli-tab').forEach(b => b.classList.remove('is-active'));
        document.querySelectorAll('.cli-tab-panel').forEach(p => p.classList.remove('is-active'));
        botao.classList.add('is-active');
        document.getElementById(idAba).classList.add('is-active');
    }

    function cliAtualizarCampoValor() {
        const tipo = document.getElementById('historico-tipo').value;
        const campoValor = document.getElementById('historico-campo-valor');
        const inputValor = document.getElementById('historico-valor');
        const ehFiado = tipo === 'fiado';
        campoValor.style.display = (ehFiado || tipo === 'atendimento' || tipo === 'outro' || tipo === 'observacao') ? 'block' : 'none';
        inputValor.required = ehFiado;
        inputValor.placeholder = ehFiado ? 'Valor em aberto (obrigatório)' : '0,00 (opcional)';
    }

    const rotulosTipoHistorico = {
        fiado:       { texto: 'Fiado',       classe: 'cli-badge-fiado' },
        observacao:  { texto: 'Observação',  classe: 'cli-badge-observacao' },
        atendimento: { texto: 'Atendimento', classe: 'cli-badge-atendimento' },
        outro:       { texto: 'Outro',       classe: 'cli-badge-outro' },
    };

    const rotulosStatusAgendamento = {
        agendado:   { texto: 'Agendado',   classe: 'cli-badge-status-agendado' },
        confirmado: { texto: 'Confirmado', classe: 'cli-badge-status-confirmado' },
        concluido:  { texto: 'Concluído',  classe: 'cli-badge-status-concluido' },
        cancelado:  { texto: 'Cancelado',  classe: 'cli-badge-status-cancelado' },
    };

    function renderizarAgendamentos(lista) {
        const alvo = document.getElementById('cli-lista-agendamentos');
        document.getElementById('cli-badge-agendamentos').textContent = lista.length;

        if (lista.length === 0) {
            alvo.innerHTML = '<p class="cli-vazio">Nenhum agendamento encontrado para este cliente.</p>';
            return;
        }

        alvo.innerHTML = lista.map(item => {
            const status = rotulosStatusAgendamento[item.Status] || { texto: item.Status, classe: 'cli-badge-outro' };
            const valor = formatarMoeda(item.Valor);
            return `
                <div class="cli-item">
                    <div class="cli-item-top">
                        <span class="cli-item-title">${escapeHtml(item.servico_nome)}${item.IncluirBarba == 1 ? ' + Barba' : ''}</span>
                        <span class="cli-badge-tipo ${status.classe}">${status.texto}</span>
                    </div>
                    <div class="cli-item-meta">${formatarData(item.Data)} às ${escapeHtml((item.hora || '').slice(0,5))}${valor ? ' · ' + valor : ''}</div>
                    ${item.Observacao ? `<div class="cli-item-desc mt-1">${escapeHtml(item.Observacao)}</div>` : ''}
                </div>
            `;
        }).join('');
    }

    function renderizarHistorico(lista) {
        const alvo = document.getElementById('cli-lista-historico');
        document.getElementById('cli-badge-historico').textContent = lista.length;

        if (lista.length === 0) {
            alvo.innerHTML = '<p class="cli-vazio">Nenhum histórico registrado ainda.</p>';
            return;
        }

        alvo.innerHTML = lista.map(item => {
            const tipo = rotulosTipoHistorico[item.tipo] || { texto: item.tipo, classe: 'cli-badge-outro' };
            const valor = formatarMoeda(item.valor);
            const badgeQuitado = item.tipo === 'fiado'
                ? `<span class="cli-badge-tipo ${Number(item.quitado) === 1 ? 'cli-badge-quitado-pago' : 'cli-badge-quitado-pendente'}">${Number(item.quitado) === 1 ? 'Pago' : 'Pendente'}</span>`
                : '';
            return `
                <div class="cli-item">
                    <div class="cli-item-top">
                        <span class="flex items-center gap-1.5 flex-wrap">
                            <span class="cli-badge-tipo ${tipo.classe}">${tipo.texto}</span>
                            ${badgeQuitado}
                        </span>
                        <span class="cli-item-meta">${formatarDataHora(item.criado_em)}</span>
                    </div>
                    <div class="cli-item-desc">${escapeHtml(item.descricao)}</div>
                    <div class="cli-item-meta mt-1">
                        ${valor ? valor + ' · ' : ''}${item.barbeiro_nome ? 'registrado por ' + escapeHtml(item.barbeiro_nome) : ''}
                    </div>
                </div>
            `;
        }).join('');
    }

    function cliPopularDadosBasicos(c) {
        document.getElementById('cliente-modal-titulo').textContent = c.nome;
        document.getElementById('cliente-modal-subtitulo').textContent = 'Cliente #' + c.idCliente;

        document.getElementById('cliente-id').value = c.idCliente;
        document.getElementById('cliente-nome').value = c.nome;
        document.getElementById('cliente-telefone').value = c.telefone;
        document.getElementById('cliente-email').value = c.email || '';
        document.getElementById(Number(c.ativo) === 1 ? 'cliente-ativo-sim' : 'cliente-ativo-nao').checked = true;

        document.getElementById('cliente-campo-criado').style.display = 'flex';
        document.getElementById('view-criado').textContent = c.criado_em ? formatarData(c.criado_em.slice(0, 10)) : '—';
    }

    function cliCarregarDetalhes(idCliente) {
        document.getElementById('cli-lista-agendamentos').innerHTML = '<p class="cli-vazio">Carregando agendamentos...</p>';
        document.getElementById('cli-lista-historico').innerHTML = '<p class="cli-vazio">Carregando histórico...</p>';

        fetch(`../scripts/cliente_detalhes.php?id=${encodeURIComponent(idCliente)}`)
            .then(resp => resp.json())
            .then(dados => {
                if (!dados.ok) {
                    toast(dados.erro || 'Não foi possível carregar os detalhes do cliente.', 'erro');
                    return;
                }
                if (dados.cliente) cliPopularDadosBasicos(dados.cliente);
                renderizarAgendamentos(dados.agendamentos || []);
                renderizarHistorico(dados.historico || []);

                // Alerta o barbeiro se o cliente tem fiado em aberto (saldo
                // devedor pendente de recebimento), mostrando o VALOR devido.
                if (dados.saldoFiadoPendente > 0) {
                    toast('Cliente com ' + formatarMoeda(dados.saldoFiadoPendente) + ' em aberto. Aguardando recebimento.', 'erro');
                }
            })
            .catch(() => {
                toast('Falha de conexão ao carregar os detalhes do cliente.', 'erro');
            });
    }

    function cliSalvarHistorico(evento) {
        evento.preventDefault();

        const form = document.getElementById('form-historico');
        const botao = document.getElementById('historico-botao-salvar');
        const textoOriginal = botao.textContent;
        botao.disabled = true;
        botao.textContent = 'Salvando...';

        fetch('../scripts/cliente_historico_salvar.php', {
            method: 'POST',
            body: new FormData(form),
        })
            .then(resp => resp.json())
            .then(dados => {
                if (!dados.ok) {
                    toast(dados.erro || 'Não foi possível salvar o histórico.', 'erro');
                    return;
                }

                const alvo = document.getElementById('cli-lista-historico');
                if (alvo.querySelector('.cli-vazio')) alvo.innerHTML = '';

                const tipo = rotulosTipoHistorico[dados.item.tipo] || { texto: dados.item.tipo, classe: 'cli-badge-outro' };
                const valor = formatarMoeda(dados.item.valor);
                const html = `
                    <div class="cli-item">
                        <div class="cli-item-top">
                            <span class="cli-badge-tipo ${tipo.classe}">${tipo.texto}</span>
                            <span class="cli-item-meta">${formatarDataHora(dados.item.criado_em)}</span>
                        </div>
                        <div class="cli-item-desc">${escapeHtml(dados.item.descricao)}</div>
                        <div class="cli-item-meta mt-1">
                            ${valor ? valor + ' · ' : ''}${dados.item.barbeiro_nome ? 'registrado por ' + escapeHtml(dados.item.barbeiro_nome) : ''}
                        </div>
                    </div>
                `;
                alvo.insertAdjacentHTML('afterbegin', html);

                const badge = document.getElementById('cli-badge-historico');
                badge.textContent = Number(badge.textContent) + 1;

                document.getElementById('historico-descricao').value = '';
                document.getElementById('historico-valor').value = '';
                toast('Histórico adicionado com sucesso.', 'sucesso');
            })
            .catch(() => {
                toast('Falha de conexão ao salvar o histórico.', 'erro');
            })
            .finally(() => {
                botao.disabled = false;
                botao.textContent = textoOriginal;
            });
    }

    function abrirClienteModal(botao) {
        const d = botao.dataset;

        document.getElementById('cliente-modal-titulo').textContent = d.nome;
        document.getElementById('cliente-modal-subtitulo').textContent = 'Cliente #' + d.id;

        document.getElementById('cliente-id').value = d.id;
        document.getElementById('cliente-nome').value = d.nome;
        document.getElementById('cliente-telefone').value = d.telefone;
        document.getElementById('cliente-email').value = d.email;
        document.getElementById(d.ativo === '1' ? 'cliente-ativo-sim' : 'cliente-ativo-nao').checked = true;

        document.getElementById('cliente-campo-criado').style.display = 'flex';
        document.getElementById('view-criado').textContent = d.criado;

        document.getElementById('historico-id-cliente').value = d.id;
        document.getElementById('form-historico').reset();
        cliAtualizarCampoValor();

        cliHabilitarAbasExtras(true);
        cliTrocarAba('cli-tab-dados', document.querySelector('.cli-tab[data-tab="cli-tab-dados"]'));

        openModal('modal-visualizar');
        cliCarregarDetalhes(d.id);
    }

    function abrirClienteModalNovo() {
        document.getElementById('cliente-modal-titulo').textContent = 'Novo Cliente';
        document.getElementById('cliente-modal-subtitulo').textContent = 'Preencha os dados abaixo';

        document.getElementById('form-cliente').reset();
        document.getElementById('cliente-id').value = '';
        document.getElementById('cliente-campo-criado').style.display = 'none';

        document.getElementById('cli-lista-agendamentos').innerHTML = '<p class="cli-vazio">Salve o cliente para ver os agendamentos.</p>';
        document.getElementById('cli-lista-historico').innerHTML = '<p class="cli-vazio">Salve o cliente para registrar o histórico.</p>';
        document.getElementById('cli-badge-agendamentos').textContent = '0';
        document.getElementById('cli-badge-historico').textContent = '0';
        document.getElementById('historico-id-cliente').value = '';
        document.getElementById('form-historico').reset();
        cliAtualizarCampoValor();

        cliHabilitarAbasExtras(false);
        cliTrocarAba('cli-tab-dados', document.querySelector('.cli-tab[data-tab="cli-tab-dados"]'));

        openModal('modal-visualizar');
    }

    function abrirClienteModalReaberto(id, nome) {
        document.getElementById('cliente-modal-titulo').textContent = nome || 'Editar Cliente';
        document.getElementById('cliente-modal-subtitulo').textContent = 'Cliente #' + id;
        document.getElementById('cliente-campo-criado').style.display = 'none';
        document.getElementById('historico-id-cliente').value = id;

        cliHabilitarAbasExtras(true);
        cliTrocarAba('cli-tab-dados', document.querySelector('.cli-tab[data-tab="cli-tab-dados"]'));

        openModal('modal-visualizar');
        cliCarregarDetalhes(id);
    }

    function cliPrepararEnvioForm() {
        const form = document.getElementById('form-cliente');
        const id = document.getElementById('cliente-id').value;
        form.action = id ? '../scripts/cliente_atualizar.php' : '../scripts/cliente_salvar.php';
        return true;
    }

    function filtrarClientes(termo) {
        const filtro = termo.trim().toLowerCase();
        const linhas = document.querySelectorAll('#tbody-clientes tr[data-busca]');
        let visiveis = 0;

        linhas.forEach(function (linha) {
            const corresponde = linha.dataset.busca.includes(filtro);
            linha.classList.toggle('hidden', !corresponde);
            if (corresponde) visiveis++;
        });

        const linhaSemResultado = document.getElementById('linha-sem-resultado');
        if (linhaSemResultado) {
            linhaSemResultado.classList.toggle('hidden', visiveis !== 0);
        }
    }

    <?php if ($reabrirCadastro): ?>
    abrirClienteModalNovo();
    <?php endif; ?>

    <?php if ($reabrirEdicao): ?>
    abrirClienteModalReaberto(<?= json_encode($voltaEditId) ?>, <?= json_encode($voltaNome) ?>);
    <?php endif; ?>
</script>

</body>
</html>
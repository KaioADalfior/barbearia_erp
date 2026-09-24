<?php
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/guard.php';
exigirSessao(['barbeiro']);
require_once __DIR__ . '/../../includes/forma_pagamento.php';

$paginaAtual = 'financeiro-fiados';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<?php include __DIR__ . '/../../includes/theme-init.php'; ?>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Financeiro — Fiados — BarbERP</title>

<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../../assets/css/admin-theme.css">
<link rel="stylesheet" href="../../assets/css/forma-pagamento.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    .modal-overlay{ background:rgba(0,0,0,0.7); backdrop-filter:blur(3px); }
    .modal-card{ background:linear-gradient(180deg, var(--charcoal-2), var(--charcoal-3)); border:1px solid rgba(61,126,201,0.16); }
    .icon-btn{
        display:inline-flex; align-items:center; justify-content:center;
        width:34px; height:34px; border-radius:0.65rem;
        border:1px solid rgba(255,255,255,0.08); background:rgba(255,255,255,0.03); color:#8fa0bd;
        transition:color .15s, border-color .15s, background-color .15s;
    }
    .icon-btn:hover{ color:var(--gold-light); border-color:rgba(61,126,201,0.4); background:rgba(61,126,201,0.08); }
    .icon-btn-receber:hover{ color:#7fd99a; border-color:rgba(66,140,82,0.4); background:rgba(66,140,82,0.1); }

    table tbody tr{ border-top:1px solid rgba(255,255,255,0.05); }
    .badge{
        display:inline-flex; align-items:center; gap:0.35rem;
        font-size:11px; font-weight:600; letter-spacing:0.03em;
        padding:0.28rem 0.65rem; border-radius:999px; white-space:nowrap;
    }
    .badge-pendente{ color:#9dc4f0; background:rgba(61,126,201,0.14); border:1px solid rgba(61,126,201,0.4); }
    .badge-forma{ color:#aebdd6; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); }
    .valor-pendente{ color:#9dc4f0; font-weight:600; }

    .view-row{ display:flex; justify-content:space-between; gap:1rem; padding:0.65rem 0; border-top:1px solid rgba(255,255,255,0.06); }
    .view-row:first-child{ border-top:none; }
    .view-label{ font-size:11px; text-transform:uppercase; letter-spacing:0.06em; color:#7f8fac; }
    .view-value{ font-size:13.5px; color:var(--cream); font-weight:500; text-align:right; }

    .linha-cliente{ cursor:pointer; }
    .linha-cliente:hover{ background:rgba(255,255,255,0.02); }
    .chevron{ transition:transform .15s; display:inline-block; }
    .chevron.aberto{ transform:rotate(90deg); }
    .linha-detalhe{ background:rgba(0,0,0,0.15); }
    .linha-detalhe td{ padding:0 !important; }
    .item-fiado{
        display:flex; justify-content:space-between; align-items:center; gap:1rem;
        padding:0.55rem 1.25rem 0.55rem 3rem;
        border-top:1px solid rgba(255,255,255,0.04);
        font-size:12.5px; color:#aebdd6;
    }
    .item-fiado:first-child{ border-top:none; }
    .campo-valor-receber{
        width:100%; background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.12);
        border-radius:0.65rem; padding:0.7rem 0.9rem; font-size:15px; font-weight:600;
        color:var(--cream); text-align:right;
    }
    .campo-valor-receber:focus{ outline:none; border-color:rgba(61,126,201,0.5); }
    .valor-max-hint{ font-size:11px; color:#7f8fac; margin-top:0.4rem; }
    .valor-max-hint button{ color:var(--gold-light); text-decoration:underline; }

    /* ---------- Modal: detalhes do cliente (somente leitura) ---------- */
    .modal-card-detalhes{ height:min(640px, 90vh); }
    @media (max-width:640px){ .modal-card-detalhes{ height:min(580px, 88vh); } }
    .cli-tabs{
        display:flex; gap:0.25rem; overflow-x:auto;
        border-bottom:1px solid rgba(255,255,255,0.08);
        margin:0 -1.75rem 1.25rem; padding:0 1.75rem;
    }
    .cli-tab{
        flex-shrink:0; display:inline-flex; align-items:center; gap:0.4rem;
        font-size:12.5px; font-weight:600; letter-spacing:0.02em; color:#7f8fac;
        padding:0.7rem 0.15rem; border-bottom:2px solid transparent; margin-right:1.1rem;
        cursor:pointer; background:none; border-top:none; border-left:none; border-right:none;
        white-space:nowrap; transition:color .15s, border-color .15s;
    }
    .cli-tab:hover{ color:var(--cream); }
    .cli-tab.is-active{ color:var(--gold-light); border-bottom-color:var(--gold); }
    .cli-tab-badge{
        display:inline-flex; align-items:center; justify-content:center;
        min-width:17px; height:17px; padding:0 5px; border-radius:999px;
        background:rgba(61,126,201,0.16); color:var(--gold-light); font-size:10px; font-weight:700;
    }
    .cli-tab-panel{ display:none; }
    .cli-tab-panel.is-active{ display:block; }
    .cli-scroll{ max-height:360px; overflow-y:auto; overflow-x:hidden; padding-right:2px; }
    .cli-item{
        background:rgba(255,255,255,0.025); border:1px solid rgba(255,255,255,0.06);
        border-radius:0.85rem; padding:0.85rem 1rem; margin-bottom:0.6rem;
        max-width:100%; overflow-wrap:anywhere;
    }
    .cli-item:last-child{ margin-bottom:0; }
    .cli-item-top{ display:flex; align-items:center; justify-content:space-between; gap:0.5rem; margin-bottom:0.3rem; }
    .cli-item-title{ font-size:13.5px; font-weight:600; color:var(--cream); min-width:0; overflow-wrap:anywhere; }
    .cli-item-meta{ font-size:11px; color:#7f8fac; }
    .cli-item-desc{
        font-size:13px; color:#aebdd6; line-height:1.5;
        overflow-wrap:anywhere; word-break:break-word; white-space:pre-wrap;
    }
    .cli-badge-tipo{
        display:inline-flex; align-items:center; font-size:10px; font-weight:700;
        letter-spacing:0.04em; text-transform:uppercase; padding:0.2rem 0.55rem;
        border-radius:999px; white-space:nowrap;
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
    .cli-vazio{ text-align:center; padding:2rem 1rem; color:#7f8fac; font-size:13px; }
    .badge-ativo{ color:#bfe6c7; background:rgba(66,140,82,0.14); border:1px solid rgba(66,140,82,0.4); }
    .badge-inativo{ color:#c9a8ab; background:rgba(140,31,40,0.12); border:1px solid rgba(140,31,40,0.4); }

    html[data-theme="light"] .icon-btn{ color:#475569; }
    html[data-theme="light"] .badge-pendente{ color:#1e4976; }
    html[data-theme="light"] .badge-forma{ color:#1e293b; }
    html[data-theme="light"] .valor-pendente{ color:#1e4976; }
    html[data-theme="light"] .badge-ativo{ color:#1f6b30; }
    html[data-theme="light"] .badge-inativo{ color:#8c1f28; }
    html[data-theme="light"] .view-label{ color:#475569; }
    html[data-theme="light"] .cli-tab{ color:#475569; }
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

    /* ---------- Modal: novo registro de fiado ---------- */
    .resultado-cliente{
        padding:0.65rem 0.85rem; border-radius:0.65rem;
        border:1px solid rgba(255,255,255,0.07); cursor:pointer;
        font-size:13px; color:var(--cream);
        transition:background-color .15s, border-color .15s;
    }
    .resultado-cliente:hover{ background:rgba(61,126,201,0.1); border-color:rgba(61,126,201,0.35); }
    .cliente-selecionado{
        background:rgba(61,126,201,0.1); border:1px solid rgba(61,126,201,0.35);
        border-radius:0.75rem; padding:0.75rem 1rem;
        display:flex; align-items:center; justify-content:space-between; gap:0.75rem;
    }
    html[data-theme="light"] .resultado-cliente{ color:#1e293b; }

    /* ---------- Campo de valor em moeda (Novo Registro de Fiado) ---------- */
    .campo-moeda-wrap{
        display:flex; align-items:center; gap:0.4rem;
        width:100%; background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.12);
        border-radius:0.65rem; padding:0.7rem 0.9rem;
        transition:border-color .15s, background-color .15s;
    }
    .campo-moeda-wrap:focus-within{ border-color:rgba(61,126,201,0.5); background:rgba(255,255,255,0.05); }
    .campo-moeda-prefixo{ font-size:15px; font-weight:600; color:#7f8fac; user-select:none; }
    .campo-moeda-input{
        flex:1; min-width:0; background:transparent; border:none; outline:none;
        padding:0; font-size:17px; font-weight:600; color:var(--cream); text-align:right;
        font-variant-numeric:tabular-nums;
    }
    .campo-moeda-input::placeholder{ color:#5b6a85; font-weight:600; }
    html[data-theme="light"] .campo-moeda-wrap{ background:rgba(0,0,0,0.03); border-color:rgba(0,0,0,0.12); }
    html[data-theme="light"] .campo-moeda-prefixo{ color:#64748b; }
    html[data-theme="light"] .campo-moeda-input{ color:#1e293b; }
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
        <div class="min-w-0 flex-1">
            <p class="eyebrow uppercase mb-1" style="color:var(--gold-light); opacity:.75">Financeiro</p>
            <h1 class="display text-3xl sm:text-4xl text-[color:var(--cream)] truncate">Contas a Receber</h1>
        </div>
    </header>

    <section class="p-5 sm:p-8">

        <!-- ==================== BARRA DE FERRAMENTAS ==================== -->
        <div class="panel-card rounded-2xl p-4 mb-6 flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
            <div class="relative w-full sm:max-w-sm">
                <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="11" cy="11" r="6.5" stroke="#7f8fac" stroke-width="1.5"/>
                    <path d="M20 20l-4.3-4.3" stroke="#7f8fac" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
                <input
                    id="busca-fiado"
                    type="text"
                    placeholder="Buscar cliente por nome ou telefone..."
                    autocomplete="off"
                    class="field w-full h-11 pl-10 pr-4 rounded-xl text-sm"
                >
            </div>
            <button type="button" onclick="abrirModalNovoFiado()" class="btn-primary h-11 px-5 rounded-xl text-sm inline-flex items-center justify-center gap-2 sm:ml-auto shrink-0">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                </svg>
                Novo Registro
            </button>
        </div>

        <!-- Zoom de 80% na tabela inteira (conteúdo visualmente menor,
             igual ao zoom do navegador) — não mexe na largura/espaço do
             card, só no tamanho do que está dentro dele. -->
        <div class="panel-card rounded-2xl overflow-hidden" style="zoom:0.8;">
            <div class="barber-stripe-thin"></div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[11px] uppercase tracking-widest text-zinc-500">
                            <th class="px-5 py-3 font-medium">Cliente</th>
                            <th class="px-5 py-3 font-medium hidden md:table-cell">Cortes em aberto</th>
                            <th class="px-5 py-3 font-medium hidden md:table-cell">Fiado mais antigo</th>
                            <th class="px-5 py-3 font-medium text-right">Saldo devedor</th>
                            <th class="px-5 py-3 font-medium text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-fiados"></tbody>
                </table>
            </div>

            <div id="estado-carregando" class="p-10 text-center">
                <p class="text-sm text-zinc-400">Carregando contas a receber...</p>
            </div>
            <div id="fiados-vazio" class="p-10 text-center hidden">
                <p class="text-sm text-zinc-400" id="fiados-vazio-texto">Nenhum fiado em aberto no momento.</p>
            </div>
            <div class="barber-stripe-thin"></div>
        </div>

    </section>
</main>

<!-- ==================== MODAL: RECEBER FIADO ==================== -->
<div id="modal-receber" class="modal-overlay fixed inset-0 z-50 hidden flex items-center justify-center p-4" onclick="fecharAoClicarFora(event, 'modal-receber')">
    <div class="modal-card rounded-3xl shadow-2xl overflow-hidden w-full max-w-lg max-h-[90vh] flex flex-col">
        <div class="barber-stripe-thin shrink-0"></div>
        <div class="p-7 overflow-y-auto">
            <div class="flex items-start justify-between mb-6">
                <h2 class="display text-2xl text-[color:var(--cream)]">Receber Fiado</h2>
                <button type="button" onclick="fecharModalReceber()" class="icon-btn shrink-0">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>

            <div class="mb-6">
                <div class="view-row">
                    <span class="view-label">Cliente</span>
                    <span class="view-value" id="receber-cliente">—</span>
                </div>
                <div class="view-row">
                    <span class="view-label">Cortes em aberto</span>
                    <span class="view-value" id="receber-qtd">—</span>
                </div>
                <div class="view-row">
                    <span class="view-label">Total devido</span>
                    <span class="view-value" id="receber-total-devido">—</span>
                </div>
            </div>

            <form id="form-receber" autocomplete="off" onsubmit="confirmarRecebimento(event)">
                <input type="hidden" id="receber-idCliente" value="">

                <div class="mb-4">
                    <label for="receber-valor" class="field-label block mb-2">Valor recebido agora</label>
                    <input type="number" id="receber-valor" class="campo-valor-receber" step="0.01" min="0.01" inputmode="decimal" required>
                    <p class="valor-max-hint">
                        Pode ser menor que o total devido — o restante continua em aberto e fica no histórico do cliente.
                        <button type="button" onclick="preencherValorTotal()">Usar valor total</button>
                    </p>
                    <p id="receber-valor-erro" class="text-xs mt-2 hidden valor-saida">Informe um valor válido.</p>
                </div>

                <?php renderFormaPagamentoCampo('receber'); ?>

                <div class="flex gap-3 mt-6">
                    <button type="submit" class="btn-primary h-12 px-6 rounded-xl text-sm flex-1">
                        Confirmar recebimento
                    </button>
                    <button type="button" onclick="fecharModalReceber()" class="btn-secondary h-12 px-6 rounded-xl text-sm">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
        <div class="barber-stripe-thin shrink-0"></div>
    </div>
</div>

<!-- ==================== MODAL: NOVO REGISTRO DE FIADO ==================== -->
<div id="modal-novo-fiado" class="modal-overlay fixed inset-0 z-50 hidden flex items-center justify-center p-4" onclick="fecharAoClicarForaNovoFiado(event)">
    <div class="modal-card rounded-3xl shadow-2xl overflow-hidden w-full max-w-lg max-h-[90vh] flex flex-col">
        <div class="barber-stripe-thin shrink-0"></div>
        <div class="p-7 overflow-y-auto">
            <div class="flex items-start justify-between mb-6">
                <h2 class="display text-2xl text-[color:var(--cream)]">Novo Registro de Fiado</h2>
                <button type="button" onclick="fecharModalNovoFiado()" class="icon-btn shrink-0">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>

            <form id="form-novo-fiado" autocomplete="off" onsubmit="confirmarNovoFiado(event)">
                <input type="hidden" id="novo-fiado-idCliente" value="">

                <div class="mb-4" id="nf-bloco-busca-cliente">
                    <label class="field-label block mb-1.5">Cliente</label>
                    <input type="text" id="nf-busca-cliente-input" placeholder="Digite o nome ou telefone do cliente..."
                           autocomplete="off" class="field w-full h-11 px-4 rounded-xl text-sm mb-2">
                    <div id="nf-resultados-cliente" class="flex flex-col gap-1.5 max-h-40 overflow-y-auto"></div>
                </div>

                <div class="mb-4 hidden" id="nf-bloco-cliente-selecionado">
                    <label class="field-label block mb-1.5">Cliente</label>
                    <div class="cliente-selecionado">
                        <div>
                            <p class="text-sm text-[color:var(--cream)]" id="nf-cliente-sel-nome">—</p>
                            <p class="text-xs text-zinc-500" id="nf-cliente-sel-telefone">—</p>
                        </div>
                        <button type="button" onclick="limparClienteNovoFiado()" class="icon-btn shrink-0">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                        </button>
                    </div>
                </div>

                <div class="mb-4">
                    <label for="nf-valor" class="field-label block mb-2">Valor devendo</label>
                    <div class="campo-moeda-wrap">
                        <span class="campo-moeda-prefixo">R$</span>
                        <input type="text" id="nf-valor" class="campo-moeda-input" inputmode="numeric" placeholder="0,00" autocomplete="off" required>
                    </div>
                </div>

                <div class="mb-1">
                    <label for="nf-descricao" class="field-label block mb-2">Descrição <span class="text-zinc-500 font-normal normal-case">(opcional)</span></label>
                    <input type="text" id="nf-descricao" maxlength="190" placeholder="Ex: Corte fiado combinado presencialmente"
                           class="field w-full h-11 px-4 rounded-xl text-sm">
                </div>

                <p id="nf-erro" class="text-xs mt-3 hidden valor-saida">Preencha os campos corretamente.</p>

                <div class="flex gap-3 mt-6">
                    <button type="submit" class="btn-primary h-12 px-6 rounded-xl text-sm flex-1">
                        Registrar fiado
                    </button>
                    <button type="button" onclick="fecharModalNovoFiado()" class="btn-secondary h-12 px-6 rounded-xl text-sm">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
        <div class="barber-stripe-thin shrink-0"></div>
    </div>
</div>

<!-- ==================== MODAL: DETALHES DO CLIENTE (somente leitura) ==================== -->
<div id="modal-cliente-detalhe" class="modal-overlay fixed inset-0 z-50 hidden flex items-center justify-center p-4" onclick="fecharAoClicarFora(event, 'modal-cliente-detalhe')">
    <div class="modal-card modal-card-detalhes rounded-3xl shadow-2xl overflow-hidden w-full max-w-2xl flex flex-col">
        <div class="barber-stripe-thin shrink-0"></div>

        <div class="p-7 pb-0 shrink-0">
            <div class="flex items-start justify-between mb-4">
                <div class="min-w-0">
                    <h2 class="display text-2xl text-[color:var(--cream)] truncate" id="cd-titulo">Cliente</h2>
                    <p class="text-xs text-zinc-500 mt-1" id="cd-subtitulo">—</p>
                </div>
                <button type="button" onclick="fecharDetalheCliente()" class="icon-btn shrink-0">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>

            <div class="cli-tabs">
                <button type="button" class="cli-tab is-active" data-tab="cd-tab-dados" onclick="cdTrocarAba('cd-tab-dados', this)">Cliente</button>
                <button type="button" class="cli-tab" data-tab="cd-tab-agendamentos" onclick="cdTrocarAba('cd-tab-agendamentos', this)">
                    Agendamentos <span class="cli-tab-badge" id="cd-badge-agendamentos">0</span>
                </button>
                <button type="button" class="cli-tab" data-tab="cd-tab-historico" onclick="cdTrocarAba('cd-tab-historico', this)">
                    Histórico <span class="cli-tab-badge" id="cd-badge-historico">0</span>
                </button>
            </div>
        </div>

        <div class="px-7 pb-7 overflow-y-auto flex-1 min-h-0">

            <!-- ---- Aba: Cliente ---- -->
            <div id="cd-tab-dados" class="cli-tab-panel is-active">
                <div class="view-row">
                    <span class="view-label">Nome</span>
                    <span class="view-value" id="cd-nome">—</span>
                </div>
                <div class="view-row">
                    <span class="view-label">Telefone</span>
                    <span class="view-value" id="cd-telefone">—</span>
                </div>
                <div class="view-row">
                    <span class="view-label">E-mail</span>
                    <span class="view-value" id="cd-email">—</span>
                </div>
                <div class="view-row">
                    <span class="view-label">Status</span>
                    <span class="view-value" id="cd-status">—</span>
                </div>
                <div class="view-row">
                    <span class="view-label">Saldo devedor (fiado)</span>
                    <span class="view-value" id="cd-saldo-fiado">—</span>
                </div>
                <div class="view-row">
                    <span class="view-label">Cadastrado em</span>
                    <span class="view-value" id="cd-criado">—</span>
                </div>
            </div>

            <!-- ---- Aba: Agendamentos ---- -->
            <div id="cd-tab-agendamentos" class="cli-tab-panel">
                <div class="cli-scroll" id="cd-lista-agendamentos">
                    <p class="cli-vazio">Carregando agendamentos...</p>
                </div>
            </div>

            <!-- ---- Aba: Histórico ---- -->
            <div id="cd-tab-historico" class="cli-tab-panel">
                <div class="cli-scroll" id="cd-lista-historico">
                    <p class="cli-vazio">Carregando histórico...</p>
                </div>
            </div>

        </div>

        <div class="barber-stripe-thin shrink-0"></div>
    </div>
</div>

<?php renderFormaPagamentoAssets(); ?>

<script>
const LABEL_FORMAS = <?= json_encode(FORMAS_PAGAMENTO_LABELS, JSON_UNESCAPED_UNICODE) ?>;

function formatarMoeda(valor) {
    return Number(valor).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}
function formatarData(iso) {
    const [ano, mes, dia] = iso.split('-');
    return dia + '/' + mes + '/' + ano;
}
function escaparHtml(texto) {
    const div = document.createElement('div');
    div.textContent = texto ?? '';
    return div.innerHTML;
}
function labelFormas(csv) {
    if (!csv) return '—';
    return csv.split(',').map(function (f) { return LABEL_FORMAS[f] || f; }).join(', ');
}
function formatarDataHora(isoString) {
    if (!isoString) return '—';
    const [dataParte, horaParte] = isoString.split(' ');
    const [ano, mes, dia] = dataParte.split('-');
    const hora = horaParte ? horaParte.slice(0, 5) : '';
    return dia + '/' + mes + '/' + ano + (hora ? ' às ' + hora : '');
}

/* ===================== Modal: detalhes do cliente (somente leitura) ===================== */

const ROTULOS_TIPO_HISTORICO = {
    fiado:       { texto: 'Fiado',       classe: 'cli-badge-fiado' },
    pagamento:   { texto: 'Pagamento',   classe: 'cli-badge-pagamento' },
    observacao:  { texto: 'Observação',  classe: 'cli-badge-observacao' },
    atendimento: { texto: 'Atendimento', classe: 'cli-badge-atendimento' },
    outro:       { texto: 'Outro',       classe: 'cli-badge-outro' },
};
const ROTULOS_STATUS_AGENDAMENTO = {
    agendado:   { texto: 'Agendado',   classe: 'cli-badge-status-agendado' },
    confirmado: { texto: 'Confirmado', classe: 'cli-badge-status-confirmado' },
    concluido:  { texto: 'Concluído',  classe: 'cli-badge-status-concluido' },
    cancelado:  { texto: 'Cancelado',  classe: 'cli-badge-status-cancelado' },
};

function cdTrocarAba(idAba, botao) {
    document.querySelectorAll('#modal-cliente-detalhe .cli-tab').forEach(function (b) { b.classList.remove('is-active'); });
    document.querySelectorAll('#modal-cliente-detalhe .cli-tab-panel').forEach(function (p) { p.classList.remove('is-active'); });
    document.getElementById(idAba).classList.add('is-active');
    botao.classList.add('is-active');
}

function abrirDetalheCliente(idCliente) {
    document.getElementById('cd-titulo').textContent = 'Carregando...';
    document.getElementById('cd-subtitulo').textContent = 'Cliente #' + idCliente;
    document.getElementById('cd-nome').textContent = '—';
    document.getElementById('cd-telefone').textContent = '—';
    document.getElementById('cd-email').textContent = '—';
    document.getElementById('cd-status').textContent = '—';
    document.getElementById('cd-saldo-fiado').textContent = '—';
    document.getElementById('cd-criado').textContent = '—';
    document.getElementById('cd-lista-agendamentos').innerHTML = '<p class="cli-vazio">Carregando agendamentos...</p>';
    document.getElementById('cd-lista-historico').innerHTML = '<p class="cli-vazio">Carregando histórico...</p>';
    document.getElementById('cd-badge-agendamentos').textContent = '0';
    document.getElementById('cd-badge-historico').textContent = '0';

    cdTrocarAba('cd-tab-dados', document.querySelector('#modal-cliente-detalhe .cli-tab[data-tab="cd-tab-dados"]'));

    document.getElementById('modal-cliente-detalhe').classList.remove('hidden');
    document.body.classList.add('overflow-hidden');

    fetch('../../Clientes/scripts/cliente_detalhes.php?id=' + encodeURIComponent(idCliente))
        .then(function (resp) { return resp.json(); })
        .then(function (dados) {
            if (!dados.ok) {
                toast(dados.erro || 'Não foi possível carregar os detalhes do cliente.', 'erro');
                return;
            }

            const c = dados.cliente;
            document.getElementById('cd-titulo').textContent = c.nome;
            document.getElementById('cd-subtitulo').textContent = 'Cliente #' + c.idCliente;
            document.getElementById('cd-nome').textContent = c.nome;
            document.getElementById('cd-telefone').textContent = c.telefone || '—';
            document.getElementById('cd-email').textContent = c.email || '—';
            document.getElementById('cd-status').innerHTML = Number(c.ativo) === 1
                ? '<span class="badge badge-ativo">Ativo</span>'
                : '<span class="badge badge-inativo">Inativo</span>';
            document.getElementById('cd-saldo-fiado').textContent = formatarMoeda(dados.saldoFiadoPendente || 0);
            document.getElementById('cd-criado').textContent = c.criado_em ? formatarData(c.criado_em.slice(0, 10)) : '—';

            renderizarAgendamentosCliente(dados.agendamentos || []);
            renderizarHistoricoCliente(dados.historico || []);
        })
        .catch(function () {
            toast('Falha de conexão ao carregar os detalhes do cliente.', 'erro');
        });
}

function fecharDetalheCliente() {
    document.getElementById('modal-cliente-detalhe').classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
}

function renderizarAgendamentosCliente(lista) {
    const alvo = document.getElementById('cd-lista-agendamentos');
    document.getElementById('cd-badge-agendamentos').textContent = lista.length;

    if (lista.length === 0) {
        alvo.innerHTML = '<p class="cli-vazio">Nenhum agendamento encontrado para este cliente.</p>';
        return;
    }

    alvo.innerHTML = lista.map(function (item) {
        const status = ROTULOS_STATUS_AGENDAMENTO[item.Status] || { texto: item.Status, classe: 'cli-badge-outro' };
        const valor = formatarMoeda(item.Valor);
        return '<div class="cli-item">' +
            '<div class="cli-item-top">' +
                '<span class="cli-item-title">' + escaparHtml(item.servico_nome) + (item.IncluirBarba == 1 ? ' + Barba' : '') + '</span>' +
                '<span class="cli-badge-tipo ' + status.classe + '">' + status.texto + '</span>' +
            '</div>' +
            '<div class="cli-item-meta">' + formatarData(item.Data) + ' às ' + (item.hora || '').slice(0, 5) + (valor ? ' · ' + valor : '') + '</div>' +
            (item.Observacao ? '<div class="cli-item-desc mt-1">' + escaparHtml(item.Observacao) + '</div>' : '') +
        '</div>';
    }).join('');
}

function renderizarHistoricoCliente(lista) {
    const alvo = document.getElementById('cd-lista-historico');
    document.getElementById('cd-badge-historico').textContent = lista.length;

    if (lista.length === 0) {
        alvo.innerHTML = '<p class="cli-vazio">Nenhum histórico registrado ainda.</p>';
        return;
    }

    alvo.innerHTML = lista.map(function (item) {
        const tipo = ROTULOS_TIPO_HISTORICO[item.tipo] || { texto: item.tipo, classe: 'cli-badge-outro' };
        const temValor = item.valor !== null && item.valor !== undefined && item.valor !== '';
        const valor = temValor ? formatarMoeda(item.valor) : '';
        const badgeQuitado = item.tipo === 'fiado'
            ? '<span class="cli-badge-tipo ' + (Number(item.quitado) === 1 ? 'cli-badge-quitado-pago' : 'cli-badge-quitado-pendente') + '">' + (Number(item.quitado) === 1 ? 'Pago' : 'Pendente') + '</span>'
            : '';
        return '<div class="cli-item">' +
            '<div class="cli-item-top">' +
                '<span class="flex items-center gap-1.5 flex-wrap">' +
                    '<span class="cli-badge-tipo ' + tipo.classe + '">' + tipo.texto + '</span>' +
                    badgeQuitado +
                '</span>' +
                '<span class="cli-item-meta">' + formatarDataHora(item.criado_em) + '</span>' +
            '</div>' +
            '<div class="cli-item-desc">' + escaparHtml(item.descricao) + '</div>' +
            '<div class="cli-item-meta mt-1">' +
                (temValor ? valor + ' · ' : '') + (item.barbeiro_nome ? 'registrado por ' + escaparHtml(item.barbeiro_nome) : '') +
            '</div>' +
        '</div>';
    }).join('');
}

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') fecharDetalheCliente();
});

let fiadosAtuais = [];
let linhasAbertas = new Set();

async function carregarFiados() {
    document.getElementById('estado-carregando').classList.remove('hidden');
    document.getElementById('fiados-vazio').classList.add('hidden');

    try {
        const termo = document.getElementById('busca-fiado').value.trim();
        const params = new URLSearchParams({ termo: termo });
        const resposta = await fetch('../scripts/fiados_listar.php?' + params.toString());
        const dados = await resposta.json();
        document.getElementById('estado-carregando').classList.add('hidden');

        if (!dados.ok) {
            toast(dados.erro || 'Não foi possível carregar as contas a receber.', 'erro');
            return;
        }

        fiadosAtuais = dados.fiados;
        renderizarFiados();
    } catch (e) {
        document.getElementById('estado-carregando').classList.add('hidden');
        toast('Erro de conexão. Tente novamente.', 'erro');
    }
}

function grupoPorCliente(idCliente) {
    return fiadosAtuais.find(function (g) { return Number(g.idCliente) === Number(idCliente); });
}

function renderizarFiados() {
    const tbody = document.getElementById('tbody-fiados');

    tbody.innerHTML = fiadosAtuais.map(function (g) {
        const aberto = linhasAbertas.has(g.idCliente);
        const itensOrdenados = g.itens.slice().sort(function (a, b) { return a.data.localeCompare(b.data); });
        const maisAntigo = itensOrdenados.length ? formatarData(itensOrdenados[0].data) : '—';

        const linhaDetalhe = '<tr class="linha-detalhe' + (aberto ? '' : ' hidden') + '" data-detalhe-cliente="' + g.idCliente + '">' +
            '<td colspan="5">' +
                itensOrdenados.map(function (item) {
                    const parcial = item.valorPago > 0
                        ? ' <span class="text-zinc-500">(já recebido ' + formatarMoeda(item.valorPago) + ')</span>'
                        : '';
                    // O título já lista os dois cortes (ex.: "Corte + Barba —
                    // Nome") quando um duplicado teve os dois cobrados; a tag
                    // "(2 cortes)" só reforça isso visualmente. Se foi cobrado
                    // só um corte do duplicado, não aparece nada extra.
                    const qtdTag = Number(item.quantidade) === 2 ? ' <span class="text-zinc-500">(2 cortes)</span>' : '';
                    return '<div class="item-fiado">' +
                        '<span>' + escaparHtml(item.titulo) + qtdTag + ' · ' + formatarData(item.data) + '</span>' +
                        '<span>' + formatarMoeda(item.saldo) + ' em aberto' + parcial + '</span>' +
                    '</div>';
                }).join('') +
            '</td>' +
        '</tr>';

        return '<tr class="linha-cliente" onclick="toggleDetalheCliente(' + g.idCliente + ')">' +
            '<td class="px-5 py-4 text-[color:var(--cream)] font-medium">' +
                '<span class="chevron' + (aberto ? ' aberto' : '') + '">▸</span> ' +
                escaparHtml(g.clienteNome || '—') +
            '</td>' +
            '<td class="px-5 py-4 text-zinc-400 hidden md:table-cell">' + g.qtdCortes + (g.qtdCortes === 1 ? ' corte' : ' cortes') + '</td>' +
            '<td class="px-5 py-4 text-zinc-400 hidden md:table-cell">' + maisAntigo + '</td>' +
            '<td class="px-5 py-4 text-right valor-pendente">' + formatarMoeda(g.saldoTotal) + '</td>' +
            '<td class="px-5 py-4 text-right">' +
                '<div class="flex items-center justify-end gap-2">' +
                    '<button type="button" onclick="event.stopPropagation(); abrirDetalheCliente(' + g.idCliente + ')" title="Visualizar cliente" class="icon-btn">' +
                        '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">' +
                            '<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/>' +
                            '<circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.4"/>' +
                        '</svg>' +
                    '</button>' +
                    '<button type="button" onclick="event.stopPropagation(); abrirModalReceber(' + g.idCliente + ')" title="Receber" class="icon-btn icon-btn-receber">' +
                        '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">' +
                            '<rect x="2.5" y="6.5" width="19" height="11" rx="1.8" stroke="currentColor" stroke-width="1.5"/>' +
                            '<circle cx="12" cy="12" r="2.6" stroke="currentColor" stroke-width="1.4"/>' +
                        '</svg>' +
                    '</button>' +
                '</div>' +
            '</td>' +
        '</tr>' + linhaDetalhe;
    }).join('');

    document.getElementById('fiados-vazio').classList.toggle('hidden', fiadosAtuais.length !== 0);
    document.getElementById('fiados-vazio-texto').textContent = document.getElementById('busca-fiado').value.trim() !== ''
        ? 'Nenhum cliente encontrado para essa busca.'
        : 'Nenhum fiado em aberto no momento.';
}

let temporizadorBuscaFiado;
document.getElementById('busca-fiado').addEventListener('input', function () {
    clearTimeout(temporizadorBuscaFiado);
    temporizadorBuscaFiado = setTimeout(carregarFiados, 300);
});

function toggleDetalheCliente(idCliente) {
    if (linhasAbertas.has(idCliente)) {
        linhasAbertas.delete(idCliente);
    } else {
        linhasAbertas.add(idCliente);
    }
    renderizarFiados();
}

function abrirModalReceber(idCliente) {
    const grupo = grupoPorCliente(idCliente);
    if (!grupo) return;

    document.getElementById('receber-idCliente').value = grupo.idCliente;
    document.getElementById('receber-cliente').textContent = grupo.clienteNome || '—';
    document.getElementById('receber-qtd').textContent = grupo.qtdCortes + (grupo.qtdCortes === 1 ? ' corte' : ' cortes');
    document.getElementById('receber-total-devido').textContent = formatarMoeda(grupo.saldoTotal);

    const inputValor = document.getElementById('receber-valor');
    inputValor.value = grupo.saldoTotal.toFixed(2);
    inputValor.max = grupo.saldoTotal.toFixed(2);
    document.getElementById('receber-valor-erro').classList.add('hidden');

    limparFormasSelecionadas('receber');

    document.getElementById('modal-receber').classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
}

function preencherValorTotal() {
    const idCliente = Number(document.getElementById('receber-idCliente').value);
    const grupo = grupoPorCliente(idCliente);
    if (!grupo) return;
    document.getElementById('receber-valor').value = grupo.saldoTotal.toFixed(2);
}

function fecharModalReceber() {
    document.getElementById('modal-receber').classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
}

function fecharAoClicarFora(evento, id) {
    if (evento.target.id === id) fecharModalReceber();
}

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') fecharModalReceber();
});

async function confirmarRecebimento(evento) {
    evento.preventDefault();

    const idCliente = document.getElementById('receber-idCliente').value;
    const grupo = grupoPorCliente(Number(idCliente));
    const inputValor = document.getElementById('receber-valor');
    const erroValor = document.getElementById('receber-valor-erro');
    const valor = parseFloat((inputValor.value || '').replace(',', '.'));

    const valorInvalido = !valor || valor <= 0 || (grupo && valor > grupo.saldoTotal + 0.001);
    erroValor.classList.toggle('hidden', !valorInvalido);
    erroValor.textContent = (grupo && valor > grupo.saldoTotal)
        ? 'Valor maior que o total devido (' + formatarMoeda(grupo.saldoTotal) + ').'
        : 'Informe um valor válido, maior que zero.';
    if (valorInvalido) return;

    if (!validarFormasSelecionadas('receber')) return;

    const formData = new FormData();
    formData.set('idCliente', idCliente);
    formData.set('valor', valor.toFixed(2));
    obterFormasSelecionadas('receber').forEach(function (f) { formData.append('formas[]', f); });

    try {
        const resposta = await fetch('../scripts/fiado_receber.php', { method: 'POST', body: formData });
        const dados = await resposta.json();

        if (!dados.ok) {
            toast(dados.erro || 'Não foi possível confirmar o recebimento.', 'erro');
            return;
        }

        fecharModalReceber();
        toast(
            dados.saldoRestante > 0.001
                ? 'Recebido ' + formatarMoeda(dados.valorAplicado) + '. Ainda deve ' + formatarMoeda(dados.saldoRestante) + '.'
                : 'Fiado quitado com sucesso.',
            'sucesso'
        );
        carregarFiados();
    } catch (e) {
        toast('Erro de conexão. Tente novamente.', 'erro');
    }
}

/* ===================== Modal: novo registro de fiado ===================== */

/**
 * Máscara de moeda em tempo real: o barbeiro digita só números (teclado
 * numérico no celular) e o campo vai formatando como "1.234,56" sozinho,
 * dígito a dígito, sem depender de vírgula/ponto digitados manualmente
 * nem das setinhas feias do input nativo type="number".
 */
function maskMoedaInput(el) {
    let digitos = el.value.replace(/\D/g, '');

    if (digitos === '') {
        el.value = '';
        return;
    }

    digitos = digitos.replace(/^0+(?=\d)/, '');
    while (digitos.length < 3) {
        digitos = '0' + digitos;
    }

    const centavos = digitos.slice(-2);
    const inteiro = (digitos.slice(0, -2).replace(/^0+(?=\d)/, '') || '0')
        .replace(/\B(?=(\d{3})+(?!\d))/g, '.');

    el.value = inteiro + ',' + centavos;
}

function valorMoedaParaNumero(valorMascarado) {
    if (!valorMascarado) return 0;
    const limpo = valorMascarado.replace(/\./g, '').replace(',', '.');
    return parseFloat(limpo) || 0;
}

document.getElementById('nf-valor').addEventListener('input', function () {
    maskMoedaInput(this);
});

function abrirModalNovoFiado() {
    document.getElementById('form-novo-fiado').reset();
    document.getElementById('novo-fiado-idCliente').value = '';
    document.getElementById('nf-valor').value = '';
    document.getElementById('nf-resultados-cliente').innerHTML = '';
    document.getElementById('nf-erro').classList.add('hidden');
    limparClienteNovoFiado();

    document.getElementById('modal-novo-fiado').classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
    setTimeout(function () { document.getElementById('nf-busca-cliente-input').focus(); }, 50);
}

function fecharModalNovoFiado() {
    document.getElementById('modal-novo-fiado').classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
}

function fecharAoClicarForaNovoFiado(evento) {
    if (evento.target.id === 'modal-novo-fiado') fecharModalNovoFiado();
}

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') fecharModalNovoFiado();
});

function limparClienteNovoFiado() {
    document.getElementById('novo-fiado-idCliente').value = '';
    document.getElementById('nf-bloco-cliente-selecionado').classList.add('hidden');
    document.getElementById('nf-bloco-busca-cliente').classList.remove('hidden');
    document.getElementById('nf-busca-cliente-input').value = '';
    document.getElementById('nf-resultados-cliente').innerHTML = '';
}

function selecionarClienteNovoFiado(cliente) {
    document.getElementById('novo-fiado-idCliente').value = cliente.idCliente;
    document.getElementById('nf-cliente-sel-nome').textContent = cliente.nome;
    document.getElementById('nf-cliente-sel-telefone').textContent = cliente.telefone || '—';
    document.getElementById('nf-bloco-busca-cliente').classList.add('hidden');
    document.getElementById('nf-bloco-cliente-selecionado').classList.remove('hidden');
    document.getElementById('nf-resultados-cliente').innerHTML = '';
}

let temporizadorBuscaNovoFiado;
document.getElementById('nf-busca-cliente-input').addEventListener('input', function () {
    const termo = this.value.trim();
    clearTimeout(temporizadorBuscaNovoFiado);
    const resultados = document.getElementById('nf-resultados-cliente');

    if (termo.length < 2) {
        resultados.innerHTML = '';
        return;
    }

    temporizadorBuscaNovoFiado = setTimeout(async function () {
        try {
            const resposta = await fetch('../../Clientes/scripts/clientes_buscar.php?termo=' + encodeURIComponent(termo));
            const dados = await resposta.json();
            resultados.innerHTML = '';

            if (!dados.ok || dados.clientes.length === 0) {
                resultados.innerHTML = '<p class="text-xs text-zinc-500 px-1 py-2">Nenhum cliente encontrado.</p>';
                return;
            }

            dados.clientes.forEach(function (c) {
                const item = document.createElement('div');
                item.className = 'resultado-cliente';
                item.innerHTML = '<strong>' + escaparHtml(c.nome) + '</strong> — ' + escaparHtml(c.telefone || '—');
                item.addEventListener('click', function () { selecionarClienteNovoFiado(c); });
                resultados.appendChild(item);
            });
        } catch (e) {
            resultados.innerHTML = '<p class="text-xs text-zinc-500 px-1 py-2">Erro ao buscar clientes.</p>';
        }
    }, 300);
});

async function confirmarNovoFiado(evento) {
    evento.preventDefault();

    const idCliente = document.getElementById('novo-fiado-idCliente').value;
    const inputValor = document.getElementById('nf-valor');
    const erro = document.getElementById('nf-erro');
    const valor = valorMoedaParaNumero(inputValor.value);

    if (!idCliente) {
        erro.textContent = 'Selecione um cliente.';
        erro.classList.remove('hidden');
        return;
    }
    if (!valor || valor <= 0) {
        erro.textContent = 'Informe um valor válido, maior que zero.';
        erro.classList.remove('hidden');
        return;
    }
    erro.classList.add('hidden');

    const formData = new FormData();
    formData.set('idCliente', idCliente);
    formData.set('valor', valor.toFixed(2));
    formData.set('descricao', document.getElementById('nf-descricao').value.trim());

    try {
        const resposta = await fetch('../scripts/fiado_criar.php', { method: 'POST', body: formData });
        const dados = await resposta.json();

        if (!dados.ok) {
            erro.textContent = dados.erro || 'Não foi possível registrar o fiado.';
            erro.classList.remove('hidden');
            return;
        }

        fecharModalNovoFiado();
        toast('Fiado registrado com sucesso.', 'sucesso');
        carregarFiados();
    } catch (e) {
        erro.textContent = 'Erro de conexão. Tente novamente.';
        erro.classList.remove('hidden');
    }
}

document.addEventListener('DOMContentLoaded', carregarFiados);
</script>

</body>
</html>
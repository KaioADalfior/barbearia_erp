<?php
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/guard.php';
exigirSessao(['barbeiro']);
require_once __DIR__ . '/../../includes/forma_pagamento.php';

$paginaAtual = 'financeiro-baixa';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<?php include __DIR__ . '/../../includes/theme-init.php'; ?>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Financeiro — Cadastrar Baixa — BarbERP</title>

<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../../assets/css/admin-theme.css?v=2">
<link rel="stylesheet" href="../../assets/css/forma-pagamento.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    /* Reduz a área de conteúdo para 80% (como Ctrl+- no navegador), só
       nesta página, para a tabela do extrato caber sem precisar de barra
       de rolagem horizontal mesmo com a sidebar ocupando espaço. */
    .main-extrato{
        zoom:0.8;
    }
    .demo-badge{
        display:inline-flex;
        align-items:center;
        gap:0.4rem;
        font-size:11px;
        font-weight:600;
        letter-spacing:0.04em;
        padding:0.3rem 0.7rem;
        border-radius:999px;
        color:#6fa8ea;
        background:rgba(61,126,201,0.10);
        border:1px solid rgba(61,126,201,0.35);
    }
    .modal-overlay{
        background:rgba(0,0,0,0.7);
        backdrop-filter:blur(3px);
    }
    .modal-card{
        background:linear-gradient(180deg, var(--charcoal-2), var(--charcoal-3));
        border:1px solid rgba(61,126,201,0.16);
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
    .icon-btn:hover{ color:var(--gold-light); border-color:rgba(61,126,201,0.4); background:rgba(61,126,201,0.08); }
    .icon-btn-danger:hover{ color:#f0a2a8; border-color:rgba(140,31,40,0.5); background:rgba(140,31,40,0.1); }

    table tbody tr{ border-top:1px solid rgba(255,255,255,0.05); }

    .badge{
        display:inline-flex;
        align-items:center;
        gap:0.35rem;
        font-size:11px;
        font-weight:600;
        letter-spacing:0.03em;
        padding:0.28rem 0.65rem;
        border-radius:999px;
        white-space:nowrap;
    }
    .badge-entrada{ color:#bfe6c7; background:rgba(66,140,82,0.14); border:1px solid rgba(66,140,82,0.4); }
    .badge-saida{ color:#f0a2a8; background:rgba(140,31,40,0.12); border:1px solid rgba(140,31,40,0.4); }
    .badge-forma{ color:#aebdd6; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); }

    .valor-entrada{ color:#7fd696; font-weight:600; }
    .valor-saida{ color:#f0a2a8; font-weight:600; }

    /* ---- Toggle Entrada/Saída dentro do modal ---- */
    .tipo-toggle{ display:grid; grid-template-columns:1fr 1fr; gap:0.6rem; }
    .tipo-toggle input{ display:none; }
    .tipo-toggle label{
        display:flex; align-items:center; justify-content:center; gap:0.5rem;
        padding:0.75rem; border-radius:0.85rem; cursor:pointer;
        border:1px solid rgba(255,255,255,0.1);
        background:rgba(255,255,255,0.03);
        color:#8fa0bd; font-size:13.5px; font-weight:600;
        transition:border-color .15s, background-color .15s, color .15s;
    }
    .tipo-toggle input:checked + label.tipo-entrada{
        border-color:rgba(66,140,82,0.5); background:rgba(66,140,82,0.14); color:#bfe6c7;
    }
    .tipo-toggle input:checked + label.tipo-saida{
        border-color:rgba(140,31,40,0.55); background:rgba(140,31,40,0.14); color:#f0c9cc;
    }

    /* Grid de "Forma de pagamento": estilos compartilhados em
       assets/css/forma-pagamento.css (componente reutilizável). */

    .summary-strip{ display:flex; flex-wrap:wrap; gap:1.5rem; }
    .summary-item .label{ font-size:11px; letter-spacing:0.08em; text-transform:uppercase; color:#7f8fac; }
    .summary-item .value{ font-size:1.15rem; font-weight:600; color:var(--cream); }

    /* ---- Modal "Ver detalhes" (somente leitura) ---- */
    .view-row{ display:flex; justify-content:space-between; gap:1rem; padding:0.65rem 0; border-top:1px solid rgba(255,255,255,0.06); }
    .view-row:first-child{ border-top:none; }
    .view-label{ font-size:11px; text-transform:uppercase; letter-spacing:0.06em; color:#7f8fac; }
    .view-value{ font-size:13.5px; color:var(--cream); font-weight:500; text-align:right; }

    /* ---------- Tema claro: reforço de contraste ---------- */
    html[data-theme="light"] .icon-btn{ color:#475569; }
    html[data-theme="light"] .icon-btn-danger:hover{ color:#8c1f28; }
    html[data-theme="light"] .badge-entrada{ color:#1f6b30; }
    html[data-theme="light"] .badge-saida{ color:#8c1f28; }
    html[data-theme="light"] .badge-forma{ color:#1e293b; }
    html[data-theme="light"] .valor-entrada{ color:#1f6b30; }
    html[data-theme="light"] .valor-saida{ color:#8c1f28; }
    html[data-theme="light"] .tipo-toggle label{ color:#475569; }
    html[data-theme="light"] .tipo-toggle input:checked + label.tipo-entrada{ color:#1f6b30; }
    html[data-theme="light"] .tipo-toggle input:checked + label.tipo-saida{ color:#8c1f28; }
    html[data-theme="light"] .summary-item .label{ color:#475569; }
</style>
</head>
<body class="flex">

<?php include __DIR__ . '/../../includes/sidebar_barbeiro.php'; ?>
<?php include __DIR__ . '/../../includes/toast.php'; ?>

<main class="flex-1 min-w-0 main-extrato">

    <header class="topbar px-5 sm:px-8 py-5 sm:py-6 flex items-center gap-4">
        <button type="button" onclick="abrirMenuMobile()" class="menu-toggle-btn lg:hidden" aria-label="Abrir menu">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M4 6h16M4 12h16M4 18h16" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
            </svg>
        </button>
        <div class="min-w-0 flex-1">
            <p class="eyebrow uppercase mb-1" style="color:var(--gold-light); opacity:.75">Financeiro</p>
            <h1 class="display text-3xl sm:text-4xl text-[color:var(--cream)] truncate">Cadastrar Baixa</h1>
        </div>
    </header>

    <section class="p-5 sm:p-8">

        <!-- ==================== RESUMO ==================== -->
        <div class="panel-card rounded-2xl p-5 mb-6 summary-strip">
            <div class="summary-item">
                <p class="label">Entradas</p>
                <p class="value valor-entrada" id="resumo-entradas">R$ 0,00</p>
            </div>
            <div class="summary-item">
                <p class="label">Saídas</p>
                <p class="value valor-saida" id="resumo-saidas">R$ 0,00</p>
            </div>
            <div class="summary-item">
                <p class="label">Saldo do extrato</p>
                <p class="value" id="resumo-saldo">R$ 0,00</p>
            </div>
            <div class="summary-item">
                <p class="label">Lançamentos</p>
                <p class="value" id="resumo-qtd">0</p>
            </div>
        </div>

        <!-- ==================== BARRA DE FERRAMENTAS ==================== -->
        <div class="panel-card rounded-2xl p-4 mb-6 flex flex-col sm:flex-row gap-3 sm:items-center sm:justify-between">

            <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
                <div class="relative w-full sm:max-w-sm">
                    <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="11" cy="11" r="6.5" stroke="#7f8fac" stroke-width="1.5"/>
                        <path d="M20 20l-4.3-4.3" stroke="#7f8fac" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                    <input
                        id="busca-baixa"
                        type="text"
                        placeholder="Buscar por título ou descrição..."
                        autocomplete="off"
                        class="field w-full h-11 pl-10 pr-4 rounded-xl text-sm"
                        oninput="renderizarExtrato()"
                    >
                </div>

                <select id="filtro-tipo" onchange="renderizarExtrato()" class="field h-11 px-4 rounded-xl text-sm">
                    <option value="todos">Todos os lançamentos</option>
                    <option value="entrada">Somente entradas</option>
                    <option value="saida">Somente saídas</option>
                </select>
            </div>

            <button type="button" onclick="abrirModalNovaBaixa()" class="btn-primary h-11 px-5 rounded-xl text-sm flex items-center justify-center gap-2 shrink-0">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 5v14M5 12h14" stroke="#ffffff" stroke-width="2" stroke-linecap="round"/>
                </svg>
                Nova baixa
            </button>
        </div>

        <!-- ==================== EXTRATO ==================== -->
        <div class="panel-card rounded-2xl overflow-hidden">
            <div class="barber-stripe-thin"></div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[11px] uppercase tracking-widest text-zinc-500">
                            <th class="px-5 py-3 font-medium">Data</th>
                            <th class="px-5 py-3 font-medium">Tipo</th>
                            <th class="px-5 py-3 font-medium">Título</th>
                            <th class="px-5 py-3 font-medium hidden md:table-cell">Descrição</th>
                            <th class="px-5 py-3 font-medium hidden lg:table-cell">Qtd.</th>
                            <th class="px-5 py-3 font-medium">Forma de pagamento</th>
                            <th class="px-5 py-3 font-medium text-right">Valor</th>
                            <th class="px-5 py-3 font-medium text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-extrato"></tbody>
                </table>
            </div>

            <div id="extrato-vazio" class="p-10 text-center hidden">
                <p class="text-sm text-zinc-400">Nenhum lançamento encontrado.</p>
            </div>
            <div class="barber-stripe-thin"></div>
        </div>

    </section>
</main>

<!-- ==================== MODAL: NOVA BAIXA ==================== -->
<div id="modal-nova-baixa" class="modal-overlay fixed inset-0 z-50 hidden flex items-center justify-center p-4" onclick="fecharAoClicarFora(event, 'modal-nova-baixa')">
    <div class="modal-card rounded-3xl shadow-2xl overflow-hidden w-full max-w-2xl max-h-[90vh] flex flex-col">
        <div class="barber-stripe-thin shrink-0"></div>

        <div class="p-7 overflow-y-auto">
            <div class="flex items-start justify-between mb-6">
                <div>
                    <h2 class="display text-2xl text-[color:var(--cream)]">Nova Baixa</h2>
                    <p class="text-xs text-zinc-500 mt-1">Preencha os dados do lançamento.</p>
                </div>
                <button type="button" onclick="fecharModalNovaBaixa()" class="icon-btn shrink-0">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>

            <form id="form-nova-baixa" autocomplete="off" onsubmit="salvarBaixa(event)">

                <!-- Tipo -->
                <div class="mb-5">
                    <label class="field-label block mb-2">Tipo de lançamento</label>
                    <div class="tipo-toggle">
                        <input type="radio" id="tipo-entrada" name="tipo" value="entrada" checked>
                        <label for="tipo-entrada" class="tipo-entrada">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 19V5M6 11l6-6 6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            Entrada
                        </label>

                        <input type="radio" id="tipo-saida" name="tipo" value="saida">
                        <label for="tipo-saida" class="tipo-saida">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 5v14M6 13l6 6 6-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            Saída
                        </label>
                    </div>
                </div>

                <!-- Título -->
                <div class="mb-5">
                    <label for="baixa-titulo" class="field-label block mb-2">Título <span class="text-zinc-500 normal-case">(obrigatório)</span></label>
                    <input type="text" id="baixa-titulo" name="titulo" required maxlength="120"
                           placeholder="Ex: Venda de produtos, Conta de energia..."
                           class="field w-full h-11 px-4 rounded-xl text-sm">
                </div>

                <!-- Descrição -->
                <div class="mb-5">
                    <label for="baixa-descricao" class="field-label block mb-2">Descrição <span class="text-zinc-500 normal-case">(opcional)</span></label>
                    <textarea id="baixa-descricao" name="descricao" rows="2" maxlength="300"
                              placeholder="Detalhes adicionais sobre o lançamento..."
                              class="field w-full px-4 py-3 rounded-xl text-sm resize-none"></textarea>
                </div>

                <div class="grid grid-cols-3 gap-4 mb-5">
                    <!-- Data -->
                    <div>
                        <label for="baixa-data" class="field-label block mb-2">Data</label>
                        <input type="date" id="baixa-data" name="data" required
                               class="field w-full h-11 px-3 rounded-xl text-sm">
                    </div>

                    <!-- Quantidade -->
                    <div>
                        <label for="baixa-quantidade" class="field-label block mb-2">Quantidade <span class="text-zinc-500 normal-case">(opc.)</span></label>
                        <input type="number" id="baixa-quantidade" name="quantidade" min="1" step="1"
                               placeholder="1"
                               class="field w-full h-11 px-3 rounded-xl text-sm">
                    </div>

                    <!-- Valor -->
                    <div>
                        <label for="baixa-valor" class="field-label block mb-2">Valor</label>
                        <div class="relative">
                            <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-sm text-zinc-500">R$</span>
                            <input type="number" id="baixa-valor" name="valor" required min="0.01" step="0.01"
                                   placeholder="0,00"
                                   class="field w-full h-11 pl-10 pr-3 rounded-xl text-sm">
                        </div>
                    </div>
                </div>

                <!-- Forma de pagamento (componente reutilizável) -->
                <div class="mb-7">
                    <?php renderFormaPagamentoCampo('baixa'); ?>
                </div>

                <div class="flex gap-3">
                    <button type="submit" class="btn-primary h-12 px-6 rounded-xl text-sm flex-1">
                        Salvar baixa
                    </button>
                    <button type="button" onclick="fecharModalNovaBaixa()" class="btn-secondary h-12 px-6 rounded-xl text-sm">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
        <div class="barber-stripe-thin shrink-0"></div>
    </div>
</div>

<!-- ==================== MODAL: VER DETALHES DA BAIXA ==================== -->
<div id="modal-detalhes-baixa" class="modal-overlay fixed inset-0 z-50 hidden flex items-center justify-center p-4" onclick="fecharAoClicarFora(event, 'modal-detalhes-baixa')">
    <div class="modal-card rounded-3xl shadow-2xl overflow-hidden w-full max-w-lg">
        <div class="barber-stripe-thin"></div>
        <div class="p-7">
            <div class="flex items-start justify-between mb-6">
                <h2 class="display text-2xl text-[color:var(--cream)]">Detalhes do Lançamento</h2>
                <button type="button" onclick="fecharModal('modal-detalhes-baixa')" class="icon-btn shrink-0">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>

            <div>
                <div class="view-row">
                    <span class="view-label">Data</span>
                    <span class="view-value" id="ver-data">—</span>
                </div>
                <div class="view-row">
                    <span class="view-label">Tipo</span>
                    <span class="view-value" id="ver-tipo">—</span>
                </div>
                <div class="view-row">
                    <span class="view-label">Título</span>
                    <span class="view-value" id="ver-titulo">—</span>
                </div>
                <div class="view-row">
                    <span class="view-label">Descrição</span>
                    <span class="view-value" id="ver-descricao">—</span>
                </div>
                <div class="view-row">
                    <span class="view-label">Quantidade</span>
                    <span class="view-value" id="ver-quantidade">—</span>
                </div>
                <div class="view-row">
                    <span class="view-label">Forma de pagamento</span>
                    <span class="view-value" id="ver-forma">—</span>
                </div>
                <div class="view-row">
                    <span class="view-label">Origem</span>
                    <span class="view-value" id="ver-origem">—</span>
                </div>
                <div class="view-row">
                    <span class="view-label">Valor</span>
                    <span class="view-value" id="ver-valor">—</span>
                </div>
            </div>

            <div class="flex gap-3 mt-7">
                <button type="button" onclick="fecharModal('modal-detalhes-baixa')" class="btn-primary h-12 px-6 rounded-xl text-sm flex-1">
                    Fechar
                </button>
            </div>
        </div>
        <div class="barber-stripe-thin"></div>
    </div>
</div>

<?php renderFormaPagamentoAssets(); ?>

<script>
/* =====================================================================
   CADASTRAR BAIXA / EXTRATO FINANCEIRO — dados reais (FinanceiroLancamentos)
   Lista via Financeiro/scripts/baixa_listar.php (somente lançamentos
   "pago" — inclui tanto baixas manuais quanto agendamentos concluídos
   não-fiado) e cria novos lançamentos via baixa_salvar.php.
   ===================================================================== */

const LABEL_FORMAS = <?= json_encode(FORMAS_PAGAMENTO_LABELS, JSON_UNESCAPED_UNICODE) ?>;

let extratoAtual = [];
let temporizadorBusca = null;

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
    return csv.split(',').map(function (f) { return LABEL_FORMAS[f] || f; });
}

async function carregarExtrato() {
    const termo = document.getElementById('busca-baixa').value.trim();
    const filtroTipo = document.getElementById('filtro-tipo').value;

    try {
        const params = new URLSearchParams({ termo: termo, tipo: filtroTipo });
        const resposta = await fetch('../scripts/baixa_listar.php?' + params.toString());
        const dados = await resposta.json();

        if (!dados.ok) {
            toast(dados.erro || 'Não foi possível carregar o extrato.', 'erro');
            return;
        }

        extratoAtual = dados.lancamentos;
        renderizarExtrato(dados.totalEntradas, dados.totalSaidas);
    } catch (e) {
        toast('Erro de conexão. Tente novamente.', 'erro');
    }
}

function renderizarExtrato(totalEntradas, totalSaidas) {
    const tbody = document.getElementById('tbody-extrato');
    tbody.innerHTML = extratoAtual.map(function (b) {
        const formasHtml = labelFormas(b.forma_pagamento).map(function (f) {
            return '<span class="badge badge-forma">' + escaparHtml(f) + '</span>';
        }).join(' ');

        return '<tr>' +
            '<td class="px-5 py-4 whitespace-nowrap text-zinc-400">' + formatarData(b.data) + '</td>' +
            '<td class="px-5 py-4">' +
                (b.tipo === 'entrada'
                    ? '<span class="badge badge-entrada">Entrada</span>'
                    : '<span class="badge badge-saida">Saída</span>') +
            '</td>' +
            '<td class="px-5 py-4 text-[color:var(--cream)] font-medium">' + escaparHtml(b.titulo) +
                (b.origem === 'agendamento' ? ' <span class="badge badge-forma">Agendamento</span>' : '') +
            '</td>' +
            '<td class="px-5 py-4 text-zinc-400 hidden md:table-cell max-w-[220px] truncate">' + (b.descricao ? escaparHtml(b.descricao) : '—') + '</td>' +
            '<td class="px-5 py-4 text-zinc-400 hidden lg:table-cell">' + (b.quantidade || '—') + '</td>' +
            '<td class="px-5 py-4"><div class="flex flex-wrap gap-1.5">' + formasHtml + '</div></td>' +
            '<td class="px-5 py-4 text-right ' + (b.tipo === 'entrada' ? 'valor-entrada' : 'valor-saida') + '">' +
                (b.tipo === 'entrada' ? '+ ' : '− ') + formatarMoeda(b.valor) +
            '</td>' +
            '<td class="px-5 py-4 text-right">' +
                '<div class="flex items-center justify-end gap-2">' +
                    '<button type="button" onclick="visualizarBaixa(' + b.idLancamento + ')" class="icon-btn" title="Ver detalhes">' +
                        '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">' +
                            '<path d="M2.5 12s3.6-7 9.5-7 9.5 7 9.5 7-3.6 7-9.5 7-9.5-7-9.5-7Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>' +
                            '<circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.5"/>' +
                        '</svg>' +
                    '</button>' +
                    '<button type="button" onclick="excluirBaixa(' + b.idLancamento + ')" class="icon-btn icon-btn-danger" title="Excluir lançamento">' +
                        '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">' +
                            '<path d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m2 0-.8 12a2 2 0 0 1-2 1.9H9.8a2 2 0 0 1-2-1.9L7 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>' +
                        '</svg>' +
                    '</button>' +
                '</div>' +
            '</td>' +
        '</tr>';
    }).join('');

    document.getElementById('extrato-vazio').classList.toggle('hidden', extratoAtual.length !== 0);

    const saldo = totalEntradas - totalSaidas;
    document.getElementById('resumo-entradas').textContent = formatarMoeda(totalEntradas);
    document.getElementById('resumo-saidas').textContent = formatarMoeda(totalSaidas);
    const elSaldo = document.getElementById('resumo-saldo');
    elSaldo.textContent = formatarMoeda(saldo);
    elSaldo.classList.toggle('valor-entrada', saldo >= 0);
    elSaldo.classList.toggle('valor-saida', saldo < 0);
    document.getElementById('resumo-qtd').textContent = extratoAtual.length;
}

document.getElementById('busca-baixa').addEventListener('input', function () {
    clearTimeout(temporizadorBusca);
    temporizadorBusca = setTimeout(carregarExtrato, 300);
});
document.getElementById('filtro-tipo').addEventListener('change', carregarExtrato);

function abrirModalNovaBaixa() {
    document.getElementById('form-nova-baixa').reset();
    document.getElementById('tipo-entrada').checked = true;
    document.getElementById('baixa-data').value = new Date().toISOString().slice(0, 10);
    limparFormasSelecionadas('baixa');
    document.getElementById('modal-nova-baixa').classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
}

function fecharModalNovaBaixa() {
    fecharModal('modal-nova-baixa');
}

function visualizarBaixa(idLancamento) {
    const item = extratoAtual.find(function (b) { return Number(b.idLancamento) === Number(idLancamento); });
    if (!item) return;

    document.getElementById('ver-data').textContent = formatarData(item.data);
    document.getElementById('ver-tipo').textContent = item.tipo === 'entrada' ? 'Entrada' : 'Saída';
    document.getElementById('ver-titulo').textContent = item.titulo;
    document.getElementById('ver-descricao').textContent = item.descricao || '—';
    document.getElementById('ver-quantidade').textContent = item.quantidade || '—';
    document.getElementById('ver-forma').textContent = item.forma_pagamento ? labelFormas(item.forma_pagamento).join(', ') : '—';
    document.getElementById('ver-origem').textContent = item.origem === 'agendamento' ? 'Agendamento concluído' : 'Lançamento manual';
    document.getElementById('ver-valor').textContent = (item.tipo === 'entrada' ? '+ ' : '− ') + formatarMoeda(item.valor);

    document.getElementById('modal-detalhes-baixa').classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
}

function fecharModal(id) {
    document.getElementById(id).classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
}

function fecharAoClicarFora(evento, id) {
    if (evento.target.id === id) fecharModal(id);
}

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        fecharModal('modal-nova-baixa');
        fecharModal('modal-detalhes-baixa');
    }
});

function excluirBaixa(idLancamento) {
    Swal.fire({
        title: 'Tem certeza que deseja excluir?',
        text: 'Essa ação não pode ser desfeita.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sim, excluir',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#8c1f28',
        cancelButtonColor: '#33415580'
    }).then(function (resultado) {
        if (!resultado.isConfirmed) return;

        const formData = new FormData();
        formData.set('idLancamento', idLancamento);

        fetch('../scripts/baixa_excluir.php', { method: 'POST', body: formData })
            .then(function (resposta) { return resposta.json(); })
            .then(function (dados) {
                if (!dados.ok) {
                    toast(dados.erro || 'Não foi possível excluir o lançamento.', 'erro');
                    return;
                }

                toast('Lançamento excluído com sucesso.', 'sucesso');
                carregarExtrato();
            })
            .catch(function () {
                toast('Erro de conexão. Tente novamente.', 'erro');
            });
    });
}

async function salvarBaixa(evento) {
    evento.preventDefault();

    if (!validarFormasSelecionadas('baixa')) return;

    const titulo = document.getElementById('baixa-titulo').value.trim();
    const data = document.getElementById('baixa-data').value;
    const valor = parseFloat(document.getElementById('baixa-valor').value);
    if (!titulo || !data || !valor || valor <= 0) return;

    const formData = new FormData(document.getElementById('form-nova-baixa'));
    obterFormasSelecionadas('baixa').forEach(function (f) { formData.append('formas[]', f); });

    const btn = document.querySelector('#form-nova-baixa button[type="submit"]');
    btn.disabled = true;
    btn.textContent = 'Salvando...';

    try {
        const resposta = await fetch('../scripts/baixa_salvar.php', { method: 'POST', body: formData });
        const dados = await resposta.json();

        if (!dados.ok) {
            toast(dados.erro || 'Não foi possível salvar a baixa.', 'erro');
        } else {
            fecharModalNovaBaixa();
            toast('Baixa lançada com sucesso.', 'sucesso');
            carregarExtrato();
        }
    } catch (e) {
        toast('Erro de conexão. Tente novamente.', 'erro');
    }

    btn.disabled = false;
    btn.textContent = 'Salvar baixa';
}

document.addEventListener('DOMContentLoaded', carregarExtrato);
</script>

</body>
</html>

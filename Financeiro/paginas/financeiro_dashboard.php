<?php
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/guard.php';
exigirSessao(['barbeiro']);
require_once __DIR__ . '/../../includes/forma_pagamento.php';

$paginaAtual = 'financeiro-dashboard';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<?php include __DIR__ . '/../../includes/theme-init.php'; ?>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Financeiro — Dashboard — BarbERP</title>

<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../../assets/css/admin-theme.css?v=2">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>

<style>
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
    .kpi-card{
        background:linear-gradient(180deg, var(--charcoal-2), var(--charcoal-3));
        border:1px solid rgba(61,126,201,0.12);
        position:relative;
        overflow:hidden;
    }
    .kpi-card__icon{
        width:38px;
        height:38px;
        border-radius:0.7rem;
        display:flex;
        align-items:center;
        justify-content:center;
        flex-shrink:0;
    }
    .kpi-entrada .kpi-card__icon{ background:rgba(66,140,82,0.14); color:#7fd696; }
    .kpi-saida   .kpi-card__icon{ background:rgba(140,31,40,0.14); color:#f0a2a8; }
    .kpi-saldo   .kpi-card__icon{ background:rgba(61,126,201,0.14); color:var(--gold-light); }
    .kpi-qtd     .kpi-card__icon{ background:rgba(15,92,102,0.30); color:#7fd0d9; }

    .period-tabs{
        display:inline-flex;
        gap:0.25rem;
        padding:0.3rem;
        border-radius:0.85rem;
        background:rgba(0,0,0,0.35);
        border:1px solid rgba(255,255,255,0.08);
    }
    .period-tab{
        border:none;
        background:transparent;
        color:#7f8fac;
        font-size:12.5px;
        font-weight:600;
        letter-spacing:0.03em;
        padding:0.55rem 1rem;
        border-radius:0.6rem;
        cursor:pointer;
        transition:background-color .15s, color .15s;
        white-space:nowrap;
    }
    .period-tab:hover{ color:var(--cream); }
    .period-tab.is-active{
        background:linear-gradient(180deg, var(--gold-light), var(--gold));
        color:#ffffff;
    }

    .chart-wrap{ position:relative; height:320px; }
    .chart-wrap-sm{ position:relative; height:260px; }

    .legend-item{
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:0.75rem;
        padding:0.6rem 0;
        border-top:1px solid rgba(255,255,255,0.06);
        font-size:13px;
    }
    .legend-item:first-child{ border-top:none; }
    .legend-dot{
        width:9px; height:9px; border-radius:999px; flex-shrink:0;
    }

    /* ---------- Tema claro: reforço de contraste ---------- */
    html[data-theme="light"] .demo-badge{ color:#1e4976; }
    html[data-theme="light"] .kpi-entrada .kpi-card__icon{ color:#1f6b30; }
    html[data-theme="light"] .kpi-saida   .kpi-card__icon{ color:#8c1f28; }
    html[data-theme="light"] .kpi-qtd     .kpi-card__icon{ color:#0d6b74; }
    html[data-theme="light"] .period-tab{ color:#475569; }
    html[data-theme="light"] .legend-item{ border-top:1px solid rgba(0,0,0,0.06); }
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
            <h1 class="display text-3xl sm:text-4xl text-[color:var(--cream)] truncate">Dashboard</h1>
        </div>
    </header>

    <section class="p-5 sm:p-8">
      <!-- Zoom de 80% em todo o conteúdo (filtros, KPIs e gráficos) — igual
           ao zoom do navegador; ocupa 100% do espaço, só o tamanho do que
           está dentro fica menor. -->
      <div style="zoom:0.8;">

        <!-- ==================== FILTROS ==================== -->
        <div class="panel-card rounded-2xl p-4 mb-6 flex flex-col lg:flex-row gap-4 lg:items-center lg:justify-between">

            <div class="period-tabs" id="period-tabs">
                <button type="button" class="period-tab is-active" data-periodo="diario" onclick="selecionarPeriodo('diario')">Diário</button>
                <button type="button" class="period-tab" data-periodo="semanal" onclick="selecionarPeriodo('semanal')">Semanal</button>
                <button type="button" class="period-tab" data-periodo="mensal" onclick="selecionarPeriodo('mensal')">Mensal</button>
                <button type="button" class="period-tab" data-periodo="anual" onclick="selecionarPeriodo('anual')">Anual</button>
            </div>

            <div class="flex flex-col sm:flex-row gap-3">
                <select id="filtro-ano" onchange="atualizarDashboard()" class="field h-11 px-4 rounded-xl text-sm">
                    <option value="2026">Ano: 2026</option>
                    <option value="2025">Ano: 2025</option>
                    <option value="2024">Ano: 2024</option>
                </select>

                <select id="filtro-forma" onchange="atualizarDashboard()" class="field h-11 px-4 rounded-xl text-sm">
                    <option value="todas">Todas as formas de pagamento</option>
                    <option value="pix">Pix</option>
                    <option value="credito">Cartão de Crédito</option>
                    <option value="debito">Cartão de Débito</option>
                    <option value="dinheiro">Dinheiro</option>
                    <option value="boleto">Boleto</option>
                </select>
            </div>
        </div>

        <p class="text-xs text-zinc-500 mb-6" style="margin-top:-1.1rem;" id="periodo-atual-aviso"></p>

        <!-- ==================== KPIs ==================== -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">

            <div class="kpi-card kpi-entrada rounded-2xl p-5">
                <div class="kpi-card__icon mb-4">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 19V5M6 11l6-6 6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <p class="text-[11px] uppercase tracking-widest text-zinc-500 mb-1">Entradas</p>
                <p class="text-xl sm:text-2xl font-semibold text-[color:var(--cream)]" id="kpi-entradas">R$ 0,00</p>
            </div>

            <div class="kpi-card kpi-saida rounded-2xl p-5">
                <div class="kpi-card__icon mb-4">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 5v14M6 13l6 6 6-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <p class="text-[11px] uppercase tracking-widest text-zinc-500 mb-1">Saídas</p>
                <p class="text-xl sm:text-2xl font-semibold text-[color:var(--cream)]" id="kpi-saidas">R$ 0,00</p>
            </div>

            <div class="kpi-card kpi-saldo rounded-2xl p-5">
                <div class="kpi-card__icon mb-4">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 3.5v17M16.5 7.2c0-1.6-1.6-2.7-4-2.7-2.6 0-4.3 1.2-4.3 3s1.4 2.5 4.3 3c2.9.5 4.3 1.3 4.3 3.1 0 1.8-1.8 3-4.3 3-2.2 0-4-1-4.3-2.6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <p class="text-[11px] uppercase tracking-widest text-zinc-500 mb-1">Saldo</p>
                <p class="text-xl sm:text-2xl font-semibold text-[color:var(--cream)]" id="kpi-saldo">R$ 0,00</p>
            </div>

            <div class="kpi-card kpi-qtd rounded-2xl p-5">
                <div class="kpi-card__icon mb-4">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="4" y="4" width="16" height="16" rx="2.5" stroke="currentColor" stroke-width="1.6"/>
                        <path d="M8 9h8M8 13h8M8 17h5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                    </svg>
                </div>
                <p class="text-[11px] uppercase tracking-widest text-zinc-500 mb-1">Baixas no período</p>
                <p class="text-xl sm:text-2xl font-semibold text-[color:var(--cream)]" id="kpi-qtd">0</p>
            </div>
        </div>

        <!-- ==================== GRÁFICOS ==================== -->
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

            <div class="panel-card rounded-2xl p-5 sm:p-6 xl:col-span-2">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="display text-xl text-[color:var(--cream)]">Entradas x Saídas</h2>
                        <p class="text-xs text-zinc-500" id="chart-subtitulo">Visão diária — últimos 7 dias</p>
                    </div>
                </div>
                <div class="chart-wrap">
                    <canvas id="chart-principal"></canvas>
                </div>
            </div>

            <div class="panel-card rounded-2xl p-5 sm:p-6">
                <h2 class="display text-xl text-[color:var(--cream)] mb-1">Formas de Pagamento</h2>
                <p class="text-xs text-zinc-500 mb-4">Distribuição no período</p>
                <div class="chart-wrap-sm">
                    <canvas id="chart-formas"></canvas>
                </div>
                <div id="legenda-formas" class="mt-2"></div>
            </div>

        </div>

        <div class="panel-card rounded-2xl p-5 sm:p-6 mt-6">
            <h2 class="display text-xl text-[color:var(--cream)] mb-1">Saldo Acumulado</h2>
            <p class="text-xs text-zinc-500 mb-4" id="chart-saldo-subtitulo">Evolução do saldo — últimos 7 dias</p>
            <div class="chart-wrap">
                <canvas id="chart-saldo"></canvas>
            </div>
        </div>

      </div>
    </section>
</main>

<script>
/* =====================================================================
   DASHBOARD FINANCEIRO — dados reais (FinanceiroLancamentos, status = pago)
   Busca via Financeiro/scripts/dashboard_dados.php. Fiado pendente nunca
   entra aqui — só passa a contar depois de recebido (Financeiro > Fiados).
   ===================================================================== */

const ehTemaClaro = document.documentElement.getAttribute('data-theme') === 'light';
const corEixo   = ehTemaClaro ? '#1e293b' : '#7f8fac';
const corLegenda = ehTemaClaro ? '#334155' : '#aebdd6';
const corGradeGraf = ehTemaClaro ? 'rgba(0,0,0,0.06)' : 'rgba(255,255,255,0.05)';

const CORES_FORMAS = {
    pix:       '#6fa8ea',
    credito:   '#3d7ec9',
    debito:    '#428c52',
    dinheiro:  '#3d7ec9',
    boleto:    '#8c1f28'
};
const LABEL_FORMAS = <?= json_encode(FORMAS_PAGAMENTO_LABELS, JSON_UNESCAPED_UNICODE) ?>;

const MESES_ABREV = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
const DIAS_SEMANA_ABREV = ['', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom']; // índice 1=segunda .. 7=domingo (ISO)

// Converte a "chave" agrupada vinda do backend (formato varia por período)
// em um rótulo amigável para o eixo do gráfico. "diario" e "semanal" vêm
// como "AAAA-MM-DD" (um dia); "mensal" e "anual" vêm como "AAAA-MM" (um mês)
// — ver FinanceiroService::intervaloPeriodo, sempre o período ATUAL, nunca
// uma janela deslizante de dias/semanas/anos passados.
function rotularChave(periodo, chave) {
    if (periodo === 'diario' || periodo === 'semanal') {
        const [ano, mes, dia] = chave.split('-').map(Number);
        if (periodo === 'semanal') {
            const diaSemanaIso = new Date(ano, mes - 1, dia).getDay() || 7; // 0 (dom) -> 7
            return (DIAS_SEMANA_ABREV[diaSemanaIso] || '') + ' ' + String(dia).padStart(2, '0') + '/' + String(mes).padStart(2, '0');
        }
        return String(dia).padStart(2, '0') + '/' + String(mes).padStart(2, '0');
    }
    // mensal e anual: "AAAA-MM"
    const mes = parseInt(chave.split('-')[1], 10) - 1;
    return MESES_ABREV[mes] || chave;
}

const SUBTITULOS = {
    diario:  'Visão diária — hoje',
    semanal: 'Visão semanal — semana atual (segunda a domingo)',
    mensal:  'Visão mensal — mês atual',
    anual:   'Visão anual — ano selecionado, por mês'
};

let periodoAtual = 'diario';
let chartPrincipal = null;
let chartFormas = null;
let chartSaldo = null;

function formatarMoeda(valor) {
    return Number(valor).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function selecionarPeriodo(periodo) {
    periodoAtual = periodo;
    document.querySelectorAll('.period-tab').forEach(function (btn) {
        btn.classList.toggle('is-active', btn.dataset.periodo === periodo);
    });

    // "Ano" só influencia a visão Anual — Diário/Semanal/Mensal são sempre
    // o período ATUAL (hoje, esta semana, este mês), então o seletor de
    // ano fica escondido nesses casos pra não sugerir que dá pra ver um
    // "mês de 2024" por exemplo (ver FinanceiroService::intervaloPeriodo).
    document.getElementById('filtro-ano').classList.toggle('hidden', periodo !== 'anual');

    const avisos = {
        diario:  'Mostrando só o dia de hoje — zera automaticamente à meia-noite.',
        semanal: 'Mostrando só a semana atual (segunda a domingo) — zera no início da próxima semana.',
        mensal:  'Mostrando só o mês atual — zera no início do próximo mês.',
        anual:   'Mostrando o ano selecionado no filtro ao lado.'
    };
    document.getElementById('periodo-atual-aviso').textContent = avisos[periodo] || '';

    atualizarDashboard();
}

async function atualizarDashboard() {
    const ano = document.getElementById('filtro-ano').value;
    const forma = document.getElementById('filtro-forma').value;
    const subtitulo = SUBTITULOS[periodoAtual];

    let dados;
    try {
        const params = new URLSearchParams({ periodo: periodoAtual, ano: ano, forma: forma });
        const resposta = await fetch('../scripts/dashboard_dados.php?' + params.toString());
        dados = await resposta.json();
        if (!dados.ok) {
            toast(dados.erro || 'Não foi possível carregar o dashboard.', 'erro');
            return;
        }
    } catch (e) {
        toast('Erro de conexão. Tente novamente.', 'erro');
        return;
    }

    const rotulos = dados.serie.map(function (p) { return rotularChave(periodoAtual, p.chave); });
    const entradas = dados.serie.map(function (p) { return Number(p.entradas); });
    const saidas = dados.serie.map(function (p) { return Number(p.saidas); });

    const totalEntradas = dados.totalEntradas;
    const totalSaidas = dados.totalSaidas;
    const saldo = dados.saldo;

    document.getElementById('kpi-entradas').textContent = formatarMoeda(totalEntradas);
    document.getElementById('kpi-saidas').textContent = formatarMoeda(totalSaidas);
    document.getElementById('kpi-saldo').textContent = formatarMoeda(saldo);
    document.getElementById('kpi-saldo').style.color = saldo >= 0 ? '#bfe6c7' : '#f0a2a8';
    document.getElementById('kpi-qtd').textContent = dados.qtd;
    document.getElementById('chart-subtitulo').textContent = subtitulo;
    document.getElementById('chart-saldo-subtitulo').textContent = subtitulo.replace('Visão', 'Evolução do saldo —').replace(/^Evolução do saldo — —?/, 'Evolução do saldo —');

    // ---- Gráfico principal: Entradas x Saídas ----
    const ctx1 = document.getElementById('chart-principal').getContext('2d');
    if (chartPrincipal) chartPrincipal.destroy();
    chartPrincipal = new Chart(ctx1, {
        type: 'bar',
        data: {
            labels: rotulos,
            datasets: [
                { label: 'Entradas', data: entradas, backgroundColor: 'rgba(66,140,82,0.75)', borderRadius: 6, maxBarThickness: 34 },
                { label: 'Saídas', data: saidas, backgroundColor: 'rgba(140,31,40,0.75)', borderRadius: 6, maxBarThickness: 34 }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { labels: { color: corLegenda, font: { family: 'Poppins' } } },
                tooltip: {
                    callbacks: { label: function (item) { return item.dataset.label + ': ' + formatarMoeda(item.parsed.y); } }
                }
            },
            scales: {
                x: { ticks: { color: corEixo }, grid: { color: corGradeGraf } },
                y: { ticks: { color: corEixo, callback: function (v) { return 'R$ ' + v; } }, grid: { color: corGradeGraf } }
            }
        }
    });

    // ---- Gráfico de saldo acumulado ----
    let acumulado = 0;
    const saldoAcumulado = entradas.map(function (e, i) {
        acumulado += (e - saidas[i]);
        return acumulado;
    });
    const ctx3 = document.getElementById('chart-saldo').getContext('2d');
    if (chartSaldo) chartSaldo.destroy();
    chartSaldo = new Chart(ctx3, {
        type: 'line',
        data: {
            labels: rotulos,
            datasets: [{
                label: 'Saldo acumulado',
                data: saldoAcumulado,
                borderColor: '#6fa8ea',
                backgroundColor: 'rgba(61,126,201,0.15)',
                fill: true,
                tension: 0.35,
                pointRadius: 3,
                pointBackgroundColor: '#6fa8ea'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: function (item) { return formatarMoeda(item.parsed.y); } } }
            },
            scales: {
                x: { ticks: { color: corEixo }, grid: { color: corGradeGraf } },
                y: { ticks: { color: corEixo, callback: function (v) { return 'R$ ' + v; } }, grid: { color: corGradeGraf } }
            }
        }
    });

    // ---- Gráfico de formas de pagamento (participação real no período) ----
    const distribuicao = Object.keys(dados.porForma).map(function (chave) {
        return { chave: chave, valor: Number(dados.porForma[chave]) };
    });
    const ctx2 = document.getElementById('chart-formas').getContext('2d');
    if (chartFormas) chartFormas.destroy();
    chartFormas = new Chart(ctx2, {
        type: 'doughnut',
        data: {
            labels: distribuicao.map(function (d) { return LABEL_FORMAS[d.chave]; }),
            datasets: [{
                data: distribuicao.map(function (d) { return d.valor; }),
                backgroundColor: distribuicao.map(function (d) { return CORES_FORMAS[d.chave]; }),
                borderColor: '#131c2e',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '68%',
            plugins: { legend: { display: false } }
        }
    });

    // ---- Legenda customizada ----
    const totalFormas = distribuicao.reduce(function (a, d) { return a + d.valor; }, 0) || 1;
    const legendaHtml = distribuicao.map(function (d) {
        const pct = ((d.valor / totalFormas) * 100).toFixed(0);
        return '<div class="legend-item">' +
            '<span class="flex items-center gap-2 text-zinc-300">' +
                '<span class="legend-dot" style="background:' + CORES_FORMAS[d.chave] + '"></span>' +
                LABEL_FORMAS[d.chave] +
            '</span>' +
            '<span class="text-zinc-400">' + pct + '%</span>' +
        '</div>';
    }).join('');
    document.getElementById('legenda-formas').innerHTML = legendaHtml;
}

document.addEventListener('DOMContentLoaded', function () {
    // Estado inicial da aba padrão ("Diário") — mesma lógica de
    // mostrar/esconder o filtro de Ano e do aviso usada ao trocar de aba.
    document.getElementById('filtro-ano').classList.add('hidden');
    document.getElementById('periodo-atual-aviso').textContent = 'Mostrando só o dia de hoje — zera automaticamente à meia-noite.';
    atualizarDashboard();
});
</script>

</body>
</html>
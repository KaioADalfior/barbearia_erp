<?php
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/guard.php';
exigirSessao(['barbeiro']);

$paginaAtual = 'financeiro-relatorios';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<?php include __DIR__ . '/../../includes/theme-init.php'; ?>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Financeiro — Relatórios — Alex Barbearia</title>

<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../../assets/css/admin-theme.css">
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
    .icon-btn-danger:hover{ color:#f0a2a8; border-color:rgba(140,31,40,0.5); background:rgba(140,31,40,0.1); }

    table tbody tr{ border-top:1px solid rgba(255,255,255,0.05); }

    .badge{
        display:inline-flex; align-items:center; gap:0.35rem;
        font-size:11px; font-weight:600; letter-spacing:0.03em;
        padding:0.28rem 0.65rem; border-radius:999px; white-space:nowrap;
    }
    .badge-diario  { color:#7fd0d9; background:rgba(15,92,102,0.20);  border:1px solid rgba(15,92,102,0.5); }
    .badge-semanal { color:#bfe6c7; background:rgba(66,140,82,0.16); border:1px solid rgba(66,140,82,0.45); }
    .badge-mensal  { color:#9dc4f0; background:rgba(61,126,201,0.16); border:1px solid rgba(61,126,201,0.45); }
    .badge-anual   { color:#f0a2a8; background:rgba(140,31,40,0.16); border:1px solid rgba(140,31,40,0.45); }
    .badge-periodo { color:#e6cf9d; background:rgba(158,120,25,0.16); border:1px solid rgba(158,120,25,0.45); }

    .valor-entrada{ color:#7fd696; font-weight:600; }
    .valor-saida{ color:#f0a2a8; font-weight:600; }

    .gerar-card{
        background:linear-gradient(180deg, var(--charcoal-2), var(--charcoal-3));
        border:1px solid rgba(61,126,201,0.14);
    }

    .tipo-radio{ display:none; }
    .tipo-label{
        display:flex; flex-direction:column; align-items:center; justify-content:center; gap:0.35rem;
        border:1px solid rgba(255,255,255,0.08); background:rgba(255,255,255,0.02);
        border-radius:0.85rem; padding:0.7rem 0.5rem; cursor:pointer; text-align:center;
        transition:border-color .15s, background-color .15s, color .15s;
        color:#8fa0bd; font-size:12px; font-weight:600; letter-spacing:0.02em;
    }
    .tipo-label:hover{ border-color:rgba(61,126,201,0.35); }
    .tipo-radio:checked + .tipo-label{
        border-color:rgba(61,126,201,0.55);
        background:rgba(61,126,201,0.1);
        color:var(--gold-light);
    }
    .tipo-label svg{ opacity:0.85; }

    .empty-state{ border:1px dashed rgba(255,255,255,0.12); border-radius:1rem; }

    /* Linha de data(s) + botão: sempre na mesma linha. Em telas estreitas
       os campos de data encolhem e o botão vira ícone-only para nunca
       quebrar de linha nem estourar o layout. */
    .linha-gerar{ display:flex; flex-direction:row; align-items:flex-end; gap:0.75rem; flex-wrap:nowrap; }
    .linha-gerar .campo-data{ flex:1 1 0; min-width:0; }
    .linha-gerar .campo-data input{ width:100%; }

    .btn-gerar-texto{ white-space:nowrap; }
    @media (max-width: 560px){
        #btn-gerar{ padding-left:0.85rem; padding-right:0.85rem; }
        .btn-gerar-texto{ display:none; }
        .field-label{ font-size:11px; }
    }

    html[data-theme="light"] .icon-btn{ color:#475569; }
    html[data-theme="light"] .icon-btn-danger:hover{ color:#8c1f28; }
    html[data-theme="light"] .badge-diario{ color:#0d6b74; }
    html[data-theme="light"] .badge-semanal{ color:#1f6b30; }
    html[data-theme="light"] .badge-mensal{ color:#1e4976; }
    html[data-theme="light"] .badge-anual{ color:#8c1f28; }
    html[data-theme="light"] .badge-periodo{ color:#8a6a10; }
    html[data-theme="light"] .valor-entrada{ color:#1f6b30; }
    html[data-theme="light"] .valor-saida{ color:#8c1f28; }
    html[data-theme="light"] .tipo-label{ color:#475569; }
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
            <h1 class="display text-3xl sm:text-4xl text-[color:var(--cream)] truncate">Relatórios</h1>
        </div>
    </header>

    <section class="p-5 sm:p-8">
      <div>

        <!-- ==================== GERAR RELATÓRIO (MANUAL) ==================== -->
        <div class="gerar-card rounded-2xl p-5 sm:p-6 mb-6">
            <div class="flex items-start gap-3 mb-5">
                <div class="w-10 h-10 rounded-xl bg-[rgba(61,126,201,0.14)] flex items-center justify-center shrink-0">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M7 3.5h7l4 4V19a1.5 1.5 0 0 1-1.5 1.5h-9.5A1.5 1.5 0 0 1 5.5 19V5A1.5 1.5 0 0 1 7 3.5Z" stroke="var(--gold-light)" stroke-width="1.4" stroke-linejoin="round"/>
                        <path d="M14 3.5V8h4.5" stroke="var(--gold-light)" stroke-width="1.4" stroke-linejoin="round"/>
                        <path d="M9 13h6M9 16h6" stroke="var(--gold-light)" stroke-width="1.3" stroke-linecap="round"/>
                    </svg>
                </div>
                <div>
                    <h2 class="display text-xl text-[color:var(--cream)] leading-none mb-1">Gerar novo relatório</h2>
                    <p class="text-sm text-zinc-500">Escolha o tipo de período. Se precisar de datas específicas, use "Período".</p>
                </div>
            </div>

            <form id="form-gerar" autocomplete="off" onsubmit="gerarRelatorio(event)">
                <div class="grid grid-cols-3 sm:grid-cols-5 gap-2.5 mb-5">
                    <input type="radio" name="tipo" id="tipo-diario" class="tipo-radio" value="diario" checked>
                    <label for="tipo-diario" class="tipo-label">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="4" y="5" width="16" height="15" rx="2" stroke="currentColor" stroke-width="1.4"/><path d="M4 9.5h16M8 3v3M16 3v3" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                        Diário
                    </label>

                    <input type="radio" name="tipo" id="tipo-semanal" class="tipo-radio" value="semanal">
                    <label for="tipo-semanal" class="tipo-label">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="3" y="5" width="18" height="15" rx="2" stroke="currentColor" stroke-width="1.4"/><path d="M3 10.5h18M7 3v3M17 3v3" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><path d="M7.5 14.5h9" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                        Semanal
                    </label>

                    <input type="radio" name="tipo" id="tipo-mensal" class="tipo-radio" value="mensal">
                    <label for="tipo-mensal" class="tipo-label">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="3" y="4.5" width="18" height="16" rx="2" stroke="currentColor" stroke-width="1.4"/><path d="M3 9.5h18" stroke="currentColor" stroke-width="1.4"/><path d="M7.5 13.5h2M11 13.5h2M14.5 13.5h2M7.5 17h2M11 17h2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                        Mensal
                    </label>

                    <input type="radio" name="tipo" id="tipo-anual" class="tipo-radio" value="anual">
                    <label for="tipo-anual" class="tipo-label">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 3.5v17M16.5 7.2c0-1.6-1.6-2.7-4-2.7-2.6 0-4.3 1.2-4.3 3s1.4 2.5 4.3 3c2.9.5 4.3 1.3 4.3 3.1 0 1.8-1.8 3-4.3 3-2.2 0-4-1-4.3-2.6" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        Anual
                    </label>

                    <input type="radio" name="tipo" id="tipo-periodo" class="tipo-radio" value="periodo">
                    <label for="tipo-periodo" class="tipo-label">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="3" y="5" width="7.5" height="14" rx="1.5" stroke="currentColor" stroke-width="1.4"/><rect x="13.5" y="5" width="7.5" height="14" rx="1.5" stroke="currentColor" stroke-width="1.4"/><path d="M10.5 12h3" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><path d="M12.3 10.5 13.8 12l-1.5 1.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        Período
                    </label>
                </div>

                <div class="linha-gerar mb-1.5">
                    <div class="campo-data" id="campo-data-unica">
                        <label for="input-data-ref" class="field-label block mb-2">Data do relatório</label>
                        <input type="date" id="input-data-ref" class="field h-12 px-4 rounded-xl text-sm">
                    </div>

                    <div class="campo-data hidden" id="campo-data-inicio">
                        <label for="input-data-inicio" class="field-label block mb-2">Data início</label>
                        <input type="date" id="input-data-inicio" class="field h-12 px-4 rounded-xl text-sm">
                    </div>

                    <div class="campo-data hidden" id="campo-data-fim">
                        <label for="input-data-fim" class="field-label block mb-2">Data fim</label>
                        <input type="date" id="input-data-fim" class="field h-12 px-4 rounded-xl text-sm">
                    </div>

                    <button type="submit" id="btn-gerar" class="btn-primary h-12 px-6 rounded-xl text-sm flex items-center justify-center gap-2 shrink-0">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 4v11M7.5 11l4.5 4.5L16.5 11" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M5 17.5V19a1.5 1.5 0 0 0 1.5 1.5h11A1.5 1.5 0 0 0 19 19v-1.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                        </svg>
                        <span id="btn-gerar-texto" class="btn-gerar-texto">Gerar relatório PDF</span>
                    </button>
                </div>
                <p class="text-xs text-zinc-500" id="ajuda-data-ref">O relatório diário cobre exatamente essa data.</p>
            </form>
        </div>

        <!-- ==================== HISTÓRICO DE RELATÓRIOS ==================== -->
        <!-- Zoom de 80% na tabela inteira (conteúdo visualmente menor, igual
             ao zoom do navegador) — não mexe na largura/espaço do card, só
             no tamanho do que está dentro dele. -->
        <div class="panel-card rounded-2xl overflow-hidden" style="zoom:0.8;">
            <div class="barber-stripe-thin"></div>

            <div class="px-5 sm:px-6 py-4 flex items-center justify-between">
                <h2 class="display text-xl text-[color:var(--cream)] leading-none">Relatórios gerados</h2>
                <span class="text-xs text-zinc-500" id="contagem-relatorios"></span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[11px] uppercase tracking-widest text-zinc-500">
                            <th class="px-5 py-3 font-medium">Relatório</th>
                            <th class="px-5 py-3 font-medium hidden sm:table-cell">Período</th>
                            <th class="px-5 py-3 font-medium hidden md:table-cell text-right">Entradas</th>
                            <th class="px-5 py-3 font-medium hidden md:table-cell text-right">Saídas</th>
                            <th class="px-5 py-3 font-medium text-right">Saldo</th>
                            <th class="px-5 py-3 font-medium hidden lg:table-cell">Gerado em</th>
                            <th class="px-5 py-3 font-medium text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-relatorios"></tbody>
                </table>
            </div>

            <div id="estado-carregando" class="p-10 text-center">
                <p class="text-sm text-zinc-400">Carregando relatórios...</p>
            </div>
            <div id="relatorios-vazio" class="p-8 sm:p-10 hidden">
                <div class="empty-state p-8 text-center">
                    <p class="text-sm text-zinc-400">Nenhum relatório gerado ainda.</p>
                    <p class="text-xs text-zinc-600 mt-1">Use o formulário acima para gerar o primeiro relatório em PDF.</p>
                </div>
            </div>
            <div class="barber-stripe-thin"></div>
        </div>

      </div>
    </section>
</main>

<script>
const TIPO_LABELS = { diario: 'Diário', semanal: 'Semanal', mensal: 'Mensal', anual: 'Anual', periodo: 'Período' };
const TIPO_AJUDA = {
    diario:  'O relatório diário cobre exatamente essa data.',
    semanal: 'Cobre a semana (segunda a domingo) que contém essa data.',
    mensal:  'Cobre o mês inteiro que contém essa data.',
    anual:   'Cobre o ano inteiro que contém essa data.',
    periodo: 'Cobre exatamente o intervalo entre as duas datas escolhidas.'
};

function formatarMoeda(valor) {
    return Number(valor).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}
function formatarData(iso) {
    const [ano, mes, dia] = iso.split('-');
    return dia + '/' + mes + '/' + ano;
}
function formatarDataHora(dataHora) {
    const d = new Date(dataHora.replace(' ', 'T'));
    if (isNaN(d.getTime())) return dataHora;
    return d.toLocaleDateString('pt-BR') + ' às ' + d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
}
function escaparHtml(texto) {
    const div = document.createElement('div');
    div.textContent = texto ?? '';
    return div.innerHTML;
}

// Alterna entre o campo de "data única" e os dois campos de "período"
// conforme o tipo de relatório escolhido, e atualiza o texto de ajuda.
function atualizarCamposData(tipo) {
    const campoUnica  = document.getElementById('campo-data-unica');
    const campoInicio = document.getElementById('campo-data-inicio');
    const campoFim    = document.getElementById('campo-data-fim');

    const ehPeriodo = tipo === 'periodo';
    campoUnica.classList.toggle('hidden', ehPeriodo);
    campoInicio.classList.toggle('hidden', !ehPeriodo);
    campoFim.classList.toggle('hidden', !ehPeriodo);

    document.getElementById('input-data-ref').required = !ehPeriodo;
    document.getElementById('input-data-inicio').required = ehPeriodo;
    document.getElementById('input-data-fim').required = ehPeriodo;

    document.getElementById('ajuda-data-ref').textContent = TIPO_AJUDA[tipo];
}

document.querySelectorAll('input[name="tipo"]').forEach(function (radio) {
    radio.addEventListener('change', function () {
        atualizarCamposData(radio.value);
    });
});

// Datas padrão: data única = hoje; período = do início do mês até hoje.
const hoje = new Date().toISOString().slice(0, 10);
const inicioMes = new Date();
inicioMes.setDate(1);
document.getElementById('input-data-ref').value = hoje;
document.getElementById('input-data-inicio').value = inicioMes.toISOString().slice(0, 10);
document.getElementById('input-data-fim').value = hoje;

atualizarCamposData('diario');

let relatoriosAtuais = [];

async function carregarRelatorios() {
    document.getElementById('estado-carregando').classList.remove('hidden');
    document.getElementById('relatorios-vazio').classList.add('hidden');

    try {
        const resposta = await fetch('../scripts/relatorio_listar.php');
        const dados = await resposta.json();
        document.getElementById('estado-carregando').classList.add('hidden');

        if (!dados.ok) {
            toast(dados.erro || 'Não foi possível carregar os relatórios.', 'erro');
            return;
        }

        relatoriosAtuais = dados.relatorios;
        renderizarRelatorios();
    } catch (e) {
        document.getElementById('estado-carregando').classList.add('hidden');
        toast('Erro de conexão. Tente novamente.', 'erro');
    }
}

function renderizarRelatorios() {
    const tbody = document.getElementById('tbody-relatorios');
    const contagem = document.getElementById('contagem-relatorios');

    contagem.textContent = relatoriosAtuais.length === 1
        ? '1 relatório'
        : relatoriosAtuais.length + ' relatórios';

    tbody.innerHTML = relatoriosAtuais.map(function (r) {
        const periodo = r.data_inicio === r.data_fim
            ? formatarData(r.data_inicio)
            : formatarData(r.data_inicio) + ' — ' + formatarData(r.data_fim);

        const saldoClasse = Number(r.saldo) >= 0 ? 'valor-entrada' : 'valor-saida';

        return '<tr>' +
            '<td class="px-5 py-4">' +
                '<p class="text-[color:var(--cream)] font-medium">' + escaparHtml(r.titulo) + '</p>' +
                '<span class="badge badge-' + r.tipo + ' mt-1">' + TIPO_LABELS[r.tipo] + '</span>' +
            '</td>' +
            '<td class="px-5 py-4 text-zinc-400 hidden sm:table-cell">' + periodo + '</td>' +
            '<td class="px-5 py-4 text-right valor-entrada hidden md:table-cell">' + formatarMoeda(r.total_entradas) + '</td>' +
            '<td class="px-5 py-4 text-right valor-saida hidden md:table-cell">' + formatarMoeda(r.total_saidas) + '</td>' +
            '<td class="px-5 py-4 text-right font-semibold ' + saldoClasse + '">' + formatarMoeda(r.saldo) + '</td>' +
            '<td class="px-5 py-4 text-zinc-500 text-xs hidden lg:table-cell">' + formatarDataHora(r.gerado_em) + '</td>' +
            '<td class="px-5 py-4 text-right whitespace-nowrap">' +
                '<a href="../scripts/relatorio_baixar.php?id=' + r.idRelatorio + '" class="icon-btn" title="Baixar PDF">' +
                    '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 4v11M7.5 11l4.5 4.5L16.5 11" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/><path d="M5 17.5V19a1.5 1.5 0 0 0 1.5 1.5h11A1.5 1.5 0 0 0 19 19v-1.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>' +
                '</a>' +
                '<button type="button" onclick="excluirRelatorio(' + r.idRelatorio + ')" class="icon-btn icon-btn-danger ml-2" title="Excluir relatório">' +
                    '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 7h16M9.5 7V5a1.5 1.5 0 0 1 1.5-1.5h2A1.5 1.5 0 0 1 14.5 5v2M6.5 7l.7 12a1.5 1.5 0 0 0 1.5 1.4h6.6a1.5 1.5 0 0 0 1.5-1.4L18 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>' +
                '</button>' +
            '</td>' +
        '</tr>';
    }).join('');

    document.getElementById('relatorios-vazio').classList.toggle('hidden', relatoriosAtuais.length !== 0);
}

async function gerarRelatorio(evento) {
    evento.preventDefault();

    const tipo = document.querySelector('input[name="tipo"]:checked').value;
    const formData = new FormData();
    formData.set('tipo', tipo);

    if (tipo === 'periodo') {
        const dataInicio = document.getElementById('input-data-inicio').value;
        const dataFim = document.getElementById('input-data-fim').value;

        if (!dataInicio || !dataFim) {
            toast('Escolha a data de início e a data de fim.', 'erro');
            return;
        }
        if (dataInicio > dataFim) {
            toast('A data de início não pode ser depois da data de fim.', 'erro');
            return;
        }

        formData.set('data_inicio', dataInicio);
        formData.set('data_fim', dataFim);
    } else {
        const data = document.getElementById('input-data-ref').value;
        if (!data) {
            toast('Escolha uma data de referência.', 'erro');
            return;
        }
        formData.set('data', data);
    }

    const btn = document.getElementById('btn-gerar');
    const btnTexto = document.getElementById('btn-gerar-texto');
    btn.disabled = true;
    btn.style.opacity = '0.6';
    btnTexto.textContent = 'Gerando PDF...';

    try {
        const resposta = await fetch('../scripts/relatorio_gerar.php', { method: 'POST', body: formData });
        const dados = await resposta.json();

        if (!dados.ok) {
            toast(dados.erro || 'Não foi possível gerar o relatório.', 'erro');
            return;
        }

        toast('Relatório gerado com sucesso.', 'sucesso');
        window.open('../scripts/relatorio_baixar.php?id=' + dados.idRelatorio + '&inline=1', '_blank');
        await carregarRelatorios();
    } catch (e) {
        toast('Erro de conexão. Tente novamente.', 'erro');
    } finally {
        btn.disabled = false;
        btn.style.opacity = '1';
        btnTexto.textContent = 'Gerar relatório PDF';
    }
}

function excluirRelatorio(idRelatorio) {
    Swal.fire({
        title: 'Tem certeza que deseja excluir?',
        text: 'O relatório em PDF será removido permanentemente.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sim, excluir',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#8c1f28',
        background: '#182338',
        color: '#e9eef6'
    }).then(function (resultado) {
        if (!resultado.isConfirmed) return;

        const formData = new FormData();
        formData.set('idRelatorio', idRelatorio);

        fetch('../scripts/relatorio_excluir.php', { method: 'POST', body: formData })
            .then(function (resposta) { return resposta.json(); })
            .then(function (dados) {
                if (!dados.ok) {
                    toast(dados.erro || 'Não foi possível excluir o relatório.', 'erro');
                    return;
                }
                toast('Relatório excluído.', 'sucesso');
                carregarRelatorios();
            })
            .catch(function () { toast('Erro de conexão. Tente novamente.', 'erro'); });
    });
}

document.addEventListener('DOMContentLoaded', carregarRelatorios);
</script>

</body>
</html>
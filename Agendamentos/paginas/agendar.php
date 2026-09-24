<?php
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/guard.php';
exigirSessao(['barbeiro']);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/forma_pagamento.php';

$paginaAtual = 'agendar';

// Serviços disponíveis, usados no <select> do modal de novo agendamento.
$servicos = $pdo->query("SELECT idServico, nome, valor FROM Servico WHERE ativo = 1 ORDER BY nome ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<?php include __DIR__ . '/../../includes/theme-init.php'; ?>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Agendar — BarbERP</title>

<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../../assets/css/admin-theme.css?v=2">
<link rel="stylesheet" href="../../assets/css/forma-pagamento.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    .cal-nav-btn{
        width:38px; height:38px;
        display:flex; align-items:center; justify-content:center;
        border-radius:0.65rem;
        border:1px solid rgba(255,255,255,0.08);
        color:#aebdd6;
        transition:background-color .15s, border-color .15s, color .15s;
    }
    .cal-nav-btn:hover{
        background:rgba(61,126,201,0.10);
        border-color:rgba(61,126,201,0.35);
        color:var(--gold-light);
    }

    /* ---------- Lista de horários ---------- */
    .horario-linha{
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:0.75rem;
        padding:0.85rem 1rem;
        border-radius:0.85rem;
        border:1px solid transparent;
        cursor:pointer;
        transition:filter .15s, transform .1s;
    }
    .horario-linha:active{ transform:scale(0.99); }

    .horario-livre{
        background:rgba(66,140,82,0.12);
        border-color:rgba(66,140,82,0.35);
    }
    .horario-livre:hover{ filter:brightness(1.15); }

    .horario-cheio{
        background:rgba(140,31,40,0.12);
        border-color:rgba(140,31,40,0.4);
    }
    .horario-cheio:hover{ filter:brightness(1.15); }

    /* ---------- Agendamento feito pelo cliente no link público ---------- */
    .horario-publico{
        background:rgba(249,115,22,0.12);
        border-color:rgba(249,115,22,0.4);
    }
    .horario-publico:hover{ filter:brightness(1.15); }
    .status-dot-publico{ background:#f97316; box-shadow:0 0 6px rgba(249,115,22,0.7); }
    .badge-publico{
        display:inline-flex; align-items:center; gap:4px;
        font-size:10.5px; font-weight:600; letter-spacing:.02em;
        color:#fdba8c; background:rgba(249,115,22,0.14);
        border:1px solid rgba(249,115,22,0.35);
        border-radius:999px; padding:2px 8px;
    }

    .horario-passado{
        opacity:0.45;
        cursor:not-allowed;
    }
    .horario-passado:hover{ filter:none; }

    .horario-inativo{
        background:rgba(230,179,60,0.12);
        border-color:rgba(230,179,60,0.35);
        cursor:not-allowed;
    }
    .horario-inativo:hover{ filter:none; }

    .status-dot-inativo{ background:#e6b33c; box-shadow:0 0 6px rgba(230,179,60,0.7); }

    /* ---------- Agendamento duplicado (dois horários vinculados) ---------- */
    .horario-duplicado{
        background:rgba(139,92,246,0.14);
        border-color:rgba(139,92,246,0.4);
    }
    .horario-duplicado:hover{ filter:brightness(1.15); }
    .status-dot-duplicado{ background:#8b5cf6; box-shadow:0 0 6px rgba(139,92,246,0.7); }
    .badge-duplicado{
        display:inline-flex; align-items:center; gap:4px;
        font-size:10.5px; font-weight:600; letter-spacing:.02em;
        color:#c4b5fd; background:rgba(139,92,246,0.14);
        border:1px solid rgba(139,92,246,0.35);
        border-radius:999px; padding:2px 8px;
    }

    .btn-inativar-horario{
        background:rgba(140,31,40,0.12);
        border:1px solid rgba(140,31,40,0.35);
        color:#e0a2a8;
    }
    .btn-inativar-horario:hover{ background:rgba(140,31,40,0.2); }
    .btn-reativar-horario{
        background:rgba(66,140,82,0.12);
        border:1px solid rgba(66,140,82,0.35);
        color:#9fd4ab;
    }
    .btn-reativar-horario:hover{ background:rgba(66,140,82,0.2); }

    .horario-hora{
        font-family:'Bebas Neue', sans-serif;
        letter-spacing:0.04em;
        font-size:19px;
        color:var(--cream);
        min-width:58px;
    }
    .horario-info{
        font-size:12.5px;
        color:#8fa0bd;
        text-align:right;
    }
    .status-dot{
        width:8px; height:8px; border-radius:999px; display:inline-block; margin-right:6px;
    }
    .status-dot-livre{ background:#4caf6a; box-shadow:0 0 6px rgba(76,175,106,0.7); }
    .status-dot-cheio{ background:#e05a63; box-shadow:0 0 6px rgba(224,90,99,0.7); }
    /* Horário livre cujo último atendimento já foi Concluído (ver
       horarios_buscar.php > concluidoNome) — continua "horario-livre"
       (pode ser reservado normalmente), só muda o rótulo/pontinho. */
    .status-dot-concluido{ background:#4fae67; box-shadow:0 0 6px rgba(79,174,103,0.7); }
    /* Horário livre cujo último atendimento foi "Cliente Ausente" (ver
       horarios_buscar.php > ausenteNome) — mesma ideia do concluído
       (continua reservável), mas em outra cor pra deixar claro que não
       houve atendimento de verdade, só uma tentativa. */
    .status-dot-ausente{ background:#e0a24a; box-shadow:0 0 6px rgba(224,162,74,0.7); }

    /* ---------- Modais ---------- */
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
    .icon-btn:hover{
        color:var(--gold-light);
        border-color:rgba(61,126,201,0.4);
        background:rgba(61,126,201,0.08);
    }
    .tab-btn{
        flex:1;
        padding:0.6rem 0.5rem;
        border-radius:0.65rem;
        font-size:13px;
        text-align:center;
        color:#7288a6;
        border:1px solid rgba(255,255,255,0.08);
        cursor:pointer;
        transition:background-color .15s, border-color .15s, color .15s;
    }
    .tab-btn.tab-ativa{
        background:linear-gradient(180deg, var(--gold-light), var(--gold));
        color:#ffffff;
        font-weight:600;
        border-color:transparent;
    }
    .resultado-cliente{
        padding:0.65rem 0.85rem;
        border-radius:0.65rem;
        border:1px solid rgba(255,255,255,0.07);
        cursor:pointer;
        font-size:13px;
        color:var(--cream);
        transition:background-color .15s, border-color .15s;
    }
    .resultado-cliente:hover{
        background:rgba(61,126,201,0.1);
        border-color:rgba(61,126,201,0.35);
    }
    .cliente-selecionado{
        background:rgba(61,126,201,0.1);
        border:1px solid rgba(61,126,201,0.35);
        border-radius:0.75rem;
        padding:0.75rem 1rem;
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:0.75rem;
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

    /* ---------- Modal: calendário ---------- */
    .cal-modal-nav-btn{
        width:32px; height:32px;
        display:flex; align-items:center; justify-content:center;
        border-radius:0.55rem;
        border:1px solid rgba(255,255,255,0.08);
        color:#aebdd6;
        transition:background-color .15s, border-color .15s, color .15s;
    }
    .cal-modal-nav-btn:hover{
        background:rgba(61,126,201,0.10);
        border-color:rgba(61,126,201,0.35);
        color:var(--gold-light);
    }
    .cal-weekday{
        font-size:11px;
        text-align:center;
        color:#7f8fac;
        letter-spacing:0.03em;
        padding-bottom:0.5rem;
    }
    .cal-grid{
        display:grid;
        grid-template-columns:repeat(7, 1fr);
        gap:4px;
    }
    .cal-day{
        aspect-ratio:1;
        display:flex;
        align-items:center;
        justify-content:center;
        font-size:13px;
        color:var(--cream);
        border-radius:0.6rem;
        border:1px solid transparent;
        cursor:pointer;
        transition:background-color .15s, border-color .15s, color .15s;
    }
    .cal-day:hover{
        background:rgba(61,126,201,0.10);
        border-color:rgba(61,126,201,0.3);
    }
    .cal-day-outro-mes{ color:#3c5170; }
    .cal-day-passado{ color:#57667f; }
    .cal-day-hoje{
        border-color:rgba(61,126,201,0.5);
        color:var(--gold-light);
        font-weight:600;
    }
    .cal-day-selecionado{
        background:linear-gradient(180deg, var(--gold-light), var(--gold));
        color:#ffffff;
        font-weight:700;
        border-color:transparent;
    }
    .cal-day-selecionado:hover{ background:linear-gradient(180deg, var(--gold-light), var(--gold)); }

    /* ---------- Modal "Selecione os Cortes" (agendamento duplicado) ---------- */
    .corte-modal-label{
        display:block;
        font-size:11px;
        letter-spacing:0.08em;
        text-transform:uppercase;
        color:#aebdd6;
        font-weight:500;
        margin-bottom:8px;
    }
    .corte-modal-label span{
        text-transform:none;
        letter-spacing:normal;
        font-weight:400;
        font-size:12px;
    }
    .corte-modal-label .obrigatorio{ color:#e0a2a8; }
    .corte-modal-label .opcional{ color:#7f8fac; }
    .corte-modal-select-wrap{ position:relative; }
    .corte-modal-select-wrap + .corte-modal-label{ margin-top:18px; }
    .corte-modal-select{
        width:100%;
        height:46px;
        padding:0 40px 0 14px;
        border-radius:0.75rem;
        background:rgba(0,0,0,0.35);
        border:1px solid rgba(255,255,255,0.08);
        color:#e9eef6;
        font-size:14px;
        font-family:inherit;
        appearance:none;
        -webkit-appearance:none;
        -moz-appearance:none;
        cursor:pointer;
        transition:border-color .2s, box-shadow .2s, background-color .2s;
    }
    .corte-modal-select:hover{ border-color:rgba(61,126,201,0.35); }
    .corte-modal-select:focus{
        outline:none;
        border-color:var(--gold);
        background:rgba(0,0,0,0.5);
        box-shadow:0 0 0 4px rgba(61,126,201,0.12);
    }
    .corte-modal-select option{ background:#101828; color:#e9eef6; }
    .corte-modal-chevron{
        position:absolute;
        right:14px;
        top:50%;
        transform:translateY(-50%);
        pointer-events:none;
        color:#7f8fac;
    }
    /* Ícone do calendário nativo do <input type="date"> é escuro por
       padrão — inverte pra ficar visível no tema escuro (usado no modal
       "Reagendar"). */
    input[type="date"].corte-modal-select::-webkit-calendar-picker-indicator{
        filter:invert(0.85);
        opacity:0.8;
        cursor:pointer;
    }

    /* ---------- Indicador de disponibilidade (dots) no calendário ---------- */
    .cal-day-wrap{
        display:flex; flex-direction:column; align-items:center; justify-content:center;
        gap:2px;
    }
    .cal-day-dot{
        width:5px; height:5px; border-radius:999px; flex-shrink:0;
    }
    .cal-day-dot-vermelho{ background:#e05a63; }
    .cal-day-dot-amarelo{ background:#e6b33c; }
    .cal-day-dot-verde{ background:#4caf6a; }

    /* ---------- Bloqueio de dia inteiro (barra de navegação + calendário) ---------- */
    /* Mesma cor âmbar usada em .horario-inativo/.status-dot-inativo, pra um
       dia bloqueado ser reconhecido de imediato como a mesma "linguagem"
       visual de indisponibilidade já usada nos horários. */
    .cal-nav-btn.is-bloqueado{
        background:rgba(230,179,60,0.14);
        border-color:rgba(230,179,60,0.45);
        color:#e6c27a;
    }
    .cal-nav-btn.is-bloqueado:hover{
        background:rgba(230,179,60,0.22);
        border-color:rgba(230,179,60,0.6);
        color:#e6c27a;
    }
    .cal-day-bloqueado{
        background:rgba(230,179,60,0.16);
        border-color:rgba(230,179,60,0.45);
        color:#e6c27a;
    }
    .cal-day-bloqueado:hover{
        background:rgba(230,179,60,0.24);
        border-color:rgba(230,179,60,0.6);
    }
    .cal-day-bloqueado.cal-day-selecionado{
        background:linear-gradient(180deg, var(--gold-light), var(--gold));
        color:#ffffff;
    }
    .cal-day-lock{
        width:10px; height:10px; flex-shrink:0; color:#e6c27a;
    }
    .aviso-dia-bloqueado{
        background:rgba(230,179,60,0.12);
        border:1px solid rgba(230,179,60,0.35);
        color:#e6c27a;
    }

    /* ---------- Modal de detalhes/atendimento (mais largo, ações no topo) ---------- */
    .det-acoes{
        display:flex;
        flex-wrap:wrap;
        align-items:center;
        gap:0.5rem;
    }
    .det-acoes__fechar{
        margin-left:auto;
        color:#7f8fac;
        transition:color .15s;
    }
    .det-acoes__fechar:hover{ color:var(--cream); }
    .det-info-grid{
        display:grid;
        grid-template-columns:repeat(2, 1fr);
        gap:0.6rem;
    }
    @media (min-width: 480px){
        .det-info-grid{ grid-template-columns:repeat(3, 1fr); }
    }
    .det-field{
        padding:0.65rem 0.85rem;
        border-radius:0.75rem;
        background:rgba(255,255,255,0.02);
        border:1px solid rgba(255,255,255,0.06);
        min-width:0;
    }
    .det-field--full{ grid-column:1 / -1; }
    .det-field__label{
        font-size:10.5px;
        letter-spacing:0.08em;
        text-transform:uppercase;
        color:#7f8fac;
        margin-bottom:3px;
    }
    .det-field__value{
        font-size:14px;
        color:var(--cream);
        word-break:break-word;
    }

    /* ---------- Lista de Espera (popup flutuante) ---------- */
    .lista-espera-fab{
        position:fixed;
        right:1.5rem;
        bottom:1.5rem;
        z-index:40;
        width:54px; height:54px;
        border-radius:999px;
        display:flex; align-items:center; justify-content:center;
        background:linear-gradient(180deg, var(--gold-light), var(--gold));
        color:#ffffff;
        box-shadow:0 8px 24px rgba(0,0,0,0.35);
        border:none;
        cursor:pointer;
        transition:transform .15s;
    }
    .lista-espera-fab:hover{ transform:scale(1.06); }
    .lista-espera-fab__badge{
        position:absolute;
        top:-4px; right:-4px;
        min-width:20px; height:20px; padding:0 5px;
        border-radius:999px;
        background:#8b5cf6;
        color:#fff;
        font-size:11px; font-weight:700;
        display:flex; align-items:center; justify-content:center;
        border:2px solid var(--charcoal, #101828);
    }
    .lista-espera-painel{
        position:fixed;
        right:1.5rem;
        bottom:5.5rem;
        z-index:40;
        width:min(360px, calc(100vw - 2rem));
        max-height:min(520px, calc(100vh - 8rem));
        display:flex;
        flex-direction:column;
        overflow:hidden;
    }
    .lista-espera-item{
        display:flex; align-items:center; justify-content:space-between; gap:0.5rem;
        padding:0.65rem 0.75rem;
        border-radius:0.65rem;
        border:1px solid rgba(255,255,255,0.07);
        background:rgba(255,255,255,0.02);
    }
    @media (max-width: 480px){
        .lista-espera-fab{ right:1rem; bottom:1rem; }
        .lista-espera-painel{ right:0.5rem; left:0.5rem; width:auto; bottom:4.75rem; }
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
            <p class="eyebrow uppercase mb-1" style="color:var(--gold-light); opacity:.75">Agendamento</p>
            <h1 class="display text-3xl sm:text-4xl text-[color:var(--cream)] truncate">Agendar</h1>
        </div>
    </header>

    <section class="p-5 sm:p-8">

        <!-- Faixa de navegação por data -->
        <div class="panel-card rounded-2xl overflow-hidden mb-6">
            <div class="barber-stripe-thin"></div>

            <div class="p-3.5 sm:p-5 flex items-center justify-between gap-2 sm:gap-4">
                <button type="button" id="btnDiaAnterior" class="cal-nav-btn shrink-0" aria-label="Dia anterior">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>

                <button type="button" id="btnAbrirCalendario" class="cal-nav-btn shrink-0" aria-label="Escolher data no calendário">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M8 2v3M16 2v3M3.5 9h17M4 5h16a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>

                <button type="button" id="btnBloquearDia" class="cal-nav-btn shrink-0" aria-label="Bloquear este dia" title="Bloquear este dia">
                    <svg id="iconBloquearDia" width="16" height="16" viewBox="0 0 24 24" fill="none">
                        <rect x="5" y="10" width="14" height="10" rx="2" stroke="currentColor" stroke-width="1.6"/>
                        <path d="M8 10V7a4 4 0 0 1 8 0v3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                    </svg>
                </button>

                <div id="areaTituloData" class="relative flex-1 min-w-0 text-center cursor-pointer select-none">
                    <p id="tituloData" class="display text-lg sm:text-xl md:text-2xl text-[color:var(--cream)] tracking-wide capitalize truncate leading-tight">—</p>
                    <p id="subtituloData" class="text-sm sm:text-base md:text-lg text-zinc-400 mt-0.5 truncate">—</p>
                </div>

                <button type="button" id="btnHoje" class="btn-secondary h-9 w-9 sm:w-auto sm:px-3.5 rounded-lg text-xs shrink-0 inline-flex items-center justify-center gap-1.5" aria-label="Ir para hoje">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" class="sm:hidden"><path d="M8 2v3M16 2v3M3.5 9h17M4 5h16a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <span class="hidden sm:inline">Hoje</span>
                </button>

                <button type="button" id="btnDiaProximo" class="cal-nav-btn shrink-0" aria-label="Próximo dia">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
            </div>

            <div class="px-4 sm:px-5 pb-3.5 sm:pb-4 -mt-1 flex items-center justify-center gap-4 flex-wrap">
                <span class="flex items-center text-[11px] text-zinc-400"><span class="status-dot status-dot-livre"></span> Livre</span>
                <span class="flex items-center text-[11px] text-zinc-400"><span class="status-dot status-dot-cheio"></span> Ocupado</span>
                <span class="flex items-center text-[11px] text-zinc-400"><span class="status-dot status-dot-publico"></span> Via link</span>
            </div>
        </div>

        <!-- Lista de horários -->
        <div class="panel-card rounded-2xl overflow-hidden min-h-[420px] flex flex-col">
            <div class="barber-stripe-thin"></div>

            <div class="p-4 sm:p-6 flex-1 flex flex-col">
                <p class="text-sm font-medium text-[color:var(--cream)] mb-4 sm:mb-6">Horários do dia</p>

                <div id="avisoDiaBloqueado" class="hidden aviso-dia-bloqueado rounded-xl px-4 py-3 text-xs sm:text-sm mb-4">
                    ⛔ Este dia está bloqueado — novos agendamentos não podem ser criados aqui. Agendamentos já existentes continuam normalmente.
                </div>

                <div id="estadoCarregando" class="hidden flex-1 flex flex-col items-center justify-center text-center opacity-50">
                    <p class="text-xs text-zinc-500">Carregando horários...</p>
                </div>

                <div id="listaHorarios" class="hidden grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-2.5"></div>
            </div>
        </div>

    </section>

</main>

<!-- ==================== MODAL: NOVO AGENDAMENTO (horário livre) ==================== -->
<div id="modal-agendar" class="modal-overlay fixed inset-0 z-50 hidden flex items-center justify-center p-4" onclick="fecharAoClicarFora(event, 'modal-agendar')">
    <div class="modal-card rounded-3xl shadow-2xl overflow-hidden w-full max-w-4xl max-h-[92vh] overflow-y-auto">
        <div class="barber-stripe-thin"></div>
        <div class="p-7">
            <div class="flex items-start justify-between mb-1">
                <h2 class="display text-2xl text-[color:var(--cream)]">Novo Agendamento</h2>
                <button type="button" onclick="closeModal('modal-agendar')" class="icon-btn shrink-0">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>
            <p class="text-xs text-zinc-500 mb-6" id="agendar-subtitulo">—</p>

            <form id="form-agendar" autocomplete="off">
                <input type="hidden" id="agendar-idHorario" name="idHorario" value="">
                <input type="hidden" id="agendar-idCliente" name="idCliente" value="">
                <input type="hidden" id="agendar-idHorarioSecundario" name="idHorarioSecundario" value="">
                <input type="hidden" id="agendar-idServicoSecundario" name="idServicoSecundario" value="">

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-8">
                <div>
                <!-- Abas: cliente existente x novo cliente -->
                <div class="flex gap-2 mb-4">
                    <div class="tab-btn tab-ativa" id="tab-buscar" onclick="trocarAbaCliente('buscar')">Buscar cliente</div>
                    <div class="tab-btn" id="tab-novo" onclick="trocarAbaCliente('novo')">Cliente novo</div>
                </div>

                <!-- Aba: buscar cliente -->
                <div id="painel-buscar">
                    <div id="bloco-busca-cliente">
                        <label class="field-label block mb-1.5">Buscar por nome ou telefone</label>
                        <input type="text" id="busca-cliente-input" placeholder="Digite para buscar..."
                               class="field w-full h-11 px-4 rounded-xl text-sm mb-2">
                        <div id="resultados-cliente" class="flex flex-col gap-1.5 max-h-40 overflow-y-auto"></div>
                    </div>

                    <div id="bloco-cliente-selecionado" class="hidden mb-1">
                        <label class="field-label block mb-1.5">Cliente</label>
                        <div class="cliente-selecionado">
                            <div>
                                <p class="text-sm text-[color:var(--cream)]" id="cliente-sel-nome">—</p>
                                <p class="text-xs text-zinc-500" id="cliente-sel-telefone">—</p>
                            </div>
                            <button type="button" onclick="limparClienteSelecionado()" class="icon-btn shrink-0">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Aba: cadastrar cliente novo -->
                <div id="painel-novo" class="hidden">
                    <div class="mb-3">
                        <label class="field-label block mb-1.5">Nome</label>
                        <input type="text" id="novo-nome" name="novoNome" placeholder="Nome do cliente" class="field w-full h-11 px-4 rounded-xl text-sm">
                    </div>
                    <div class="grid grid-cols-2 gap-3 mb-1">
                        <div>
                            <label class="field-label block mb-1.5">Telefone</label>
                            <input type="text" id="novo-telefone" name="novoTelefone" placeholder="(00) 00000-0000" class="field w-full h-11 px-4 rounded-xl text-sm">
                        </div>
                        <div>
                            <label class="field-label block mb-1.5">E-mail (opcional)</label>
                            <input type="email" id="novo-email" name="novoEmail" placeholder="email@exemplo.com" class="field w-full h-11 px-4 rounded-xl text-sm">
                        </div>
                    </div>
                </div>

                </div>
                <div>
                <div class="mt-5 mb-3 lg:mt-0">
                    <label class="field-label block mb-1.5" id="label-agendar-servico">Serviço</label>
                    <select id="agendar-idServico" name="idServico" class="field w-full h-11 px-4 rounded-xl text-sm">
                        <option value="">Selecione...</option>
                        <?php foreach ($servicos as $servico): ?>
                            <option value="<?= (int) $servico['idServico'] ?>">
                                <?= htmlspecialchars($servico['nome']) ?> — R$ <?= number_format((float) $servico['valor'], 2, ',', '.') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($servicos)): ?>
                        <p class="text-xs text-[#e0a2a8] mt-1.5">Nenhum serviço cadastrado. Rode scriptBD/atualizacao_servicos.sql.</p>
                    <?php endif; ?>
                </div>

                <div class="fiado-toggle-row mb-4" id="duplicar-toggle-row">
                    <div>
                        <p class="field-label mb-0.5">Duplicar horário</p>
                        <p class="text-xs text-zinc-500" id="duplicar-toggle-legenda">Reserva este horário e o próximo, para um só atendimento (ex.: pai e filho).</p>
                    </div>
                    <div class="flex items-center gap-3 shrink-0">
                        <span id="duplicar-estado" class="fiado-toggle-row__estado">OFF</span>
                        <label class="switch">
                            <input type="checkbox" id="agendar-duplicar" name="duplicar" value="1" onchange="alternarDuplicarHorario(this.checked)">
                            <span class="switch-track"></span>
                        </label>
                    </div>
                </div>

                <div id="duplicar-detalhes" class="hidden mb-4">
                    <p id="duplicar-info" class="text-xs mt-1 mb-2" style="color:var(--gold-light);">—</p>
                    <button type="button" id="btn-alterar-cortes" onclick="abrirModalSelecionarCortes()" class="btn-secondary h-9 px-3.5 rounded-lg text-xs">
                        Selecione os Cortes
                    </button>
                </div>

                <div class="mb-4" id="fidelidade-wrapper">
                    <label class="field-label block mb-2">Agendamento de Fidelidade <span class="text-zinc-500 normal-case">(opcional)</span></label>
                    <div class="forma-grid" id="fidelidade-grid">
                        <input type="radio" class="forma-check" id="fidelidade-sem" name="fidelidade" value="" checked>
                        <label for="fidelidade-sem" class="forma-label">
                            <span class="forma-label__check"></span>
                            Sem fidelidade
                        </label>

                        <input type="radio" class="forma-check" id="fidelidade-7" name="fidelidade" value="1">
                        <label for="fidelidade-7" class="forma-label">
                            <span class="forma-label__check"></span>
                            7 em 7 dias
                        </label>

                        <input type="radio" class="forma-check" id="fidelidade-15" name="fidelidade" value="2">
                        <label for="fidelidade-15" class="forma-label">
                            <span class="forma-label__check"></span>
                            15 em 15 dias
                        </label>

                        <input type="radio" class="forma-check" id="fidelidade-20" name="fidelidade" value="3">
                        <label for="fidelidade-20" class="forma-label">
                            <span class="forma-label__check"></span>
                            20 em 20 dias
                        </label>

                        <input type="radio" class="forma-check" id="fidelidade-30" name="fidelidade" value="4">
                        <label for="fidelidade-30" class="forma-label">
                            <span class="forma-label__check"></span>
                            30 em 30 dias
                        </label>
                    </div>
                    <p id="fidelidade-aviso" class="hidden text-xs mt-2" style="color:var(--gold-light);">—</p>
                </div>
                </div>
                </div>

                <div class="mb-2">
                    <label class="field-label block mb-1.5">Observação (opcional)</label>
                    <textarea id="agendar-observacao" name="observacao" rows="2" placeholder="Alguma observação sobre o atendimento..."
                              class="field w-full px-4 py-2.5 rounded-xl text-sm resize-none"></textarea>
                </div>

                <p id="agendar-erro" class="hidden text-xs text-[#e0a2a8] mt-2"></p>

                <div class="flex gap-3 mt-6">
                    <button type="submit" id="btn-salvar-agendamento" class="btn-primary h-12 px-6 rounded-xl text-sm flex-1">
                        Confirmar agendamento
                    </button>
                    <button type="button" onclick="closeModal('modal-agendar')" class="btn-secondary h-12 px-6 rounded-xl text-sm">
                        Cancelar
                    </button>
                </div>
            </form>

            <div class="mt-3 pt-3" style="border-top:1px solid rgba(255,255,255,0.06)">
                <button type="button" id="btn-inativar-horario" onclick="inativarHorario()"
                        class="btn-inativar-horario h-11 w-full rounded-xl text-sm font-medium transition-colors">
                    Inativar horário
                </button>
                <p class="text-[11px] text-zinc-500 text-center mt-2">
                    Bloqueia este horário apenas neste dia. Nos outros dias ele continua ativo normalmente.
                </p>
            </div>
        </div>
        <div class="barber-stripe-thin"></div>
    </div>
</div>

<!-- ==================== MODAL: CALENDÁRIO (filtrar dia) ==================== -->
<div id="modal-calendario" class="modal-overlay fixed inset-0 z-50 hidden flex items-center justify-center p-4" onclick="fecharAoClicarFora(event, 'modal-calendario')">
    <div class="modal-card rounded-3xl shadow-2xl overflow-hidden w-full max-w-sm">
        <div class="barber-stripe-thin"></div>
        <div class="p-6">
            <div class="flex items-center justify-between mb-5">
                <button type="button" id="calModalMesAnterior" class="cal-modal-nav-btn shrink-0" aria-label="Mês anterior">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
                <p id="calModalTituloMes" class="display text-xl text-[color:var(--cream)] tracking-wide capitalize">—</p>
                <button type="button" id="calModalMesProximo" class="cal-modal-nav-btn shrink-0" aria-label="Próximo mês">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
            </div>

            <div class="cal-grid mb-1.5">
                <span class="cal-weekday">D</span>
                <span class="cal-weekday">S</span>
                <span class="cal-weekday">T</span>
                <span class="cal-weekday">Q</span>
                <span class="cal-weekday">Q</span>
                <span class="cal-weekday">S</span>
                <span class="cal-weekday">S</span>
            </div>
            <div id="calModalGrade" class="cal-grid"></div>

            <button type="button" id="calModalIrHoje" class="btn-secondary h-10 w-full rounded-xl text-xs mt-5">
                Ir para hoje
            </button>
        </div>
        <div class="barber-stripe-thin"></div>
    </div>
</div>

<!-- ==================== MODAL: DETALHES (horário ocupado) ==================== -->
<div id="modal-detalhes" class="modal-overlay fixed inset-0 z-50 hidden flex items-center justify-center p-4" onclick="fecharAoClicarFora(event, 'modal-detalhes')">
    <div class="modal-card rounded-3xl shadow-2xl overflow-hidden w-full max-w-2xl max-h-[92vh] flex flex-col">
        <div class="barber-stripe-thin shrink-0"></div>
        <div class="p-6 sm:p-7 overflow-y-auto">
            <div class="flex items-start justify-between mb-5">
                <h2 class="display text-2xl text-[color:var(--cream)]">Agendamento</h2>
                <button type="button" onclick="closeModal('modal-detalhes')" class="icon-btn shrink-0">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>

            <!-- Ações principais, sempre visíveis no topo -->
            <div class="det-acoes mb-6">
                <button type="button" id="btn-concluir-agendamento" onclick="concluirAgendamento()" class="btn-primary h-10 px-4 rounded-xl text-sm">
                    Concluir
                </button>
                <button type="button" id="btn-alterar-agendamento" onclick="abrirMenuAlterarAgendamento()" class="btn-secondary h-10 px-4 rounded-xl text-sm">
                    🔁 Alterar agendamento
                </button>
                <button type="button" id="btn-cancelar-agendamento" onclick="confirmarCancelamento()" class="btn-secondary h-10 px-4 rounded-xl text-sm" style="color:#e0a2a8;">
                    Cancelar
                </button>
                <button type="button" onclick="closeModal('modal-detalhes')" class="det-acoes__fechar h-10 px-3 rounded-xl text-sm">
                    Fechar
                </button>
            </div>

            <div class="det-info-grid">
                <div class="det-field det-field--full hidden" id="det-status-row">
                    <p class="det-field__label">Status</p>
                    <p class="det-field__value" id="det-status">—</p>
                </div>
                <div class="det-field">
                    <p class="det-field__label">Horário</p>
                    <p class="det-field__value" id="det-hora">—</p>
                </div>
                <div class="det-field">
                    <p class="det-field__label">Cliente</p>
                    <p class="det-field__value" id="det-nome">—</p>
                </div>
                <div class="det-field">
                    <p class="det-field__label">Telefone</p>
                    <p class="det-field__value" id="det-telefone">—</p>
                </div>
                <div class="det-field">
                    <p class="det-field__label">Serviço</p>
                    <p class="det-field__value" id="det-servico">—</p>
                </div>
                <div class="det-field">
                    <p class="det-field__label">Barba</p>
                    <p class="det-field__value" id="det-barba">—</p>
                </div>
                <div class="det-field det-field--full" id="det-observacao-row">
                    <p class="det-field__label">Observação</p>
                    <p class="det-field__value" id="det-observacao">—</p>
                </div>
                <div class="det-field det-field--full hidden" id="det-duplicado-row">
                    <p class="det-field__label">🟣 Duplicado</p>
                    <p class="det-field__value" id="det-duplicado-valor">—</p>
                </div>
                <div class="det-field det-field--full hidden" id="det-origem-row">
                    <p class="det-field__label" style="color:#fdba8c;">🟠 Origem</p>
                    <p class="det-field__value">Cliente agendou sozinho pelo link público</p>
                </div>
            </div>

            <input type="hidden" id="det-idAgendamento" value="">
        </div>
        <div class="barber-stripe-thin shrink-0"></div>
    </div>
</div>

<!-- ==================== MODAL: CONCLUIR AGENDAMENTO ==================== -->
<div id="modal-concluir" class="modal-overlay fixed inset-0 z-50 hidden flex items-center justify-center p-4" onclick="fecharAoClicarForaConcluir(event)">
    <div class="modal-card rounded-3xl shadow-2xl overflow-hidden w-full max-w-lg max-h-[90vh] flex flex-col">
        <div class="barber-stripe-thin shrink-0"></div>
        <div class="p-7 overflow-y-auto">
            <div class="flex items-start justify-between mb-6">
                <h2 class="display text-2xl text-[color:var(--cream)]">Concluir Agendamento</h2>
                <button type="button" onclick="fecharModalConcluir()" class="icon-btn shrink-0">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>

            <form id="form-concluir" autocomplete="off" onsubmit="confirmarConclusao(event)">
                <div class="mb-6">
                    <div class="view-row">
                        <span class="view-label">Cliente</span>
                        <span class="view-value" id="concluir-nome">—</span>
                    </div>

                    <!-- Agendamento avulso: um único campo de serviço -->
                    <div class="view-row" id="concluir-servico-solo-row" style="align-items:center;">
                        <span class="view-label">Serviço realizado</span>
                        <div class="corte-modal-select-wrap" style="max-width:230px; flex:1;">
                            <select id="concluir-idServico" name="idServicoRealizado"
                                    class="corte-modal-select" style="height:38px; font-size:13px; padding:0 34px 0 12px;"
                                    onchange="atualizarValorConcluir()"></select>
                            <svg class="corte-modal-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </div>
                    </div>

                    <!-- Agendamento duplicado: dois campos independentes, um
                         por horário — cada pessoa pode ter feito um corte
                         diferente do que constava no agendamento original
                         (ex.: pai e filho, cada um com seu próprio corte). -->
                    <div id="concluir-servico-duplicado-wrap" class="hidden">
                        <div class="view-row" style="align-items:center;">
                            <span class="view-label" id="concluir-duplicado-label-1">Corte do Horário 1</span>
                            <div class="corte-modal-select-wrap" style="max-width:230px; flex:1;">
                                <select id="concluir-idServico-principal" class="corte-modal-select" style="height:38px; font-size:13px; padding:0 34px 0 12px;" onchange="atualizarValorConcluir()"></select>
                                <svg class="corte-modal-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </div>
                        </div>
                        <div class="view-row" style="align-items:center;">
                            <span class="view-label" id="concluir-duplicado-label-2">Corte do Horário 2</span>
                            <div class="corte-modal-select-wrap" style="max-width:230px; flex:1;">
                                <select id="concluir-idServico-secundario" class="corte-modal-select" style="height:38px; font-size:13px; padding:0 34px 0 12px;" onchange="atualizarValorConcluir()"></select>
                                <svg class="corte-modal-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </div>
                        </div>
                        <p id="concluir-servico-aviso-duplicado" class="text-[11px] text-zinc-500 mt-1" style="text-align:right;">
                            Cada horário pode ter um corte diferente do que foi originalmente agendado.
                        </p>
                    </div>

                    <div class="view-row">
                        <span class="view-label">Valor</span>
                        <span class="view-value" id="concluir-valor">—</span>
                    </div>
                </div>

                <!-- Toggle Cliente Ausente: quando ligado, esconde forma de
                     pagamento e "Receber depois" (não há cobrança nenhuma) -->
                <div class="fiado-toggle-row mb-6" id="ausente-toggle-row" style="border:1px solid rgba(224,162,74,0.35); background:rgba(224,162,74,0.08);">
                    <div>
                        <p class="field-label mb-0.5">Cliente ausente</p>
                        <p class="text-xs text-zinc-500">O cliente não veio. Libera o horário sem gerar cobrança no financeiro.</p>
                    </div>
                    <div class="flex items-center gap-3 shrink-0">
                        <span id="concluir-ausente-estado" class="fiado-toggle-row__estado">OFF</span>
                        <label class="switch">
                            <input type="checkbox" id="concluir-ausente" onchange="alternarEstadoAusente(this.checked)">
                            <span class="switch-track"></span>
                        </label>
                    </div>
                </div>

                <div class="mb-6" id="concluir-forma-wrap">
                    <?php renderFormaPagamentoCampo('concluir'); ?>
                    <p id="concluir-forma-fiado-aviso" class="text-xs text-zinc-500 mt-2 hidden"></p>
                </div>

                <!-- Toggle Fiado (reaproveita o componente .switch já usado em Configurações) -->
                <div class="fiado-toggle-row mb-7" id="concluir-fiado-wrap">
                    <div>
                        <p class="field-label mb-0.5">Receber depois</p>
                        <p class="text-xs text-zinc-500">Quando ligado, o pagamento fica pendente até o recebimento.</p>
                    </div>
                    <div class="flex items-center gap-3 shrink-0">
                        <span id="concluir-fiado-estado" class="fiado-toggle-row__estado">OFF</span>
                        <label class="switch">
                            <input type="checkbox" id="concluir-fiado" onchange="alternarEstadoFiado(this.checked)">
                            <span class="switch-track"></span>
                        </label>
                    </div>
                </div>

                <input type="hidden" id="concluir-idAgendamento" value="">
                <input type="hidden" id="concluir-idAgendamento-secundario" value="">

                <div class="flex gap-3">
                    <button type="submit" id="btn-confirmar-conclusao" class="btn-primary h-12 px-6 rounded-xl text-sm flex-1">
                        Concluir agendamento
                    </button>
                    <button type="button" onclick="fecharModalConcluir()" class="btn-secondary h-12 px-6 rounded-xl text-sm">
                        Cancelar
                    </button>
                </div>

                <!-- Só aparece quando é duplicado com o horário irmão ainda
                     ativo: concluir SÓ o horário que foi clicado, deixando o
                     outro em aberto (ex.: pai e filho, um sai antes do
                     outro) — ver agendamento_concluir_individual.php. -->
                <button type="button" id="btn-concluir-somente-este" onclick="confirmarConclusaoIndividual()" class="hidden w-full h-11 mt-3 rounded-xl text-sm btn-secondary">
                    Concluir só este horário
                </button>
            </form>
        </div>
        <div class="barber-stripe-thin shrink-0"></div>
    </div>
</div>

<!-- ==================== LISTA DE ESPERA (popup flutuante) ==================== -->
<button type="button" id="btnListaEspera" class="lista-espera-fab" onclick="toggleListaEspera()" aria-label="Lista de espera">
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.7"/>
        <path d="M12 7v5l3.2 2" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
    <span id="listaEsperaBadge" class="lista-espera-fab__badge hidden">0</span>
</button>

<div id="painelListaEspera" class="lista-espera-painel modal-card rounded-2xl shadow-2xl hidden">
    <div class="barber-stripe-thin shrink-0"></div>
    <div class="p-4 flex-1 overflow-y-auto flex flex-col min-h-0">
        <div class="flex items-center justify-between mb-3 shrink-0">
            <h3 class="display text-lg text-[color:var(--cream)]">Lista de Espera</h3>
            <button type="button" onclick="toggleListaEspera()" class="icon-btn shrink-0">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
            </button>
        </div>

        <div class="shrink-0 mb-3">
            <input type="text" id="le-busca-input" placeholder="Buscar cliente para adicionar..." class="field w-full h-10 px-3 rounded-lg text-sm">
            <div id="le-resultados" class="flex flex-col gap-1.5 mt-1.5"></div>
        </div>

        <p class="text-[11px] uppercase tracking-wide text-zinc-500 mb-2 shrink-0">Aguardando</p>
        <div id="le-lista" class="flex flex-col gap-2 overflow-y-auto"></div>
        <p id="le-vazio" class="text-xs text-zinc-500 text-center py-6">Ninguém na lista de espera.</p>
    </div>
</div>

<?php renderFormaPagamentoAssets(); ?>

<script>
// Lista de serviços disponível no PHP, reaproveitada aqui só para montar o
// modal "Selecione os Cortes" (agendamento duplicado) sem precisar de uma
// nova chamada ao servidor.
const SERVICOS_DISPONIVEIS = <?= json_encode(array_map(function ($s) {
    return ['id' => (int) $s['idServico'], 'nome' => $s['nome'], 'valor' => (float) $s['valor']];
}, $servicos)) ?>;

(function () {
    const hoje = new Date();
    hoje.setHours(0, 0, 0, 0);

    let dataSelecionada = new Date(hoje); // objeto Date, sempre à meia-noite
    let horariosAtuais = [];
    let requisicaoAtual = 0;
    let diaBloqueadoAtual = false; // dia selecionado está bloqueado pelo barbeiro? (ver DiaBloqueado)

    const tituloData         = document.getElementById('tituloData');
    const subtituloData      = document.getElementById('subtituloData');
    const estadoCarregando   = document.getElementById('estadoCarregando');
    const listaHorarios      = document.getElementById('listaHorarios');

    const nomesMes = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
    let mesModal = hoje.getMonth();
    let anoModal = hoje.getFullYear();

    const calModalTituloMes = document.getElementById('calModalTituloMes');
    const calModalGrade     = document.getElementById('calModalGrade');

    function pad(n) { return n < 10 ? '0' + n : '' + n; }

    function formatarDataChave(d) {
        return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
    }

    function atualizarFaixaData() {
        const diaMesAno = dataSelecionada.toLocaleDateString('pt-BR', { day: '2-digit', month: 'long', year: 'numeric' });

        if (dataSelecionada.getTime() === hoje.getTime()) {
            tituloData.textContent = 'Hoje';
        } else {
            const diaSemana = dataSelecionada.toLocaleDateString('pt-BR', { weekday: 'long' });
            tituloData.textContent = diaSemana.charAt(0).toUpperCase() + diaSemana.slice(1);
        }
        subtituloData.textContent = diaMesAno;
    }

    function irParaDia(d) {
        d.setHours(0, 0, 0, 0);
        dataSelecionada = d;
        atualizarFaixaData();
        carregarHorarios();
    }

    // ---------------- Modal: calendário para filtrar o dia ----------------
    // Indicador de disponibilidade (dots): uma única consulta agregada por
    // mês visível (Agendamentos/scripts/mes_disponibilidade.php), nunca uma
    // consulta por dia — resultado fica em cache em memória por mês/ano.
    const disponibilidadeMesCache = {};

    async function garantirDisponibilidadeMes(ano, mes) {
        const chave = ano + '-' + mes;
        if (disponibilidadeMesCache[chave]) return disponibilidadeMesCache[chave];

        try {
            const resposta = await fetch(`../scripts/mes_disponibilidade.php?mes=${mes + 1}&ano=${ano}`);
            const dados = await resposta.json();
            disponibilidadeMesCache[chave] = (dados.ok && dados.dias) ? dados.dias : {};
        } catch (e) {
            disponibilidadeMesCache[chave] = {};
        }

        return disponibilidadeMesCache[chave];
    }

    async function renderizarCalendarioModal() {
        calModalTituloMes.textContent = `${nomesMes[mesModal]} ${anoModal}`;
        calModalGrade.innerHTML = '';

        const mesRenderizado = mesModal;
        const anoRenderizado = anoModal;
        const diasDisponibilidade = await garantirDisponibilidadeMes(anoRenderizado, mesRenderizado);

        // Se o barbeiro já trocou de mês enquanto a consulta estava em
        // andamento, descarta este resultado — quem desenha por último é a
        // chamada mais recente de renderizarCalendarioModal().
        if (mesModal !== mesRenderizado || anoModal !== anoRenderizado) return;

        const primeiroDiaSemana = new Date(anoModal, mesModal, 1).getDay(); // 0=Dom
        const totalDiasMes = new Date(anoModal, mesModal + 1, 0).getDate();
        const totalDiasMesAnterior = new Date(anoModal, mesModal, 0).getDate();

        // Dias do fim do mês anterior, pra completar a primeira semana
        for (let i = primeiroDiaSemana; i > 0; i--) {
            const dia = totalDiasMesAnterior - i + 1;
            const d = new Date(anoModal, mesModal - 1, dia);
            calModalGrade.appendChild(criarCelulaCalModal(d, true, null));
        }

        for (let dia = 1; dia <= totalDiasMes; dia++) {
            const d = new Date(anoModal, mesModal, dia);
            calModalGrade.appendChild(criarCelulaCalModal(d, false, diasDisponibilidade));
        }

        // Completa a última semana com dias do mês seguinte
        const totalCelulas = primeiroDiaSemana + totalDiasMes;
        const sobras = (7 - (totalCelulas % 7)) % 7;
        for (let dia = 1; dia <= sobras; dia++) {
            const d = new Date(anoModal, mesModal + 1, dia);
            calModalGrade.appendChild(criarCelulaCalModal(d, true, null));
        }
    }

    function criarCelulaCalModal(d, outroMes, diasDisponibilidade) {
        d.setHours(0, 0, 0, 0);
        const celula = document.createElement('div');
        celula.className = 'cal-day';

        const numero = document.createElement('span');
        numero.textContent = d.getDate();
        celula.appendChild(numero);

        // Indicador do dia: cadeado (bloqueado pelo barbeiro) tem prioridade
        // sobre a bolinha de vagas — um dia bloqueado é bloqueado independente
        // de quantos horários livres ainda existam nele. A bolinha de vagas
        // continua só para dias do mês atual, hoje em diante (dias passados
        // não precisam de indicador).
        if (!outroMes && diasDisponibilidade) {
            const chaveDia = formatarDataChave(d);
            const info = diasDisponibilidade[chaveDia];

            if (info && info.bloqueado) {
                celula.classList.add('cal-day-wrap', 'cal-day-bloqueado');
                const cadeado = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                cadeado.setAttribute('viewBox', '0 0 24 24');
                cadeado.setAttribute('class', 'cal-day-lock');
                cadeado.innerHTML = '<rect x="5" y="10" width="14" height="10" rx="2" fill="none" stroke="currentColor" stroke-width="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>';
                const tituloCadeado = document.createElementNS('http://www.w3.org/2000/svg', 'title');
                tituloCadeado.textContent = 'Dia bloqueado';
                cadeado.appendChild(tituloCadeado);
                celula.appendChild(cadeado);
            } else if (info && info.status && d.getTime() >= hoje.getTime()) {
                celula.classList.add('cal-day-wrap');
                const dot = document.createElement('span');
                dot.className = 'cal-day-dot cal-day-dot-' + info.status;
                dot.title = info.vagas + ' vaga(s) disponível(is)';
                celula.appendChild(dot);
            }
        }

        if (outroMes) celula.classList.add('cal-day-outro-mes');
        if (d.getTime() < hoje.getTime()) celula.classList.add('cal-day-passado');
        if (d.getTime() === hoje.getTime()) celula.classList.add('cal-day-hoje');
        if (d.getTime() === dataSelecionada.getTime()) celula.classList.add('cal-day-selecionado');

        celula.addEventListener('click', () => {
            irParaDia(new Date(d));
            closeModal('modal-calendario');
        });

        return celula;
    }

    function abrirModalCalendario() {
        mesModal = dataSelecionada.getMonth();
        anoModal = dataSelecionada.getFullYear();
        renderizarCalendarioModal();
        openModal('modal-calendario');
    }

    document.getElementById('btnAbrirCalendario').addEventListener('click', abrirModalCalendario);
    document.getElementById('areaTituloData').addEventListener('click', abrirModalCalendario);

    document.getElementById('calModalMesAnterior').addEventListener('click', () => {
        mesModal--;
        if (mesModal < 0) { mesModal = 11; anoModal--; }
        renderizarCalendarioModal();
    });

    document.getElementById('calModalMesProximo').addEventListener('click', () => {
        mesModal++;
        if (mesModal > 11) { mesModal = 0; anoModal++; }
        renderizarCalendarioModal();
    });

    document.getElementById('calModalIrHoje').addEventListener('click', () => {
        irParaDia(new Date(hoje));
        closeModal('modal-calendario');
    });

    async function carregarHorarios() {
        const dataChave = formatarDataChave(dataSelecionada);

        listaHorarios.classList.add('hidden');
        estadoCarregando.classList.remove('hidden');

        const minhaRequisicao = ++requisicaoAtual;

        try {
            const resposta = await fetch('../scripts/horarios_buscar.php?data=' + encodeURIComponent(dataChave));
            const dados = await resposta.json();

            if (minhaRequisicao !== requisicaoAtual) return; // resposta antiga, ignora

            if (!dados.ok) {
                estadoCarregando.classList.add('hidden');
                listaHorarios.classList.remove('hidden');
                listaHorarios.innerHTML = `<p class="text-xs text-zinc-500 text-center py-8 col-span-full">${escapeHtml(dados.erro || 'Não foi possível carregar os horários.')}</p>`;
                return;
            }

            diaBloqueadoAtual = !!dados.diaBloqueado;
            atualizarBotaoBloqueioDia();
            document.getElementById('avisoDiaBloqueado').classList.toggle('hidden', !diaBloqueadoAtual);

            horariosAtuais = dados.horarios;
            renderizarListaHorarios(dataChave);
        } catch (e) {
            if (minhaRequisicao !== requisicaoAtual) return;
            estadoCarregando.classList.add('hidden');
            listaHorarios.classList.remove('hidden');
            listaHorarios.innerHTML = '<p class="text-xs text-zinc-500 text-center py-8 col-span-full">Erro de conexão. Não foi possível falar com o servidor.</p>';
        }
    }

    function renderizarListaHorarios(dataChave) {
        estadoCarregando.classList.add('hidden');
        listaHorarios.classList.remove('hidden');
        listaHorarios.innerHTML = '';

        const agora = new Date();
        const ehHoje = dataSelecionada.getTime() === hoje.getTime();
        const diaPassado = dataSelecionada.getTime() < hoje.getTime();

        if (horariosAtuais.length === 0) {
            listaHorarios.innerHTML = '<p class="text-xs text-zinc-500 text-center py-8 col-span-full">Nenhum horário cadastrado para este dia.</p>';
            return;
        }

        horariosAtuais.forEach(h => {
            const [hh, mm] = h.hora.split(':').map(Number);
            const horarioObj = new Date(dataSelecionada);
            horarioObj.setHours(hh, mm, 0, 0);
            const horaJaPassou = ehHoje && horarioObj < agora;
            const naoPodeAgendar = !h.ocupado && (horaJaPassou || diaPassado);
            const inativo = !h.ocupado && !h.disponivel;
            // Dia inteiro bloqueado pelo barbeiro (ver DiaBloqueado): só
            // afeta horários LIVRES — agendamentos já existentes (ocupados)
            // e horários já inativados individualmente continuam exatamente
            // como estavam, sem apagar nada.
            const bloqueadoPeloDia = diaBloqueadoAtual && !h.ocupado && !inativo;

            let estadoClasse = 'horario-livre';
            if (h.ocupado) estadoClasse = h.duplicado ? 'horario-duplicado' : (h.origemPublica ? 'horario-publico' : 'horario-cheio');
            else if (inativo || bloqueadoPeloDia) estadoClasse = 'horario-inativo';

            const linha = document.createElement('div');
            linha.className = 'horario-linha ' + estadoClasse + (naoPodeAgendar && !inativo && !bloqueadoPeloDia ? ' horario-passado' : '');

            const esquerda = document.createElement('div');
            esquerda.innerHTML = `<span class="horario-hora">${h.hora}</span>`;

            const direita = document.createElement('div');
            direita.className = 'horario-info';
            if (h.ocupado && h.duplicado) {
                direita.innerHTML = `<span class="status-dot status-dot-duplicado"></span>${escapeHtml(h.cliente.nome)} <span class="badge-duplicado">Duplicado</span>`;
            } else if (h.ocupado && h.origemPublica) {
                // Agendamento que o próprio cliente fez sozinho pelo link
                // público (ver Publico/paginas/agendar.php), sem passar pelo
                // barbeiro — mesma linha/clique de sempre (abre o modal de
                // detalhes normalmente), só com cor e rótulo diferentes pra
                // avisar de onde veio.
                direita.innerHTML = `<span class="status-dot status-dot-publico"></span>${escapeHtml(h.cliente.nome)} <span class="badge-publico">Via link</span>`;
            } else if (h.ocupado) {
                direita.innerHTML = `<span class="status-dot status-dot-cheio"></span>${escapeHtml(h.cliente.nome)}`;
            } else if (inativo && h.ausenteNome) {
                // Horário BLOQUEADO (disponivel=0) porque o último
                // agendamento aqui foi marcado como "Cliente Ausente" — ao
                // contrário de "Concluído", esse horário não reabre
                // sozinho pra reserva; precisa de reativação manual, igual
                // um horário inativado à mão (mesmo clique reativa, ver
                // reativarHorario abaixo), só que com rótulo/cor próprios
                // pra deixar claro o motivo (ver horarios_buscar.php >
                // ausenteNome e includes/FinanceiroService.php >
                // marcarAgendamentoAusente).
                direita.innerHTML = `<span class="status-dot status-dot-ausente"></span>${escapeHtml(h.ausenteNome)} - Ausente`;
            } else if (inativo) {
                direita.innerHTML = `<span class="status-dot status-dot-inativo"></span>Indisponível`;
            } else if (bloqueadoPeloDia) {
                direita.innerHTML = `<span class="status-dot status-dot-inativo"></span>Dia bloqueado`;
            } else if (h.concluidoNome) {
                // Horário livre (pode ser reservado de novo), mas o último
                // atendimento aqui já foi concluído — mostra quem foi
                // atendido em vez de só "Vago" (ver horarios_buscar.php).
                direita.innerHTML = `<span class="status-dot status-dot-concluido"></span>Concluído - ${escapeHtml(h.concluidoNome)}`;
            } else {
                direita.innerHTML = `<span class="status-dot status-dot-livre"></span>Vago`;
            }

            linha.appendChild(esquerda);
            linha.appendChild(direita);

            if (h.ocupado) {
                // Agendamentos já feitos sempre podem ser vistos, mesmo em dias passados
                // (nesse caso só sem a opção de cancelar).
                linha.addEventListener('click', () => abrirModalDetalhes(h, dataChave, diaPassado));
            } else if (inativo) {
                if (!diaPassado) {
                    linha.addEventListener('click', () => reativarHorario(h, dataChave));
                } else {
                    linha.classList.add('horario-passado');
                }
            } else if (bloqueadoPeloDia) {
                // Não abre o modal de agendamento — o dia inteiro está
                // bloqueado (ver botão "Bloquear dia" na barra de navegação).
                linha.addEventListener('click', () => toast('Este dia está bloqueado. Desbloqueie o dia para agendar.', 'erro'));
            } else if (!naoPodeAgendar) {
                linha.addEventListener('click', () => abrirModalAgendar(h, dataChave, dataSelecionada));
            }

            listaHorarios.appendChild(linha);
        });
    }

    function escapeHtml(texto) {
        const div = document.createElement('div');
        div.textContent = texto ?? '';
        return div.innerHTML;
    }

    // ---------------- Navegação da faixa de data ----------------
    document.getElementById('btnDiaAnterior').addEventListener('click', () => {
        const d = new Date(dataSelecionada);
        d.setDate(d.getDate() - 1);
        irParaDia(d);
    });

    document.getElementById('btnDiaProximo').addEventListener('click', () => {
        const d = new Date(dataSelecionada);
        d.setDate(d.getDate() + 1);
        irParaDia(d);
    });

    document.getElementById('btnHoje').addEventListener('click', () => {
        irParaDia(new Date(hoje));
    });

    // ---------------- Bloqueio de dia inteiro ----------------
    function atualizarBotaoBloqueioDia() {
        const btn = document.getElementById('btnBloquearDia');
        const icone = document.getElementById('iconBloquearDia');
        if (!btn || !icone) return;

        btn.classList.toggle('is-bloqueado', diaBloqueadoAtual);
        const rotulo = diaBloqueadoAtual ? 'Desbloquear este dia' : 'Bloquear este dia';
        btn.title = rotulo;
        btn.setAttribute('aria-label', rotulo);

        // Cadeado fechado (bloquear) x cadeado aberto (já bloqueado — a
        // ação disponível passa a ser desbloquear).
        icone.innerHTML = diaBloqueadoAtual
            ? '<rect x="5" y="10" width="14" height="10" rx="2" stroke="currentColor" stroke-width="1.6"/><path d="M8 10V7a3.7 3.7 0 0 1 7.2-1.2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>'
            : '<rect x="5" y="10" width="14" height="10" rx="2" stroke="currentColor" stroke-width="1.6"/><path d="M8 10V7a4 4 0 0 1 8 0v3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>';
    }

    document.getElementById('btnBloquearDia').addEventListener('click', async () => {
        const dataChave = formatarDataChave(dataSelecionada);
        const acao = diaBloqueadoAtual ? 'desbloquear' : 'bloquear';

        const resultado = await Swal.fire({
            title: acao === 'bloquear' ? 'Bloquear este dia?' : 'Desbloquear este dia?',
            text: acao === 'bloquear'
                ? 'Nenhum novo agendamento poderá ser criado neste dia. Agendamentos já existentes não são apagados.'
                : 'Este dia volta a aceitar novos agendamentos normalmente.',
            icon: 'question',
            background: '#101828',
            color: '#e9eef6',
            showCancelButton: true,
            confirmButtonText: acao === 'bloquear' ? 'Sim, bloquear' : 'Sim, desbloquear',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: acao === 'bloquear' ? '#8c1f28' : '#3d7ec9',
            reverseButtons: true
        });

        if (!resultado.isConfirmed) return;

        const btn = document.getElementById('btnBloquearDia');
        btn.disabled = true;

        try {
            const formData = new FormData();
            formData.set('data', dataChave);
            formData.set('acao', acao);

            const resposta = await fetch('../scripts/dia_bloqueio_status.php', { method: 'POST', body: formData });
            const dados = await resposta.json();

            if (!dados.ok) {
                toast(dados.erro || 'Não foi possível atualizar o bloqueio do dia.', 'erro');
            } else {
                toast(acao === 'bloquear' ? 'Dia bloqueado.' : 'Dia desbloqueado.', 'sucesso');
                // O status do mês (indicador no calendário) pode ter mudado —
                // descarta o cache pra próxima vez que o modal de calendário abrir.
                Object.keys(disponibilidadeMesCache).forEach(function (chave) { delete disponibilidadeMesCache[chave]; });
                carregarHorarios();
            }
        } catch (e) {
            toast('Erro de conexão. Tente novamente.', 'erro');
        }

        btn.disabled = false;
    });

    atualizarFaixaData();
    carregarHorarios();

    // ---------------- Recarregar após ação nos modais ----------------
    window.recarregarHorariosAtuais = function () {
        carregarHorarios();
    };

    // ---------------- Modal: novo agendamento (horário livre) ----------------
    let dataSlotAgendarAtual = null; // Date do horário selecionado, usada pelo aviso de fidelidade

    window.abrirModalAgendar = function (horario, dataChave, dataObj) {
        const form = document.getElementById('form-agendar');
        form.reset();
        document.getElementById('agendar-idHorario').value = horario.idHorario;
        document.getElementById('agendar-idHorarioSecundario').value = '';
        document.getElementById('agendar-idCliente').value = '';
        document.getElementById('agendar-erro').classList.add('hidden');
        document.getElementById('resultados-cliente').innerHTML = '';
        document.getElementById('busca-cliente-input').value = '';
        limparClienteSelecionado();
        trocarAbaCliente('buscar');

        const dataFormatada = dataObj.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' });
        document.getElementById('agendar-subtitulo').textContent = `${horario.hora} — ${dataFormatada}`;

        dataSlotAgendarAtual = dataObj;
        atualizarAvisoFidelidade();

        // ---------- Duplicar horário: detecta automaticamente o próximo slot livre ----------
        // Usa a própria lista já carregada (horariosAtuais), sem nenhuma
        // chamada extra ao servidor — o "próximo horário" é simplesmente o
        // item seguinte na grade do dia, se estiver livre.
        const duplicarRow     = document.getElementById('duplicar-toggle-row');
        const duplicarToggle  = document.getElementById('agendar-duplicar');
        const duplicarLegenda = document.getElementById('duplicar-toggle-legenda');

        duplicarToggle.checked = false;
        duplicarToggle.disabled = false;
        alternarDuplicarHorario(false);

        const proximoParaDuplicar = calcularProximoHorarioParaDuplicar(horario);
        if (proximoParaDuplicar) {
            duplicarRow.dataset.disponivel = '1';
            document.getElementById('agendar-idHorarioSecundario').value = proximoParaDuplicar.idHorario;
            document.getElementById('duplicar-info').textContent =
                `Também reserva ${proximoParaDuplicar.hora}, para o mesmo atendimento.`;
            duplicarLegenda.textContent =
                `Reserva ${horario.hora} + ${proximoParaDuplicar.hora}, para um só atendimento (ex.: pai e filho).`;
        } else {
            duplicarRow.dataset.disponivel = '0';
            duplicarToggle.disabled = true;
            duplicarLegenda.textContent = 'Não disponível: não há um próximo horário livre logo em seguida.';
        }

        atualizarExclusividadeAgendamentoEspecial();

        openModal('modal-agendar');
    };

    // Próximo horário livre imediatamente após o clicado, na mesma grade do
    // dia já carregada — usado pelo "Duplicar horário". Só considera um slot
    // realmente CONSECUTIVO (exatamente 40min depois, o passo padrão da
    // grade — ver includes/HorarioService.php), pra nunca "pular" o
    // intervalo de almoço (ex.: 11:20 -> 13:00 não conta como consecutivo).
    function calcularProximoHorarioParaDuplicar(horario) {
        const indice = horariosAtuais.findIndex(h => h.idHorario === horario.idHorario);
        if (indice === -1) return null;
        const proximo = horariosAtuais[indice + 1];
        if (!proximo || proximo.ocupado || !proximo.disponivel) return null;

        const minutos = (h) => { const [hh, mm] = h.hora.split(':').map(Number); return hh * 60 + mm; };
        if (minutos(proximo) - minutos(horario) !== 40) return null;

        return proximo;
    }

    // "Duplicar horário" e "Agendamento de Fidelidade" não se combinam
    // nesta primeira versão: liga um automaticamente desliga/desabilita o
    // outro na interface (o backend também ignora a fidelidade quando
    // duplicar está ativo, ver Agendamentos/scripts/agendamento_salvar.php).
    function atualizarExclusividadeAgendamentoEspecial() {
        const duplicarToggle = document.getElementById('agendar-duplicar');
        const duplicarRow    = document.getElementById('duplicar-toggle-row');
        const fidelidadeWrapper = document.getElementById('fidelidade-wrapper');
        const fidelidadeSelecionada = document.querySelector('input[name="fidelidade"]:checked');
        const fidelidadeAtiva = !!fidelidadeSelecionada && fidelidadeSelecionada.value !== '';

        if (fidelidadeAtiva) {
            if (duplicarToggle.checked) {
                duplicarToggle.checked = false;
                alternarDuplicarHorario(false);
            }
            duplicarToggle.disabled = true;
            duplicarRow.style.opacity = '0.45';
            duplicarRow.style.pointerEvents = 'none';
        } else {
            duplicarRow.style.opacity = '';
            duplicarRow.style.pointerEvents = '';
            duplicarToggle.disabled = duplicarRow.dataset.disponivel !== '1';
        }

        const duplicarLigado = duplicarToggle.checked;
        fidelidadeWrapper.style.opacity = duplicarLigado ? '0.45' : '';
        fidelidadeWrapper.style.pointerEvents = duplicarLigado ? 'none' : '';

        // Enquanto duplicado está ativo, o select "Serviço" representa o
        // corte do 1º horário — o rótulo deixa isso claro.
        document.getElementById('label-agendar-servico').textContent = duplicarLigado ? 'Serviço (1º horário)' : 'Serviço';
    }

    window.alternarDuplicarHorario = function (ligado) {
        const badge = document.getElementById('duplicar-estado');
        badge.textContent = ligado ? 'ON' : 'OFF';
        badge.classList.toggle('is-on', ligado);
        document.getElementById('duplicar-detalhes').classList.toggle('hidden', !ligado);
        atualizarExclusividadeAgendamentoEspecial();

        if (ligado) {
            document.getElementById('agendar-idServicoSecundario').value = '';
            atualizarInfoCortesDuplicado();
            abrirModalSelecionarCortes();
        }
    };

    // "Selecione os Cortes": só existe (e só é obrigatório escolher) quando
    // o agendamento duplicado está ativo. Corte do 1º horário é obrigatório;
    // corte do 2º horário é opcional (ex.: pai faz barba, filho faz corte —
    // cada um com seu serviço e seu próprio valor).
    window.abrirModalSelecionarCortes = function () {
        const valorAtual1 = document.getElementById('agendar-idServico').value;
        const valorAtual2 = document.getElementById('agendar-idServicoSecundario').value;

        const montarOpcoes = function (selecionado, incluirPlaceholder) {
            let html = incluirPlaceholder ? '<option value="">Selecione...</option>' : '';
            SERVICOS_DISPONIVEIS.forEach(function (s) {
                const marcado = String(s.id) === String(selecionado) ? 'selected' : '';
                const valorFormatado = s.valor.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                html += `<option value="${s.id}" ${marcado}>${escapeHtml(s.nome)} — R$ ${valorFormatado}</option>`;
            });
            return html;
        };

        const chevronSvg = '<svg class="corte-modal-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';

        Swal.fire({
            title: 'Selecione os Cortes',
            html: `
                <div style="text-align:left;">
                    <label class="corte-modal-label">Corte do 1º horário <span class="obrigatorio">(obrigatório)</span></label>
                    <div class="corte-modal-select-wrap">
                        <select id="swal-corte-1" class="corte-modal-select">${montarOpcoes(valorAtual1, true)}</select>
                        ${chevronSvg}
                    </div>

                    <label class="corte-modal-label">Corte do 2º horário <span class="opcional">(opcional)</span></label>
                    <div class="corte-modal-select-wrap">
                        <select id="swal-corte-2" class="corte-modal-select">
                            <option value="">Nenhum — não cobrar o 2º horário</option>
                            ${montarOpcoes(valorAtual2, false)}
                        </select>
                        ${chevronSvg}
                    </div>
                </div>
            `,
            background: '#101828',
            color: '#e9eef6',
            confirmButtonColor: '#3d7ec9',
            confirmButtonText: 'Confirmar',
            showCancelButton: true,
            cancelButtonText: 'Cancelar',
            focusConfirm: false,
            allowOutsideClick: false,
            preConfirm: function () {
                const corte1 = document.getElementById('swal-corte-1').value;
                if (!corte1) {
                    Swal.showValidationMessage('Selecione o corte do 1º horário.');
                    return false;
                }
                const corte2 = document.getElementById('swal-corte-2').value;
                return { corte1: corte1, corte2: corte2 };
            }
        }).then(function (resultado) {
            if (!resultado.isConfirmed) {
                // Cancelou sem nenhum corte já definido antes -> não faz
                // sentido manter "Duplicar horário" ligado sem saber o corte.
                if (!document.getElementById('agendar-idServico').value) {
                    document.getElementById('agendar-duplicar').checked = false;
                    alternarDuplicarHorario(false);
                }
                return;
            }

            document.getElementById('agendar-idServico').value = resultado.value.corte1;
            document.getElementById('agendar-idServicoSecundario').value = resultado.value.corte2;
            atualizarInfoCortesDuplicado();
        });
    };

    function atualizarInfoCortesDuplicado() {
        const info = document.getElementById('duplicar-info');
        const idCorte1 = document.getElementById('agendar-idServico').value;
        const idCorte2 = document.getElementById('agendar-idServicoSecundario').value;
        const corte1 = SERVICOS_DISPONIVEIS.find(function (s) { return String(s.id) === idCorte1; });
        const corte2 = idCorte2 ? SERVICOS_DISPONIVEIS.find(function (s) { return String(s.id) === idCorte2; }) : null;

        if (!corte1) {
            info.textContent = 'Toque em "Selecione os Cortes" para escolher.';
            return;
        }

        const fmt = function (v) { return v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }); };

        info.textContent = corte2
            ? `1º horário: ${corte1.nome} (${fmt(corte1.valor)}) · 2º horário: ${corte2.nome} (${fmt(corte2.valor)}) — total ${fmt(corte1.valor + corte2.valor)}`
            : `1º horário: ${corte1.nome} (${fmt(corte1.valor)}) · 2º horário reservado, sem cobrança própria.`;
    }

    // Rótulos e aviso dinâmico da opção "Agendamento de Fidelidade": deixa
    // claro que serão criados agendamentos futuros automaticamente, até
    // aproximadamente 1 ano a partir da data deste primeiro agendamento.
    // Códigos representam SEMANAS (1 a 4), nunca dias corridos — os rótulos
    // abaixo são só a linguagem comercial mostrada ao barbeiro. O cálculo
    // real (mesmo dia da semana, mesmo horário) é feito no backend.
    const ROTULOS_FIDELIDADE = { '1': '7 em 7 dias', '2': '15 em 15 dias', '3': '20 em 20 dias', '4': '30 em 30 dias' };

    function atualizarAvisoFidelidade() {
        const aviso = document.getElementById('fidelidade-aviso');
        const selecionado = document.querySelector('input[name="fidelidade"]:checked');

        if (!aviso || !selecionado || selecionado.value === '' || !dataSlotAgendarAtual) {
            if (aviso) aviso.classList.add('hidden');
            return;
        }

        const dataFinal = new Date(dataSlotAgendarAtual);
        dataFinal.setFullYear(dataFinal.getFullYear() + 1);
        const dataFinalFormatada = dataFinal.toLocaleDateString('pt-BR');

        aviso.textContent = `Serão criados agendamentos automáticos ${ROTULOS_FIDELIDADE[selecionado.value]}, no mesmo horário, até aproximadamente ${dataFinalFormatada}.`;
        aviso.classList.remove('hidden');
    }

    document.querySelectorAll('input[name="fidelidade"]').forEach(function (el) {
        el.addEventListener('change', function () {
            atualizarAvisoFidelidade();
            atualizarExclusividadeAgendamentoEspecial();
        });
    });

    // ---------------- Inativar / reativar horário (só quando livre) ----------------
    window.inativarHorario = async function () {
        const idHorario = document.getElementById('agendar-idHorario').value;
        if (!idHorario) return;

        const confirmacao = await Swal.fire({
            title: 'Inativar este horário?',
            text: 'Ele ficará indisponível apenas neste dia. Nos outros dias continua ativo normalmente.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sim, inativar',
            cancelButtonText: 'Cancelar'
        });
        if (!confirmacao.isConfirmed) return;

        const btn = document.getElementById('btn-inativar-horario');
        btn.disabled = true;
        btn.textContent = 'Inativando...';

        try {
            const formData = new FormData();
            formData.set('idHorario', idHorario);
            formData.set('acao', 'inativar');
            const resposta = await fetch('../scripts/horario_status.php', { method: 'POST', body: formData });
            const dados = await resposta.json();

            if (!dados.ok) {
                toast(dados.erro || 'Não foi possível inativar o horário.', 'erro');
            } else {
                closeModal('modal-agendar');
                toast('Horário inativado neste dia.', 'sucesso');
                recarregarHorariosAtuais();
            }
        } catch (e) {
            toast('Erro de conexão. Tente novamente.', 'erro');
        }

        btn.disabled = false;
        btn.textContent = 'Inativar horário';
    };

    window.reativarHorario = async function (horario, dataChave) {
        const confirmacao = await Swal.fire({
            title: 'Reativar este horário?',
            text: `${horario.hora} — ${dataChave.split('-').reverse().join('/')} voltará a ficar disponível para agendamento.`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sim, reativar',
            cancelButtonText: 'Cancelar'
        });
        if (!confirmacao.isConfirmed) return;

        try {
            const formData = new FormData();
            formData.set('idHorario', horario.idHorario);
            formData.set('acao', 'reativar');
            const resposta = await fetch('../scripts/horario_status.php', { method: 'POST', body: formData });
            const dados = await resposta.json();

            if (!dados.ok) {
                toast(dados.erro || 'Não foi possível reativar o horário.', 'erro');
            } else {
                toast('Horário reativado.', 'sucesso');
                recarregarHorariosAtuais();
            }
        } catch (e) {
            toast('Erro de conexão. Tente novamente.', 'erro');
        }
    };

    window.trocarAbaCliente = function (aba) {
        document.getElementById('tab-buscar').classList.toggle('tab-ativa', aba === 'buscar');
        document.getElementById('tab-novo').classList.toggle('tab-ativa', aba === 'novo');
        document.getElementById('painel-buscar').classList.toggle('hidden', aba !== 'buscar');
        document.getElementById('painel-novo').classList.toggle('hidden', aba !== 'novo');
    };

    window.limparClienteSelecionado = function () {
        document.getElementById('agendar-idCliente').value = '';
        document.getElementById('bloco-cliente-selecionado').classList.add('hidden');
        document.getElementById('bloco-busca-cliente').classList.remove('hidden');
    };

    function selecionarCliente(cliente) {
        document.getElementById('agendar-idCliente').value = cliente.idCliente;
        document.getElementById('cliente-sel-nome').textContent = cliente.nome;
        document.getElementById('cliente-sel-telefone').textContent = cliente.telefone;
        document.getElementById('bloco-busca-cliente').classList.add('hidden');
        document.getElementById('bloco-cliente-selecionado').classList.remove('hidden');
        document.getElementById('resultados-cliente').innerHTML = '';
    }

    let temporizadorBusca = null;
    document.getElementById('busca-cliente-input').addEventListener('input', function () {
        const termo = this.value.trim();
        clearTimeout(temporizadorBusca);
        const resultados = document.getElementById('resultados-cliente');

        if (termo.length < 2) {
            resultados.innerHTML = '';
            return;
        }

        temporizadorBusca = setTimeout(async () => {
            try {
                const resposta = await fetch('../../Clientes/scripts/clientes_buscar.php?termo=' + encodeURIComponent(termo));
                const dados = await resposta.json();
                resultados.innerHTML = '';

                if (!dados.ok || dados.clientes.length === 0) {
                    resultados.innerHTML = '<p class="text-xs text-zinc-500 px-1 py-2">Nenhum cliente encontrado. Use a aba "Cliente novo".</p>';
                    return;
                }

                dados.clientes.forEach(c => {
                    const item = document.createElement('div');
                    item.className = 'resultado-cliente';
                    item.innerHTML = `<strong>${escapeHtml(c.nome)}</strong> — ${escapeHtml(c.telefone)}`;
                    item.addEventListener('click', () => selecionarCliente(c));
                    resultados.appendChild(item);
                });
            } catch (e) {
                resultados.innerHTML = '<p class="text-xs text-zinc-500 px-1 py-2">Erro ao buscar clientes.</p>';
            }
        }, 300);
    });

    document.getElementById('form-agendar').addEventListener('submit', async function (e) {
        e.preventDefault();

        const erroEl = document.getElementById('agendar-erro');
        erroEl.classList.add('hidden');

        const abaBuscar = !document.getElementById('painel-buscar').classList.contains('hidden');
        const idCliente = document.getElementById('agendar-idCliente').value;

        if (abaBuscar && !idCliente) {
            erroEl.textContent = 'Selecione um cliente na busca ou use a aba "Cliente novo".';
            erroEl.classList.remove('hidden');
            return;
        }

        const idServico = document.getElementById('agendar-idServico').value;
        if (!idServico) {
            erroEl.textContent = 'Selecione o serviço.';
            erroEl.classList.remove('hidden');
            return;
        }

        const btn = document.getElementById('btn-salvar-agendamento');
        btn.disabled = true;
        btn.textContent = 'Salvando...';

        const formData = new FormData(this);
        if (!abaBuscar) {
            formData.set('idCliente', '');
        }

        try {
            const resposta = await fetch('../scripts/agendamento_salvar.php', { method: 'POST', body: formData });
            const dados = await resposta.json();

            if (!dados.ok) {
                if (dados.codigo === 'agendamento_duplicado') {
                    closeModal('modal-agendar');
                    Swal.fire({
                        title: 'Agendamento não permitido',
                        text: 'Este cliente já possui um agendamento hoje.',
                        icon: 'error'
                    });
                    btn.disabled = false;
                    btn.textContent = 'Confirmar agendamento';
                    return;
                }

                erroEl.textContent = dados.erro || 'Não foi possível salvar o agendamento.';
                erroEl.classList.remove('hidden');
                btn.disabled = false;
                btn.textContent = 'Confirmar agendamento';
                return;
            }

            closeModal('modal-agendar');

            if (dados.fidelidade) {
                const f = dados.fidelidade;
                let html = `Fidelidade <strong>${escapeHtml(f.rotulo)}</strong>: ${f.criadas} agendamento(s) futuro(s) criado(s) automaticamente, no mesmo horário.`;

                if (f.puladas && f.puladas.length > 0) {
                    const datasPuladas = f.puladas.map(function (p) {
                        return p.data.split('-').reverse().join('/');
                    }).join(', ');
                    html += `<br><br><span style="color:#e0a2a8;">${f.puladas.length} ocorrência(s) não pôde(puderam) ser criada(s) por conflito de horário:</span><br>${escapeHtml(datasPuladas)}`;
                }

                Swal.fire({
                    title: 'Agendamento confirmado!',
                    html: html,
                    icon: 'success',
                    background: '#101828',
                    color: '#e9eef6',
                    confirmButtonColor: '#3d7ec9',
                    confirmButtonText: 'Entendi'
                });
            } else if (dados.duplicado) {
                const dpl = dados.duplicado;
                const totalFormatado = Number(dpl.valorTotal).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
                let resumoCortes = `${escapeHtml(dpl.horaPrincipal)} — ${escapeHtml(dpl.servicoPrincipal)}`;
                resumoCortes += dpl.servicoSecundario
                    ? ` + ${escapeHtml(dpl.horaSecundaria)} — ${escapeHtml(dpl.servicoSecundario)}`
                    : ` (${escapeHtml(dpl.horaSecundaria)} também reservado, sem cobrança própria)`;

                Swal.fire({
                    title: 'Agendamento duplicado confirmado!',
                    html: `🟣 ${resumoCortes}<br><br>Total cobrado: <strong>${totalFormatado}</strong>`,
                    icon: 'success',
                    background: '#101828',
                    color: '#e9eef6',
                    confirmButtonColor: '#3d7ec9',
                    confirmButtonText: 'Entendi'
                });
            } else {
                toast('Agendamento confirmado com sucesso.', 'sucesso');
            }

            recarregarHorariosAtuais();
            carregarListaEspera();
        } catch (e) {
            erroEl.textContent = 'Erro de conexão. Tente novamente.';
            erroEl.classList.remove('hidden');
        }

        btn.disabled = false;
        btn.textContent = 'Confirmar agendamento';
    });

    // ---------------- Modal: detalhes (horário ocupado) ----------------
    let horarioSelecionado = null; // guarda o horário atual, usado ao abrir o modal de Concluir
    let dataAgendamentoSelecionado = null; // guarda a data (Y-m-d) do horário atual, usada pelo Reagendar
    let alterarClienteSelecionado = null; // cliente escolhido no fluxo "Alterar cliente"
    let trocarClienteSelecionado = null; // agendamento escolhido no fluxo "Trocar cliente"

    window.abrirModalDetalhes = function (horario, dataChave, somenteVisualizacao) {
        horarioSelecionado = horario;
        dataAgendamentoSelecionado = dataChave;

        document.getElementById('det-idAgendamento').value = horario.idAgendamento;
        document.getElementById('det-hora').textContent = horario.hora + ' — ' + dataChave.split('-').reverse().join('/');
        document.getElementById('det-nome').textContent = horario.cliente.nome;

        // Status por extenso, sempre junto do nome do cliente quando
        // concluído (nunca só "Concluído" solto) — só aparece quando o
        // agendamento já está finalizado (concluído/cancelado); enquanto
        // está agendado/confirmado a linha some, pois as ações abaixo (
        // Cancelar/Concluir) já deixam o estado óbvio.
        const linhaStatus = document.getElementById('det-status-row');
        if (horario.status === 'concluido') {
            document.getElementById('det-status').textContent = 'Concluído - ' + horario.cliente.nome;
            linhaStatus.classList.remove('hidden');
        } else if (horario.status === 'ausente') {
            document.getElementById('det-status').textContent = 'Cliente Ausente - ' + horario.cliente.nome;
            linhaStatus.classList.remove('hidden');
        } else if (horario.status === 'cancelado') {
            document.getElementById('det-status').textContent = 'Cancelado - ' + horario.cliente.nome;
            linhaStatus.classList.remove('hidden');
        } else {
            linhaStatus.classList.add('hidden');
        }
        document.getElementById('det-telefone').textContent = horario.cliente.telefone;
        document.getElementById('det-servico').textContent = horario.servico || '—';
        document.getElementById('det-barba').textContent = horario.incluirBarba ? 'Sim' : 'Não';
        document.getElementById('det-observacao').textContent = horario.observacao || '—';

        const linhaDuplicado = document.getElementById('det-duplicado-row');
        if (horario.duplicado) {
            const irmao = horariosAtuais.find(h => h.duplicado && h.grupoAgendamento === horario.grupoAgendamento && h.idHorario !== horario.idHorario);
            document.getElementById('det-duplicado-valor').textContent = irmao
                ? `Sim — vinculado a ${irmao.hora}`
                : 'Sim';
            linhaDuplicado.classList.remove('hidden');
        } else {
            linhaDuplicado.classList.add('hidden');
        }

        document.getElementById('det-origem-row').classList.toggle('hidden', !horario.origemPublica);

        // Um agendamento já concluído ou cancelado não pode mais ser
        // cancelado/concluído de novo — só sobra "Fechar". Fora isso, as
        // duas ações só aparecem quando não é um dia passado (somenteVisualizacao).
        const jaFinalizado = horario.status === 'concluido' || horario.status === 'cancelado' || horario.status === 'ausente';
        document.getElementById('btn-cancelar-agendamento').classList.toggle('hidden', !!somenteVisualizacao || jaFinalizado);
        document.getElementById('btn-concluir-agendamento').classList.toggle('hidden', !!somenteVisualizacao || jaFinalizado);

        // "Alterar agendamento" (Alterar cliente / Trocar cliente / Trocar
        // horário — ver abrirMenuAlterarAgendamento) só existe pra um
        // agendamento que ainda pode ser mexido (não é dia passado, não
        // está concluído/cancelado). A exceção é um agendamento "duplicado"
        // (dois horários vinculados) — nenhuma das três operações é segura
        // ali, então o botão fica escondido nesse caso.
        const podeAlterarAgendamento = !horario.duplicado && !somenteVisualizacao && !jaFinalizado;
        document.getElementById('btn-alterar-agendamento').classList.toggle('hidden', !podeAlterarAgendamento);

        openModal('modal-detalhes');
    };

    // Botão "Cancelar": se for um agendamento duplicado (dois horários
    // vinculados) e o horário "irmão" ainda estiver ativo, pergunta antes
    // se o barbeiro quer cancelar SÓ o horário que está vendo agora
    // (mantendo o outro ativo normalmente) ou os dois de uma vez. Fora
    // esse caso especial (duplicado nunca tem fidelidade — as duas coisas
    // não se combinam, ver agendamento_salvar.php), sempre passa primeiro
    // pelo modal de escolha "Cancelar este corte" x "Cancelar todos os
    // cortes".
    window.confirmarCancelamento = function () {
        if (!horarioSelecionado) return;

        const irmao = horarioSelecionado.duplicado
            ? horariosAtuais.find(h => h.duplicado && h.grupoAgendamento === horarioSelecionado.grupoAgendamento && h.idHorario !== horarioSelecionado.idHorario)
            : null;

        if (!irmao) {
            abrirModalEscolhaCancelamento();
            return;
        }

        Swal.fire({
            title: 'Cancelar agendamento duplicado',
            html: `Este cliente tem dois horários vinculados: <strong>${escapeHtml(horarioSelecionado.hora)}</strong> e <strong>${escapeHtml(irmao.hora)}</strong>. O que deseja fazer?`,
            icon: 'question',
            background: '#101828',
            color: '#e9eef6',
            confirmButtonText: `Cancelar só ${escapeHtml(horarioSelecionado.hora)}`,
            confirmButtonColor: '#3d7ec9',
            showDenyButton: true,
            denyButtonText: 'Cancelar os dois horários',
            denyButtonColor: '#b3413d',
            showCancelButton: true,
            cancelButtonText: 'Voltar',
        }).then(function (r) {
            if (r.isConfirmed) {
                liberarUmHorarioDoDuplicado(horarioSelecionado.idAgendamento, irmao.hora);
            } else if (r.isDenied) {
                cancelarAgendamento();
            }
        });
    };

    // Cancela SÓ o horário que o barbeiro escolheu (qualquer um dos dois
    // lados do duplicado — não é mais fixo em "sempre o Horário 2") e
    // libera só o Horario dele. O outro continua ativo (ver comentário em
    // Agendamentos/scripts/agendamento_liberar_secundario.php sobre o que
    // acontece com o valor cobrado em cada caso).
    window.liberarUmHorarioDoDuplicado = async function (idAgendamentoParaCancelar, horaQueContinua) {
        const btn = document.getElementById('btn-cancelar-agendamento');
        btn.disabled = true;
        btn.textContent = 'Cancelando...';

        try {
            const formData = new FormData();
            formData.set('idAgendamento', idAgendamentoParaCancelar);
            const resposta = await fetch('../scripts/agendamento_liberar_secundario.php', { method: 'POST', body: formData });
            const dados = await resposta.json();

            if (!dados.ok) {
                toast(dados.erro || 'Não foi possível cancelar o horário.', 'erro');
            } else {
                closeModal('modal-detalhes');
                toast(`Horário ${dados.hora || ''} cancelado. O horário ${escapeHtml(horaQueContinua)} continua agendado normalmente.`, 'sucesso');
                recarregarHorariosAtuais();
            }
        } catch (e) {
            toast('Erro de conexão. Tente novamente.', 'erro');
        }

        btn.disabled = false;
        btn.textContent = 'Cancelar';
    };

    window.cancelarAgendamento = async function () {
        const idAgendamento = document.getElementById('det-idAgendamento').value;
        if (!idAgendamento) return;

        const btn = document.getElementById('btn-cancelar-agendamento');
        btn.disabled = true;
        btn.textContent = 'Cancelando...';

        try {
            const formData = new FormData();
            formData.set('idAgendamento', idAgendamento);
            const resposta = await fetch('../scripts/agendamento_cancelar.php', { method: 'POST', body: formData });
            const dados = await resposta.json();

            if (!dados.ok) {
                toast(dados.erro || 'Não foi possível cancelar o agendamento.', 'erro');
            } else {
                closeModal('modal-detalhes');
                toast(dados.idAgendamentoSecundario
                    ? 'Agendamento duplicado cancelado. Os dois horários foram liberados.'
                    : 'Agendamento cancelado. Horário liberado.', 'sucesso');
                recarregarHorariosAtuais();
            }
        } catch (e) {
            toast('Erro de conexão. Tente novamente.', 'erro');
        }

        btn.disabled = false;
        btn.textContent = 'Cancelar';
    };

    // ---------------- Cancelamento: "só este corte" x "todos os cortes futuros" ----------------
    // Modal próprio do sistema (SweetAlert2 — o mesmo padrão usado em toda
    // a tela, nunca um confirm() nativo do navegador), com as duas opções
    // pedidas e uma forma de voltar sem executar nada.
    window.abrirModalEscolhaCancelamento = function () {
        if (!horarioSelecionado) return;

        Swal.fire({
            title: 'Cancelar agendamento',
            html: 'Como deseja cancelar este agendamento?',
            icon: 'question',
            background: '#101828',
            color: '#e9eef6',
            showDenyButton: true,
            showCancelButton: true,
            confirmButtonText: 'Cancelar este corte',
            confirmButtonColor: '#3d7ec9',
            denyButtonText: 'Cancelar todos os cortes',
            denyButtonColor: '#b3413d',
            cancelButtonText: 'Voltar',
            reverseButtons: true,
        }).then(function (r) {
            if (r.isConfirmed) {
                // Opção 1: cancela SÓ este agendamento — os demais
                // agendamentos futuros do cliente permanecem intactos.
                cancelarAgendamento();
            } else if (r.isDenied) {
                // Opção 2: pede uma segunda confirmação antes de seguir,
                // por afetar vários agendamentos futuros de uma vez.
                confirmarCancelamentoTodosOsCortes();
            }
            // Fechou com "Voltar", Esc ou clique fora: não faz nada.
        });
    };

    window.confirmarCancelamentoTodosOsCortes = function () {
        if (!horarioSelecionado) return;

        Swal.fire({
            title: 'Cancelar todos os cortes futuros?',
            html: `Tem certeza que deseja cancelar <strong>todos os cortes futuros</strong> de <strong>${escapeHtml(horarioSelecionado.cliente.nome)}</strong>? Esta ação não pode ser desfeita.`,
            icon: 'warning',
            background: '#101828',
            color: '#e9eef6',
            showCancelButton: true,
            confirmButtonText: 'Sim, cancelar todos',
            confirmButtonColor: '#b3413d',
            cancelButtonText: 'Voltar',
        }).then(function (r) {
            if (r.isConfirmed) {
                cancelarTodosOsCortes();
            }
        });
    };

    // Cancela TODOS os agendamentos futuros ainda ativos (agendado/
    // confirmado, Data >= hoje) do mesmo cliente — inclui toda a fidelidade
    // e qualquer outro agendamento avulso futuro dele. Nunca toca no
    // histórico (concluído) nem no cadastro do cliente (ver
    // Agendamentos/scripts/agendamento_cancelar_todos_futuros.php).
    window.cancelarTodosOsCortes = async function () {
        const idAgendamento = document.getElementById('det-idAgendamento').value;
        if (!idAgendamento) return;

        const btn = document.getElementById('btn-cancelar-agendamento');
        btn.disabled = true;
        btn.textContent = 'Cancelando...';

        try {
            const formData = new FormData();
            formData.set('idAgendamento', idAgendamento);
            const resposta = await fetch('../scripts/agendamento_cancelar_todos_futuros.php', { method: 'POST', body: formData });
            const dados = await resposta.json();

            if (!dados.ok) {
                toast(dados.erro || 'Não foi possível cancelar os cortes futuros.', 'erro');
            } else {
                closeModal('modal-detalhes');
                const qtd = dados.cancelados || 0;
                toast(
                    qtd > 0
                        ? `${qtd} agendamento(s) futuro(s) de ${dados.cliente} cancelado(s). O histórico foi mantido.`
                        : `Nenhum outro agendamento futuro encontrado para ${dados.cliente}.`,
                    'sucesso'
                );
                recarregarHorariosAtuais();
            }
        } catch (e) {
            toast('Erro de conexão. Tente novamente.', 'erro');
        }

        btn.disabled = false;
        btn.textContent = 'Cancelar';
    };

    // ---------------- Modal: concluir agendamento (forma de pagamento + fiado) ----------------
    window.concluirAgendamento = function () {
        const idAgendamento = document.getElementById('det-idAgendamento').value;
        if (!idAgendamento || !horarioSelecionado) return;

        document.getElementById('concluir-idAgendamento').value = idAgendamento;
        document.getElementById('concluir-nome').textContent = horarioSelecionado.cliente.nome;

        const opcoesServicoHtml = function (idServicoAgendadoDesteHorario, nomeAgendado, valorAgendado) {
            let opcoes = SERVICOS_DISPONIVEIS.slice();
            if (idServicoAgendadoDesteHorario && !opcoes.some(function (s) { return s.id === idServicoAgendadoDesteHorario; })) {
                // Serviço originalmente agendado não está mais ativo/cadastrado
                // — mantém como opção, pra não travar a conclusão do atendimento.
                opcoes = [{ id: idServicoAgendadoDesteHorario, nome: nomeAgendado || 'Serviço agendado', valor: valorAgendado || 0 }].concat(opcoes);
            }
            return opcoes.map(function (s) {
                return `<option value="${s.id}">${escapeHtml(s.nome)} — ${Number(s.valor).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}</option>`;
            }).join('');
        };

        // ---- Serviço realmente realizado: SELECT alimentado pelos
        // serviços ATIVOS cadastrados no banco (SERVICOS_DISPONIVEIS, vindo
        // de $servicos no PHP — nunca uma lista fixa no JS), pré-selecionado
        // com o que foi originalmente agendado. Permite escolher, por
        // exemplo, só "Corte" mesmo se o cliente tinha agendado "Corte +
        // Barba". Num agendamento duplicado (dois horários vinculados),
        // cada horário tem o SEU PRÓPRIO campo — cada pessoa pode ter feito
        // um corte diferente do que constava no agendamento original.
        const irmao = horarioSelecionado.duplicado
            ? horariosAtuais.find(h => h.duplicado && h.grupoAgendamento === horarioSelecionado.grupoAgendamento && h.idHorario !== horarioSelecionado.idHorario)
            : null;

        if (horarioSelecionado.duplicado && irmao) {
            const principal   = horarioSelecionado.ehPrincipal ? horarioSelecionado : irmao;
            const secundario  = horarioSelecionado.ehPrincipal ? irmao : horarioSelecionado;

            document.getElementById('concluir-servico-solo-row').classList.add('hidden');
            document.getElementById('concluir-servico-duplicado-wrap').classList.remove('hidden');

            document.getElementById('concluir-duplicado-label-1').textContent = 'Corte do Horário 1 (' + principal.hora + ')';
            document.getElementById('concluir-duplicado-label-2').textContent = 'Corte do Horário 2 (' + secundario.hora + ')';

            const selectPrincipal  = document.getElementById('concluir-idServico-principal');
            const selectSecundario = document.getElementById('concluir-idServico-secundario');
            selectPrincipal.innerHTML  = opcoesServicoHtml(principal.idServico, principal.servico, principal.valor);
            selectSecundario.innerHTML = opcoesServicoHtml(secundario.idServico, secundario.servico, secundario.valor);
            selectPrincipal.value  = principal.idServico || '';
            selectSecundario.value = secundario.idServico || '';

            document.getElementById('concluir-idAgendamento').value = principal.idAgendamento;
            document.getElementById('concluir-idAgendamento-secundario').value = secundario.idAgendamento;

            // "Concluir só este horário" sempre se refere ao que o barbeiro
            // efetivamente clicou pra abrir o modal (horarioSelecionado),
            // não necessariamente o Horário 1.
            const btnSomenteEste = document.getElementById('btn-concluir-somente-este');
            btnSomenteEste.textContent = 'Concluir só o Horário ' + (horarioSelecionado.ehPrincipal ? '1' : '2') + ' (' + horarioSelecionado.hora + ')';
            btnSomenteEste.classList.remove('hidden');

            atualizarValorConcluir();
        } else {
            document.getElementById('concluir-servico-solo-row').classList.remove('hidden');
            document.getElementById('concluir-servico-duplicado-wrap').classList.add('hidden');
            document.getElementById('btn-concluir-somente-este').classList.add('hidden');
            document.getElementById('concluir-idAgendamento-secundario').value = '';

            const selectServico = document.getElementById('concluir-idServico');
            selectServico.innerHTML = opcoesServicoHtml(horarioSelecionado.idServico, horarioSelecionado.servico, horarioSelecionado.valor);
            selectServico.value = horarioSelecionado.idServico || '';

            document.getElementById('concluir-valor').textContent = horarioSelecionado.valor
                ? Number(horarioSelecionado.valor).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
                : '—';
        }

        limparFormasSelecionadas('concluir');
        document.getElementById('concluir-fiado').checked = false;
        alternarEstadoFiado(false);
        document.getElementById('concluir-ausente').checked = false;
        alternarEstadoAusente(false);

        closeModal('modal-detalhes');
        openModal('modal-concluir');
    };

    // Recalcula o valor exibido quando o barbeiro troca o serviço realmente
    // realizado — soma os dois cortes num duplicado com cobranca "dois",
    // ou só o do Horário 1 quando a cobrança é "um" (mesma regra definida
    // na criação do agendamento, só que agora sobre o que foi
    // efetivamente escolhido aqui).
    window.atualizarValorConcluir = function () {
        const duplicadoAtivo = !document.getElementById('concluir-servico-duplicado-wrap').classList.contains('hidden');

        if (duplicadoAtivo) {
            const idPrincipal  = Number(document.getElementById('concluir-idServico-principal').value);
            const idSecundario = Number(document.getElementById('concluir-idServico-secundario').value);
            const servicoPrincipal  = SERVICOS_DISPONIVEIS.find(function (s) { return s.id === idPrincipal; });
            const servicoSecundario = SERVICOS_DISPONIVEIS.find(function (s) { return s.id === idSecundario; });

            if (servicoPrincipal) {
                const cobrancaDois = horarioSelecionado.cobrancaDuplicado === 'dois';
                const total = Number(servicoPrincipal.valor) + (cobrancaDois && servicoSecundario ? Number(servicoSecundario.valor) : 0);
                document.getElementById('concluir-valor').textContent =
                    total.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
            }
            return;
        }

        const select = document.getElementById('concluir-idServico');
        const idServico = Number(select.value);
        const servico = SERVICOS_DISPONIVEIS.find(function (s) { return s.id === idServico; });
        if (servico) {
            document.getElementById('concluir-valor').textContent =
                Number(servico.valor).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
        }
    };

    window.alternarEstadoFiado = function (ligado) {
        const badge = document.getElementById('concluir-fiado-estado');
        badge.textContent = ligado ? 'ON' : 'OFF';
        badge.classList.toggle('is-on', ligado);

        // Fiado ligado: bloqueia a forma de pagamento (só é escolhida no
        // recebimento, lá em Financeiro > Fiados). Fiado desligado: libera.
        definirFormasHabilitadas('concluir', !ligado);
        document.getElementById('concluir-forma-fiado-aviso').classList.toggle('hidden', !ligado);
    };

    // "Cliente Ausente": não há cobrança nenhuma, então forma de pagamento
    // e "Receber depois" (fiado) somem inteiramente da tela nesse caso —
    // bem diferente do fiado, que só bloqueia a forma de pagamento mas
    // continua exigindo o restante do fluxo normal. "Concluir só este
    // horário" também some — ausente já marca as duas linhas juntas.
    window.alternarEstadoAusente = function (ligado) {
        const badge = document.getElementById('concluir-ausente-estado');
        badge.textContent = ligado ? 'ON' : 'OFF';
        badge.classList.toggle('is-on', ligado);

        document.getElementById('concluir-forma-wrap').classList.toggle('hidden', ligado);
        document.getElementById('concluir-fiado-wrap').classList.toggle('hidden', ligado);

        const btnSomenteEste = document.getElementById('btn-concluir-somente-este');
        if (ligado) {
            // Zera fiado — não faz sentido os dois ligados ao mesmo tempo.
            document.getElementById('concluir-fiado').checked = false;
            alternarEstadoFiado(false);
            btnSomenteEste.classList.add('hidden');
        } else if (horarioSelecionado && horarioSelecionado.duplicado) {
            const irmaoAindaAtivo = horariosAtuais.find(h => h.duplicado && h.grupoAgendamento === horarioSelecionado.grupoAgendamento && h.idHorario !== horarioSelecionado.idHorario);
            btnSomenteEste.classList.toggle('hidden', !irmaoAindaAtivo);
        }

        document.getElementById('btn-confirmar-conclusao').textContent = ligado
            ? 'Marcar como ausente'
            : 'Concluir agendamento';
    };

    window.fecharModalConcluir = function () {
        closeModal('modal-concluir');
    };

    window.fecharAoClicarForaConcluir = function (evento) {
        if (evento.target.id === 'modal-concluir') fecharModalConcluir();
    };

    window.confirmarConclusao = async function (evento) {
        evento.preventDefault();

        const ausente = document.getElementById('concluir-ausente').checked;
        const fiado   = !ausente && document.getElementById('concluir-fiado').checked;
        const duplicadoAtivo = !document.getElementById('concluir-servico-duplicado-wrap').classList.contains('hidden');

        // Forma de pagamento só é obrigatória quando NÃO é fiado e NÃO é
        // "Cliente Ausente" — no fiado ela fica bloqueada e é definida
        // depois, no recebimento; no ausente não existe cobrança nenhuma.
        if (!ausente && !fiado && !validarFormasSelecionadas('concluir')) return;

        const idAgendamento = document.getElementById('concluir-idAgendamento').value;

        const btn = document.getElementById('btn-confirmar-conclusao');
        btn.disabled = true;
        btn.textContent = ausente ? 'Marcando...' : 'Concluindo...';

        try {
            const formData = new FormData();
            formData.set('idAgendamento', idAgendamento);
            formData.set('ausente', ausente ? '1' : '0');
            formData.set('fiado', fiado ? '1' : '0');
            if (!ausente) {
                if (duplicadoAtivo) {
                    const idPrincipal  = document.getElementById('concluir-idServico-principal').value;
                    const idSecundario = document.getElementById('concluir-idServico-secundario').value;
                    if (idPrincipal)  formData.set('idServicoRealizado', idPrincipal);
                    if (idSecundario) formData.set('idServicoRealizadoSecundario', idSecundario);
                } else {
                    const idServicoRealizadoSelecionado = document.getElementById('concluir-idServico').value;
                    if (idServicoRealizadoSelecionado) {
                        formData.set('idServicoRealizado', idServicoRealizadoSelecionado);
                    }
                }
                if (!fiado) {
                    obterFormasSelecionadas('concluir').forEach(function (f) { formData.append('formas[]', f); });
                }
            }

            const resposta = await fetch('../scripts/agendamento_concluir.php', { method: 'POST', body: formData });
            const dados = await resposta.json();

            if (!dados.ok) {
                toast(dados.erro || 'Não foi possível concluir o agendamento.', 'erro');
            } else {
                fecharModalConcluir();
                let mensagemConcluido;
                if (ausente) {
                    mensagemConcluido = dados.idAgendamentoSecundario
                        ? 'Cliente ausente registrado (os dois horários foram liberados, sem cobrança).'
                        : 'Cliente ausente registrado. Horário liberado, sem cobrança.';
                } else {
                    mensagemConcluido = fiado ? 'Agendamento concluído. Lançado como fiado.' : 'Agendamento concluído.';
                    if (dados.idAgendamentoSecundario) {
                        mensagemConcluido = 'Agendamento duplicado concluído (os dois horários). ' + (fiado ? 'Lançado como fiado.' : 'Pagamento registrado.');
                    }
                }
                toast(mensagemConcluido, 'sucesso');
                recarregarHorariosAtuais();
            }
        } catch (e) {
            toast('Erro de conexão. Tente novamente.', 'erro');
        }

        btn.disabled = false;
        btn.textContent = ausente ? 'Marcar como ausente' : 'Concluir agendamento';
    };

    // "Concluir só este horário": conclui SÓ o horário que foi clicado
    // (guardado em horarioSelecionado), deixando o outro do duplicado em
    // aberto — ver Agendamentos/scripts/agendamento_concluir_individual.php.
    window.confirmarConclusaoIndividual = async function () {
        if (!horarioSelecionado) return;

        const fiado = document.getElementById('concluir-fiado').checked;
        if (!fiado && !validarFormasSelecionadas('concluir')) return;

        const ehPrincipal = horarioSelecionado.ehPrincipal;
        const idServicoRealizadoSelecionado = ehPrincipal
            ? document.getElementById('concluir-idServico-principal').value
            : document.getElementById('concluir-idServico-secundario').value;

        const btn = document.getElementById('btn-concluir-somente-este');
        btn.disabled = true;
        btn.textContent = 'Concluindo...';

        try {
            const formData = new FormData();
            formData.set('idAgendamento', horarioSelecionado.idAgendamento);
            formData.set('fiado', fiado ? '1' : '0');
            if (idServicoRealizadoSelecionado) {
                formData.set('idServicoRealizado', idServicoRealizadoSelecionado);
            }
            if (!fiado) {
                obterFormasSelecionadas('concluir').forEach(function (f) { formData.append('formas[]', f); });
            }

            const resposta = await fetch('../scripts/agendamento_concluir_individual.php', { method: 'POST', body: formData });
            const dados = await resposta.json();

            if (!dados.ok) {
                toast(dados.erro || 'Não foi possível concluir este horário.', 'erro');
            } else {
                fecharModalConcluir();
                toast('Horário concluído. ' + (dados.promovido ? 'O outro horário do par continua agendado normalmente.' : ''), 'sucesso');
                recarregarHorariosAtuais();
            }
        } catch (e) {
            toast('Erro de conexão. Tente novamente.', 'erro');
        }

        btn.disabled = false;
        btn.textContent = 'Concluir só este horário';
    };

    // ---------------- Reagendamento (fidelidade) ----------------
    // Botão "Reagendar", visível só em agendamentos que fazem parte de uma
    // fidelidade (ver includes/FidelidadeService.php). Duas opções:
    //   - "Reagendar somente esta semana": move só esta ocorrência, a
    //     sequência de fidelidade original continua intacta.
    //   - "Reagendar fidelidade": recalcula toda a sequência (cancela as
    //     ocorrências futuras e gera novas a partir da nova data-base).
    const chevronSvgReagendar = '<svg class="corte-modal-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';

    window.abrirFluxoReagendar = function () {
        if (!horarioSelecionado) return;

        // Cliente avulso (sem fidelidade): não há "sequência" pra escolher
        // entre mover só uma semana ou tudo — vai direto pro único fluxo
        // que existe pra ele.
        if (!horarioSelecionado.fidelidade) {
            abrirModalReagendarUnico();
            return;
        }

        Swal.fire({
            title: 'Reagendar',
            text: 'Como deseja reagendar?',
            icon: 'question',
            background: '#101828',
            color: '#e9eef6',
            showDenyButton: true,
            showCancelButton: true,
            confirmButtonText: 'Reagendar somente esta semana',
            denyButtonText: 'Reagendar fidelidade',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#3d7ec9',
            denyButtonColor: '#8b5cf6',
            reverseButtons: true
        }).then(function (resultado) {
            if (resultado.isConfirmed) {
                abrirModalReagendarUnico();
            } else if (resultado.isDenied) {
                abrirModalReagendarFidelidade();
            }
        });
    };

    // Preenche o <select> de horários livres do dia escolhido — a mesma
    // grade já usada em toda a agenda (Agendamentos/scripts/horarios_buscar.php),
    // então o reagendamento nunca cai fora dela nem em horário ocupado.
    function popularSelectHorasReagendar(selectEl, dataEscolhida) {
        selectEl.disabled = true;
        selectEl.innerHTML = '<option value="">Carregando...</option>';

        fetch('../scripts/horarios_buscar.php?data=' + encodeURIComponent(dataEscolhida))
            .then(function (r) { return r.json(); })
            .then(function (dados) {
                if (!dados.ok) {
                    selectEl.innerHTML = '<option value="">Não foi possível carregar</option>';
                    return;
                }
                const livres = (dados.horarios || []).filter(function (h) { return !h.ocupado && h.disponivel; });
                if (livres.length === 0) {
                    selectEl.innerHTML = '<option value="">Nenhum horário livre nesse dia</option>';
                    return;
                }
                selectEl.innerHTML = '<option value="">Selecione...</option>' +
                    livres.map(function (h) { return `<option value="${h.hora}">${h.hora}</option>`; }).join('');
                selectEl.disabled = false;
            })
            .catch(function () {
                selectEl.innerHTML = '<option value="">Erro ao carregar horários</option>';
            });
    }

    // "Reagendar somente esta semana": move só o agendamento clicado.
    window.abrirModalReagendarUnico = function () {
        const h = horarioSelecionado;
        const dataAtualFmt = dataAgendamentoSelecionado.split('-').reverse().join('/');
        const minData = formatarDataChave(hoje);

        closeModal('modal-detalhes');

        Swal.fire({
            title: 'Reagendar agendamento',
            html: `
                <div style="text-align:left;">
                    <div class="view-row" style="border-top:none; padding-top:0;">
                        <span class="view-label">Cliente</span><span class="view-value">${escapeHtml(h.cliente.nome)}</span>
                    </div>
                    <div class="view-row">
                        <span class="view-label">Data atual</span><span class="view-value">${dataAtualFmt}</span>
                    </div>
                    <div class="view-row">
                        <span class="view-label">Horário atual</span><span class="view-value">${escapeHtml(h.hora)}</span>
                    </div>

                    <label class="corte-modal-label" style="margin-top:18px;">Nova data</label>
                    <input type="date" id="reagendar-data" class="corte-modal-select" min="${minData}">

                    <label class="corte-modal-label" style="margin-top:16px;">Novo horário</label>
                    <div class="corte-modal-select-wrap">
                        <select id="reagendar-hora" class="corte-modal-select" disabled><option value="">Escolha a data primeiro...</option></select>
                        ${chevronSvgReagendar}
                    </div>
                </div>
            `,
            background: '#101828',
            color: '#e9eef6',
            confirmButtonColor: '#3d7ec9',
            confirmButtonText: 'Confirmar reagendamento',
            showCancelButton: true,
            cancelButtonText: 'Cancelar',
            focusConfirm: false,
            allowOutsideClick: false,
            didOpen: function () {
                document.getElementById('reagendar-data').addEventListener('change', function () {
                    if (this.value) popularSelectHorasReagendar(document.getElementById('reagendar-hora'), this.value);
                });
            },
            preConfirm: function () {
                const novaData = document.getElementById('reagendar-data').value;
                const novaHora = document.getElementById('reagendar-hora').value;
                if (!novaData || !novaHora) {
                    Swal.showValidationMessage('Escolha a nova data e o novo horário.');
                    return false;
                }
                return { novaData: novaData, novaHora: novaHora };
            }
        }).then(function (resultado) {
            if (!resultado.isConfirmed) return;

            const formData = new FormData();
            formData.set('idAgendamento', h.idAgendamento);
            formData.set('novaData', resultado.value.novaData);
            formData.set('novaHora', resultado.value.novaHora);

            fetch('../scripts/agendamento_reagendar_unico.php', { method: 'POST', body: formData })
                .then(function (r) { return r.json(); })
                .then(function (dados) {
                    if (!dados.ok) {
                        toast(dados.erro || 'Não foi possível reagendar.', 'erro');
                        return;
                    }
                    toast(
                        h.fidelidade ? 'Agendamento reagendado. A fidelidade original continua intacta.' : 'Agendamento reagendado.',
                        'sucesso'
                    );
                    recarregarHorariosAtuais();
                })
                .catch(function () {
                    toast('Erro de conexão. Tente novamente.', 'erro');
                });
        });
    };

    // "Reagendar fidelidade": recalcula toda a sequência a partir da nova
    // data/horário base.
    window.abrirModalReagendarFidelidade = function () {
        const h = horarioSelecionado;
        const dataAtualFmt = dataAgendamentoSelecionado.split('-').reverse().join('/');
        const minData = formatarDataChave(hoje);
        const rotuloFidelidade = ROTULOS_FIDELIDADE[h.fidelidade] || '—';

        closeModal('modal-detalhes');

        Swal.fire({
            title: 'Reagendar fidelidade',
            html: `
                <div style="text-align:left;">
                    <div class="view-row" style="border-top:none; padding-top:0;">
                        <span class="view-label">Cliente</span><span class="view-value">${escapeHtml(h.cliente.nome)}</span>
                    </div>
                    <div class="view-row">
                        <span class="view-label">Agendamento atual</span><span class="view-value">${dataAtualFmt} às ${escapeHtml(h.hora)}</span>
                    </div>
                    <div class="view-row">
                        <span class="view-label">Frequência</span><span class="view-value">${escapeHtml(rotuloFidelidade)}</span>
                    </div>

                    <label class="corte-modal-label" style="margin-top:18px;">Nova data inicial</label>
                    <input type="date" id="reagendar-data" class="corte-modal-select" min="${minData}">

                    <label class="corte-modal-label" style="margin-top:16px;">Novo horário</label>
                    <div class="corte-modal-select-wrap">
                        <select id="reagendar-hora" class="corte-modal-select" disabled><option value="">Escolha a data primeiro...</option></select>
                        ${chevronSvgReagendar}
                    </div>

                    <p class="text-xs text-zinc-500" style="margin-top:14px;">Período: próximos 12 meses a partir da nova data inicial.</p>
                </div>
            `,
            background: '#101828',
            color: '#e9eef6',
            confirmButtonColor: '#8b5cf6',
            confirmButtonText: 'Confirmar reagendamento da fidelidade',
            showCancelButton: true,
            cancelButtonText: 'Cancelar',
            focusConfirm: false,
            allowOutsideClick: false,
            didOpen: function () {
                document.getElementById('reagendar-data').addEventListener('change', function () {
                    if (this.value) popularSelectHorasReagendar(document.getElementById('reagendar-hora'), this.value);
                });
            },
            preConfirm: function () {
                const novaData = document.getElementById('reagendar-data').value;
                const novaHora = document.getElementById('reagendar-hora').value;
                if (!novaData || !novaHora) {
                    Swal.showValidationMessage('Escolha a nova data inicial e o novo horário.');
                    return false;
                }
                return { novaData: novaData, novaHora: novaHora };
            }
        }).then(function (resultado) {
            if (!resultado.isConfirmed) return;

            const formData = new FormData();
            formData.set('idAgendamento', h.idAgendamento);
            formData.set('novaData', resultado.value.novaData);
            formData.set('novaHora', resultado.value.novaHora);

            fetch('../scripts/agendamento_reagendar_fidelidade.php', { method: 'POST', body: formData })
                .then(function (r) { return r.json(); })
                .then(function (dados) {
                    if (!dados.ok) {
                        toast(dados.erro || 'Não foi possível reagendar a fidelidade.', 'erro');
                        return;
                    }

                    let html = `Fidelidade <strong>${escapeHtml(dados.rotulo)}</strong> recalculada a partir de ${escapeHtml(resultado.value.novaData.split('-').reverse().join('/'))}: ${dados.criadas} agendamento(s) criado(s), ${dados.canceladas} ocorrência(s) antiga(s) cancelada(s).`;

                    if (dados.puladas && dados.puladas.length > 0) {
                        const datasPuladas = dados.puladas.map(function (p) { return p.data.split('-').reverse().join('/'); }).join(', ');
                        html += `<br><br><span style="color:#e0a2a8;">${dados.puladas.length} ocorrência(s) não pôde(puderam) ser criada(s) por conflito:</span><br>${escapeHtml(datasPuladas)}`;
                    }

                    Swal.fire({
                        title: 'Fidelidade reagendada!',
                        html: html,
                        icon: 'success',
                        background: '#101828',
                        color: '#e9eef6',
                        confirmButtonColor: '#3d7ec9',
                        confirmButtonText: 'Entendi'
                    });

                    recarregarHorariosAtuais();
                })
                .catch(function () {
                    toast('Erro de conexão. Tente novamente.', 'erro');
                });
        });
    };

    // ================== Alterar agendamento (Alterar cliente / Trocar cliente / Trocar horário) ==================
    // Ponto de entrada único (botão "🔁 Alterar agendamento" no modal de
    // detalhes), que oferece as três operações distintas descritas no topo
    // deste arquivo. "Trocar horário" reaproveita 100% o fluxo de
    // reagendamento já existente (abrirFluxoReagendar) — nenhuma lógica
    // nova pra isso, só um novo ponto de entrada.
    window.abrirMenuAlterarAgendamento = function () {
        if (!horarioSelecionado) return;

        Swal.fire({
            title: 'Alterar agendamento',
            text: 'O que você deseja fazer?',
            icon: 'question',
            background: '#101828',
            color: '#e9eef6',
            showDenyButton: true,
            showCancelButton: true,
            confirmButtonText: 'Alterar cliente',
            denyButtonText: 'Trocar cliente',
            cancelButtonText: 'Trocar horário',
            confirmButtonColor: '#3d7ec9',
            denyButtonColor: '#8b5cf6',
            cancelButtonColor: '#3a4a63',
            reverseButtons: true
        }).then(function (resultado) {
            if (resultado.isConfirmed) {
                abrirModalAlterarCliente();
            } else if (resultado.isDenied) {
                abrirModalTrocarCliente();
            } else if (resultado.dismiss === Swal.DismissReason.cancel) {
                abrirFluxoReagendar();
            }
        });
    };

    // ---------------- Alterar cliente ----------------
    // Substitui o cliente do agendamento atual, mantendo o mesmo horário.
    // Depois de substituir, oferece reagendar o cliente antigo pra outro
    // horário livre (ver agendamento_alterar_cliente.php).
    window.abrirModalAlterarCliente = function () {
        if (!horarioSelecionado) return;
        alterarClienteSelecionado = null;

        const idAgendamentoAtual = document.getElementById('det-idAgendamento').value;
        const idClienteAtual = horarioSelecionado.cliente.idCliente;

        Swal.fire({
            title: 'Alterar cliente',
            html: `
                <div style="text-align:left;">
                    <p class="text-xs text-zinc-500 mb-2">O horário continua o mesmo (${escapeHtml(horarioSelecionado.hora)} — ${escapeHtml(dataAgendamentoSelecionado.split('-').reverse().join('/'))}) — só o cliente muda. Busque o novo cliente:</p>
                    <input type="text" id="swal-alterar-busca" class="field w-full h-11 px-4 rounded-xl text-sm mb-2" placeholder="Nome ou telefone...">
                    <div id="swal-alterar-resultados" style="max-height:180px; overflow-y:auto; display:flex; flex-direction:column; gap:6px;"></div>
                    <div id="swal-alterar-selecionado" class="hidden" style="margin-top:10px; padding:10px 12px; border-radius:10px; background:rgba(61,126,201,0.1); border:1px solid rgba(61,126,201,0.35); font-size:13px;"></div>
                </div>
            `,
            background: '#101828',
            color: '#e9eef6',
            confirmButtonColor: '#3d7ec9',
            confirmButtonText: 'Alterar cliente',
            showCancelButton: true,
            cancelButtonText: 'Cancelar',
            focusConfirm: false,
            allowOutsideClick: false,
            didOpen: function () {
                let temporizador = null;
                document.getElementById('swal-alterar-busca').addEventListener('input', function () {
                    const termo = this.value.trim();
                    clearTimeout(temporizador);
                    const resultados = document.getElementById('swal-alterar-resultados');

                    if (termo.length < 2) {
                        resultados.innerHTML = '';
                        return;
                    }

                    temporizador = setTimeout(async () => {
                        try {
                            const resposta = await fetch('../../Clientes/scripts/clientes_buscar.php?termo=' + encodeURIComponent(termo));
                            const dados = await resposta.json();
                            resultados.innerHTML = '';

                            const clientes = (dados.clientes || []).filter(function (c) { return c.idCliente !== idClienteAtual; });

                            if (!dados.ok || clientes.length === 0) {
                                resultados.innerHTML = '<p class="text-xs text-zinc-500 px-1 py-2">Nenhum cliente encontrado.</p>';
                                return;
                            }

                            clientes.forEach(function (c) {
                                const item = document.createElement('div');
                                item.className = 'resultado-cliente';
                                item.innerHTML = `<strong>${escapeHtml(c.nome)}</strong> — ${escapeHtml(c.telefone)}`;
                                item.addEventListener('click', function () {
                                    alterarClienteSelecionado = c;
                                    resultados.innerHTML = '';
                                    document.getElementById('swal-alterar-busca').value = '';
                                    const sel = document.getElementById('swal-alterar-selecionado');
                                    sel.classList.remove('hidden');
                                    sel.innerHTML = `Novo cliente: <strong>${escapeHtml(c.nome)}</strong> — ${escapeHtml(c.telefone)}`;
                                });
                                resultados.appendChild(item);
                            });
                        } catch (e) {
                            resultados.innerHTML = '<p class="text-xs text-zinc-500 px-1 py-2">Erro ao buscar clientes.</p>';
                        }
                    }, 300);
                });
            },
            preConfirm: function () {
                if (!alterarClienteSelecionado) {
                    Swal.showValidationMessage('Busque e selecione o novo cliente.');
                    return false;
                }
                return alterarClienteSelecionado;
            }
        }).then(function (resultado) {
            if (!resultado.isConfirmed) return;

            const formData = new FormData();
            formData.set('idAgendamento', idAgendamentoAtual);
            formData.set('novoIdCliente', resultado.value.idCliente);

            fetch('../scripts/agendamento_alterar_cliente.php', { method: 'POST', body: formData })
                .then(function (r) { return r.json(); })
                .then(function (dados) {
                    if (!dados.ok) {
                        toast(dados.erro || 'Não foi possível alterar o cliente.', 'erro');
                        return;
                    }

                    closeModal('modal-detalhes');
                    toast('Cliente alterado com sucesso.', 'sucesso');

                    Swal.fire({
                        title: 'Reagendar cliente anterior?',
                        html: `O cliente <strong>${escapeHtml(dados.clienteAntigo.nome)}</strong> estava agendado para este horário.<br>Deseja reagendar ${escapeHtml(dados.clienteAntigo.nome)} para outro horário?`,
                        icon: 'question',
                        background: '#101828',
                        color: '#e9eef6',
                        showDenyButton: true,
                        confirmButtonText: 'Sim, reagendar',
                        denyButtonText: 'Não, apenas substituir',
                        confirmButtonColor: '#3d7ec9',
                        denyButtonColor: '#3a4a63',
                        reverseButtons: true
                    }).then(function (r2) {
                        if (r2.isConfirmed) {
                            abrirPickerReagendarClienteAntigo(dados.clienteAntigo, dados.servicoOriginal, dados.observacaoOriginal);
                        } else {
                            recarregarHorariosAtuais();
                        }
                    });
                })
                .catch(function () {
                    toast('Erro de conexão. Tente novamente.', 'erro');
                });
        });
    };

    // Escolhe um novo horário livre (qualquer dia, dia/semana/mês) pro
    // cliente que acabou de ser substituído — reaproveita o mesmo padrão de
    // data + hora usado no reagendamento normal (popularSelectHorasReagendar
    // já respeita disponibilidade, dia bloqueado e conflitos).
    window.abrirPickerReagendarClienteAntigo = function (clienteAntigo, servicoOriginal, observacaoOriginal) {
        const minData = formatarDataChave(hoje);

        Swal.fire({
            title: 'Reagendar ' + clienteAntigo.nome,
            html: `
                <div style="text-align:left;">
                    <label class="corte-modal-label">Nova data</label>
                    <input type="date" id="reagendar-antigo-data" class="corte-modal-select" min="${minData}">

                    <label class="corte-modal-label" style="margin-top:16px;">Novo horário</label>
                    <div class="corte-modal-select-wrap">
                        <select id="reagendar-antigo-hora" class="corte-modal-select" disabled><option value="">Escolha a data primeiro...</option></select>
                        ${chevronSvgReagendar}
                    </div>
                </div>
            `,
            background: '#101828',
            color: '#e9eef6',
            confirmButtonColor: '#3d7ec9',
            confirmButtonText: 'Reagendar',
            showCancelButton: true,
            cancelButtonText: 'Cancelar',
            focusConfirm: false,
            allowOutsideClick: false,
            didOpen: function () {
                document.getElementById('reagendar-antigo-data').addEventListener('change', function () {
                    if (this.value) popularSelectHorasReagendar(document.getElementById('reagendar-antigo-hora'), this.value);
                });
            },
            preConfirm: function () {
                const novaData = document.getElementById('reagendar-antigo-data').value;
                const novaHora = document.getElementById('reagendar-antigo-hora').value;
                if (!novaData || !novaHora) {
                    Swal.showValidationMessage('Escolha a nova data e o novo horário.');
                    return false;
                }
                return { novaData: novaData, novaHora: novaHora };
            }
        }).then(function (resultado) {
            if (!resultado.isConfirmed) {
                recarregarHorariosAtuais();
                return;
            }

            const formData = new FormData();
            formData.set('idCliente', clienteAntigo.idCliente);
            formData.set('idServico', servicoOriginal.id);
            formData.set('novaData', resultado.value.novaData);
            formData.set('novaHora', resultado.value.novaHora);
            if (observacaoOriginal) formData.set('observacao', observacaoOriginal);

            fetch('../scripts/agendamento_criar_para_cliente_existente.php', { method: 'POST', body: formData })
                .then(function (r) { return r.json(); })
                .then(function (dados) {
                    if (!dados.ok) {
                        toast(dados.erro || 'Não foi possível reagendar.', 'erro');
                    } else {
                        toast(clienteAntigo.nome + ' reagendado(a) para ' + resultado.value.novaData.split('-').reverse().join('/') + ' às ' + resultado.value.novaHora + '.', 'sucesso');
                    }
                    recarregarHorariosAtuais();
                })
                .catch(function () {
                    toast('Erro de conexão. Tente novamente.', 'erro');
                    recarregarHorariosAtuais();
                });
        });
    };

    // ---------------- Trocar cliente ----------------
    // Troca os clientes de dois agendamentos ATIVOS entre si — cada um
    // assume o horário que era do outro (ver agendamento_trocar_cliente.php).
    window.abrirModalTrocarCliente = function () {
        if (!horarioSelecionado) return;
        trocarClienteSelecionado = null;

        const idAgendamentoAtual = document.getElementById('det-idAgendamento').value;

        Swal.fire({
            title: 'Trocar cliente',
            html: `
                <div style="text-align:left;">
                    <p class="text-xs text-zinc-500 mb-2">Busque outro cliente que já possua um agendamento — os dois horários serão trocados entre si.</p>
                    <input type="text" id="swal-trocar-busca" class="field w-full h-11 px-4 rounded-xl text-sm mb-2" placeholder="Nome ou telefone...">
                    <div id="swal-trocar-resultados" style="max-height:180px; overflow-y:auto; display:flex; flex-direction:column; gap:6px;"></div>
                    <div id="swal-trocar-selecionado" class="hidden" style="margin-top:10px; padding:10px 12px; border-radius:10px; background:rgba(139,92,246,0.12); border:1px solid rgba(139,92,246,0.4); font-size:13px;"></div>
                </div>
            `,
            background: '#101828',
            color: '#e9eef6',
            confirmButtonColor: '#8b5cf6',
            confirmButtonText: 'Trocar',
            showCancelButton: true,
            cancelButtonText: 'Cancelar',
            focusConfirm: false,
            allowOutsideClick: false,
            didOpen: function () {
                let temporizador = null;
                document.getElementById('swal-trocar-busca').addEventListener('input', function () {
                    const termo = this.value.trim();
                    clearTimeout(temporizador);
                    const resultados = document.getElementById('swal-trocar-resultados');

                    if (termo.length < 2) {
                        resultados.innerHTML = '';
                        return;
                    }

                    temporizador = setTimeout(async () => {
                        try {
                            const resposta = await fetch('../scripts/agendamento_buscar_para_troca.php?termo=' + encodeURIComponent(termo) + '&excluir=' + encodeURIComponent(idAgendamentoAtual));
                            const dados = await resposta.json();
                            resultados.innerHTML = '';

                            if (!dados.ok || dados.agendamentos.length === 0) {
                                resultados.innerHTML = '<p class="text-xs text-zinc-500 px-1 py-2">Nenhum agendamento encontrado.</p>';
                                return;
                            }

                            dados.agendamentos.forEach(function (ag) {
                                const dataFmt = ag.data.split('-').reverse().join('/');
                                const item = document.createElement('div');
                                item.className = 'resultado-cliente';
                                item.innerHTML = `<strong>${escapeHtml(ag.clienteNome)}</strong> — ${dataFmt} às ${escapeHtml(ag.hora)} (${escapeHtml(ag.servicoNome)})`;
                                item.addEventListener('click', function () {
                                    trocarClienteSelecionado = ag;
                                    resultados.innerHTML = '';
                                    document.getElementById('swal-trocar-busca').value = '';
                                    const sel = document.getElementById('swal-trocar-selecionado');
                                    sel.classList.remove('hidden');
                                    sel.innerHTML = `Selecionado: <strong>${escapeHtml(ag.clienteNome)}</strong> — ${dataFmt} às ${escapeHtml(ag.hora)}`;
                                });
                                resultados.appendChild(item);
                            });
                        } catch (e) {
                            resultados.innerHTML = '<p class="text-xs text-zinc-500 px-1 py-2">Erro ao buscar.</p>';
                        }
                    }, 300);
                });
            },
            preConfirm: function () {
                if (!trocarClienteSelecionado) {
                    Swal.showValidationMessage('Busque e selecione um agendamento pra trocar.');
                    return false;
                }
                return trocarClienteSelecionado;
            }
        }).then(function (resultado) {
            if (!resultado.isConfirmed) return;

            const formData = new FormData();
            formData.set('idAgendamentoA', idAgendamentoAtual);
            formData.set('idAgendamentoB', resultado.value.idAgendamento);

            fetch('../scripts/agendamento_trocar_cliente.php', { method: 'POST', body: formData })
                .then(function (r) { return r.json(); })
                .then(function (dados) {
                    if (!dados.ok) {
                        toast(dados.erro || 'Não foi possível trocar os clientes.', 'erro');
                        return;
                    }
                    closeModal('modal-detalhes');
                    toast('Clientes trocados com sucesso.', 'sucesso');
                    recarregarHorariosAtuais();
                })
                .catch(function () {
                    toast('Erro de conexão. Tente novamente.', 'erro');
                });
        });
    };

    // ---------------- Lista de Espera (popup flutuante) ----------------
    // Etapa 1: buscar/cadastrar cliente, ver quem está aguardando, remover
    // da lista. Encaixe automático, notificações e WhatsApp ficam para uma
    // etapa futura (fora do escopo desta versão).
    let listaEsperaAberta = false;
    let listaEsperaAtual = [];

    window.toggleListaEspera = function () {
        listaEsperaAberta = !listaEsperaAberta;
        document.getElementById('painelListaEspera').classList.toggle('hidden', !listaEsperaAberta);
        if (listaEsperaAberta) {
            carregarListaEspera();
        } else {
            document.getElementById('le-busca-input').value = '';
            document.getElementById('le-resultados').innerHTML = '';
        }
    };

    async function carregarListaEspera() {
        try {
            const resposta = await fetch('../scripts/listaespera_listar.php');
            const dados = await resposta.json();
            listaEsperaAtual = dados.ok ? dados.lista : [];
        } catch (e) {
            listaEsperaAtual = [];
        }
        renderizarListaEspera();
    }

    function renderizarListaEspera() {
        const badge = document.getElementById('listaEsperaBadge');
        badge.textContent = listaEsperaAtual.length;
        badge.classList.toggle('hidden', listaEsperaAtual.length === 0);

        const lista = document.getElementById('le-lista');
        const vazio = document.getElementById('le-vazio');

        if (listaEsperaAtual.length === 0) {
            lista.innerHTML = '';
            vazio.classList.remove('hidden');
            return;
        }

        vazio.classList.add('hidden');
        lista.innerHTML = '';

        listaEsperaAtual.forEach(item => {
            const linha = document.createElement('div');
            linha.className = 'lista-espera-item';

            const info = document.createElement('div');
            info.className = 'min-w-0';
            info.innerHTML = `<p class="text-sm text-[color:var(--cream)] truncate">${escapeHtml(item.cliente.nome)}</p>
                               <p class="text-xs text-zinc-500 truncate">${escapeHtml(item.cliente.telefone)}${item.observacao ? ' — ' + escapeHtml(item.observacao) : ''}</p>`;

            const btnRemover = document.createElement('button');
            btnRemover.type = 'button';
            btnRemover.className = 'icon-btn shrink-0';
            btnRemover.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>';
            btnRemover.addEventListener('click', () => removerDaListaEspera(item.idEspera));

            linha.appendChild(info);
            linha.appendChild(btnRemover);
            lista.appendChild(linha);
        });
    }

    async function removerDaListaEspera(idEspera) {
        try {
            const formData = new FormData();
            formData.set('idEspera', idEspera);
            const resposta = await fetch('../scripts/listaespera_remover.php', { method: 'POST', body: formData });
            const dados = await resposta.json();
            if (!dados.ok) {
                toast(dados.erro || 'Não foi possível remover da lista.', 'erro');
                return;
            }
            toast('Removido da lista de espera.', 'sucesso');
            carregarListaEspera();
        } catch (e) {
            toast('Erro de conexão. Tente novamente.', 'erro');
        }
    }

    async function adicionarNaListaEspera(cliente) {
        try {
            const formData = new FormData();
            formData.set('idCliente', cliente.idCliente);
            const resposta = await fetch('../scripts/listaespera_adicionar.php', { method: 'POST', body: formData });
            const dados = await resposta.json();
            if (!dados.ok) {
                toast(dados.erro || 'Não foi possível adicionar à lista.', 'erro');
                return;
            }
            document.getElementById('le-busca-input').value = '';
            document.getElementById('le-resultados').innerHTML = '';
            toast('Adicionado à lista de espera.', 'sucesso');
            carregarListaEspera();
        } catch (e) {
            toast('Erro de conexão. Tente novamente.', 'erro');
        }
    }

    let temporizadorBuscaListaEspera = null;
    const leBuscaInput = document.getElementById('le-busca-input');
    if (leBuscaInput) {
        leBuscaInput.addEventListener('input', function () {
            const termo = this.value.trim();
            clearTimeout(temporizadorBuscaListaEspera);
            const resultados = document.getElementById('le-resultados');

            if (termo.length < 2) {
                resultados.innerHTML = '';
                return;
            }

            temporizadorBuscaListaEspera = setTimeout(async () => {
                try {
                    const resposta = await fetch('../../Clientes/scripts/clientes_buscar.php?termo=' + encodeURIComponent(termo));
                    const dados = await resposta.json();
                    resultados.innerHTML = '';

                    if (!dados.ok || dados.clientes.length === 0) {
                        resultados.innerHTML = '<p class="text-xs text-zinc-500 px-1 py-2">Nenhum cliente encontrado.</p>';
                        return;
                    }

                    dados.clientes.forEach(c => {
                        const item = document.createElement('div');
                        item.className = 'resultado-cliente';
                        item.innerHTML = `<strong>${escapeHtml(c.nome)}</strong> — ${escapeHtml(c.telefone)}`;
                        item.addEventListener('click', () => adicionarNaListaEspera(c));
                        resultados.appendChild(item);
                    });
                } catch (e) {
                    resultados.innerHTML = '<p class="text-xs text-zinc-500 px-1 py-2">Erro ao buscar clientes.</p>';
                }
            }, 300);
        });
    }

    // Carrega a lista de espera já ao abrir a página, só para o contador do
    // botão flutuante — o painel em si só é montado quando o barbeiro abre.
    carregarListaEspera();

    // ---------------- Utilitários de modal ----------------
    window.openModal = function (id) {
        document.getElementById(id).classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    };

    window.closeModal = function (id) {
        document.getElementById(id).classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    };

    window.fecharAoClicarFora = function (evento, id) {
        if (evento.target.id === id) {
            closeModal(id);
        }
    };

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            ['modal-agendar', 'modal-detalhes', 'modal-calendario', 'modal-concluir'].forEach(closeModal);
        }
    });
})();
</script>

</body>
</html>
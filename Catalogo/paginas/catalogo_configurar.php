<?php
// Catalogo/paginas/catalogo_configurar.php
// Catálogo > Configurar: o barbeiro monta a vitrine pública da barbearia
// (/c/agendar) — identidade, contato, horário de atendimento, descrição e
// ordem dos serviços, cargo dos profissionais. Só apresentação: a regra de
// agendamento continua exatamente a mesma.

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/guard.php';
exigirSessao(['barbeiro']);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/CatalogoService.php';
require_once __DIR__ . '/../../includes/PublicoTokenService.php';

$paginaAtual = 'catalogo-configurar';

$catalogoOk = CatalogoService::disponivel($pdo);
$cfg        = CatalogoService::config($pdo);
$servicos   = CatalogoService::servicos($pdo, true);
$barbeiros  = CatalogoService::barbeiros($pdo, true);

$esquema    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' ? 'https' : 'http';
$urlPublica = $esquema . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/c/agendar';

$logoUrl = CatalogoService::imagemUrl($cfg['logo']);
$capaUrl = CatalogoService::imagemUrl($cfg['capa']);

$categoriasExistentes = array_values(array_unique(array_filter(array_map(fn($s) => $s['categoria'], $servicos))));

$h = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<?php include __DIR__ . '/../../includes/theme-init.php'; ?>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Configurar catálogo — BarbERP</title>

<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/admin-theme.css?v=2">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.css">
<script src="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.js"></script>

<style>
    .cg-wrap{ max-width:1080px; margin:0 auto; }
    .cg-link{
        display:flex; flex-wrap:wrap; align-items:center; gap:12px; justify-content:space-between;
        padding:14px 16px; border-radius:1rem; margin-bottom:18px;
    }
    .cg-link__url{
        display:flex; align-items:center; gap:10px; min-width:0; flex:1 1 260px;
        font-size:13px; color:var(--cream);
    }
    .cg-link__url code{
        display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
        padding:8px 12px; border-radius:.7rem; background:rgba(0,0,0,.28); border:1px solid rgba(255,255,255,.08);
        flex:1; min-width:0; font-size:12.5px;
    }
    .cg-btn{
        display:inline-flex; align-items:center; justify-content:center; gap:8px;
        height:40px; padding:0 16px; border-radius:.8rem; font-size:13px; font-weight:600; cursor:pointer;
        border:1px solid rgba(255,255,255,.1); background:rgba(255,255,255,.04); color:var(--cream);
        text-decoration:none; transition:background-color .15s, border-color .15s, filter .15s; white-space:nowrap;
    }
    .cg-btn:hover{ background:rgba(255,255,255,.09); border-color:rgba(255,255,255,.2); }
    .cg-btn--pri{ background:linear-gradient(180deg,var(--gold-light),var(--gold)); border-color:transparent; color:#fff; }
    .cg-btn--pri:hover{ filter:brightness(1.08); background:linear-gradient(180deg,var(--gold-light),var(--gold)); }
    .cg-btn--sm{ height:34px; padding:0 12px; font-size:12px; border-radius:.65rem; }
    .cg-btn--perigo{ color:#f0a2a8; }
    .cg-btn[disabled]{ opacity:.55; cursor:wait; }

    .cg-tabs{
        display:flex; gap:6px; overflow-x:auto; padding:5px; border-radius:1rem; margin-bottom:18px;
        background:rgba(255,255,255,.03); border:1px solid rgba(255,255,255,.06); scrollbar-width:none;
    }
    .cg-tabs::-webkit-scrollbar{ display:none; }
    .cg-tab{
        flex:0 0 auto; height:38px; padding:0 16px; border-radius:.75rem; border:none; cursor:pointer;
        font-size:13px; font-weight:500; color:#8fa0bd; background:transparent; transition:background-color .15s, color .15s;
        font-family:inherit;
    }
    .cg-tab:hover{ color:var(--cream); }
    .cg-tab.is-ativa{ background:rgba(61,126,201,.18); color:var(--cream); box-shadow:inset 0 0 0 1px rgba(91,147,247,.35); }
    .cg-painel{ display:none; }
    .cg-painel.is-ativo{ display:block; animation:cgFade .25s ease; }
    @keyframes cgFade{ from{ opacity:0; transform:translateY(6px); } to{ opacity:1; transform:none; } }

    .cg-card{ border-radius:1.2rem; padding:22px; margin-bottom:18px; }
    .cg-card h2{ font-size:15px; font-weight:600; color:var(--cream); margin:0 0 4px; }
    .cg-card__sub{ font-size:12.5px; color:#8fa0bd; margin:0 0 18px; line-height:1.5; }
    .cg-grid{ display:grid; gap:16px; grid-template-columns:repeat(2, minmax(0,1fr)); }
    .cg-grid .cg-full{ grid-column:1 / -1; }
    @media (max-width:720px){ .cg-grid{ grid-template-columns:1fr; } .cg-card{ padding:18px; } }
    .cg-label{ display:block; font-size:11.5px; letter-spacing:.1em; text-transform:uppercase; font-weight:500; color:#aebdd6; margin-bottom:7px; }
    .cg-label small{ text-transform:none; letter-spacing:0; color:#7f8fac; font-weight:400; }
    .cg-input, .cg-textarea{
        width:100%; border-radius:.8rem; padding:0 14px; font-size:14px; font-family:inherit; color:var(--cream);
        background:rgba(0,0,0,.28); border:1px solid rgba(255,255,255,.09); transition:border-color .2s, box-shadow .2s;
    }
    .cg-input{ height:46px; }
    .cg-textarea{ padding:12px 14px; min-height:92px; resize:vertical; line-height:1.5; }
    .cg-input:focus, .cg-textarea:focus{ outline:none; border-color:var(--gold); box-shadow:0 0 0 4px rgba(47,111,237,.14); }
    .cg-input::placeholder, .cg-textarea::placeholder{ color:#5b6f8c; }
    .cg-hint{ font-size:11.5px; color:#7f8fac; margin-top:6px; }

    /* ---------- Pré-visualização da vitrine (capa + logo) ---------- */
    .cg-hero{ position:relative; border-radius:1.1rem; overflow:hidden; border:1px solid rgba(255,255,255,.08); background:var(--charcoal-3); }
    .cg-hero__capa{
        height:190px; background-color:#0e1523; background-size:cover; background-position:center; position:relative;
        display:flex; align-items:center; justify-content:center; color:#5b6f8c; font-size:13px;
    }
    .cg-hero__capa::after{ content:''; position:absolute; inset:0; background:linear-gradient(180deg, transparent 40%, rgba(0,0,0,.45)); pointer-events:none; }
    .cg-hero__capa-acoes{ position:absolute; right:12px; top:12px; display:flex; gap:8px; z-index:2; }
    .cg-hero__rodape{ display:flex; align-items:center; gap:16px; padding:0 20px 18px; margin-top:-38px; position:relative; z-index:2; }
    .cg-logo{
        width:84px; height:84px; border-radius:999px; flex-shrink:0; overflow:hidden; position:relative;
        background:linear-gradient(145deg,#17233a,#0b0f17); border:3px solid var(--charcoal-2);
        display:flex; align-items:center; justify-content:center; font-size:30px; font-weight:700; color:var(--gold-light);
        box-shadow:0 8px 22px -8px rgba(0,0,0,.7);
    }
    .cg-logo img{ width:100%; height:100%; object-fit:cover; display:block; }
    .cg-hero__nome{ font-size:18px; font-weight:600; color:var(--cream); margin:0; line-height:1.2; }
    .cg-hero__slogan{ font-size:12.5px; color:#9fb0cc; margin:3px 0 0; }
    .cg-hero__logo-acoes{ margin-left:auto; display:flex; gap:8px; flex-wrap:wrap; justify-content:flex-end; align-self:flex-end; }
    @media (max-width:560px){
        .cg-hero__capa{ height:140px; }
        .cg-hero__rodape{ flex-wrap:wrap; padding:0 16px 16px; }
        .cg-hero__logo-acoes{ margin-left:0; width:100%; justify-content:flex-start; }
    }

    /* ---------- Cor e tema ---------- */
    .cg-cores{ display:flex; flex-wrap:wrap; gap:10px; align-items:center; }
    .cg-cor{
        width:38px; height:38px; border-radius:999px; border:3px solid transparent; cursor:pointer; position:relative;
        box-shadow:0 0 0 1px rgba(255,255,255,.16); transition:transform .15s;
    }
    .cg-cor:hover{ transform:scale(1.08); }
    .cg-cor.is-ativa{ border-color:var(--charcoal-2); box-shadow:0 0 0 2px var(--cream); }
    .cg-cor-custom{ width:38px; height:38px; padding:0; border:none; background:none; cursor:pointer; border-radius:999px; }
    .cg-temas{ display:grid; grid-template-columns:1fr 1fr; gap:12px; }
    .cg-tema{
        border-radius:.9rem; padding:12px; cursor:pointer; border:1px solid rgba(255,255,255,.1); background:rgba(255,255,255,.02);
        display:flex; align-items:center; gap:12px; font-size:13px; color:var(--cream); transition:border-color .15s, background-color .15s;
    }
    .cg-tema input{ position:absolute; opacity:0; pointer-events:none; }
    .cg-tema.is-ativo{ border-color:var(--gold-light); background:rgba(47,111,237,.1); }
    .cg-tema__amostra{ width:44px; height:32px; border-radius:.5rem; border:1px solid rgba(255,255,255,.18); flex-shrink:0; }

    /* ---------- Chips selecionáveis ---------- */
    .cg-chips{ display:flex; flex-wrap:wrap; gap:10px; }
    .cg-chip{
        display:inline-flex; align-items:center; gap:9px; padding:10px 14px; border-radius:999px; cursor:pointer; font-size:13px;
        border:1px solid rgba(255,255,255,.1); background:rgba(255,255,255,.02); color:#b9c6dc; user-select:none;
        transition:border-color .15s, background-color .15s, color .15s;
    }
    .cg-chip input{ position:absolute; opacity:0; pointer-events:none; }
    .cg-chip:hover{ border-color:rgba(255,255,255,.22); }
    .cg-chip.is-ativo{ border-color:var(--gold-light); background:rgba(47,111,237,.14); color:var(--cream); }
    .cg-chip svg{ flex-shrink:0; }

    /* ---------- Horários ---------- */
    .cg-dia{
        display:grid; grid-template-columns:170px 1fr; gap:12px 16px; align-items:center;
        padding:14px 0; border-top:1px solid rgba(255,255,255,.06);
    }
    .cg-dia:first-of-type{ border-top:none; }
    .cg-dia__nome{ display:flex; align-items:center; gap:12px; font-size:14px; color:var(--cream); font-weight:500; }
    .cg-dia__turnos{ display:flex; flex-wrap:wrap; align-items:center; gap:10px; }
    .cg-dia__turnos .cg-input{ width:112px; height:40px; padding:0 10px; }
    .cg-dia__sep{ color:#7f8fac; font-size:12px; }
    .cg-dia__fechado{ font-size:13px; color:#7f8fac; }
    .cg-dia.is-fechado .cg-dia__turnos{ display:none; }
    .cg-dia:not(.is-fechado) .cg-dia__fechado{ display:none; }
    @media (max-width:640px){ .cg-dia{ grid-template-columns:1fr; } }

    /* ---------- Interruptor ---------- */
    .cg-switch{ position:relative; width:42px; height:24px; flex-shrink:0; display:inline-block; }
    .cg-switch input{ position:absolute; inset:0; opacity:0; cursor:pointer; margin:0; z-index:2; }
    .cg-switch span{ position:absolute; inset:0; border-radius:999px; background:rgba(255,255,255,.14); transition:background-color .2s; }
    .cg-switch span::after{ content:''; position:absolute; top:3px; left:3px; width:18px; height:18px; border-radius:999px; background:#fff; transition:transform .2s; }
    .cg-switch input:checked + span{ background:var(--gold); }
    .cg-switch input:checked + span::after{ transform:translateX(18px); }
    .cg-switch input:focus-visible + span{ box-shadow:0 0 0 3px rgba(91,147,247,.45); }

    /* ---------- Linhas de serviços / profissionais ---------- */
    .cg-lista{ display:flex; flex-direction:column; gap:12px; }
    .cg-item{
        display:grid; grid-template-columns:auto 56px minmax(0,1fr) minmax(0,1.4fr) auto; gap:14px; align-items:center;
        padding:14px; border-radius:1rem; border:1px solid rgba(255,255,255,.08); background:rgba(255,255,255,.02);
        transition:opacity .2s, border-color .2s;
    }
    .cg-item.is-oculto{ opacity:.55; }
    .cg-item--barbeiro{ grid-template-columns:auto 56px minmax(0,1fr) minmax(0,1fr) auto; }
    .cg-item__ordem{ display:flex; flex-direction:column; gap:4px; }
    .cg-item__ordem button{
        width:28px; height:24px; border-radius:.5rem; border:1px solid rgba(255,255,255,.1); background:rgba(255,255,255,.03);
        color:#8fa0bd; cursor:pointer; display:flex; align-items:center; justify-content:center; padding:0;
    }
    .cg-item__ordem button:hover:not(:disabled){ color:var(--gold-light); border-color:rgba(91,147,247,.5); }
    .cg-item__ordem button:disabled{ opacity:.3; cursor:default; }
    .cg-item__thumb{
        width:56px; height:56px; border-radius:.8rem; overflow:hidden; background:rgba(255,255,255,.05);
        display:flex; align-items:center; justify-content:center; color:#5b6f8c; font-weight:600; font-size:18px; flex-shrink:0;
    }
    .cg-item__thumb--redondo{ border-radius:999px; }
    .cg-item__thumb img{ width:100%; height:100%; object-fit:cover; display:block; }
    .cg-item__nome{ font-size:14px; font-weight:600; color:var(--cream); margin:0; }
    .cg-item__meta{ font-size:12px; color:#8fa0bd; margin:3px 0 0; }
    .cg-item__campos{ display:flex; flex-direction:column; gap:8px; min-width:0; }
    .cg-item__campos .cg-input{ height:38px; font-size:13px; }
    .cg-item__vis{ display:flex; align-items:center; gap:8px; font-size:12px; color:#8fa0bd; flex-direction:column; }
    @media (max-width:820px){
        .cg-item, .cg-item--barbeiro{ grid-template-columns:auto 52px minmax(0,1fr) auto; }
        .cg-item__campos{ grid-column:1 / -1; }
        .cg-item__vis{ flex-direction:row; }
    }

    /* ---------- Barra de salvar ---------- */
    .cg-barra{
        position:sticky; bottom:0; z-index:30; margin:8px -4px 0; padding:14px 16px; border-radius:1rem;
        display:flex; align-items:center; gap:14px; justify-content:space-between; flex-wrap:wrap;
        background:linear-gradient(180deg, var(--charcoal-2), var(--charcoal-3)); border:1px solid rgba(91,147,247,.25);
        box-shadow:0 -12px 32px -14px rgba(0,0,0,.7);
    }
    .cg-barra__estado{ font-size:13px; color:#8fa0bd; display:flex; align-items:center; gap:8px; }
    .cg-barra__estado i{ width:8px; height:8px; border-radius:999px; background:#4b5a75; display:inline-block; }
    .cg-barra.is-suja .cg-barra__estado{ color:#fbbf24; }
    .cg-barra.is-suja .cg-barra__estado i{ background:#f59e0b; }

    .cg-aviso{
        border-radius:.9rem; padding:12px 14px; font-size:13px; line-height:1.5; margin-bottom:18px;
        border:1px solid rgba(245,158,11,.4); background:rgba(245,158,11,.1); color:#fcd9a0;
    }

    /* ---------- Modal de recorte ---------- */
    .recorte-overlay{
        position:fixed; inset:0; z-index:200; display:none; align-items:center; justify-content:center;
        padding:16px; background:rgba(5,8,14,.82);
    }
    .recorte-overlay.aberto{ display:flex; }
    .recorte-modal{
        width:100%; max-width:560px; background:linear-gradient(180deg, var(--charcoal-2), var(--charcoal-3));
        border:1px solid rgba(61,126,201,.25); border-radius:20px; padding:20px; box-shadow:0 24px 60px -12px rgba(0,0,0,.8);
    }
    .recorte-area{ width:100%; height:min(62vw, 340px); background:#05080e; border-radius:12px; overflow:hidden; }
    .recorte-area img{ display:block; max-width:100%; }
    .recorte-modal.is-redondo .cropper-view-box, .recorte-modal.is-redondo .cropper-face{ border-radius:50%; }
    .recorte-modal .cropper-view-box{ outline:2px solid rgba(91,147,247,.9); }
    .recorte-zoom{ width:100%; accent-color:var(--gold-light); }

    html[data-theme="light"] .cg-label{ color:#475569; }
    html[data-theme="light"] .cg-chip{ color:#334155; }
    html[data-theme="light"] .cg-card__sub, html[data-theme="light"] .cg-hint, html[data-theme="light"] .cg-item__meta{ color:#64748b; }
    html[data-theme="light"] .cg-input, html[data-theme="light"] .cg-textarea{ background:#fff; border-color:#cbd5e1; }
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
            <p class="eyebrow uppercase mb-1" style="color:var(--gold-light); opacity:.75">Catálogo</p>
            <h1 class="display text-2xl sm:text-4xl text-[color:var(--cream)] leading-tight">Configurar catálogo</h1>
        </div>
    </header>

    <section class="p-5 sm:p-8">
    <div class="cg-wrap">

        <?php if (!$catalogoOk): ?>
            <div class="cg-aviso">
                O banco de dados ainda não tem as tabelas do catálogo e o sistema não conseguiu criá-las sozinho.
                Execute o script <strong>scriptBD/atualizacao_catalogo.sql</strong> e recarregue esta página.
            </div>
        <?php endif; ?>

        <!-- Link da vitrine -->
        <div class="panel-card cg-link">
            <div class="cg-link__url">
                <span style="color:#8fa0bd; white-space:nowrap;">Sua página de agendamento</span>
                <code id="cg-url"><?= $h($urlPublica) ?></code>
            </div>
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <button type="button" class="cg-btn cg-btn--sm" id="cg-copiar">Copiar link</button>
                <a class="cg-btn cg-btn--sm cg-btn--pri" href="/c/agendar" target="_blank" rel="noopener">Ver página</a>
            </div>
        </div>

        <div class="cg-tabs" role="tablist" id="cg-tabs">
            <button type="button" class="cg-tab is-ativa" data-aba="identidade" role="tab">Identidade</button>
            <button type="button" class="cg-tab" data-aba="contato" role="tab">Contato e local</button>
            <button type="button" class="cg-tab" data-aba="horarios" role="tab">Horário de atendimento</button>
            <button type="button" class="cg-tab" data-aba="servicos" role="tab">Serviços</button>
            <button type="button" class="cg-tab" data-aba="profissionais" role="tab">Profissionais</button>
        </div>

        <form id="cg-form" autocomplete="off" onsubmit="return false;">
            <?= csrf_field() ?>

            <!-- ================= IDENTIDADE ================= -->
            <div class="cg-painel is-ativo" data-painel="identidade">
                <div class="panel-card cg-card">
                    <h2>Capa e logo</h2>
                    <p class="cg-card__sub">É a primeira coisa que o cliente vê. Use uma foto da fachada ou do ambiente na capa e a logomarca no círculo.</p>

                    <div class="cg-hero">
                        <div class="cg-hero__capa" id="cg-capa" style="<?= $capaUrl ? "background-image:url('" . $h($capaUrl) . "')" : '' ?>">
                            <span id="cg-capa-vazia" <?= $capaUrl ? 'style="display:none"' : '' ?>>Sem capa — clique em “Alterar capa”</span>
                            <div class="cg-hero__capa-acoes">
                                <button type="button" class="cg-btn cg-btn--sm" data-img="capa">Alterar capa</button>
                                <button type="button" class="cg-btn cg-btn--sm cg-btn--perigo" data-img-remover="capa" id="cg-capa-remover" <?= $capaUrl ? '' : 'style="display:none"' ?>>Remover</button>
                            </div>
                        </div>
                        <div class="cg-hero__rodape">
                            <div class="cg-logo" id="cg-logo">
                                <img id="cg-logo-img" alt="Logo" <?= $logoUrl ? 'src="' . $h($logoUrl) . '"' : 'style="display:none"' ?>>
                                <span id="cg-logo-letra" <?= $logoUrl ? 'style="display:none"' : '' ?>><?= $h(mb_strtoupper(mb_substr($cfg['nome_exibicao'] ?: ($barbeiros[0]['nome'] ?? 'B'), 0, 1))) ?></span>
                            </div>
                            <div style="min-width:0;">
                                <p class="cg-hero__nome" id="cg-prev-nome"><?= $h($cfg['nome_exibicao'] ?: 'Nome da barbearia') ?></p>
                                <p class="cg-hero__slogan" id="cg-prev-slogan"><?= $h($cfg['slogan'] ?: 'Seu slogan aparece aqui') ?></p>
                            </div>
                            <div class="cg-hero__logo-acoes">
                                <button type="button" class="cg-btn cg-btn--sm" data-img="logo">Alterar logo</button>
                                <button type="button" class="cg-btn cg-btn--sm cg-btn--perigo" data-img-remover="logo" id="cg-logo-remover" <?= $logoUrl ? '' : 'style="display:none"' ?>>Remover</button>
                            </div>
                        </div>
                    </div>
                    <p class="cg-hint">As imagens são salvas assim que você confirma o recorte. Formatos: JPG, PNG ou WEBP, até 4MB.</p>
                </div>

                <div class="panel-card cg-card">
                    <h2>Apresentação</h2>
                    <p class="cg-card__sub">Textos que aparecem no topo e na lateral da página.</p>
                    <div class="cg-grid">
                        <div>
                            <label class="cg-label" for="nome_exibicao">Nome da barbearia</label>
                            <input class="cg-input" id="nome_exibicao" name="nome_exibicao" maxlength="120" placeholder="Ex.: Barbearia London" value="<?= $h($cfg['nome_exibicao']) ?>">
                        </div>
                        <div>
                            <label class="cg-label" for="slogan">Slogan <small>(opcional)</small></label>
                            <input class="cg-input" id="slogan" name="slogan" maxlength="160" placeholder="Ex.: Tradição e estilo em cada corte" value="<?= $h($cfg['slogan']) ?>">
                        </div>
                        <div class="cg-full">
                            <label class="cg-label" for="sobre">Sobre a barbearia <small>(opcional)</small></label>
                            <textarea class="cg-textarea" id="sobre" name="sobre" maxlength="1500" placeholder="Conte em poucas linhas o que torna sua barbearia especial."><?= $h($cfg['sobre']) ?></textarea>
                        </div>
                        <div class="cg-full">
                            <label class="cg-label" for="aviso">Aviso em destaque <small>(opcional)</small></label>
                            <input class="cg-input" id="aviso" name="aviso" maxlength="255" placeholder="Ex.: Fechado no feriado de 15/11. Voltamos dia 16!" value="<?= $h($cfg['aviso']) ?>">
                            <p class="cg-hint">Aparece como uma faixa no topo da página. Deixe em branco para não mostrar.</p>
                        </div>
                    </div>
                </div>

                <div class="panel-card cg-card">
                    <h2>Aparência</h2>
                    <p class="cg-card__sub">A cor de destaque pinta botões, seleções e detalhes da página.</p>
                    <div class="cg-grid">
                        <div>
                            <label class="cg-label">Cor de destaque</label>
                            <div class="cg-cores" id="cg-cores">
                                <?php foreach (CatalogoService::CORES_PRESET as $hex => $nomeCor): ?>
                                    <button type="button" class="cg-cor <?= strtolower($cfg['cor_destaque']) === $hex ? 'is-ativa' : '' ?>"
                                            data-cor="<?= $hex ?>" title="<?= $h($nomeCor) ?>" aria-label="<?= $h($nomeCor) ?>" style="background:<?= $hex ?>"></button>
                                <?php endforeach; ?>
                                <input type="color" class="cg-cor-custom" id="cg-cor-custom" value="<?= $h($cfg['cor_destaque']) ?>" title="Outra cor" aria-label="Outra cor">
                            </div>
                            <input type="hidden" id="cor_destaque" name="cor_destaque" value="<?= $h($cfg['cor_destaque']) ?>">
                        </div>
                        <div>
                            <label class="cg-label">Tema da página</label>
                            <div class="cg-temas" id="cg-temas">
                                <label class="cg-tema <?= $cfg['tema'] !== 'claro' ? 'is-ativo' : '' ?>">
                                    <input type="radio" name="tema" value="escuro" <?= $cfg['tema'] !== 'claro' ? 'checked' : '' ?>>
                                    <span class="cg-tema__amostra" style="background:linear-gradient(135deg,#0b0f17,#1a2236)"></span>Escuro
                                </label>
                                <label class="cg-tema <?= $cfg['tema'] === 'claro' ? 'is-ativo' : '' ?>">
                                    <input type="radio" name="tema" value="claro" <?= $cfg['tema'] === 'claro' ? 'checked' : '' ?>>
                                    <span class="cg-tema__amostra" style="background:linear-gradient(135deg,#ffffff,#eef2f8)"></span>Claro
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ================= CONTATO E LOCAL ================= -->
            <div class="cg-painel" data-painel="contato">
                <div class="panel-card cg-card">
                    <h2>Localização e contato</h2>
                    <p class="cg-card__sub">Só aparece na página o que você preencher.</p>
                    <div class="cg-grid">
                        <div class="cg-full">
                            <label class="cg-label" for="endereco">Endereço</label>
                            <input class="cg-input" id="endereco" name="endereco" maxlength="255" placeholder="Rua, número, bairro — cidade/UF" value="<?= $h($cfg['endereco']) ?>">
                        </div>
                        <div class="cg-full">
                            <label class="cg-label" for="mapa_url">Link do mapa <small>(Google Maps, Waze…)</small></label>
                            <input class="cg-input" id="mapa_url" name="mapa_url" maxlength="500" inputmode="url" placeholder="https://maps.google.com/..." value="<?= $h($cfg['mapa_url']) ?>">
                            <p class="cg-hint">Com o link, o endereço vira um botão “Como chegar”.</p>
                        </div>
                        <div>
                            <label class="cg-label" for="whatsapp">WhatsApp</label>
                            <input class="cg-input" id="whatsapp" name="whatsapp" inputmode="tel" placeholder="(27) 99999-9999" value="<?= $h($cfg['whatsapp']) ?>">
                        </div>
                        <div>
                            <label class="cg-label" for="telefone">Telefone fixo <small>(opcional)</small></label>
                            <input class="cg-input" id="telefone" name="telefone" maxlength="30" inputmode="tel" placeholder="(27) 3333-3333" value="<?= $h($cfg['telefone']) ?>">
                        </div>
                        <div>
                            <label class="cg-label" for="instagram">Instagram</label>
                            <input class="cg-input" id="instagram" name="instagram" maxlength="80" placeholder="@suabarbearia" value="<?= $h($cfg['instagram'] ? '@' . $cfg['instagram'] : '') ?>">
                        </div>
                        <div>
                            <label class="cg-label" for="facebook">Facebook <small>(link)</small></label>
                            <input class="cg-input" id="facebook" name="facebook" maxlength="160" inputmode="url" placeholder="https://facebook.com/..." value="<?= $h($cfg['facebook']) ?>">
                        </div>
                    </div>
                </div>

                <div class="panel-card cg-card">
                    <h2>Formas de pagamento</h2>
                    <p class="cg-card__sub">Informativo — mostra ao cliente o que você aceita.</p>
                    <div class="cg-chips">
                        <?php foreach (CatalogoService::FORMAS_PAGAMENTO as $chave => $rotulo): $on = in_array($chave, $cfg['formas_pagamento'], true); ?>
                            <label class="cg-chip <?= $on ? 'is-ativo' : '' ?>">
                                <input type="checkbox" name="formas_pagamento[]" value="<?= $chave ?>" <?= $on ? 'checked' : '' ?>><?= $h($rotulo) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="panel-card cg-card">
                    <h2>Comodidades</h2>
                    <p class="cg-card__sub">Marque o que sua barbearia oferece.</p>
                    <div class="cg-chips">
                        <?php foreach (CatalogoService::COMODIDADES as $chave => $rotulo): $on = in_array($chave, $cfg['comodidades'], true); ?>
                            <label class="cg-chip <?= $on ? 'is-ativo' : '' ?>">
                                <input type="checkbox" name="comodidades[]" value="<?= $chave ?>" <?= $on ? 'checked' : '' ?>>
                                <?= CatalogoService::iconeComodidade($chave) ?><?= $h($rotulo) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- ================= HORÁRIOS ================= -->
            <div class="cg-painel" data-painel="horarios">
                <div class="panel-card cg-card">
                    <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; align-items:flex-start; margin-bottom:6px;">
                        <div>
                            <h2>Horário de atendimento</h2>
                            <p class="cg-card__sub" style="margin-bottom:0">Informativo para o cliente. Os horários disponíveis para agendar continuam vindo da agenda de cada profissional.</p>
                        </div>
                        <button type="button" class="cg-btn cg-btn--sm" id="cg-copiar-horario">Copiar o 1º dia aberto para os outros</button>
                    </div>
                    <div id="cg-dias">
                        <?php foreach (CatalogoService::DIAS_ORDEM as $dia):
                            $d = $cfg['horarios'][(string) $dia] ?? ['aberto' => false, 'turnos' => []];
                            $t = $d['turnos'] ?? [];
                        ?>
                            <div class="cg-dia <?= !empty($d['aberto']) ? '' : 'is-fechado' ?>" data-dia="<?= $dia ?>">
                                <div class="cg-dia__nome">
                                    <label class="cg-switch"><input type="checkbox" name="horarios[<?= $dia ?>][aberto]" value="1" <?= !empty($d['aberto']) ? 'checked' : '' ?>><span></span></label>
                                    <?= $h(CatalogoService::DIAS_NOMES[$dia]) ?>
                                </div>
                                <div>
                                    <span class="cg-dia__fechado">Fechado</span>
                                    <div class="cg-dia__turnos">
                                        <input type="time" class="cg-input" name="horarios[<?= $dia ?>][t1_ini]" value="<?= $h($t[0][0] ?? '') ?>" aria-label="Abre">
                                        <span class="cg-dia__sep">às</span>
                                        <input type="time" class="cg-input" name="horarios[<?= $dia ?>][t1_fim]" value="<?= $h($t[0][1] ?? '') ?>" aria-label="Fecha">
                                        <span class="cg-dia__sep">e</span>
                                        <input type="time" class="cg-input" name="horarios[<?= $dia ?>][t2_ini]" value="<?= $h($t[1][0] ?? '') ?>" aria-label="Volta do intervalo">
                                        <span class="cg-dia__sep">às</span>
                                        <input type="time" class="cg-input" name="horarios[<?= $dia ?>][t2_fim]" value="<?= $h($t[1][1] ?? '') ?>" aria-label="Fecha à tarde">
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="cg-hint">O segundo período (depois do intervalo de almoço) é opcional. Se nenhum dia estiver aberto, o bloco de horários não aparece na página.</p>
                </div>
            </div>

            <!-- ================= SERVIÇOS ================= -->
            <div class="cg-painel" data-painel="servicos">
                <div class="panel-card cg-card">
                    <h2>Serviços do catálogo</h2>
                    <p class="cg-card__sub">
                        Escolha o que aparece na página, a ordem, a categoria e uma descrição curta. Nome, preço, duração e foto são editados em
                        <a href="/servicos" style="color:var(--gold-light)">Serviços</a>; serviços inativos não aparecem.
                    </p>
                    <?php if (!$servicos): ?>
                        <p class="cg-hint">Nenhum serviço ativo cadastrado ainda.</p>
                    <?php endif; ?>
                    <div class="cg-lista" id="cg-servicos">
                        <?php foreach ($servicos as $s): ?>
                            <div class="cg-item <?= $s['visivel'] ? '' : 'is-oculto' ?>" data-id="<?= $s['id'] ?>">
                                <div class="cg-item__ordem">
                                    <button type="button" data-mover="-1" aria-label="Subir"><svg width="12" height="12" viewBox="0 0 24 24" fill="none"><path d="M6 15l6-6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
                                    <button type="button" data-mover="1" aria-label="Descer"><svg width="12" height="12" viewBox="0 0 24 24" fill="none"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
                                </div>
                                <div class="cg-item__thumb">
                                    <?php if ($s['foto']): ?><img src="<?= $h($s['foto']) ?>" alt=""><?php else: ?>
                                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"><rect x="3.5" y="5.5" width="17" height="13" rx="2"/><circle cx="9" cy="10.5" r="1.5"/><path d="M4 17l5-4.5 3.5 3 3-2.5L20 16"/></svg>
                                    <?php endif; ?>
                                </div>
                                <div style="min-width:0">
                                    <p class="cg-item__nome"><?= $h($s['nome']) ?></p>
                                    <p class="cg-item__meta">R$ <?= number_format($s['valor'], 2, ',', '.') ?> · <?= $s['duracao'] ?> min</p>
                                </div>
                                <div class="cg-item__campos">
                                    <input class="cg-input" name="servicos[<?= $s['id'] ?>][categoria]" list="cg-categorias" maxlength="60" placeholder="Categoria (ex.: Cabelo, Barba, Combos)" value="<?= $h($s['categoria']) ?>">
                                    <input class="cg-input" name="servicos[<?= $s['id'] ?>][descricao]" maxlength="255" placeholder="Descrição curta (opcional)" value="<?= $h($s['descricao']) ?>">
                                    <input type="hidden" name="servicos[<?= $s['id'] ?>][ordem]" value="<?= $s['ordem'] ?>" data-ordem>
                                </div>
                                <div class="cg-item__vis">
                                    <label class="cg-switch"><input type="checkbox" name="servicos[<?= $s['id'] ?>][visivel]" value="1" <?= $s['visivel'] ? 'checked' : '' ?> data-visivel><span></span></label>
                                    <span>Exibir</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <datalist id="cg-categorias">
                        <?php foreach ($categoriasExistentes as $c): ?><option value="<?= $h($c) ?>"><?php endforeach; ?>
                    </datalist>
                </div>
            </div>

            <!-- ================= PROFISSIONAIS ================= -->
            <div class="cg-painel" data-painel="profissionais">
                <div class="panel-card cg-card">
                    <h2>Profissionais</h2>
                    <p class="cg-card__sub">
                        Quem aparece na escolha do profissional, em que ordem e com qual cargo. A foto vem do Perfil de cada barbeiro.
                        Ocultar um profissional não apaga a agenda dele — só tira da vitrine.
                    </p>
                    <div class="cg-lista" id="cg-barbeiros">
                        <?php foreach ($barbeiros as $b): ?>
                            <div class="cg-item cg-item--barbeiro <?= $b['visivel'] ? '' : 'is-oculto' ?>" data-id="<?= $b['id'] ?>">
                                <div class="cg-item__ordem">
                                    <button type="button" data-mover="-1" aria-label="Subir"><svg width="12" height="12" viewBox="0 0 24 24" fill="none"><path d="M6 15l6-6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
                                    <button type="button" data-mover="1" aria-label="Descer"><svg width="12" height="12" viewBox="0 0 24 24" fill="none"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
                                </div>
                                <div class="cg-item__thumb cg-item__thumb--redondo">
                                    <?php if ($b['foto']): ?><img src="<?= $h($b['foto']) ?>" alt=""><?php else: ?><?= $h(mb_strtoupper(mb_substr($b['nome'], 0, 1))) ?><?php endif; ?>
                                </div>
                                <div style="min-width:0">
                                    <p class="cg-item__nome"><?= $h($b['nome']) ?></p>
                                    <p class="cg-item__meta">Agenda própria</p>
                                </div>
                                <div class="cg-item__campos">
                                    <input class="cg-input" name="barbeiros[<?= $b['id'] ?>][cargo]" maxlength="60" placeholder="Cargo (padrão: Barbeiro)" value="<?= $h($b['cargo']) ?>">
                                    <input type="hidden" name="barbeiros[<?= $b['id'] ?>][ordem]" value="<?= $b['ordem'] ?>" data-ordem>
                                </div>
                                <div class="cg-item__vis">
                                    <label class="cg-switch"><input type="checkbox" name="barbeiros[<?= $b['id'] ?>][visivel]" value="1" <?= $b['visivel'] ? 'checked' : '' ?> data-visivel><span></span></label>
                                    <span>Exibir</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="cg-barra" id="cg-barra">
                <div class="cg-barra__estado"><i></i><span id="cg-estado">Tudo salvo</span></div>
                <div style="display:flex; gap:8px;">
                    <a class="cg-btn" href="/c/agendar" target="_blank" rel="noopener">Ver página</a>
                    <button type="button" class="cg-btn cg-btn--pri" id="cg-salvar">Salvar alterações</button>
                </div>
            </div>
        </form>
    </div>
    </section>
</main>

<!-- ==================== MODAL: AJUSTAR IMAGEM ==================== -->
<div id="recorte-overlay" class="recorte-overlay" role="dialog" aria-modal="true" aria-labelledby="recorte-titulo">
    <div class="recorte-modal" id="recorte-modal">
        <p id="recorte-titulo" style="font-size:14px; font-weight:600; color:var(--cream); margin:0 0 4px;">Ajustar imagem</p>
        <p style="font-size:12px; color:#8fa0bd; margin:0 0 12px;">Arraste para posicionar e use o controle para aproximar ou afastar.</p>
        <div class="recorte-area"><img id="recorte-img" alt="Pré-visualização"></div>
        <div style="display:flex; align-items:center; gap:12px; margin-top:16px;">
            <span style="font-size:12px; color:#8fa0bd">&minus;</span>
            <input type="range" id="recorte-zoom" class="recorte-zoom" min="0" max="1" step="0.01" value="0" aria-label="Zoom">
            <span style="font-size:12px; color:#8fa0bd">+</span>
        </div>
        <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:18px;">
            <button type="button" class="cg-btn" id="recorte-cancelar">Cancelar</button>
            <button type="button" class="cg-btn cg-btn--pri" id="recorte-salvar">Usar imagem</button>
        </div>
    </div>
</div>
<input type="file" id="cg-picker" accept="image/jpeg,image/png,image/webp" style="display:none">

<script>
(function () {
    const form   = document.getElementById('cg-form');
    const barra  = document.getElementById('cg-barra');
    const estado = document.getElementById('cg-estado');
    const csrf   = form.querySelector('input[name="_csrf"]').value;
    let sujo = false;

    function marcarSujo(v) {
        sujo = v;
        barra.classList.toggle('is-suja', v);
        estado.textContent = v ? 'Alterações não salvas' : 'Tudo salvo';
    }
    form.addEventListener('input', function () { marcarSujo(true); });
    form.addEventListener('change', function () { marcarSujo(true); });
    window.addEventListener('beforeunload', function (e) { if (sujo) { e.preventDefault(); e.returnValue = ''; } });

    // ---------- Abas ----------
    document.querySelectorAll('.cg-tab').forEach(function (aba) {
        aba.addEventListener('click', function () {
            document.querySelectorAll('.cg-tab').forEach(function (a) { a.classList.toggle('is-ativa', a === aba); });
            document.querySelectorAll('.cg-painel').forEach(function (p) { p.classList.toggle('is-ativo', p.dataset.painel === aba.dataset.aba); });
            try { history.replaceState(null, '', '#' + aba.dataset.aba); } catch (e) {}
        });
    });
    const hash = (location.hash || '').replace('#', '');
    const abaInicial = document.querySelector('.cg-tab[data-aba="' + hash + '"]');
    if (abaInicial) abaInicial.click();

    // ---------- Copiar link ----------
    document.getElementById('cg-copiar').addEventListener('click', function () {
        const url = document.getElementById('cg-url').textContent.trim();
        const ok = function () { toast('Link copiado.', 'sucesso'); };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(url).then(ok, function () { toast('Não foi possível copiar. Selecione e copie manualmente.', 'erro'); });
        } else {
            const t = document.createElement('textarea'); t.value = url; document.body.appendChild(t); t.select();
            try { document.execCommand('copy'); ok(); } catch (e) { toast('Não foi possível copiar.', 'erro'); }
            t.remove();
        }
    });

    // ---------- Pré-visualização de nome/slogan ----------
    const inNome = document.getElementById('nome_exibicao'), inSlogan = document.getElementById('slogan');
    inNome.addEventListener('input', function () { document.getElementById('cg-prev-nome').textContent = this.value.trim() || 'Nome da barbearia'; });
    inSlogan.addEventListener('input', function () { document.getElementById('cg-prev-slogan').textContent = this.value.trim() || 'Seu slogan aparece aqui'; });

    // ---------- Cor e tema ----------
    const inCor = document.getElementById('cor_destaque');
    function setCor(hex) {
        inCor.value = hex;
        document.querySelectorAll('.cg-cor').forEach(function (b) { b.classList.toggle('is-ativa', b.dataset.cor === hex); });
        document.getElementById('cg-cor-custom').value = hex;
        marcarSujo(true);
    }
    document.querySelectorAll('.cg-cor').forEach(function (b) { b.addEventListener('click', function () { setCor(b.dataset.cor); }); });
    document.getElementById('cg-cor-custom').addEventListener('input', function () { setCor(this.value.toLowerCase()); });
    document.querySelectorAll('#cg-temas .cg-tema').forEach(function (l) {
        l.querySelector('input').addEventListener('change', function () {
            document.querySelectorAll('#cg-temas .cg-tema').forEach(function (x) { x.classList.toggle('is-ativo', x === l); });
        });
    });

    // ---------- Chips ----------
    document.querySelectorAll('.cg-chip input').forEach(function (i) {
        i.addEventListener('change', function () { i.closest('.cg-chip').classList.toggle('is-ativo', i.checked); });
    });

    // ---------- Horários ----------
    document.querySelectorAll('.cg-dia').forEach(function (linha) {
        linha.querySelector('.cg-switch input').addEventListener('change', function () {
            linha.classList.toggle('is-fechado', !this.checked);
            if (this.checked && !linha.querySelector('[name$="[t1_ini]"]').value) {
                linha.querySelector('[name$="[t1_ini]"]').value = '09:00';
                linha.querySelector('[name$="[t1_fim]"]').value = '18:00';
            }
        });
    });
    document.getElementById('cg-copiar-horario').addEventListener('click', function () {
        const linhas = Array.from(document.querySelectorAll('.cg-dia'));
        const origem = linhas.find(function (l) { return l.querySelector('.cg-switch input').checked; });
        if (!origem) { toast('Marque e preencha ao menos um dia primeiro.', 'erro'); return; }
        const campos = ['t1_ini', 't1_fim', 't2_ini', 't2_fim'];
        linhas.forEach(function (l) {
            if (l === origem) return;
            l.querySelector('.cg-switch input').checked = true;
            l.classList.remove('is-fechado');
            campos.forEach(function (c) { l.querySelector('[name$="[' + c + ']"]').value = origem.querySelector('[name$="[' + c + ']"]').value; });
        });
        marcarSujo(true);
        toast('Horário copiado para os outros dias. Ajuste o que for diferente.', 'sucesso');
    });

    // ---------- Listas (ordem e visibilidade) ----------
    function atualizarSetas(lista) {
        const itens = Array.from(lista.children);
        itens.forEach(function (it, i) {
            it.querySelector('[data-mover="-1"]').disabled = i === 0;
            it.querySelector('[data-mover="1"]').disabled = i === itens.length - 1;
            it.querySelector('[data-ordem]').value = i + 1;
        });
    }
    ['cg-servicos', 'cg-barbeiros'].forEach(function (id) {
        const lista = document.getElementById(id);
        if (!lista) return;
        atualizarSetas(lista);
        lista.addEventListener('click', function (e) {
            const b = e.target.closest('[data-mover]');
            if (!b || b.disabled) return;
            const item = b.closest('.cg-item');
            if (b.dataset.mover === '-1' && item.previousElementSibling) lista.insertBefore(item, item.previousElementSibling);
            if (b.dataset.mover === '1' && item.nextElementSibling) lista.insertBefore(item.nextElementSibling, item);
            atualizarSetas(lista);
            marcarSujo(true);
        });
        lista.addEventListener('change', function (e) {
            if (e.target.matches('[data-visivel]')) e.target.closest('.cg-item').classList.toggle('is-oculto', !e.target.checked);
        });
    });
    // Ao carregar, a ordem salva (ou alfabética) vira 1..N sem contar como alteração do usuário.
    marcarSujo(false);

    // ---------- Salvar ----------
    const btnSalvar = document.getElementById('cg-salvar');
    btnSalvar.addEventListener('click', async function () {
        btnSalvar.disabled = true;
        btnSalvar.textContent = 'Salvando…';
        try {
            const resp = await fetch('/Catalogo/scripts/catalogo_salvar.php', { method: 'POST', body: new FormData(form) });
            const dados = await resp.json();
            if (!dados.ok) {
                toast(dados.erro || 'Não foi possível salvar.', 'erro');
            } else {
                marcarSujo(false);
                toast('Catálogo salvo! A página já está atualizada.', 'sucesso');
            }
        } catch (e) {
            toast('Erro de conexão. Tente novamente.', 'erro');
        }
        btnSalvar.disabled = false;
        btnSalvar.textContent = 'Salvar alterações';
    });

    // ---------- Imagens (logo / capa) com recorte ----------
    const CONFIG_IMG = {
        logo: { aspecto: 1,       largura: 512,  altura: 512, mime: 'image/png',  redondo: true,  titulo: 'Ajustar logo' },
        capa: { aspecto: 8 / 3,   largura: 1600, altura: 600, mime: 'image/jpeg', redondo: false, titulo: 'Ajustar capa' }
    };
    const overlay = document.getElementById('recorte-overlay');
    const modal   = document.getElementById('recorte-modal');
    const imgEl   = document.getElementById('recorte-img');
    const zoomEl  = document.getElementById('recorte-zoom');
    const picker  = document.getElementById('cg-picker');
    const btnUsar = document.getElementById('recorte-salvar');
    let cropper = null, urlTemp = null, baseRatio = 0, tipoAtual = null;

    function fecharRecorte() {
        overlay.classList.remove('aberto');
        if (cropper) { cropper.destroy(); cropper = null; }
        baseRatio = 0;
        if (urlTemp) { URL.revokeObjectURL(urlTemp); urlTemp = null; }
        imgEl.removeAttribute('src');
        btnUsar.disabled = false; btnUsar.textContent = 'Usar imagem';
    }

    document.querySelectorAll('[data-img]').forEach(function (b) {
        b.addEventListener('click', function () { tipoAtual = b.dataset.img; picker.value = ''; picker.click(); });
    });

    picker.addEventListener('change', function () {
        const arquivo = this.files[0];
        if (!arquivo || !tipoAtual) return;
        if (!/^image\/(jpeg|png|webp)$/.test(arquivo.type)) { toast('Formato inválido. Envie uma imagem JPG, PNG ou WEBP.', 'erro'); return; }
        if (typeof Cropper === 'undefined') { toast('Não foi possível carregar o editor de imagem. Recarregue a página.', 'erro'); return; }

        const c = CONFIG_IMG[tipoAtual];
        document.getElementById('recorte-titulo').textContent = c.titulo;
        modal.classList.toggle('is-redondo', c.redondo);
        urlTemp = URL.createObjectURL(arquivo);
        imgEl.src = urlTemp;
        overlay.classList.add('aberto');
        if (cropper) cropper.destroy();
        cropper = new Cropper(imgEl, {
            aspectRatio: c.aspecto, viewMode: 1, dragMode: 'move', autoCropArea: 0.95, background: false, guides: false,
            center: false, highlight: false, cropBoxMovable: false, cropBoxResizable: false, toggleDragModeOnDblclick: false,
            ready: function () { zoomEl.value = 0; baseRatio = cropper.getImageData().width / cropper.getImageData().naturalWidth; },
            zoom: function (e) { if (baseRatio) zoomEl.value = Math.min(1, Math.max(0, (e.detail.ratio / baseRatio - 1) / 3)); }
        });
    });

    zoomEl.addEventListener('input', function () { if (cropper && baseRatio) cropper.zoomTo(baseRatio * (1 + parseFloat(this.value) * 3)); });
    document.getElementById('recorte-cancelar').addEventListener('click', fecharRecorte);
    overlay.addEventListener('click', function (e) { if (e.target === overlay) fecharRecorte(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && overlay.classList.contains('aberto')) fecharRecorte(); });

    function aplicarImagem(tipo, url) {
        if (tipo === 'capa') {
            document.getElementById('cg-capa').style.backgroundImage = url ? "url('" + url + "')" : '';
            document.getElementById('cg-capa-vazia').style.display = url ? 'none' : '';
            document.getElementById('cg-capa-remover').style.display = url ? '' : 'none';
        } else {
            const img = document.getElementById('cg-logo-img');
            if (url) { img.src = url; } else { img.removeAttribute('src'); }
            img.style.display = url ? '' : 'none';
            document.getElementById('cg-logo-letra').style.display = url ? 'none' : '';
            document.getElementById('cg-logo-remover').style.display = url ? '' : 'none';
        }
    }

    async function enviarImagem(tipo, corpo) {
        corpo.set('tipo', tipo);
        corpo.set('_csrf', csrf);
        const resp = await fetch('/Catalogo/scripts/catalogo_imagem.php', { method: 'POST', body: corpo });
        return resp.json();
    }

    btnUsar.addEventListener('click', function () {
        if (!cropper) return;
        const c = CONFIG_IMG[tipoAtual], tipo = tipoAtual;
        btnUsar.disabled = true; btnUsar.textContent = 'Enviando…';
        const canvas = cropper.getCroppedCanvas({
            width: c.largura, height: c.altura, imageSmoothingQuality: 'high',
            fillColor: c.mime === 'image/jpeg' ? '#ffffff' : 'transparent'
        });
        canvas.toBlob(async function (blob) {
            if (!blob) { toast('Não foi possível processar a imagem.', 'erro'); btnUsar.disabled = false; btnUsar.textContent = 'Usar imagem'; return; }
            try {
                const corpo = new FormData();
                corpo.set('imagem', blob, tipo + (c.mime === 'image/png' ? '.png' : '.jpg'));
                const dados = await enviarImagem(tipo, corpo);
                if (!dados.ok) { toast(dados.erro || 'Não foi possível enviar a imagem.', 'erro'); btnUsar.disabled = false; btnUsar.textContent = 'Usar imagem'; return; }
                aplicarImagem(tipo, dados.url + '?v=' + Date.now());
                fecharRecorte();
                toast((tipo === 'logo' ? 'Logo' : 'Capa') + ' atualizada.', 'sucesso');
            } catch (e) {
                toast('Erro de conexão. Tente novamente.', 'erro');
                btnUsar.disabled = false; btnUsar.textContent = 'Usar imagem';
            }
        }, c.mime, 0.9);
    });

    document.querySelectorAll('[data-img-remover]').forEach(function (b) {
        b.addEventListener('click', async function () {
            const tipo = b.dataset.imgRemover;
            if (!window.Swal) { if (!confirm('Remover esta imagem?')) return; }
            else {
                const r = await Swal.fire({ title: 'Remover ' + (tipo === 'logo' ? 'o logo' : 'a capa') + '?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Sim, remover', cancelButtonText: 'Cancelar' });
                if (!r.isConfirmed) return;
            }
            try {
                const corpo = new FormData(); corpo.set('acao', 'remover');
                const dados = await enviarImagem(tipo, corpo);
                if (!dados.ok) { toast(dados.erro || 'Não foi possível remover.', 'erro'); return; }
                aplicarImagem(tipo, null);
                toast('Imagem removida.', 'sucesso');
            } catch (e) { toast('Erro de conexão. Tente novamente.', 'erro'); }
        });
    });
})();
</script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

</body>
</html>

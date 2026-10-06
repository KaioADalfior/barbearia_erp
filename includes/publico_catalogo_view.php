<?php
/**
 * includes/publico_catalogo_view.php
 *
 * Tela pública de agendamento (vitrine da barbearia). Usada pelas duas URLs
 * que já existiam, sem trocar nenhuma delas:
 *   - /c/agendar            -> Publico/paginas/escolher_barbeiro.php (link geral)
 *   - /c/agendar/<token>    -> Publico/paginas/agendar.php           (link pessoal)
 *
 * Só INTERFACE. Toda a regra de agendamento continua nos endpoints de
 * Publico/scripts/* (disponibilidade do mês, horários do dia, confirmação),
 * chamados aqui exatamente como antes, com o link do barbeiro escolhido.
 *
 * Variáveis esperadas (definidas pelas páginas que incluem este arquivo):
 *   $pdo, $tokenFixo (?string — link pessoal; null no link geral),
 *   $barbeiroFixo (?array — resultado de PublicoTokenService::resolverBarbeiro)
 */

if (!isset($pdo) || !array_key_exists('tokenFixo', get_defined_vars())) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/CatalogoService.php';
require_once __DIR__ . '/PublicoTokenService.php';
require_once __DIR__ . '/csrf.php';

// A página carrega o token CSRF da sessão do visitante: nunca pode ser guardada
// em cache de proxy/CDN/navegador (senão o cliente envia um token velho).
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}

$h = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$cfg      = CatalogoService::config($pdo);
$servicos = CatalogoService::servicos($pdo);

// ---------------------------------------------------------------- profissionais
$barbeiros = [];
if ($tokenFixo !== null && $barbeiroFixo !== null) {
    // Link pessoal: agenda só com esse profissional (mesmo que ele esteja oculto na vitrine geral).
    $info = null;
    foreach (CatalogoService::barbeiros($pdo, true) as $b) {
        if ($b['id'] === (int) $barbeiroFixo['id_barbeiro']) { $info = $b; break; }
    }
    $barbeiros[] = [
        'nome'  => $barbeiroFixo['nome'],
        'foto'  => $info['foto'] ?? null,
        'cargo' => ($info['cargo'] ?? '') !== '' ? $info['cargo'] : 'Barbeiro',
        'token' => $tokenFixo,
    ];
} else {
    foreach (CatalogoService::barbeiros($pdo) as $b) {
        $token = PublicoTokenService::garantirLink($pdo, $b['id'], $b['nome'], $b['link']);
        $barbeiros[] = [
            'nome'  => $b['nome'],
            'foto'  => $b['foto'],
            'cargo' => $b['cargo'] !== '' ? $b['cargo'] : 'Barbeiro',
            'token' => $token,
        ];
    }
}

// ----------------------------------------------------------------- identidade
$nomeLoja = $cfg['nome_exibicao'] !== '' ? $cfg['nome_exibicao'] : ($tokenFixo !== null && $barbeiroFixo ? $barbeiroFixo['nome'] : 'Agende seu horário');
$logoUrl  = CatalogoService::imagemUrl($cfg['logo']);
$capaUrl  = CatalogoService::imagemUrl($cfg['capa']);
$cor      = preg_match('/^#[0-9a-f]{6}$/i', (string) $cfg['cor_destaque']) ? strtolower($cfg['cor_destaque']) : '#2f6fed';
[$cr, $cg, $cb] = CatalogoService::rgb($cor);
$corTexto = CatalogoService::corTextoSobre($cor);
$claro    = $cfg['tema'] === 'claro';

// ------------------------------------------------------- horário de atendimento
$horarios = $cfg['horarios'];
$diaHoje  = (int) date('w');
$agoraMin = ((int) date('G')) * 60 + (int) date('i');
$minutos  = static fn(string $hhmm): int => ((int) substr($hhmm, 0, 2)) * 60 + (int) substr($hhmm, 3, 2);

$statusLoja = null; // ['aberto' => bool, 'texto' => string]
if ($horarios) {
    $hoje = $horarios[(string) $diaHoje] ?? ['aberto' => false, 'turnos' => []];
    $statusLoja = ['aberto' => false, 'texto' => 'Fechado hoje'];
    if (!empty($hoje['aberto'])) {
        $proximo = null;
        foreach ($hoje['turnos'] as [$ini, $fim]) {
            if ($agoraMin >= $minutos($ini) && $agoraMin < $minutos($fim)) {
                $statusLoja = ['aberto' => true, 'texto' => 'Aberto agora · até ' . $fim];
                $proximo = false;
                break;
            }
            if ($agoraMin < $minutos($ini) && $proximo === null) {
                $proximo = $ini;
            }
        }
        if ($statusLoja['aberto'] === false) {
            $statusLoja['texto'] = $proximo ? 'Fechado agora · abre às ' . $proximo : 'Fechado agora';
        }
    }
}

// ----------------------------------------------------------------- contato
$wa        = $cfg['whatsapp'];
$waDigitos = $wa !== '' ? (strlen($wa) <= 11 ? '55' . $wa : $wa) : '';
$fmtTelefone = static function (string $d): string {
    if (strlen($d) === 13 && str_starts_with($d, '55')) { $d = substr($d, 2); }
    if (strlen($d) === 11) { return '(' . substr($d, 0, 2) . ') ' . substr($d, 2, 5) . '-' . substr($d, 7); }
    if (strlen($d) === 10) { return '(' . substr($d, 0, 2) . ') ' . substr($d, 2, 4) . '-' . substr($d, 6); }
    return $d;
};

$categorias = array_values(array_unique(array_filter(array_map(fn($s) => $s['categoria'], $servicos))));
$haCategorias = count($categorias) > 0;
$haOutros = $haCategorias && count(array_filter($servicos, fn($s) => $s['categoria'] === '')) > 0;

$temLateral = $cfg['endereco'] !== '' || $horarios || $cfg['formas_pagamento'] || $cfg['comodidades']
    || $wa !== '' || $cfg['telefone'] !== '' || $cfg['instagram'] !== '' || $cfg['facebook'] !== '';

$appJs = [
    'token'      => $tokenFixo,
    'csrf'       => csrf_token(),
    'hoje'       => date('Y-m-d'),
    'loja'       => $nomeLoja,
    'whatsapp'   => $waDigitos,
    'servicos'   => array_map(fn($s) => [
        'id' => $s['id'], 'nome' => $s['nome'], 'duracao' => $s['duracao'], 'valor' => $s['valor'],
        'foto' => $s['foto'], 'descricao' => $s['descricao'],
    ], $servicos),
    'barbeiros'  => $barbeiros,
];
$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

$tesoura = '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true"><path d="M6.5 5.5a2.5 2.5 0 1 1 3.4 3.4L18 17.5"/><path d="M6.5 18.5a2.5 2.5 0 1 0 3.4-3.4L18 6.5"/></svg>';
?>
<!DOCTYPE html>
<html lang="pt-br" data-tema="<?= $claro ? 'claro' : 'escuro' ?>">
<head>
<meta charset="UTF-8">
<script>document.documentElement.className+=" js";</script>
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= $h($nomeLoja) ?> — Agendamento online</title>
<meta name="description" content="<?= $h($cfg['slogan'] !== '' ? $cfg['slogan'] : 'Agende seu horário online em poucos passos.') ?>">
<meta name="theme-color" content="<?= $claro ? '#f5f7fb' : '#0b0f17' ?>">
<meta property="og:title" content="<?= $h($nomeLoja) ?>">
<meta property="og:description" content="Agende seu horário online em poucos passos.">
<?php if ($capaUrl || $logoUrl): ?><meta property="og:image" content="<?= $h($capaUrl ?: $logoUrl) ?>"><?php endif; ?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
:root{
    --acc:<?= $cor ?>;
    --acc-rgb:<?= "$cr,$cg,$cb" ?>;
    --acc-on:<?= $corTexto ?>;
    --ok:#22c55e;
    --warn:#f59e0b;
    --err:#ef4444;
    --raio:18px;
    --sombra:0 18px 40px -22px rgba(0,0,0,.6);
}
html[data-tema="escuro"]{
    --bg:#0b0f17; --bg-2:#0e1420; --surface:#121a2a; --surface-2:#18223a; --texto:#e8edf6; --muted:#8e9bb3;
    --borda:rgba(255,255,255,.08); --borda-forte:rgba(255,255,255,.16); --overlay:rgba(3,6,12,.66);
    color-scheme:dark;
}
html[data-tema="claro"]{
    --bg:#f4f6fa; --bg-2:#ffffff; --surface:#ffffff; --surface-2:#f0f3f9; --texto:#101828; --muted:#5d6a80;
    --borda:rgba(16,24,40,.10); --borda-forte:rgba(16,24,40,.22); --overlay:rgba(16,24,40,.5);
    --sombra:0 18px 40px -26px rgba(16,24,40,.35);
    color-scheme:light;
}
*{ box-sizing:border-box; }
html{ -webkit-text-size-adjust:100%; scroll-behavior:smooth; }
body{
    margin:0; font-family:'Poppins',system-ui,-apple-system,'Segoe UI',sans-serif; background:var(--bg); color:var(--texto);
    line-height:1.45; -webkit-font-smoothing:antialiased; min-height:100vh;
}
body.trava{ overflow:hidden; }
button{ font-family:inherit; color:inherit; }
[hidden]{ display:none !important; }
a{ color:inherit; }
.sr{ position:absolute; width:1px; height:1px; overflow:hidden; clip:rect(0 0 0 0); white-space:nowrap; }
:focus-visible{ outline:2px solid var(--acc); outline-offset:2px; }

/* ---------- Topo ---------- */
.topo{
    position:sticky; top:0; z-index:40; display:flex; align-items:center; justify-content:space-between; gap:12px;
    padding:10px max(16px, env(safe-area-inset-right)) 10px max(16px, env(safe-area-inset-left));
    background:color-mix(in srgb, var(--bg) 82%, transparent); backdrop-filter:blur(14px); -webkit-backdrop-filter:blur(14px);
    border-bottom:1px solid var(--borda);
}
.topo__marca{ display:flex; align-items:center; gap:10px; min-width:0; text-decoration:none; }
.topo__logo{ width:34px; height:34px; border-radius:999px; overflow:hidden; flex-shrink:0; background:rgba(var(--acc-rgb),.18); color:var(--acc); display:flex; align-items:center; justify-content:center; font-weight:700; font-size:14px; }
.topo__logo img{ width:100%; height:100%; object-fit:cover; display:block; }
.topo__nome{ font-size:14px; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

.btn{
    display:inline-flex; align-items:center; justify-content:center; gap:8px; border:none; cursor:pointer; text-decoration:none;
    height:46px; padding:0 22px; border-radius:14px; font-size:14px; font-weight:600; transition:transform .15s, filter .15s, background-color .15s, opacity .15s;
    background:var(--acc); color:var(--acc-on); box-shadow:0 10px 24px -12px rgba(var(--acc-rgb),.9);
}
.btn:hover:not(:disabled){ filter:brightness(1.08); }
.btn:active:not(:disabled){ transform:scale(.98); }
.btn:disabled{ opacity:.4; cursor:not-allowed; box-shadow:none; }
.btn--sm{ height:38px; padding:0 16px; font-size:13px; border-radius:12px; }
.js .topo .btn{ opacity:0; transform:translateY(-6px); pointer-events:none; }
.js .topo.is-cta .btn{ opacity:1; transform:none; pointer-events:auto; }
.btn--bloco{ width:100%; }
.btn--sec{ background:var(--surface-2); color:var(--texto); border:1px solid var(--borda); box-shadow:none; }
.btn--sec:hover:not(:disabled){ background:var(--surface); border-color:var(--borda-forte); filter:none; }
.btn--zap{ background:#1faa59; color:#fff; box-shadow:0 10px 24px -12px rgba(31,170,89,.9); }

/* ---------- Aviso ---------- */
.aviso{
    display:flex; gap:10px; align-items:flex-start; justify-content:center; text-align:left;
    padding:10px 16px; font-size:13px; font-weight:500; background:rgba(var(--acc-rgb),.16); color:var(--texto);
    border-bottom:1px solid rgba(var(--acc-rgb),.35);
}
.aviso svg{ flex-shrink:0; margin-top:2px; color:var(--acc); }

/* ---------- Capa e identidade ---------- */
.pagina{ max-width:1120px; margin:0 auto; padding:0 16px 56px; }
.capa{
    position:relative; height:176px; margin:16px 0 0; border-radius:24px; overflow:hidden;
    background:
        radial-gradient(120% 140% at 0% 0%, rgba(var(--acc-rgb),.55), transparent 55%),
        radial-gradient(90% 120% at 100% 100%, rgba(var(--acc-rgb),.28), transparent 60%),
        var(--surface-2);
    border:1px solid var(--borda);
}
.capa img{ position:absolute; inset:0; width:100%; height:100%; object-fit:cover; display:block; }
.capa::after{ content:''; position:absolute; inset:0; background:linear-gradient(180deg, transparent 45%, rgba(0,0,0,.35)); pointer-events:none; }
@media (min-width:720px){ .capa{ height:260px; } }

.loja{ display:flex; gap:16px; align-items:flex-start; margin:-44px 20px 0; position:relative; z-index:2; flex-wrap:wrap; }
.loja__logo{
    width:92px; height:92px; border-radius:999px; flex-shrink:0; overflow:hidden; background:var(--surface);
    border:4px solid var(--bg); box-shadow:var(--sombra); display:flex; align-items:center; justify-content:center;
    color:var(--acc); font-size:34px; font-weight:700;
}
.loja__logo img{ width:100%; height:100%; object-fit:cover; display:block; }
.loja__txt{ flex:1 1 260px; min-width:0; padding-top:54px; }
.loja__nome{ margin:0; font-size:clamp(22px, 5vw, 32px); font-weight:700; letter-spacing:-.02em; line-height:1.15; }
.loja__slogan{ margin:4px 0 0; color:var(--muted); font-size:14px; }
.loja__cta{ padding-top:56px; }
@media (max-width:719px){
    .loja{ margin:-40px 4px 0; }
    .loja__txt{ padding-top:0; flex-basis:100%; }
    .loja__cta{ width:100%; padding-top:0; }
    .loja__cta .btn{ width:100%; }
}
.chips-info{ display:flex; flex-wrap:wrap; gap:8px; margin:16px 4px 0; }
.chip-info{
    display:inline-flex; align-items:center; gap:7px; padding:7px 12px; border-radius:999px; font-size:12.5px; font-weight:500;
    background:var(--surface); border:1px solid var(--borda); color:var(--muted); text-decoration:none;
}
a.chip-info:hover{ border-color:var(--borda-forte); color:var(--texto); }
.chip-info i{ width:8px; height:8px; border-radius:999px; background:var(--muted); display:inline-block; }
.chip-info--aberto{ color:var(--ok); border-color:rgba(34,197,94,.4); background:rgba(34,197,94,.1); }
.chip-info--aberto i{ background:var(--ok); box-shadow:0 0 0 4px rgba(34,197,94,.2); }
.chip-info--fechado i{ background:var(--err); }

.garantias{ display:flex; flex-wrap:wrap; gap:8px 20px; margin:18px 4px 0; padding-top:16px; border-top:1px solid var(--borda); font-size:12.5px; color:var(--muted); }
.garantias span{ display:inline-flex; align-items:center; gap:7px; }
.garantias svg{ color:var(--acc); flex-shrink:0; }

/* ---------- Layout ---------- */
.layout{ display:grid; grid-template-columns:minmax(0,1fr); gap:28px; margin-top:28px; }
.principal, .lateral{ min-width:0; }
@media (min-width:960px){ .layout{ grid-template-columns:minmax(0,1fr) 340px; align-items:start; } .lateral{ position:sticky; top:76px; } }
.bloco{ margin-bottom:28px; }
.bloco__titulo{ font-size:19px; font-weight:700; letter-spacing:-.01em; margin:0 0 4px; display:flex; align-items:center; gap:10px; }
.bloco__titulo::before{ content:''; width:4px; height:18px; border-radius:99px; background:var(--acc); flex-shrink:0; }
.bloco__sub{ margin:0 0 16px; color:var(--muted); font-size:13.5px; }
.sobre{ color:var(--muted); font-size:14.5px; line-height:1.65; white-space:pre-line; margin:0; }

/* ---------- Busca / categorias ---------- */
.busca{ position:relative; margin-bottom:14px; }
.busca svg{ position:absolute; left:14px; top:50%; transform:translateY(-50%); color:var(--muted); pointer-events:none; }
.busca input{
    width:100%; height:48px; border-radius:14px; padding:0 16px 0 44px; font-size:14px; font-family:inherit;
    background:var(--surface); border:1px solid var(--borda); color:var(--texto);
}
.busca input:focus{ outline:none; border-color:var(--acc); box-shadow:0 0 0 4px rgba(var(--acc-rgb),.16); }
.cats{ display:flex; gap:8px; overflow-x:auto; padding:2px 0 14px; scrollbar-width:none; -webkit-overflow-scrolling:touch; }
.cats::-webkit-scrollbar{ display:none; }
.cat{
    flex:0 0 auto; height:38px; padding:0 16px; border-radius:999px; font-size:13px; font-weight:500; cursor:pointer;
    background:var(--surface); border:1px solid var(--borda); color:var(--muted); transition:all .15s;
}
.cat:hover{ color:var(--texto); border-color:var(--borda-forte); }
.cat.is-ativa{ background:var(--acc); border-color:var(--acc); color:var(--acc-on); }

/* ---------- Cards de serviço ---------- */
.servicos{ display:flex; flex-direction:column; gap:12px; }
.servico{
    display:grid; grid-template-columns:auto minmax(0,1fr) auto; gap:16px; align-items:center; text-align:left; width:100%;
    padding:14px; border-radius:var(--raio); background:var(--surface); border:1px solid var(--borda); cursor:pointer;
    transition:border-color .18s, transform .18s, box-shadow .18s;
}
.servico:hover{ border-color:rgba(var(--acc-rgb),.7); transform:translateY(-1px); box-shadow:var(--sombra); }
.servico__foto{
    width:84px; height:84px; border-radius:14px; overflow:hidden; flex-shrink:0; display:flex; align-items:center; justify-content:center;
    background:rgba(var(--acc-rgb),.14); color:var(--acc);
}
.servico__foto img{ width:100%; height:100%; object-fit:cover; display:block; }
.servico__nome{ margin:0; font-size:15.5px; font-weight:600; line-height:1.3; }
.servico__desc{
    margin:4px 0 0; font-size:13px; color:var(--muted); line-height:1.45;
    display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;
}
.servico__meta{ display:flex; align-items:center; gap:14px; margin-top:8px; font-size:13px; color:var(--muted); flex-wrap:wrap; }
.servico__preco{ font-size:16px; font-weight:700; color:var(--texto); }
.servico__dur{ display:inline-flex; align-items:center; gap:5px; }
.servico__acao{
    height:42px; padding:0 20px; border-radius:12px; display:inline-flex; align-items:center; font-size:13.5px; font-weight:600;
    background:rgba(var(--acc-rgb),.12); color:var(--acc); border:1.5px solid rgba(var(--acc-rgb),.55); pointer-events:none;
    transition:background-color .18s, color .18s, border-color .18s;
}
.servico:hover .servico__acao, .servico:focus-visible .servico__acao{ background:var(--acc); color:var(--acc-on); border-color:var(--acc); }
@media (max-width:560px){
    .servico{ grid-template-columns:auto minmax(0,1fr); gap:12px; align-items:start; }
    .servico__foto{ width:72px; height:72px; }
    .servico__acao{ grid-column:1 / -1; justify-content:center; height:44px; }
}
.vazio-lista{ padding:28px; text-align:center; color:var(--muted); font-size:14px; border:1px dashed var(--borda-forte); border-radius:var(--raio); }

/* ---------- Equipe ---------- */
.equipe{ display:flex; gap:12px; overflow-x:auto; padding-bottom:6px; scrollbar-width:none; }
.equipe::-webkit-scrollbar{ display:none; }
.membro{ flex:0 0 auto; width:116px; text-align:center; padding:14px 8px; border-radius:var(--raio); background:var(--surface); border:1px solid var(--borda); }
.avatar{ border-radius:999px; overflow:hidden; display:flex; align-items:center; justify-content:center; background:rgba(var(--acc-rgb),.16); color:var(--acc); font-weight:700; flex-shrink:0; }
.avatar img{ width:100%; height:100%; object-fit:cover; display:block; }
.membro .avatar{ width:64px; height:64px; margin:0 auto 8px; font-size:22px; }
.membro__nome{ font-size:13px; font-weight:600; margin:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.membro__cargo{ font-size:11.5px; color:var(--muted); margin:2px 0 0; }

/* ---------- Lateral ---------- */
.cartao{ background:var(--surface); border:1px solid var(--borda); border-radius:var(--raio); padding:18px; margin-bottom:14px; }
.cartao__titulo{ margin:0 0 12px; font-size:14px; font-weight:600; }
.linha-info{ display:flex; gap:12px; align-items:flex-start; font-size:13.5px; color:var(--muted); line-height:1.5; }
.linha-info + .linha-info{ margin-top:12px; }
.linha-info svg{ flex-shrink:0; margin-top:1px; color:var(--acc); }
.linha-info a{ text-decoration:none; color:var(--texto); font-weight:500; }
.linha-info a:hover{ color:var(--acc); }
.rota{ margin-top:14px; }
.horas{ list-style:none; margin:0; padding:0; font-size:13px; }
.horas li{ display:flex; justify-content:space-between; gap:12px; padding:9px 0; border-top:1px solid var(--borda); color:var(--muted); }
.horas li:first-child{ border-top:none; padding-top:0; }
.horas li.hoje{ color:var(--texto); font-weight:600; }
.horas li .fechado{ color:var(--muted); font-weight:400; }
.horas__hoje{ font-size:10.5px; padding:2px 8px; border-radius:999px; margin-left:8px; background:rgba(var(--acc-rgb),.18); color:var(--acc); font-weight:600; vertical-align:1px; }
.horas__turnos{ text-align:right; }
.pilulas{ display:flex; flex-wrap:wrap; gap:8px; }
.pilula{ display:inline-flex; align-items:center; gap:7px; padding:7px 12px; border-radius:999px; font-size:12.5px; background:var(--surface-2); border:1px solid var(--borda); }
.pilula svg{ color:var(--acc); }
.sociais{ display:flex; gap:10px; margin-top:4px; flex-wrap:wrap; }
.social{
    width:44px; height:44px; border-radius:999px; display:inline-flex; align-items:center; justify-content:center; text-decoration:none;
    background:var(--surface-2); border:1px solid var(--borda); transition:border-color .15s, color .15s;
}
.social:hover{ border-color:var(--acc); color:var(--acc); }

.rodape{ margin-top:12px; padding:28px 0 12px; border-top:1px solid var(--borda); text-align:center; font-size:12px; color:var(--muted); }
.rodape__nome{ margin:0 0 4px; font-size:14px; font-weight:600; color:var(--texto); }
.rodape__end{ margin:0 0 14px; font-size:12.5px; }
.rodape__sociais{ display:flex; justify-content:center; gap:10px; margin-bottom:16px; }
.rodape__sociais .social{ width:38px; height:38px; }
.rodape__marca{ margin:0; opacity:.7; }

/* ---------- Sheet de agendamento ---------- */
.sheet{ position:fixed; inset:0; z-index:100; visibility:hidden; pointer-events:none; }
.sheet.is-aberto{ visibility:visible; pointer-events:auto; }
.sheet__fundo{ position:absolute; inset:0; background:var(--overlay); backdrop-filter:blur(5px); -webkit-backdrop-filter:blur(5px); opacity:0; transition:opacity .25s; }
.sheet.is-aberto .sheet__fundo{ opacity:1; }
.sheet__painel{
    position:absolute; inset:0; display:flex; flex-direction:column; background:var(--bg-2); overflow:hidden;
    transform:translateY(28px); opacity:0; transition:transform .28s cubic-bezier(.2,.8,.2,1), opacity .22s;
}
.sheet.is-aberto .sheet__painel{ transform:none; opacity:1; }
@media (min-width:720px){
    .sheet__painel{
        inset:auto; top:50%; left:50%; width:min(580px, 94vw); height:min(760px, 92vh); border-radius:26px;
        border:1px solid var(--borda); box-shadow:0 40px 90px -30px rgba(0,0,0,.8);
        transform:translate(-50%, calc(-50% + 24px));
    }
    .sheet.is-aberto .sheet__painel{ transform:translate(-50%, -50%); }
}
.sheet__topo{ display:flex; align-items:center; gap:10px; padding:14px 16px 0; padding-top:max(14px, env(safe-area-inset-top)); }
.icon-btn{
    width:40px; height:40px; border-radius:999px; border:1px solid var(--borda); background:var(--surface); cursor:pointer;
    display:inline-flex; align-items:center; justify-content:center; flex-shrink:0; transition:border-color .15s, background-color .15s;
}
.icon-btn:hover{ border-color:var(--borda-forte); background:var(--surface-2); }
.icon-btn[hidden]{ display:none; }
.sheet__titulo{ flex:1; text-align:center; font-size:15px; font-weight:600; margin:0; }
.progresso{ display:flex; align-items:flex-start; padding:16px 20px 0; list-style:none; margin:0; }
.progresso li{ flex:1; display:flex; flex-direction:column; align-items:center; gap:6px; position:relative; font-size:11px; font-weight:500; color:var(--muted); text-align:center; }
.progresso li::before{ content:''; position:absolute; top:12px; right:50%; width:100%; height:2px; background:var(--borda-forte); z-index:0; }
.progresso li:first-child::before{ display:none; }
.progresso li.feito::before, .progresso li.atual::before{ background:var(--acc); }
.progresso__n{
    position:relative; z-index:1; width:26px; height:26px; border-radius:99px; display:flex; align-items:center; justify-content:center;
    font-size:12px; font-weight:700; background:var(--bg-2); border:2px solid var(--borda-forte); color:var(--muted); transition:all .25s;
}
.progresso li.atual .progresso__n{ border-color:var(--acc); color:var(--acc); box-shadow:0 0 0 4px rgba(var(--acc-rgb),.16); }
.progresso li.atual{ color:var(--texto); }
.progresso li.feito .progresso__n{ background:var(--acc); border-color:var(--acc); color:var(--acc-on); }
.progresso li.feito{ color:var(--texto); }

.escolhido{
    display:flex; align-items:center; gap:12px; margin:14px 20px 0; padding:10px 12px; border-radius:14px;
    background:rgba(var(--acc-rgb),.1); border:1px solid rgba(var(--acc-rgb),.3);
}
.escolhido .servico__foto{ width:42px; height:42px; border-radius:10px; }
.escolhido .servico__foto svg{ width:20px; height:20px; }
.escolhido__nome{ margin:0; font-size:13.5px; font-weight:600; line-height:1.25; }
.escolhido__meta{ margin:1px 0 0; font-size:12px; color:var(--muted); }
.escolhido__preco{ margin-left:auto; font-weight:700; font-size:14px; white-space:nowrap; }

.sheet__corpo{ flex:1; overflow-y:auto; overflow-x:hidden; padding:20px; -webkit-overflow-scrolling:touch; overscroll-behavior:contain; }
.passo{ display:none; }
.passo.is-ativo{ display:block; animation:entra .3s cubic-bezier(.2,.8,.2,1); }
.sheet[data-dir="voltar"] .passo.is-ativo{ animation-name:entraVolta; }
@keyframes entra{ from{ opacity:0; transform:translateX(22px); } to{ opacity:1; transform:none; } }
@keyframes entraVolta{ from{ opacity:0; transform:translateX(-22px); } to{ opacity:1; transform:none; } }
.passo__titulo{ margin:0 0 4px; font-size:20px; font-weight:700; letter-spacing:-.01em; }
.passo__sub{ margin:0 0 18px; color:var(--muted); font-size:13.5px; }

.sheet__rodape{
    padding:14px 20px; padding-bottom:max(14px, env(safe-area-inset-bottom)); border-top:1px solid var(--borda); background:var(--bg-2);
}
.sheet__rodape[hidden]{ display:none; }

/* Profissionais */
.profs{ display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:12px; }
@media (min-width:480px){ .profs{ grid-template-columns:repeat(3, minmax(0,1fr)); } }
.prof{
    display:flex; flex-direction:column; align-items:center; text-align:center; gap:2px; padding:18px 10px 16px; cursor:pointer;
    border-radius:var(--raio); background:var(--surface); border:1.5px solid var(--borda); transition:border-color .15s, background-color .15s, transform .15s;
}
.prof:hover{ border-color:rgba(var(--acc-rgb),.7); transform:translateY(-1px); }
.prof.is-sel{ border-color:var(--acc); background:rgba(var(--acc-rgb),.12); }
.prof{ position:relative; }
.prof__ok{ position:absolute; top:10px; right:10px; width:22px; height:22px; border-radius:99px; background:var(--acc); color:var(--acc-on); display:none; align-items:center; justify-content:center; }
.prof.is-sel .prof__ok{ display:flex; }
.prof .avatar{ width:72px; height:72px; font-size:26px; margin-bottom:8px; box-shadow:0 0 0 3px transparent; transition:box-shadow .15s; }
.prof.is-sel .avatar{ box-shadow:0 0 0 3px var(--acc); }
.prof__nome{ font-size:14px; font-weight:600; line-height:1.25; word-break:break-word; }
.prof__cargo{ font-size:12px; color:var(--muted); }

/* Datas */
.datas-topo{ display:flex; align-items:center; justify-content:space-between; margin-bottom:10px; gap:10px; }
.datas-mes{ font-size:14px; font-weight:600; text-transform:capitalize; }
.datas-nav{ display:flex; gap:8px; }
.datas-nav .icon-btn{ width:34px; height:34px; }
.datas-nav .icon-btn:disabled{ opacity:.35; cursor:default; }
.datas{ display:flex; gap:8px; overflow-x:auto; padding:4px 2px 12px; scroll-snap-type:x proximity; scroll-padding:0 20px; scrollbar-width:none; -webkit-overflow-scrolling:touch; margin:0 -20px; padding-left:20px; padding-right:20px; }
.datas::-webkit-scrollbar{ display:none; }
.dia{
    flex:0 0 auto; width:62px; padding:10px 0 11px; border-radius:16px; cursor:pointer; text-align:center; scroll-snap-align:start;
    background:var(--surface); border:1.5px solid var(--borda); transition:all .15s; position:relative;
}
.dia:hover:not(:disabled){ border-color:rgba(var(--acc-rgb),.7); }
.dia__sem{ display:block; font-size:11.5px; font-weight:500; color:var(--muted); text-transform:capitalize; }
.dia__num{ display:block; font-size:21px; font-weight:700; line-height:1.15; margin-top:1px; }
.dia__mes{ display:block; font-size:10px; color:var(--muted); text-transform:uppercase; letter-spacing:.06em; height:12px; margin-top:1px; }
.dia__ponto{ position:absolute; top:7px; right:8px; width:6px; height:6px; border-radius:99px; background:var(--warn); }
.dia.is-sel{ background:var(--acc); border-color:var(--acc); color:var(--acc-on); box-shadow:0 10px 22px -12px rgba(var(--acc-rgb),.95); }
.dia.is-sel .dia__sem, .dia.is-sel .dia__mes{ color:inherit; opacity:.85; }
.dia.is-sel .dia__ponto{ background:var(--acc-on); }
.dia:disabled{ opacity:.35; cursor:not-allowed; background:transparent; border-style:dashed; }
.dia:disabled .dia__num{ text-decoration:line-through; text-decoration-thickness:1.5px; }
.dia--sk{ height:82px; border-color:transparent; background:linear-gradient(90deg, var(--surface) 25%, var(--surface-2) 50%, var(--surface) 75%); background-size:200% 100%; animation:brilho 1.3s infinite; cursor:default; }
@keyframes brilho{ to{ background-position:-200% 0; } }
.legenda{ display:flex; gap:14px; font-size:11.5px; color:var(--muted); margin:2px 2px 0; flex-wrap:wrap; }
.legenda span{ display:inline-flex; align-items:center; gap:6px; }
.legenda i{ width:6px; height:6px; border-radius:99px; display:inline-block; }

/* Horários */
.periodo{ margin-top:22px; }
.periodo__titulo{ display:flex; align-items:center; gap:8px; margin:0 0 10px; font-size:12px; font-weight:600; letter-spacing:.1em; text-transform:uppercase; color:var(--muted); }
.periodo__titulo::after{ content:''; flex:1; height:1px; background:var(--borda); }
.horas-grade{ display:grid; grid-template-columns:repeat(4, minmax(0,1fr)); gap:8px; }
@media (max-width:380px){ .horas-grade{ grid-template-columns:repeat(3, minmax(0,1fr)); } }
@media (min-width:560px){ .horas-grade{ grid-template-columns:repeat(5, minmax(0,1fr)); } }
.hora{
    height:46px; border-radius:12px; font-size:14px; font-weight:600; cursor:pointer; background:var(--surface); border:1.5px solid var(--borda);
    transition:all .15s;
}
.hora:hover:not(:disabled){ border-color:rgba(var(--acc-rgb),.7); }
.hora:disabled{ cursor:not-allowed; opacity:.38; background:transparent; border-style:dashed; text-decoration:line-through; text-decoration-thickness:1.5px; }
.hora.is-sel{ background:var(--acc); border-color:var(--acc); color:var(--acc-on); box-shadow:0 10px 22px -12px rgba(var(--acc-rgb),.95); }
.hora--sk{ border-color:transparent; background:linear-gradient(90deg, var(--surface) 25%, var(--surface-2) 50%, var(--surface) 75%); background-size:200% 100%; animation:brilho 1.3s infinite; cursor:default; }
.estado{
    margin-top:22px; padding:22px 18px; border-radius:var(--raio); text-align:center; border:1px dashed var(--borda-forte); color:var(--muted); font-size:13.5px;
}
.estado strong{ display:block; color:var(--texto); font-size:14.5px; margin-bottom:4px; }
.estado .btn{ margin-top:14px; }
.estado--erro{ border-color:rgba(239,68,68,.5); background:rgba(239,68,68,.07); }

/* Confirmação */
.resumo{ background:var(--surface); border:1px solid var(--borda); border-radius:var(--raio); padding:6px 16px; margin-bottom:20px; }
.resumo__linha{ display:flex; justify-content:space-between; align-items:center; gap:14px; padding:12px 0; border-top:1px solid var(--borda); font-size:13.5px; }
.resumo__linha:first-child{ border-top:none; }
.resumo__rot{ color:var(--muted); flex-shrink:0; }
.resumo__val{ text-align:right; font-weight:600; display:flex; align-items:center; gap:8px; justify-content:flex-end; min-width:0; }
.resumo__val .avatar{ width:26px; height:26px; font-size:11px; }
.resumo__total{ background:rgba(var(--acc-rgb),.1); margin:6px -16px 0; padding:14px 16px; border-radius:0 0 var(--raio) var(--raio); border-top:1px solid rgba(var(--acc-rgb),.3); }
.resumo__total .resumo__val{ font-size:20px; color:var(--texto); }
.form-titulo{ font-size:15px; font-weight:600; margin:0 0 12px; }
.campo{ margin-bottom:14px; }
.campo label{ display:block; font-size:12px; font-weight:500; color:var(--muted); margin-bottom:6px; }
.campo label small{ color:var(--muted); opacity:.8; font-weight:400; }
.campo input, .campo textarea{
    width:100%; height:50px; border-radius:14px; padding:0 16px; font-size:16px; font-family:inherit;
    background:var(--surface); border:1.5px solid var(--borda); color:var(--texto); transition:border-color .15s, box-shadow .15s;
}
.campo textarea{ height:auto; padding:13px 16px; resize:none; line-height:1.45; }
.campo input:focus, .campo textarea:focus{ outline:none; border-color:var(--acc); box-shadow:0 0 0 4px rgba(var(--acc-rgb),.16); }
.campo.is-erro input{ border-color:var(--err); }
.campo__erro{ display:none; margin-top:6px; font-size:12px; color:var(--err); }
.campo.is-erro .campo__erro{ display:block; }
.link-btn{ background:none; border:none; padding:0; cursor:pointer; color:var(--acc); font-size:13px; font-weight:600; }
.campo-oculto{ position:absolute; left:-9999px; top:-9999px; opacity:0; height:0; width:0; overflow:hidden; }
.alerta{
    display:none; margin-bottom:14px; padding:12px 14px; border-radius:14px; font-size:13.5px; line-height:1.45;
    background:rgba(239,68,68,.1); border:1px solid rgba(239,68,68,.45); color:var(--texto);
}
.alerta.is-visivel{ display:block; }
.alerta .link-btn{ display:block; margin-top:8px; }
.rodape-acoes{ display:flex; gap:10px; }
.rodape-acoes .btn--sec{ flex:0 0 auto; }
.rodape-acoes .btn:not(.btn--sec){ flex:1; }
.spin{ width:18px; height:18px; border-radius:99px; border:2.5px solid currentColor; border-right-color:transparent; animation:giro .7s linear infinite; }
@keyframes giro{ to{ transform:rotate(360deg); } }

/* Sucesso */
.sucesso{ text-align:center; padding:12px 0 4px; }
.sucesso__icone{
    width:84px; height:84px; margin:6px auto 18px; border-radius:99px; display:flex; align-items:center; justify-content:center;
    background:rgba(34,197,94,.14); border:2px solid rgba(34,197,94,.5); color:var(--ok);
    animation:pop .5s cubic-bezier(.2,1.4,.4,1);
}
.sucesso__icone path{ stroke-dasharray:30; stroke-dashoffset:30; animation:tracar .5s .25s forwards ease-out; }
@keyframes pop{ from{ transform:scale(.4); opacity:0; } to{ transform:none; opacity:1; } }
@keyframes tracar{ to{ stroke-dashoffset:0; } }
.sucesso h2{ margin:0 0 6px; font-size:22px; font-weight:700; }
.sucesso p{ margin:0 0 20px; color:var(--muted); font-size:14px; }
.sucesso .resumo{ text-align:left; }
.acoes-coluna{ display:flex; flex-direction:column; gap:10px; }

@media (prefers-reduced-motion:reduce){
    *{ animation-duration:.01ms !important; transition-duration:.01ms !important; scroll-behavior:auto !important; }
}
</style>
</head>
<body>

<header class="topo" id="topo">
    <a class="topo__marca" href="#topo-pagina" aria-label="<?= $h($nomeLoja) ?>">
        <span class="topo__logo"><?php if ($logoUrl): ?><img src="<?= $h($logoUrl) ?>" alt=""><?php else: ?><?= $h(mb_strtoupper(mb_substr($nomeLoja, 0, 1))) ?><?php endif; ?></span>
        <span class="topo__nome"><?= $h($nomeLoja) ?></span>
    </a>
    <a class="btn btn--sm" href="#servicos">Agendar agora</a>
</header>

<?php if ($cfg['aviso'] !== ''): ?>
    <div class="aviso" role="status">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16.5v.01"/></svg>
        <span><?= $h($cfg['aviso']) ?></span>
    </div>
<?php endif; ?>

<div class="pagina" id="topo-pagina">

    <div class="capa"><?php if ($capaUrl): ?><img src="<?= $h($capaUrl) ?>" alt=""><?php endif; ?></div>

    <div class="loja">
        <div class="loja__logo"><?php if ($logoUrl): ?><img src="<?= $h($logoUrl) ?>" alt="Logo <?= $h($nomeLoja) ?>"><?php else: ?><?= $h(mb_strtoupper(mb_substr($nomeLoja, 0, 1))) ?><?php endif; ?></div>
        <div class="loja__txt">
            <h1 class="loja__nome"><?= $h($nomeLoja) ?></h1>
            <?php if ($cfg['slogan'] !== ''): ?><p class="loja__slogan"><?= $h($cfg['slogan']) ?></p><?php endif; ?>
        </div>
        <div class="loja__cta"><a class="btn" href="#servicos">Agendar agora</a></div>
    </div>

    <?php if ($statusLoja || $cfg['endereco'] !== ''): ?>
        <div class="chips-info">
            <?php if ($statusLoja): ?>
                <span class="chip-info <?= $statusLoja['aberto'] ? 'chip-info--aberto' : 'chip-info--fechado' ?>"><i></i><?= $h($statusLoja['texto']) ?></span>
            <?php endif; ?>
            <?php if ($cfg['endereco'] !== ''):
                $tagE = $cfg['mapa_url'] !== '' ? 'a' : 'span';
            ?>
                <<?= $tagE ?> class="chip-info" <?= $cfg['mapa_url'] !== '' ? 'href="' . $h($cfg['mapa_url']) . '" target="_blank" rel="noopener"' : '' ?>>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s-6.5-5.6-6.5-11a6.5 6.5 0 0 1 13 0c0 5.4-6.5 11-6.5 11Z"/><circle cx="12" cy="10" r="2.3"/></svg>
                    <?= $h($cfg['endereco']) ?>
                </<?= $tagE ?>>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="garantias" aria-label="Como funciona">
        <span><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/></svg>Leva menos de 1 minuto</span>
        <span><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="3.5"/><path d="M5 20a7 7 0 0 1 14 0"/></svg>Sem cadastro nem senha</span>
        <span><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.8l4.4 4.4L19 7.6"/></svg>Confirmação na hora</span>
    </div>

    <div class="layout">
        <div class="principal">

            <?php if ($cfg['sobre'] !== ''): ?>
                <section class="bloco">
                    <h2 class="bloco__titulo">Sobre nós</h2>
                    <p class="sobre"><?= $h($cfg['sobre']) ?></p>
                </section>
            <?php endif; ?>

            <section class="bloco" id="servicos" style="scroll-margin-top:84px;">
                <h2 class="bloco__titulo">Escolha um serviço</h2>
                <p class="bloco__sub">Toque no serviço para ver profissionais, datas e horários.</p>

                <?php if (count($servicos) > 6): ?>
                    <div class="busca">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="M20 20l-4.3-4.3"/></svg>
                        <label class="sr" for="busca-servico">Buscar serviço</label>
                        <input id="busca-servico" type="search" placeholder="Buscar serviço…" autocomplete="off">
                    </div>
                <?php endif; ?>

                <?php if ($haCategorias): ?>
                    <div class="cats" id="cats" role="tablist" aria-label="Categorias">
                        <button type="button" class="cat is-ativa" data-cat="*">Todos</button>
                        <?php foreach ($categorias as $c): ?><button type="button" class="cat" data-cat="<?= $h($c) ?>"><?= $h($c) ?></button><?php endforeach; ?>
                        <?php if ($haOutros): ?><button type="button" class="cat" data-cat="">Outros</button><?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="servicos" id="lista-servicos">
                    <?php foreach ($servicos as $s): ?>
                        <button type="button" class="servico" data-id="<?= $s['id'] ?>" data-cat="<?= $h($s['categoria']) ?>"
                                data-busca="<?= $h(mb_strtolower($s['nome'] . ' ' . $s['descricao'] . ' ' . $s['categoria'])) ?>">
                            <span class="servico__foto"><?php if ($s['foto']): ?><img src="<?= $h($s['foto']) ?>" alt="" loading="lazy"><?php else: ?><?= $tesoura ?><?php endif; ?></span>
                            <span>
                                <h3 class="servico__nome"><?= $h($s['nome']) ?></h3>
                                <?php if ($s['descricao'] !== ''): ?><p class="servico__desc"><?= $h($s['descricao']) ?></p><?php endif; ?>
                                <span class="servico__meta">
                                    <span class="servico__preco">R$ <?= number_format($s['valor'], 2, ',', '.') ?></span>
                                    <span class="servico__dur">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/></svg>
                                        <?= $s['duracao'] ?> min
                                    </span>
                                </span>
                            </span>
                            <span class="servico__acao">Agendar</span>
                        </button>
                    <?php endforeach; ?>
                </div>
                <?php if (!$servicos): ?>
                    <div class="vazio-lista">Nenhum serviço disponível no momento.</div>
                <?php endif; ?>
                <div class="vazio-lista" id="sem-resultado" hidden>Nenhum serviço encontrado.</div>
            </section>

            <?php if (count($barbeiros) > 0 && $tokenFixo === null): ?>
                <section class="bloco">
                    <h2 class="bloco__titulo">Nossa equipe</h2>
                    <p class="bloco__sub">Profissionais que atendem por aqui.</p>
                    <div class="equipe">
                        <?php foreach ($barbeiros as $b): ?>
                            <div class="membro">
                                <div class="avatar"><?php if ($b['foto']): ?><img src="<?= $h($b['foto']) ?>" alt=""><?php else: ?><?= $h(mb_strtoupper(mb_substr($b['nome'], 0, 1))) ?><?php endif; ?></div>
                                <p class="membro__nome"><?= $h($b['nome']) ?></p>
                                <p class="membro__cargo"><?= $h($b['cargo']) ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>

        <?php if ($temLateral): ?>
        <aside class="lateral">

            <?php if ($cfg['endereco'] !== ''): ?>
                <div class="cartao">
                    <h3 class="cartao__titulo">Localização</h3>
                    <div class="linha-info">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s-6.5-5.6-6.5-11a6.5 6.5 0 0 1 13 0c0 5.4-6.5 11-6.5 11Z"/><circle cx="12" cy="10" r="2.3"/></svg>
                        <span><?= $h($cfg['endereco']) ?></span>
                    </div>
                    <?php if ($cfg['mapa_url'] !== ''): ?>
                        <a class="btn btn--sec btn--sm btn--bloco rota" href="<?= $h($cfg['mapa_url']) ?>" target="_blank" rel="noopener">Como chegar</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($horarios): ?>
                <div class="cartao">
                    <h3 class="cartao__titulo">Horário de atendimento</h3>
                    <ul class="horas">
                        <?php foreach (CatalogoService::DIAS_ORDEM as $dia):
                            $d = $horarios[(string) $dia] ?? ['aberto' => false, 'turnos' => []];
                        ?>
                            <li class="<?= $dia === $diaHoje ? 'hoje' : '' ?>">
                                <span><?= $h(CatalogoService::DIAS_NOMES[$dia]) ?><?php if ($dia === $diaHoje): ?><span class="horas__hoje">Hoje</span><?php endif; ?></span>
                                <?php if (!empty($d['aberto'])): ?>
                                    <span class="horas__turnos"><?= implode('<br>', array_map(fn($t) => $h($t[0]) . ' – ' . $h($t[1]), $d['turnos'])) ?></span>
                                <?php else: ?>
                                    <span class="fechado">Fechado</span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($cfg['formas_pagamento']): ?>
                <div class="cartao">
                    <h3 class="cartao__titulo">Formas de pagamento</h3>
                    <div class="pilulas">
                        <?php foreach ($cfg['formas_pagamento'] as $f): if (isset(CatalogoService::FORMAS_PAGAMENTO[$f])): ?>
                            <span class="pilula"><?= $h(CatalogoService::FORMAS_PAGAMENTO[$f]) ?></span>
                        <?php endif; endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($cfg['comodidades']): ?>
                <div class="cartao">
                    <h3 class="cartao__titulo">Comodidades</h3>
                    <div class="pilulas">
                        <?php foreach ($cfg['comodidades'] as $c): if (isset(CatalogoService::COMODIDADES[$c])): ?>
                            <span class="pilula"><?= CatalogoService::iconeComodidade($c) ?><?= $h(CatalogoService::COMODIDADES[$c]) ?></span>
                        <?php endif; endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($wa !== '' || $cfg['telefone'] !== '' || $cfg['instagram'] !== '' || $cfg['facebook'] !== ''): ?>
                <div class="cartao">
                    <h3 class="cartao__titulo">Contato</h3>
                    <?php if ($wa !== ''): ?>
                        <div class="linha-info">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 20l1.3-4.2A8 8 0 1 1 8.4 18.8L4 20Z"/></svg>
                            <a href="https://wa.me/<?= $h($waDigitos) ?>" target="_blank" rel="noopener"><?= $h($fmtTelefone($wa)) ?> <span style="color:var(--muted);font-weight:400">· WhatsApp</span></a>
                        </div>
                    <?php endif; ?>
                    <?php if ($cfg['telefone'] !== ''): ?>
                        <div class="linha-info">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A15 15 0 0 1 3 6a2 2 0 0 1 2-2Z"/></svg>
                            <a href="tel:<?= $h(preg_replace('/[^\d+]/', '', $cfg['telefone'])) ?>"><?= $h($cfg['telefone']) ?></a>
                        </div>
                    <?php endif; ?>
                    <?php if ($cfg['instagram'] !== '' || $cfg['facebook'] !== ''): ?>
                        <div class="sociais" style="margin-top:14px">
                            <?php if ($cfg['instagram'] !== ''): ?>
                                <a class="social" href="https://instagram.com/<?= $h($cfg['instagram']) ?>" target="_blank" rel="noopener" aria-label="Instagram @<?= $h($cfg['instagram']) ?>">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3.5" y="3.5" width="17" height="17" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.2" cy="6.8" r=".8" fill="currentColor"/></svg>
                                </a>
                            <?php endif; ?>
                            <?php if ($cfg['facebook'] !== ''): ?>
                                <a class="social" href="<?= $h($cfg['facebook']) ?>" target="_blank" rel="noopener" aria-label="Facebook">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 8h2.5V4.5H14a3.5 3.5 0 0 0-3.5 3.5v2H8v3.5h2.5V20H14v-6.5h2.5l.5-3.5H14V8.5A.5.5 0 0 1 14.5 8"/></svg>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </aside>
        <?php endif; ?>
    </div>

    <footer class="rodape">
        <p class="rodape__nome"><?= $h($nomeLoja) ?></p>
        <?php if ($cfg['endereco'] !== ''): ?><p class="rodape__end"><?= $h($cfg['endereco']) ?></p><?php endif; ?>
        <?php if ($cfg['instagram'] !== '' || $cfg['facebook'] !== ''): ?>
            <div class="rodape__sociais">
                <?php if ($cfg['instagram'] !== ''): ?><a class="social" href="https://instagram.com/<?= $h($cfg['instagram']) ?>" target="_blank" rel="noopener" aria-label="Instagram"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3.5" y="3.5" width="17" height="17" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.2" cy="6.8" r=".8" fill="currentColor"/></svg></a><?php endif; ?>
                <?php if ($cfg['facebook'] !== ''): ?><a class="social" href="<?= $h($cfg['facebook']) ?>" target="_blank" rel="noopener" aria-label="Facebook"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 8h2.5V4.5H14a3.5 3.5 0 0 0-3.5 3.5v2H8v3.5h2.5V20H14v-6.5h2.5l.5-3.5H14V8.5A.5.5 0 0 1 14.5 8"/></svg></a><?php endif; ?>
            </div>
        <?php endif; ?>
        <p class="rodape__marca">Agendamento online · BarbERP</p>
    </footer>
</div>

<!-- ============================ SHEET DE AGENDAMENTO ============================ -->
<div class="sheet" id="sheet" role="dialog" aria-modal="true" aria-labelledby="sheet-titulo" data-dir="avancar">
    <div class="sheet__fundo" id="sheet-fundo"></div>
    <div class="sheet__painel">
        <div class="sheet__topo">
            <button type="button" class="icon-btn" id="btn-voltar" aria-label="Voltar">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 6l-6 6 6 6"/></svg>
            </button>
            <h2 class="sheet__titulo" id="sheet-titulo">Agendar</h2>
            <button type="button" class="icon-btn" id="btn-fechar" aria-label="Fechar">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
            </button>
        </div>
        <ol class="progresso" id="progresso" aria-label="Etapas do agendamento"></ol>

        <div class="escolhido" id="escolhido">
            <span class="servico__foto" id="escolhido-foto"></span>
            <div style="min-width:0">
                <p class="escolhido__nome" id="escolhido-nome"></p>
                <p class="escolhido__meta" id="escolhido-meta"></p>
            </div>
            <span class="escolhido__preco" id="escolhido-preco"></span>
        </div>

        <div class="sheet__corpo" id="sheet-corpo">

            <!-- Profissional -->
            <section class="passo" data-passo="prof">
                <h3 class="passo__titulo">Escolha seu profissional</h3>
                <p class="passo__sub">Com quem você quer agendar?</p>
                <div class="profs" id="lista-profs"></div>
            </section>

            <!-- Data e horário -->
            <section class="passo" data-passo="data">
                <h3 class="passo__titulo">Escolha o dia e o horário</h3>
                <p class="passo__sub" id="data-sub">Selecione uma data para ver os horários.</p>
                <div class="datas-topo">
                    <span class="datas-mes" id="datas-mes">—</span>
                    <div class="datas-nav">
                        <button type="button" class="icon-btn" id="datas-ant" aria-label="Dias anteriores"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 6l-6 6 6 6"/></svg></button>
                        <button type="button" class="icon-btn" id="datas-prox" aria-label="Próximos dias"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg></button>
                    </div>
                </div>
                <div class="datas" id="datas" role="listbox" aria-label="Datas"></div>
                <div class="legenda">
                    <span><i style="background:var(--warn)"></i>Poucas vagas</span>
                    <span><i style="background:var(--muted);opacity:.5"></i>Indisponível / ocupado</span>
                </div>
                <div id="area-horarios" aria-live="polite"></div>
            </section>

            <!-- Confirmação -->
            <section class="passo" data-passo="confirmar">
                <h3 class="passo__titulo">Confirme seu agendamento</h3>
                <p class="passo__sub">Confira os detalhes e informe seus dados para finalizar.</p>
                <div class="resumo" id="resumo"></div>

                <h4 class="form-titulo">Seus dados</h4>
                <div class="campo" data-campo="nome">
                    <label for="in-nome">Nome completo</label>
                    <input id="in-nome" type="text" autocomplete="name" placeholder="Como podemos te chamar?" maxlength="100">
                    <span class="campo__erro">Informe seu nome completo.</span>
                </div>
                <div class="campo" data-campo="telefone">
                    <label for="in-telefone">WhatsApp / telefone</label>
                    <input id="in-telefone" type="tel" inputmode="tel" autocomplete="tel" placeholder="(00) 00000-0000" maxlength="20">
                    <span class="campo__erro">Informe um telefone com DDD.</span>
                </div>
                <div class="campo" data-campo="email">
                    <label for="in-email">E-mail <small>(opcional)</small></label>
                    <input id="in-email" type="email" inputmode="email" autocomplete="email" placeholder="voce@email.com" maxlength="120">
                    <span class="campo__erro">Informe um e-mail válido ou deixe em branco.</span>
                </div>
                <div class="campo">
                    <button type="button" class="link-btn" id="btn-obs">+ Adicionar observação</button>
                    <div id="obs-bloco" hidden>
                        <label for="in-obs" style="margin-top:4px">Observação <small>(opcional)</small></label>
                        <textarea id="in-obs" rows="2" placeholder="Alguma preferência ou detalhe?" maxlength="300"></textarea>
                    </div>
                </div>
                <input type="text" id="in-site" class="campo-oculto" tabindex="-1" autocomplete="off" aria-hidden="true">

                <div class="alerta" id="erro-geral" role="alert"></div>
            </section>

            <!-- Sucesso -->
            <section class="passo" data-passo="sucesso">
                <div class="sucesso">
                    <div class="sucesso__icone">
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none"><path d="M5 12.8l4.4 4.4L19 7.6" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </div>
                    <h2>Agendamento confirmado!</h2>
                    <p>Te esperamos no horário abaixo. Até lá!</p>
                    <div class="resumo" id="resumo-sucesso"></div>
                    <div class="acoes-coluna">
                        <button type="button" class="btn btn--bloco" id="btn-ics">Adicionar ao calendário</button>
                        <?php if ($waDigitos !== ''): ?><a class="btn btn--zap btn--bloco" id="btn-zap" href="#" target="_blank" rel="noopener">Falar com a barbearia no WhatsApp</a><?php endif; ?>
                        <button type="button" class="btn btn--sec btn--bloco" id="btn-novo">Fazer outro agendamento</button>
                    </div>
                </div>
            </section>
        </div>

        <div class="sheet__rodape" id="sheet-rodape" hidden>
            <div class="rodape-acoes" id="rodape-data" hidden>
                <button type="button" class="btn" id="btn-continuar" disabled>Continuar</button>
            </div>
            <div class="rodape-acoes" id="rodape-confirmar" hidden>
                <button type="button" class="btn btn--sec" id="btn-alterar">Alterar</button>
                <button type="button" class="btn" id="btn-confirmar">Confirmar agendamento</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';
    const APP = <?= json_encode($appJs, $jsonFlags) ?>;
    const MESES = ['janeiro','fevereiro','março','abril','maio','junho','julho','agosto','setembro','outubro','novembro','dezembro'];
    const MESES_CURTOS = ['jan','fev','mar','abr','mai','jun','jul','ago','set','out','nov','dez'];
    const DIAS_SEM = ['dom','seg','ter','qua','qui','sex','sáb'];
    const DIAS_SEM_LONGO = ['domingo','segunda-feira','terça-feira','quarta-feira','quinta-feira','sexta-feira','sábado'];
    const TOTAL_DIAS = 45;

    const $ = function (id) { return document.getElementById(id); };
    const sheet = $('sheet'), corpo = $('sheet-corpo');
    const TESOURA = <?= json_encode($tesoura, $jsonFlags) ?>;

    const E = {
        servico: null, barbeiro: null, data: null, horario: null,
        passos: [], idx: 0, ultimoFoco: null,
        disp: {},        // 'YYYY-MM-DD' -> {ok:boolean, poucas:boolean}
        seqDisp: 0, seqHoras: 0, ultimoTel: ''
    };

    // ------------------------------------------------------------ utilidades
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]; }); }
    function brl(v) { return 'R$ ' + Number(v).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function pad(n) { return String(n).padStart(2, '0'); }
    function iso(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
    function parseIso(s) { const p = s.split('-'); return new Date(+p[0], +p[1] - 1, +p[2]); }
    function dataExtensa(s) { const d = parseIso(s); return DIAS_SEM_LONGO[d.getDay()] + ', ' + d.getDate() + ' de ' + MESES[d.getMonth()] + ' de ' + d.getFullYear(); }
    function dataExtensaCap(s) { const t = dataExtensa(s); return t.charAt(0).toUpperCase() + t.slice(1); }
    function dataBr(s) { const p = s.split('-'); return p[2] + '/' + p[1] + '/' + p[0]; }
    function avatar(b, extra) {
        const inicial = esc((b.nome || '?').trim().charAt(0).toUpperCase());
        return '<span class="avatar ' + (extra || '') + '">' + (b.foto ? '<img src="' + esc(b.foto) + '" alt="">' : inicial) + '</span>';
    }
    function get(url) {
        return fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } }).then(function (r) {
            return r.json().catch(function () { throw new Error('resposta'); });
        });
    }

    // ----------------------------------------------------- busca e categorias
    const lista = $('lista-servicos');
    const busca = $('busca-servico');
    let catAtiva = '*';
    function filtrar() {
        const termo = busca ? busca.value.trim().toLowerCase() : '';
        let visiveis = 0;
        lista.querySelectorAll('.servico').forEach(function (c) {
            const okCat = catAtiva === '*' || c.dataset.cat === catAtiva;
            const okBusca = !termo || c.dataset.busca.indexOf(termo) !== -1;
            c.hidden = !(okCat && okBusca);
            if (!c.hidden) visiveis++;
        });
        const vazio = $('sem-resultado');
        if (vazio) vazio.hidden = visiveis !== 0 || !lista.children.length;
    }
    if (busca) busca.addEventListener('input', filtrar);
    document.querySelectorAll('#cats .cat').forEach(function (b) {
        b.addEventListener('click', function () {
            catAtiva = b.dataset.cat;
            document.querySelectorAll('#cats .cat').forEach(function (x) { x.classList.toggle('is-ativa', x === b); });
            filtrar();
        });
    });

    // Botão "Agendar agora" do topo só aparece depois que o do cabeçalho da loja sai da tela.
    (function () {
        const topo = $('topo'), alvo = document.querySelector('.loja__cta');
        if (!topo || !alvo || !('IntersectionObserver' in window)) { if (topo) topo.classList.add('is-cta'); return; }
        new IntersectionObserver(function (en) {
            topo.classList.toggle('is-cta', !en[0].isIntersecting);
        }, { rootMargin: '-60px 0px 0px 0px' }).observe(alvo);
    })();

    // -------------------------------------------------------------- sheet
    const passoTitulos = { prof: 'Profissional', data: 'Data e horário', confirmar: 'Confirmação', sucesso: 'Pronto!' };

    function abrirSheet(servico) {
        E.servico = servico;
        E.barbeiro = null; E.data = null; E.horario = null; E.disp = {};
        E.ultimoFoco = document.activeElement;

        // Com um único profissional (ou link pessoal), a escolha de profissional é pulada.
        const unico = APP.barbeiros.length === 1;
        if (unico) E.barbeiro = APP.barbeiros[0];
        E.passos = unico ? ['data', 'confirmar'] : ['prof', 'data', 'confirmar'];
        E.idx = 0;

        $('escolhido-foto').innerHTML = servico.foto ? '<img src="' + esc(servico.foto) + '" alt="">' : TESOURA;
        $('escolhido-nome').textContent = servico.nome;
        $('escolhido-meta').textContent = servico.duracao + ' min';
        $('escolhido-preco').textContent = brl(servico.valor);
        $('escolhido').hidden = false;

        $('erro-geral').classList.remove('is-visivel');
        document.querySelectorAll('.campo').forEach(function (c) { c.classList.remove('is-erro'); });
        const btn = $('btn-confirmar'); btn.disabled = false; btn.textContent = 'Confirmar agendamento';

        sheet.dataset.dir = 'avancar';
        sheet.classList.add('is-aberto');
        document.body.classList.add('trava');
        try { history.pushState({ agendar: 1 }, ''); } catch (e) {}
        mostrarPasso(true);
        $('btn-fechar').focus({ preventScroll: true });
    }

    function fecharSheet(voltarHistorico) {
        if (!sheet.classList.contains('is-aberto')) return;
        sheet.classList.remove('is-aberto');
        document.body.classList.remove('trava');
        E.seqDisp++; E.seqHoras++;
        if (voltarHistorico !== false && history.state && history.state.agendar) { try { history.back(); } catch (e) {} }
        if (E.ultimoFoco && E.ultimoFoco.focus) { try { E.ultimoFoco.focus({ preventScroll: true }); } catch (e) {} }
    }

    function nomeDoPasso() { return E.passos[E.idx]; }

    function mostrarPasso(primeira) {
        const passo = nomeDoPasso();
        document.querySelectorAll('.passo').forEach(function (p) { p.classList.toggle('is-ativo', p.dataset.passo === passo); });
        corpo.scrollTop = 0;
        $('sheet-titulo').textContent = passoTitulos[passo] || 'Agendar';

        // progresso (etapas numeradas; o passo "sucesso" esconde a barra)
        $('progresso').innerHTML = E.passos.map(function (p, i) {
            const estado = i < E.idx ? 'feito' : (i === E.idx ? 'atual' : '');
            const rotulo = p === 'prof' ? 'Profissional' : (p === 'data' ? 'Data e horário' : 'Confirmação');
            const miolo = i < E.idx ? '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.8l4.4 4.4L19 7.6"/></svg>' : (i + 1);
            return '<li class="' + estado + '"' + (i === E.idx ? ' aria-current="step"' : '') + '><span class="progresso__n">' + miolo + '</span>' + rotulo + '</li>';
        }).join('');
        $('progresso').hidden = passo === 'sucesso';
        $('escolhido').hidden = passo === 'sucesso';
        $('escolhido-meta').textContent = E.servico.duracao + ' min' + (E.barbeiro ? ' · ' + E.barbeiro.nome : '');

        // voltar: some no primeiro passo e no sucesso
        $('btn-voltar').hidden = (E.idx === 0) || passo === 'sucesso';

        // rodapé
        const rodape = $('sheet-rodape');
        $('rodape-data').hidden = passo !== 'data';
        $('rodape-confirmar').hidden = passo !== 'confirmar';
        rodape.hidden = !(passo === 'data' || passo === 'confirmar');

        if (passo === 'prof') desenharProfs();
        if (passo === 'data') iniciarDatas();
        if (passo === 'confirmar') { $('erro-geral').classList.remove('is-visivel'); desenharResumo(); }
    }

    function irPara(delta) {
        sheet.dataset.dir = delta > 0 ? 'avancar' : 'voltar';
        E.idx = Math.max(0, Math.min(E.passos.length - 1, E.idx + delta));
        mostrarPasso(false);
    }

    $('btn-fechar').addEventListener('click', function () { fecharSheet(); });
    $('sheet-fundo').addEventListener('click', function () { fecharSheet(); });
    $('btn-voltar').addEventListener('click', function () { irPara(-1); });
    $('btn-alterar').addEventListener('click', function () { irPara(-1); });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && sheet.classList.contains('is-aberto')) fecharSheet();
    });
    window.addEventListener('popstate', function () { if (sheet.classList.contains('is-aberto')) fecharSheet(false); });

    // Mantém o foco dentro do sheet enquanto ele está aberto
    sheet.addEventListener('keydown', function (e) {
        if (e.key !== 'Tab') return;
        const foc = Array.from(sheet.querySelectorAll('button, input, textarea, a[href]')).filter(function (el) {
            return !el.disabled && el.offsetParent !== null && el.tabIndex !== -1;
        });
        if (!foc.length) return;
        const primeiro = foc[0], ultimo = foc[foc.length - 1];
        if (e.shiftKey && document.activeElement === primeiro) { e.preventDefault(); ultimo.focus(); }
        else if (!e.shiftKey && document.activeElement === ultimo) { e.preventDefault(); primeiro.focus(); }
    });

    lista.addEventListener('click', function (e) {
        const card = e.target.closest('.servico');
        if (!card) return;
        const s = APP.servicos.find(function (x) { return x.id === parseInt(card.dataset.id, 10); });
        if (s) abrirSheet(s);
    });

    // ---------------------------------------------------------- profissional
    function desenharProfs() {
        const el = $('lista-profs');
        if (!APP.barbeiros.length) {
            el.innerHTML = '<div class="estado" style="grid-column:1/-1"><strong>Nenhum profissional disponível</strong>Tente novamente mais tarde.</div>';
            return;
        }
        el.innerHTML = APP.barbeiros.map(function (b, i) {
            return '<button type="button" class="prof' + (E.barbeiro && E.barbeiro.token === b.token ? ' is-sel' : '') + '" data-i="' + i + '">'
                + '<span class="prof__ok"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.8l4.4 4.4L19 7.6"/></svg></span>' + avatar(b) + '<span class="prof__nome">' + esc(b.nome) + '</span><span class="prof__cargo">' + esc(b.cargo) + '</span></button>';
        }).join('');
    }
    $('lista-profs').addEventListener('click', function (e) {
        const card = e.target.closest('.prof');
        if (!card) return;
        const b = APP.barbeiros[parseInt(card.dataset.i, 10)];
        if (!E.barbeiro || E.barbeiro.token !== b.token) { E.barbeiro = b; E.data = null; E.horario = null; E.disp = {}; }
        card.parentNode.querySelectorAll('.prof').forEach(function (x) { x.classList.toggle('is-sel', x === card); });
        setTimeout(function () { irPara(1); }, 160);
    });

    // ------------------------------------------------------------------ datas
    function listaDias() {
        const base = parseIso(APP.hoje), dias = [];
        for (let i = 0; i < TOTAL_DIAS; i++) { const d = new Date(base); d.setDate(base.getDate() + i); dias.push(d); }
        return dias;
    }

    function iniciarDatas() {
        const quem = E.barbeiro ? ' com ' + E.barbeiro.nome : '';
        $('data-sub').textContent = 'Agendamento' + quem + '. Toque em um dia para ver os horários livres.';
        $('area-horarios').innerHTML = '';
        $('btn-continuar').disabled = !E.horario;
        const dias = listaDias();

        // esqueletos enquanto carrega
        $('datas').innerHTML = dias.slice(0, 8).map(function () { return '<span class="dia dia--sk" style="pointer-events:none"></span>'; }).join('');
        $('datas-mes').textContent = MESES[dias[0].getMonth()] + ' ' + dias[0].getFullYear();

        const seq = ++E.seqDisp;
        const meses = {};
        dias.forEach(function (d) { meses[d.getFullYear() + '-' + (d.getMonth() + 1)] = [d.getFullYear(), d.getMonth() + 1]; });
        const reqs = Object.keys(meses).map(function (k) {
            const m = meses[k];
            return get('/Publico/scripts/publico_mes_disponibilidade.php?t=' + encodeURIComponent(E.barbeiro.token) + '&mes=' + m[1] + '&ano=' + m[0])
                .then(function (r) { return r && r.ok ? r.dias : null; }).catch(function () { return null; });
        });

        Promise.all(reqs).then(function (lotes) {
            if (seq !== E.seqDisp) return;
            if (lotes.every(function (l) { return l === null; })) { estadoDatasErro(); return; }
            E.disp = {};
            lotes.forEach(function (l) {
                if (!l) return;
                Object.keys(l).forEach(function (k) {
                    const i = l[k];
                    E.disp[k] = { ok: i.status !== 'passado' && !i.bloqueado && i.vagas > 0, poucas: i.status === 'amarelo' };
                });
            });
            desenharDatas(dias);
        });
    }

    function estadoDatasErro() {
        $('datas').innerHTML = '';
        $('area-horarios').innerHTML = '<div class="estado estado--erro"><strong>Não foi possível carregar as datas</strong>Verifique sua conexão e tente novamente.<br><button type="button" class="btn btn--sm" id="btn-retry-datas">Tentar novamente</button></div>';
        $('btn-retry-datas').addEventListener('click', iniciarDatas);
    }

    function desenharDatas(dias) {
        const hoje = APP.hoje;
        const alg = dias.some(function (d) { const i = E.disp[iso(d)]; return i && i.ok; });
        $('datas').innerHTML = dias.map(function (d, idx) {
            const k = iso(d), info = E.disp[k] || { ok: false };
            const rotulo = k === hoje ? 'Hoje' : DIAS_SEM[d.getDay()];
            const mostrarMes = idx === 0 || d.getDate() === 1;
            return '<button type="button" class="dia' + (E.data === k ? ' is-sel' : '') + '" data-d="' + k + '" role="option"'
                + (info.ok ? '' : ' disabled aria-disabled="true"') + ' aria-selected="' + (E.data === k) + '"'
                + ' aria-label="' + esc(dataExtensa(k)) + (info.ok ? '' : ' — indisponível') + '">'
                + (info.ok && info.poucas ? '<span class="dia__ponto"></span>' : '')
                + '<span class="dia__sem">' + rotulo + '</span><span class="dia__num">' + d.getDate() + '</span>'
                + '<span class="dia__mes">' + (mostrarMes ? MESES_CURTOS[d.getMonth()] : '') + '</span></button>';
        }).join('');

        if (!alg) {
            $('area-horarios').innerHTML = '<div class="estado"><strong>Sem horários nos próximos dias</strong>Este profissional não tem vagas abertas agora.'
                + (E.passos[0] === 'prof' ? '<br><button type="button" class="btn btn--sm btn--sec" id="btn-trocar-prof">Escolher outro profissional</button>' : '') + '</div>';
            const t = $('btn-trocar-prof'); if (t) t.addEventListener('click', function () { irPara(-1); });
            return;
        }
        if (E.data && E.disp[E.data] && E.disp[E.data].ok) {
            carregarHoras(E.data);
        } else {
            E.data = null; E.horario = null; $('btn-continuar').disabled = true;
            const primeiro = $('datas').querySelector('.dia:not(:disabled)');
            if (primeiro) {
                const alvo = posNoScroll(primeiro) - 20;
                // Se o primeiro dia livre já está visível no começo, não corta os dias anteriores.
                $('datas').scrollLeft = alvo < $('datas').clientWidth * 0.6 ? 0 : alvo;
            }
        }
        atualizarMesTopo();
    }

    // Posição horizontal de um item dentro da área rolável (independe do offsetParent).
    function posNoScroll(el) {
        const box = $('datas');
        return el.getBoundingClientRect().left - box.getBoundingClientRect().left + box.scrollLeft;
    }
    function atualizarMesTopo() {
        const box = $('datas'), btns = box.querySelectorAll('.dia[data-d]');
        let ref = null;
        for (let i = 0; i < btns.length; i++) { if (posNoScroll(btns[i]) + btns[i].offsetWidth > box.scrollLeft + 24) { ref = btns[i]; break; } }
        if (ref) { const d = parseIso(ref.dataset.d); $('datas-mes').textContent = MESES[d.getMonth()] + ' ' + d.getFullYear(); }
        $('datas-ant').disabled = box.scrollLeft <= 4;
        $('datas-prox').disabled = box.scrollLeft + box.clientWidth >= box.scrollWidth - 4;
    }
    $('datas').addEventListener('scroll', function () { window.requestAnimationFrame(atualizarMesTopo); }, { passive: true });
    $('datas-ant').addEventListener('click', function () { $('datas').scrollBy({ left: -Math.max(200, $('datas').clientWidth * 0.8), behavior: 'smooth' }); });
    $('datas-prox').addEventListener('click', function () { $('datas').scrollBy({ left: Math.max(200, $('datas').clientWidth * 0.8), behavior: 'smooth' }); });

    $('datas').addEventListener('click', function (e) {
        const b = e.target.closest('.dia[data-d]');
        if (!b || b.disabled) return;
        E.data = b.dataset.d; E.horario = null;
        $('btn-continuar').disabled = true;
        $('datas').querySelectorAll('.dia').forEach(function (x) { x.classList.toggle('is-sel', x === b); x.setAttribute('aria-selected', x === b ? 'true' : 'false'); });
        carregarHoras(E.data);
    });

    // --------------------------------------------------------------- horários
    function carregarHoras(data) {
        const area = $('area-horarios');
        const seq = ++E.seqHoras;
        area.innerHTML = '<div class="periodo"><div class="periodo__titulo">Horários</div><div class="horas-grade">'
            + new Array(8).fill('<span class="hora hora--sk"></span>').join('') + '</div></div>';

        get('/Publico/scripts/publico_horarios_buscar.php?t=' + encodeURIComponent(E.barbeiro.token) + '&data=' + data)
            .then(function (r) {
                if (seq !== E.seqHoras) return;
                if (!r || !r.ok) { throw new Error('falha'); }
                // Mesma grade do sistema de gestão (Agendamentos > agendar.php): todos os horários do
                // dia aparecem; os ocupados, inativados ou já passados ficam desabilitados.
                const todos = r.horarios || [];
                const livres = todos.filter(function (h) { return h.disponivel; });
                let aviso = '';
                if (!livres.length) {
                    // Dia sem horário livre: marca como indisponível e orienta.
                    E.disp[data] = { ok: false, poucas: false };
                    const bt = $('datas').querySelector('.dia[data-d="' + data + '"]');
                    if (bt) { bt.disabled = true; bt.classList.remove('is-sel'); bt.setAttribute('aria-disabled', 'true'); }
                    E.data = null; E.horario = null; $('btn-continuar').disabled = true;
                    aviso = '<div class="estado"><strong>Sem horários livres neste dia</strong>'
                        + (r.diaBloqueado ? 'Não há atendimento neste dia.' : 'Todos os horários já foram ocupados.') + ' Escolha outra data.</div>';
                    if (!todos.length) { area.innerHTML = aviso; return; }
                }
                const grupos = [['Manhã', 0, 12], ['Tarde', 12, 18], ['Noite', 18, 24]];
                let html = aviso;
                grupos.forEach(function (g) {
                    const hs = todos.filter(function (h) { const hr = parseInt(h.hora.slice(0, 2), 10); return hr >= g[1] && hr < g[2]; });
                    if (!hs.length) return;
                    html += '<div class="periodo"><div class="periodo__titulo">' + g[0] + '</div><div class="horas-grade">'
                        + hs.map(function (h) {
                            if (!h.disponivel) {
                                return '<button type="button" class="hora" disabled aria-label="' + h.hora + ' — indisponível">' + h.hora + '</button>';
                            }
                            return '<button type="button" class="hora' + (E.horario && E.horario.idHorario === h.idHorario ? ' is-sel' : '') + '" data-id="' + h.idHorario + '" data-h="' + h.hora + '">' + h.hora + '</button>';
                        }).join('') + '</div></div>';
                });
                area.innerHTML = html;
                if (livres.length && window.matchMedia('(max-width:719px)').matches) {
                    area.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            })
            .catch(function () {
                if (seq !== E.seqHoras) return;
                area.innerHTML = '<div class="estado estado--erro"><strong>Não foi possível carregar os horários</strong>Verifique sua conexão e tente novamente.<br><button type="button" class="btn btn--sm" id="btn-retry-horas">Tentar novamente</button></div>';
                $('btn-retry-horas').addEventListener('click', function () { carregarHoras(data); });
            });
    }

    $('area-horarios').addEventListener('click', function (e) {
        const b = e.target.closest('.hora[data-id]');
        if (!b) return;
        E.horario = { idHorario: parseInt(b.dataset.id, 10), hora: b.dataset.h };
        $('area-horarios').querySelectorAll('.hora').forEach(function (x) { x.classList.toggle('is-sel', x === b); });
        const btn = $('btn-continuar'); btn.disabled = false;
        btn.textContent = 'Continuar · ' + E.horario.hora;
    });
    $('btn-continuar').addEventListener('click', function () { if (E.data && E.horario) irPara(1); });

    // ------------------------------------------------------------ confirmação
    function linhasResumo() {
        return '<div class="resumo__linha"><span class="resumo__rot">Serviço</span><span class="resumo__val">' + esc(E.servico.nome) + '</span></div>'
            + '<div class="resumo__linha"><span class="resumo__rot">Profissional</span><span class="resumo__val">' + avatar(E.barbeiro) + esc(E.barbeiro.nome) + '</span></div>'
            + '<div class="resumo__linha"><span class="resumo__rot">Data</span><span class="resumo__val" style="text-align:right">' + esc(dataExtensaCap(E.data)) + '</span></div>'
            + '<div class="resumo__linha"><span class="resumo__rot">Horário</span><span class="resumo__val">' + esc(E.horario.hora) + '</span></div>'
            + '<div class="resumo__linha"><span class="resumo__rot">Duração</span><span class="resumo__val">' + E.servico.duracao + ' min</span></div>'
            + '<div class="resumo__linha resumo__total"><span class="resumo__rot">Total</span><span class="resumo__val">' + brl(E.servico.valor) + '</span></div>';
    }
    function desenharResumo() { $('resumo').innerHTML = linhasResumo(); }

    $('btn-obs').addEventListener('click', function () {
        $('obs-bloco').hidden = false; this.hidden = true; $('in-obs').focus();
    });
    $('in-telefone').addEventListener('input', function () {
        const d = this.value.replace(/\D/g, '').slice(0, 11);
        let v = d;
        if (d.length > 10) v = '(' + d.slice(0, 2) + ') ' + d.slice(2, 7) + '-' + d.slice(7);
        else if (d.length > 6) v = '(' + d.slice(0, 2) + ') ' + d.slice(2, 6) + '-' + d.slice(6);
        else if (d.length > 2) v = '(' + d.slice(0, 2) + ') ' + d.slice(2);
        this.value = v;
    });
    document.querySelectorAll('.campo input').forEach(function (i) {
        i.addEventListener('input', function () { const c = i.closest('.campo'); if (c) c.classList.remove('is-erro'); });
    });

    function validar() {
        const nome = $('in-nome').value.trim(), tel = $('in-telefone').value.replace(/\D/g, ''), email = $('in-email').value.trim();
        const erros = {
            nome: nome.length < 3 || nome.indexOf(' ') === -1 && nome.length < 3,
            telefone: tel.length < 10,
            email: email !== '' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)
        };
        let primeiro = null;
        Object.keys(erros).forEach(function (k) {
            const c = document.querySelector('.campo[data-campo="' + k + '"]');
            c.classList.toggle('is-erro', erros[k]);
            if (erros[k] && !primeiro) primeiro = c;
        });
        if (primeiro) { primeiro.querySelector('input').focus(); primeiro.scrollIntoView({ block: 'center', behavior: 'smooth' }); }
        return !primeiro;
    }

    function mostrarErro(texto, comAcao, tipo) {
        const box = $('erro-geral');
        box.innerHTML = esc(texto) + (comAcao ? '<button type="button" class="link-btn" id="btn-outro-horario">' + (tipo === 'dia' ? 'Escolher outro dia' : 'Escolher outro horário') + '</button>' : '');
        box.classList.add('is-visivel');
        box.scrollIntoView({ block: 'center', behavior: 'smooth' });
        const b = $('btn-outro-horario');
        if (b) b.addEventListener('click', function () { E.horario = null; $('btn-continuar').textContent = 'Continuar'; irPara(-1); });
    }

    $('btn-confirmar').addEventListener('click', function () {
        $('erro-geral').classList.remove('is-visivel');
        if (!validar()) return;
        const botao = $('btn-confirmar');
        botao.disabled = true; botao.innerHTML = '<span class="spin"></span> Agendando…';

        const campos = {
            t: E.barbeiro.token,
            idHorario: E.horario.idHorario,
            idServico: E.servico.id,
            nome: $('in-nome').value.trim(),
            telefone: $('in-telefone').value.trim(),
            email: $('in-email').value.trim(),
            observacao: $('in-obs').value.trim(),
            site: $('in-site').value
        };

        function restaurar() { botao.disabled = false; botao.textContent = 'Confirmar agendamento'; }

        // Envia; se a sessão/CSRF expirou (403), renova o token UMA vez e repete — o cliente não perde o que escolheu.
        function enviar(jaRenovou) {
            const dados = new URLSearchParams();
            Object.keys(campos).forEach(function (k) { dados.set(k, campos[k]); });
            dados.set('_csrf', APP.csrf);

            return fetch('/Publico/scripts/publico_agendar_salvar.php', {
                method: 'POST', credentials: 'same-origin', cache: 'no-store',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' }, body: dados.toString()
            }).then(function (r) {
                return r.text().then(function (t) {
                    let j = null; try { j = JSON.parse(t); } catch (e) {}
                    return { status: r.status, j: j };
                });
            }).then(function (res) {
                if (res.status === 403 && !jaRenovou) {
                    return fetch('/Publico/scripts/publico_csrf.php', { credentials: 'same-origin', cache: 'no-store', headers: { 'Accept': 'application/json' } })
                        .then(function (r) { return r.json(); })
                        .then(function (n) { if (n && n.csrf) { APP.csrf = n.csrf; } return enviar(true); });
                }
                return res;
            });
        }

        enviar(false).then(function (res) {
            const r = res.j;
            if (!r) {
                restaurar();
                mostrarErro(res.status >= 500
                    ? 'O sistema teve um problema ao salvar seu agendamento. Tente novamente em instantes.'
                    : 'Não foi possível concluir o agendamento. Tente novamente.', false);
                return;
            }
            if (!r.ok) {
                restaurar();
                let msg = r.erro || 'Não foi possível agendar. Tente novamente.';
                if (r.codigo === 'agendamento_duplicado') {
                    msg = 'Você já tem um agendamento ativo neste dia' + (r.hora ? ', às ' + r.hora : '') + (r.profissional ? ' com ' + r.profissional : '') + '. Escolha outro dia para marcar um novo horário.';
                    restaurar();
                    mostrarErro(msg, true, 'dia');
                    return;
                }
                if (res.status === 403) { msg = 'Não conseguimos validar sua sessão. Se você abriu este link dentro do Instagram ou WhatsApp, abra-o no navegador do celular (Chrome/Safari) e tente de novo.'; }
                const sugerirOutro = /hor[aá]rio|dia/i.test(r.erro || '') && r.codigo !== 'agendamento_duplicado' && res.status !== 403;
                mostrarErro(msg, sugerirOutro);
                return;
            }
            E.ultimoTel = $('in-telefone').value.trim();
            const conf = r.confirmacao || {};
            E.confirmado = { data: conf.data || E.data, hora: conf.hora || E.horario.hora };
            $('resumo-sucesso').innerHTML = linhasResumo().replace(/<div class="resumo__linha resumo__total">[\s\S]*$/, '')
                + '<div class="resumo__linha"><span class="resumo__rot">Valor</span><span class="resumo__val">' + brl(E.servico.valor) + '</span></div>';
            const z = $('btn-zap');
            if (z && APP.whatsapp) {
                const msg = 'Olá! Acabei de agendar ' + E.servico.nome + ' com ' + E.barbeiro.nome + ' em ' + dataBr(E.confirmado.data) + ' às ' + E.confirmado.hora + '.';
                z.href = 'https://wa.me/' + APP.whatsapp + '?text=' + encodeURIComponent(msg);
            }
            E.passos = ['sucesso']; E.idx = 0;
            sheet.dataset.dir = 'avancar';
            mostrarPasso(false);
        }).catch(function () {
            restaurar();
            mostrarErro('Erro de conexão. Verifique sua internet e tente novamente.', false);
        });
    });

    // ------------------------------------------------------------------ sucesso
    $('btn-ics').addEventListener('click', function () {
        const c = E.confirmado; if (!c) return;
        const ini = c.data.replace(/-/g, '') + 'T' + c.hora.replace(':', '') + '00';
        const fimD = parseIso(c.data); const [hh, mm] = c.hora.split(':').map(Number);
        fimD.setHours(hh, mm + E.servico.duracao);
        const fim = fimD.getFullYear() + pad(fimD.getMonth() + 1) + pad(fimD.getDate()) + 'T' + pad(fimD.getHours()) + pad(fimD.getMinutes()) + '00';
        const txt = function (s) { return String(s).replace(/([,;\\])/g, '\\$1').replace(/\n/g, '\\n'); };
        const ics = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//BarbERP//Agendamento//PT', 'BEGIN:VEVENT',
            'UID:' + Date.now() + '@barberp', 'DTSTAMP:' + new Date().toISOString().replace(/[-:]/g, '').split('.')[0] + 'Z',
            'DTSTART:' + ini, 'DTEND:' + fim, 'SUMMARY:' + txt(E.servico.nome + ' — ' + APP.loja),
            'DESCRIPTION:' + txt('Profissional: ' + E.barbeiro.nome), 'END:VEVENT', 'END:VCALENDAR'].join('\r\n');
        const blob = new Blob([ics], { type: 'text/calendar;charset=utf-8' });
        const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = 'agendamento.ics';
        document.body.appendChild(a); a.click(); a.remove(); setTimeout(function () { URL.revokeObjectURL(a.href); }, 2000);
    });
    $('btn-novo').addEventListener('click', function () {
        $('in-obs').value = ''; $('obs-bloco').hidden = true; $('btn-obs').hidden = false;
        fecharSheet();
        window.scrollTo({ top: $('servicos').offsetTop - 80, behavior: 'smooth' });
    });
})();
</script>
</body>
</html>

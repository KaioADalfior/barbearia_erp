<?php
// Publico/paginas/agendar.php
// Agendamento público por link (sem login). Recebe o token do barbeiro
// pela query string "t" (a URL bonita /c/agendar/<token> é reescrita para
// cá pelo servidor — ver .htaccess e .nixpacks/assets/nginx.template.conf),
// resolve o token e monta a tela em 3 passos: Serviço -> Data e Horário ->
// Seus Dados. Todo o agendamento de verdade acontece via AJAX em
// Publico/scripts/*, reaproveitando as mesmas regras já validadas no fluxo
// interno do barbeiro (HorarioService, FinanceiroService).

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/PublicoTokenService.php';

$token = trim($_GET['t'] ?? '');
$barbeiro = PublicoTokenService::resolverBarbeiro($pdo, $token);

if ($barbeiro === null) {
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="pt-br">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Link inválido</title>
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
        <style>
            body{ margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center; background:#0b0f17; color:#e7ebf3; font-family:'Poppins',sans-serif; text-align:center; padding:24px; }
            .box{ max-width:420px; }
            h1{ font-size:22px; margin-bottom:8px; }
            p{ color:#8b97ac; font-size:14px; line-height:1.5; }
        </style>
    </head>
    <body>
        <div class="box">
            <h1>Link de agendamento inválido</h1>
            <p>Esse link não existe mais ou foi desativado pelo profissional. Peça um link atualizado a ele.</p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Lista de serviços renderizada direto no HTML (sem esperar um fetch extra
// só para o primeiro passo aparecer) — Publico/scripts/publico_info.php
// continua existindo como endpoint independente, usado por qualquer
// atualização futura sem recarregar a página inteira.
$stmtServicos = $pdo->prepare('SELECT idServico, nome, duracao_minutos, valor FROM Servico WHERE ativo = 1 ORDER BY nome ASC');
$stmtServicos->execute();
$servicos = $stmtServicos->fetchAll();

$nomeBarbeiro = $barbeiro['nome'];
$fotoBarbeiro = null;
if (!empty($barbeiro['foto'])) {
    $caminhoFoto = __DIR__ . '/../../assets/uploads/perfil/' . $barbeiro['foto'];
    if (is_file($caminhoFoto)) {
        $fotoBarbeiro = '../../assets/uploads/perfil/' . rawurlencode($barbeiro['foto']);
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Agendar com <?= htmlspecialchars($nomeBarbeiro) ?></title>

<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">

<style>
    :root{
        --onyx:#0b0f17;
        --charcoal:#111827;
        --charcoal-2:#141b2b;
        --charcoal-3:#1a2236;
        --gold:#2f6fed;
        --gold-light:#5b93f7;
        --cream:#e7ebf3;
        --success:#22c55e;
        --warning:#f59e0b;
        --danger:#ef4444;
    }
    *{ box-sizing:border-box; }
    body{
        font-family:'Poppins', sans-serif;
        background-color:var(--onyx);
        background-image:
            radial-gradient(ellipse at 50% 0%, rgba(47,111,237,0.12), transparent 55%),
            radial-gradient(ellipse at 50% 100%, rgba(0,0,0,0.65), transparent 60%);
        color:var(--cream);
        min-height:100vh;
        margin:0;
    }
    .barber-stripe{ height:4px; width:100%; background:linear-gradient(90deg, var(--gold), var(--gold-light)); }
    .card{
        background:linear-gradient(180deg, var(--charcoal), var(--charcoal-2));
        border:1px solid rgba(47,111,237,0.14);
        border-radius:1.5rem;
        overflow:hidden;
    }
    .field{
        background:rgba(0,0,0,0.35);
        border:1px solid rgba(255,255,255,0.08);
        color:var(--cream);
        transition:border-color .2s, box-shadow .2s, background-color .2s;
    }
    .field::placeholder{ color:#5b6f8c; }
    .field:focus{
        outline:none;
        border-color:var(--gold);
        background:rgba(0,0,0,0.5);
        box-shadow:0 0 0 4px rgba(47,111,237,0.12);
    }
    .btn-primario{
        background:linear-gradient(180deg, var(--gold-light), var(--gold));
        color:#fff;
        font-weight:600;
        letter-spacing:0.02em;
        transition:filter .2s, transform .15s, box-shadow .2s, opacity .2s;
        box-shadow:0 8px 20px -8px rgba(47,111,237,0.55);
    }
    .btn-primario:hover:not(:disabled){ filter:brightness(1.08); }
    .btn-primario:active:not(:disabled){ transform:scale(0.98); }
    .btn-primario:disabled{ opacity:.4; cursor:not-allowed; box-shadow:none; }
    .btn-secundario{
        background:rgba(255,255,255,0.04);
        border:1px solid rgba(255,255,255,0.10);
        color:var(--cream);
        transition:background-color .15s, border-color .15s;
    }
    .btn-secundario:hover{ background:rgba(255,255,255,0.08); border-color:rgba(255,255,255,0.2); }

    /* ---------- Passos ---------- */
    .steps{ display:flex; align-items:center; justify-content:center; gap:0; margin-bottom:28px; }
    .step{ display:flex; flex-direction:column; align-items:center; gap:6px; min-width:74px; }
    .step__circulo{
        width:32px; height:32px; border-radius:999px; display:flex; align-items:center; justify-content:center;
        font-size:13px; font-weight:600; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.12); color:#8b97ac;
        transition:background-color .2s, border-color .2s, color .2s;
    }
    .step.is-ativo .step__circulo{ background:linear-gradient(180deg, var(--gold-light), var(--gold)); border-color:transparent; color:#fff; }
    .step.is-feito .step__circulo{ background:rgba(34,197,94,0.16); border-color:rgba(34,197,94,0.5); color:var(--success); }
    .step__rotulo{ font-size:10.5px; letter-spacing:0.06em; color:#7f8fac; text-transform:uppercase; text-align:center; }
    .step.is-ativo .step__rotulo{ color:var(--cream); }
    .step__linha{ flex:1; height:1px; background:rgba(255,255,255,0.12); margin:0 4px; margin-bottom:20px; max-width:60px; }

    /* ---------- Serviços ---------- */
    .servico-card{
        display:flex; align-items:center; justify-content:space-between; gap:12px;
        padding:14px 16px; border-radius:1rem; border:1px solid rgba(255,255,255,0.08);
        background:rgba(255,255,255,0.02); cursor:pointer; transition:border-color .15s, background-color .15s;
    }
    .servico-card:hover{ background:rgba(255,255,255,0.05); }
    .servico-card.is-selecionado{ border-color:var(--gold-light); background:rgba(47,111,237,0.10); }
    .servico-card__radio{
        width:18px; height:18px; border-radius:999px; border:2px solid rgba(255,255,255,0.25); flex-shrink:0;
        display:flex; align-items:center; justify-content:center;
    }
    .servico-card.is-selecionado .servico-card__radio{ border-color:var(--gold-light); }
    .servico-card.is-selecionado .servico-card__radio::after{ content:''; width:9px; height:9px; border-radius:999px; background:var(--gold-light); }

    /* ---------- Calendário ---------- */
    .calendario__cabecalho{ display:flex; align-items:center; justify-content:space-between; margin-bottom:14px; }
    .calendario__mes{ font-weight:600; font-size:15px; text-transform:capitalize; }
    .calendario__nav{
        width:32px; height:32px; border-radius:0.6rem; display:flex; align-items:center; justify-content:center;
        background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); cursor:pointer; color:var(--cream);
        transition:background-color .15s;
    }
    .calendario__nav:hover:not(:disabled){ background:rgba(255,255,255,0.09); }
    .calendario__nav:disabled{ opacity:.3; cursor:not-allowed; }
    .calendario__grade{ display:grid; grid-template-columns:repeat(7, 1fr); gap:4px; }
    .calendario__semana{ text-align:center; font-size:10.5px; color:#65748f; letter-spacing:0.05em; padding-bottom:4px; }
    .dia{
        position:relative; aspect-ratio:1; display:flex; flex-direction:column; align-items:center; justify-content:center;
        border-radius:0.7rem; font-size:13px; cursor:pointer; border:1px solid transparent; color:var(--cream);
        background:rgba(255,255,255,0.02); transition:background-color .15s, border-color .15s;
    }
    .dia:hover:not(.is-desabilitado){ background:rgba(255,255,255,0.07); }
    .dia.is-desabilitado{ color:#3d4759; cursor:default; }
    .dia.is-vazio{ visibility:hidden; }
    .dia.is-selecionado{ border-color:var(--gold-light); background:rgba(47,111,237,0.16); }
    .dia__ponto{ width:5px; height:5px; border-radius:999px; margin-top:2px; }
    .dia__ponto.verde{ background:var(--success); }
    .dia__ponto.amarelo{ background:var(--warning); }
    .dia__ponto.vermelho{ background:var(--danger); }

    /* ---------- Horários ---------- */
    .horarios-grade{ display:grid; grid-template-columns:repeat(auto-fill, minmax(78px, 1fr)); gap:8px; }
    .horario-btn{
        padding:10px 6px; border-radius:0.7rem; text-align:center; font-size:13px; font-weight:500;
        background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.08); color:var(--cream);
        cursor:pointer; transition:border-color .15s, background-color .15s;
    }
    .horario-btn:hover:not(:disabled){ border-color:var(--gold-light); }
    .horario-btn:disabled{ opacity:.25; cursor:not-allowed; text-decoration:line-through; }
    .horario-btn.is-selecionado{ background:linear-gradient(180deg, var(--gold-light), var(--gold)); border-color:transparent; color:#fff; }

    .field-label{ font-size:12px; letter-spacing:0.08em; font-weight:500; color:#aebdd6; }
    .resumo-linha{ display:flex; justify-content:space-between; font-size:13px; padding:7px 0; border-bottom:1px solid rgba(255,255,255,0.06); }
    .resumo-linha:last-child{ border-bottom:none; }
    .alerta{ border:1px solid rgba(239,68,68,0.5); background:rgba(239,68,68,0.12); color:#f0c9cc; border-radius:0.8rem; padding:10px 14px; font-size:13px; }
    .sucesso-icone{
        width:64px; height:64px; border-radius:999px; background:rgba(34,197,94,0.14); border:1px solid rgba(34,197,94,0.5);
        display:flex; align-items:center; justify-content:center; margin:0 auto 16px;
    }
    /* Campo-armadilha: existe no DOM (bots que preenchem tudo caem nele),
       mas nunca visível/alcançável por uma pessoa real. */
    .campo-oculto{ position:absolute; left:-9999px; top:-9999px; opacity:0; height:0; width:0; overflow:hidden; }
</style>
</head>
<body>

<div class="max-w-xl mx-auto px-5 py-10">

    <!-- Marca do barbeiro -->
    <div class="flex flex-col items-center mb-8 text-center">
        <?php if ($fotoBarbeiro): ?>
            <img src="<?= htmlspecialchars($fotoBarbeiro) ?>" alt="" class="w-16 h-16 rounded-full object-cover mb-3" style="border:2px solid rgba(47,111,237,0.5);">
        <?php else: ?>
            <div class="w-16 h-16 rounded-full flex items-center justify-center mb-3 text-xl font-semibold" style="background:linear-gradient(145deg,#17233a,#0b0f17); border:1px solid rgba(47,111,237,0.5); color:var(--gold-light);">
                <?= htmlspecialchars(mb_strtoupper(mb_substr($nomeBarbeiro, 0, 1))) ?>
            </div>
        <?php endif; ?>
        <h1 class="text-xl font-semibold"><?= htmlspecialchars($nomeBarbeiro) ?></h1>
        <p class="text-xs mt-1" style="color:#7f8fac;">Agende seu horário em poucos passos</p>
    </div>

    <!-- Indicador de passos -->
    <div class="steps">
        <div class="step is-ativo" data-step-indicador="1">
            <div class="step__circulo">1</div>
            <div class="step__rotulo">Serviço</div>
        </div>
        <div class="step__linha"></div>
        <div class="step" data-step-indicador="2">
            <div class="step__circulo">2</div>
            <div class="step__rotulo">Data e Hora</div>
        </div>
        <div class="step__linha"></div>
        <div class="step" data-step-indicador="3">
            <div class="step__circulo">3</div>
            <div class="step__rotulo">Seus Dados</div>
        </div>
    </div>

    <!-- Passo 1: Serviço -->
    <section class="card p-6" data-step="1">
        <div class="barber-stripe absolute inset-x-0 top-0" style="position:relative; margin:-24px -24px 20px -24px; width:calc(100% + 48px);"></div>
        <h2 class="text-sm font-semibold mb-4" style="color:#aebdd6;">Escolha o serviço</h2>
        <div class="flex flex-col gap-2.5" id="lista-servicos">
            <?php foreach ($servicos as $s): ?>
                <div class="servico-card"
                     data-id-servico="<?= (int) $s['idServico'] ?>"
                     data-nome-servico="<?= htmlspecialchars($s['nome']) ?>"
                     data-valor-servico="<?= htmlspecialchars(number_format((float) $s['valor'], 2, ',', '.')) ?>">
                    <div>
                        <p class="text-sm font-medium"><?= htmlspecialchars($s['nome']) ?></p>
                        <p class="text-xs mt-0.5" style="color:#7f8fac;"><?= (int) $s['duracao_minutos'] ?> min</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-sm font-semibold" style="color:var(--gold-light);">R$ <?= number_format((float) $s['valor'], 2, ',', '.') ?></span>
                        <span class="servico-card__radio"></span>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (empty($servicos)): ?>
                <p class="text-sm" style="color:#7f8fac;">Nenhum serviço disponível no momento.</p>
            <?php endif; ?>
        </div>
        <button type="button" class="btn-primario w-full h-12 rounded-xl text-sm mt-6" id="btn-ir-passo-2" disabled>Continuar</button>
    </section>

    <!-- Passo 2: Data e Horário -->
    <section class="card p-6 hidden" data-step="2">
        <div class="calendario__cabecalho">
            <button type="button" class="calendario__nav" id="mes-anterior" aria-label="Mês anterior">‹</button>
            <span class="calendario__mes" id="calendario-titulo-mes">—</span>
            <button type="button" class="calendario__nav" id="mes-seguinte" aria-label="Próximo mês">›</button>
        </div>
        <div class="calendario__grade mb-2">
            <div class="calendario__semana">D</div><div class="calendario__semana">S</div><div class="calendario__semana">T</div>
            <div class="calendario__semana">Q</div><div class="calendario__semana">Q</div><div class="calendario__semana">S</div><div class="calendario__semana">S</div>
        </div>
        <div class="calendario__grade" id="calendario-dias"></div>

        <div class="mt-6" id="bloco-horarios" style="display:none;">
            <h3 class="text-sm font-semibold mb-3" style="color:#aebdd6;">Horários disponíveis</h3>
            <div class="horarios-grade" id="lista-horarios"></div>
            <p class="text-xs mt-3" style="color:#7f8fac;" id="horarios-vazio" style="display:none;">Nenhum horário livre nesse dia.</p>
        </div>

        <div class="flex gap-3 mt-6">
            <button type="button" class="btn-secundario h-12 px-5 rounded-xl text-sm" data-voltar="1">Voltar</button>
            <button type="button" class="btn-primario flex-1 h-12 rounded-xl text-sm" id="btn-ir-passo-3" disabled>Continuar</button>
        </div>
    </section>

    <!-- Passo 3: Seus dados -->
    <section class="card p-6 hidden" data-step="3">
        <h2 class="text-sm font-semibold mb-4" style="color:#aebdd6;">Seus dados</h2>

        <div class="mb-4">
            <label class="field-label block mb-2 uppercase">Nome completo</label>
            <input type="text" id="input-nome" placeholder="Seu nome completo" class="field w-full h-12 px-4 rounded-xl text-sm" autocomplete="name">
        </div>
        <div class="mb-4">
            <label class="field-label block mb-2 uppercase">WhatsApp / Telefone</label>
            <input type="tel" id="input-telefone" placeholder="(00) 00000-0000" class="field w-full h-12 px-4 rounded-xl text-sm" autocomplete="tel">
        </div>
        <div class="mb-4">
            <label class="field-label block mb-2 uppercase">E-mail (opcional)</label>
            <input type="email" id="input-email" placeholder="seu@email.com" class="field w-full h-12 px-4 rounded-xl text-sm" autocomplete="email">
        </div>
        <div class="mb-5">
            <label class="field-label block mb-2 uppercase">Observação (opcional)</label>
            <textarea id="input-observacao" rows="2" placeholder="Alguma preferência ou detalhe?" class="field w-full px-4 py-3 rounded-xl text-sm"></textarea>
        </div>

        <!-- Campo-armadilha contra bots — nunca preencher. -->
        <input type="text" id="input-site" name="site" class="campo-oculto" tabindex="-1" autocomplete="off">

        <div class="card p-4 mb-5" style="background:rgba(255,255,255,0.02);">
            <p class="text-xs font-semibold mb-1 uppercase" style="color:#7f8fac; letter-spacing:0.06em;">Revise seu agendamento</p>
            <div class="resumo-linha"><span style="color:#8b97ac;">Serviço</span><span id="resumo-servico">—</span></div>
            <div class="resumo-linha"><span style="color:#8b97ac;">Data</span><span id="resumo-data">—</span></div>
            <div class="resumo-linha"><span style="color:#8b97ac;">Horário</span><span id="resumo-hora">—</span></div>
            <div class="resumo-linha"><span style="color:#8b97ac;">Valor</span><span id="resumo-valor">—</span></div>
        </div>

        <div class="alerta mb-4 hidden" id="caixa-erro"></div>

        <div class="flex gap-3">
            <button type="button" class="btn-secundario h-12 px-5 rounded-xl text-sm" data-voltar="2">Voltar</button>
            <button type="button" class="btn-primario flex-1 h-12 rounded-xl text-sm" id="btn-confirmar">Confirmar agendamento</button>
        </div>
    </section>

    <!-- Sucesso -->
    <section class="card p-8 text-center hidden" data-step="sucesso">
        <div class="sucesso-icone">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M5 13l4 4L19 7" stroke="#22c55e" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>
        <h2 class="text-lg font-semibold mb-2">Agendamento confirmado!</h2>
        <p class="text-sm" style="color:#8b97ac;">Te esperamos <span id="sucesso-resumo" class="font-medium" style="color:var(--cream);"></span>.</p>
    </section>

    <p class="text-center text-xs mt-10 font-light" style="color:#4b5673;">
        Agendamento online — powered by BarbERP
    </p>
</div>

<script>
(function () {
    const TOKEN = <?= json_encode($token) ?>;
    const CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;

    const estado = {
        idServico: null,
        nomeServico: null,
        valorServico: null,
        data: null,
        hora: null,
        idHorario: null,
        mesAtual: parseInt('<?= (int) date('n') ?>', 10),
        anoAtual: parseInt('<?= (int) date('Y') ?>', 10),
    };
    const HOJE = new Date(<?= (int) date('Y') ?>, <?= (int) date('n') - 1 ?>, <?= (int) date('j') ?>);

    const MESES = ['janeiro','fevereiro','março','abril','maio','junho','julho','agosto','setembro','outubro','novembro','dezembro'];

    function irParaPasso(passo) {
        document.querySelectorAll('[data-step]').forEach(function (secao) {
            secao.classList.toggle('hidden', secao.dataset.step !== String(passo));
        });
        document.querySelectorAll('[data-step-indicador]').forEach(function (item) {
            const n = parseInt(item.dataset.stepIndicador, 10);
            item.classList.toggle('is-ativo', String(n) === String(passo));
            item.classList.toggle('is-feito', typeof passo === 'number' && n < passo);
        });
    }

    document.querySelectorAll('[data-voltar]').forEach(function (btn) {
        btn.addEventListener('click', function () { irParaPasso(parseInt(btn.dataset.voltar, 10)); });
    });

    // ---------- Passo 1: Serviço ----------
    document.querySelectorAll('.servico-card').forEach(function (card) {
        card.addEventListener('click', function () {
            document.querySelectorAll('.servico-card').forEach(function (c) { c.classList.remove('is-selecionado'); });
            card.classList.add('is-selecionado');
            estado.idServico = parseInt(card.dataset.idServico, 10);
            estado.nomeServico = card.dataset.nomeServico;
            estado.valorServico = card.dataset.valorServico;
            document.getElementById('btn-ir-passo-2').disabled = false;
        });
    });

    document.getElementById('btn-ir-passo-2').addEventListener('click', function () {
        irParaPasso(2);
        carregarMes(estado.anoAtual, estado.mesAtual);
    });

    // ---------- Passo 2: Calendário ----------
    function carregarMes(ano, mes) {
        document.getElementById('calendario-titulo-mes').textContent = MESES[mes - 1] + ' de ' + ano;
        const podeVoltar = !(ano === HOJE.getFullYear() && mes === HOJE.getMonth() + 1);
        document.getElementById('mes-anterior').disabled = !podeVoltar;

        fetch('../scripts/publico_mes_disponibilidade.php?t=' + encodeURIComponent(TOKEN) + '&mes=' + mes + '&ano=' + ano)
            .then(function (r) { return r.json(); })
            .then(function (resposta) {
                if (!resposta.ok) { return; }
                desenharCalendario(ano, mes, resposta.dias);
            });
    }

    function desenharCalendario(ano, mes, dias) {
        const container = document.getElementById('calendario-dias');
        container.innerHTML = '';

        const primeiroDiaSemana = new Date(ano, mes - 1, 1).getDay();
        for (let i = 0; i < primeiroDiaSemana; i++) {
            const vazio = document.createElement('div');
            vazio.className = 'dia is-vazio';
            container.appendChild(vazio);
        }

        const totalDias = new Date(ano, mes, 0).getDate();
        for (let d = 1; d <= totalDias; d++) {
            const chave = ano + '-' + String(mes).padStart(2, '0') + '-' + String(d).padStart(2, '0');
            const info = dias[chave] || { status: 'vermelho', bloqueado: false };

            const el = document.createElement('div');
            el.className = 'dia';
            el.textContent = d;

            const desabilitado = info.status === 'passado' || info.bloqueado;
            if (desabilitado) {
                el.classList.add('is-desabilitado');
            } else {
                const ponto = document.createElement('span');
                ponto.className = 'dia__ponto ' + info.status;
                el.appendChild(ponto);
                el.addEventListener('click', function () { selecionarDia(chave, el); });
            }

            if (estado.data === chave) {
                el.classList.add('is-selecionado');
            }

            container.appendChild(el);
        }
    }

    function selecionarDia(data, elementoClicado) {
        document.querySelectorAll('.dia.is-selecionado').forEach(function (d) { d.classList.remove('is-selecionado'); });
        elementoClicado.classList.add('is-selecionado');
        estado.data = data;
        estado.hora = null;
        estado.idHorario = null;
        document.getElementById('btn-ir-passo-3').disabled = true;

        const blocoHorarios = document.getElementById('bloco-horarios');
        const lista = document.getElementById('lista-horarios');
        const vazio = document.getElementById('horarios-vazio');
        blocoHorarios.style.display = 'block';
        lista.innerHTML = '<p class="text-xs" style="color:#7f8fac;">Carregando horários…</p>';
        vazio.style.display = 'none';

        fetch('../scripts/publico_horarios_buscar.php?t=' + encodeURIComponent(TOKEN) + '&data=' + data)
            .then(function (r) { return r.json(); })
            .then(function (resposta) {
                lista.innerHTML = '';
                if (!resposta.ok || resposta.horarios.length === 0) {
                    vazio.style.display = 'block';
                    return;
                }
                let algumLivre = false;
                resposta.horarios.forEach(function (h) {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'horario-btn';
                    btn.textContent = h.hora;
                    btn.disabled = !h.disponivel;
                    if (h.disponivel) {
                        algumLivre = true;
                        btn.addEventListener('click', function () { selecionarHorario(h, btn); });
                    }
                    lista.appendChild(btn);
                });
                if (!algumLivre) { vazio.style.display = 'block'; }
            });
    }

    function selecionarHorario(horario, elementoClicado) {
        document.querySelectorAll('.horario-btn.is-selecionado').forEach(function (b) { b.classList.remove('is-selecionado'); });
        elementoClicado.classList.add('is-selecionado');
        estado.hora = horario.hora;
        estado.idHorario = horario.idHorario;
        document.getElementById('btn-ir-passo-3').disabled = false;
    }

    document.getElementById('mes-anterior').addEventListener('click', function () {
        estado.mesAtual--;
        if (estado.mesAtual < 1) { estado.mesAtual = 12; estado.anoAtual--; }
        carregarMes(estado.anoAtual, estado.mesAtual);
    });
    document.getElementById('mes-seguinte').addEventListener('click', function () {
        estado.mesAtual++;
        if (estado.mesAtual > 12) { estado.mesAtual = 1; estado.anoAtual++; }
        carregarMes(estado.anoAtual, estado.mesAtual);
    });

    document.getElementById('btn-ir-passo-3').addEventListener('click', function () {
        document.getElementById('resumo-servico').textContent = estado.nomeServico;
        document.getElementById('resumo-data').textContent = formatarDataBr(estado.data);
        document.getElementById('resumo-hora').textContent = estado.hora;
        document.getElementById('resumo-valor').textContent = 'R$ ' + estado.valorServico;
        irParaPasso(3);
    });

    function formatarDataBr(dataIso) {
        const [ano, mes, dia] = dataIso.split('-');
        return dia + '/' + mes + '/' + ano;
    }

    // ---------- Passo 3: Confirmar ----------
    document.getElementById('btn-confirmar').addEventListener('click', function () {
        const caixaErro = document.getElementById('caixa-erro');
        caixaErro.classList.add('hidden');

        const nome = document.getElementById('input-nome').value.trim();
        const telefone = document.getElementById('input-telefone').value.trim();
        const email = document.getElementById('input-email').value.trim();
        const observacao = document.getElementById('input-observacao').value.trim();

        const botao = document.getElementById('btn-confirmar');
        botao.disabled = true;
        botao.textContent = 'Agendando…';

        const dados = new URLSearchParams();
        dados.set('t', TOKEN);
        dados.set('_csrf', CSRF_TOKEN);
        dados.set('idHorario', estado.idHorario);
        dados.set('idServico', estado.idServico);
        dados.set('nome', nome);
        dados.set('telefone', telefone);
        dados.set('email', email);
        dados.set('observacao', observacao);
        dados.set('site', document.getElementById('input-site').value);

        fetch('../scripts/publico_agendar_salvar.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: dados.toString(),
        })
            .then(function (r) { return r.json(); })
            .then(function (resposta) {
                if (!resposta.ok) {
                    caixaErro.textContent = resposta.erro || 'Não foi possível agendar. Tente novamente.';
                    caixaErro.classList.remove('hidden');
                    botao.disabled = false;
                    botao.textContent = 'Confirmar agendamento';
                    return;
                }
                document.getElementById('sucesso-resumo').textContent =
                    'dia ' + formatarDataBr(resposta.confirmacao.data) + ' às ' + resposta.confirmacao.hora;
                irParaPasso('sucesso');
            })
            .catch(function () {
                caixaErro.textContent = 'Erro de conexão. Tente novamente.';
                caixaErro.classList.remove('hidden');
                botao.disabled = false;
                botao.textContent = 'Confirmar agendamento';
            });
    });
})();
</script>

</body>
</html>

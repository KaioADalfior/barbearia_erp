<?php
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/guard.php';
exigirSessao(['barbeiro']);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/forma_pagamento.php';

$paginaAtual = 'agendamento-listar';
$idBarbeiro  = (int) $_SESSION['id'];

// Abreviações exibidas no badge da lista (o rótulo completo aparece no
// tooltip, via FORMAS_PAGAMENTO_LABELS de includes/forma_pagamento.php).
const FORMA_PAGAMENTO_SIGLA = [
    'pix'      => 'P',
    'credito'  => 'CC',
    'debito'   => 'CB',
    'dinheiro' => 'DIN',
    'boleto'   => 'BOL',
];

// ---------- Busca todos os agendamentos do barbeiro logado ----------
// LEFT JOIN com FinanceiroLancamentos (origem = 'agendamento') para trazer
// a forma de pagamento: só existe depois que o agendamento é concluído
// (Agendamentos/scripts/agendamento_concluir.php via FinanceiroService).
$stmt = $pdo->prepare(
    "SELECT a.idAgendamento, a.Data, a.Valor, a.IncluirBarba, a.Status,
            h.hora,
            c.nome AS clienteNome,
            s.nome AS servicoNome,
            fl.forma_pagamento, fl.status AS statusPagamento
     FROM Agendamentos a
     INNER JOIN Horario h  ON h.idHorario  = a.idHorario
     INNER JOIN Cliente c  ON c.idCliente  = a.idCliente
     INNER JOIN Servico s  ON s.idServico  = a.idServico
     LEFT JOIN FinanceiroLancamentos fl
            ON fl.idAgendamento = a.idAgendamento AND fl.origem = 'agendamento'
     WHERE h.id_barbeiro = :idBarbeiro
     ORDER BY a.Data DESC, h.hora DESC"
);
$stmt->execute(['idBarbeiro' => $idBarbeiro]);
$agendamentos = $stmt->fetchAll();

// ---------- Contagens para as abas de status ----------
$totais = ['todos' => count($agendamentos), 'agendado' => 0, 'confirmado' => 0, 'concluido' => 0, 'cancelado' => 0, 'ausente' => 0];
foreach ($agendamentos as $ag) {
    if (isset($totais[$ag['Status']])) {
        $totais[$ag['Status']]++;
    }
}

$STATUS_INFO = [
    'agendado'   => ['label' => 'Agendado',   'badge' => 'badge-agendado'],
    'confirmado' => ['label' => 'Confirmado', 'badge' => 'badge-confirmado'],
    'concluido'  => ['label' => 'Concluído',  'badge' => 'badge-concluido'],
    'cancelado'  => ['label' => 'Cancelado',  'badge' => 'badge-cancelado'],
    // "Cliente Ausente" (ver Agendamentos/scripts/agendamento_concluir.php):
    // conclui o horário sem gerar cobrança — badge em tom âmbar, igual ao
    // pontinho usado na grade de Agendar (ver agendar.php > status-dot-ausente),
    // pra manter a mesma cor/identidade visual em todo o sistema.
    'ausente'    => ['label' => 'Ausente',    'badge' => 'badge-ausente'],
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<?php include __DIR__ . '/../../includes/theme-init.php'; ?>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Agendamentos — BarbERP</title>

<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../../assets/css/admin-theme.css?v=2">

<style>
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

    .badge{
        display:inline-flex;
        align-items:center;
        gap:0.35rem;
        font-size:11px;
        font-weight:600;
        letter-spacing:0.04em;
        padding:0.28rem 0.65rem;
        border-radius:999px;
        white-space:nowrap;
    }
    .badge-agendado{   color:#9dc4f0; background:rgba(61,126,201,0.14); border:1px solid rgba(61,126,201,0.4); }
    .badge-confirmado{ color:#7fd0d9; background:rgba(15,92,102,0.32);   border:1px solid rgba(24,138,150,0.55); }
    .badge-concluido{  color:#bfe6c7; background:rgba(66,140,82,0.14);  border:1px solid rgba(66,140,82,0.4); }
    .badge-cancelado{  color:#c9a8ab; background:rgba(140,31,40,0.12); border:1px solid rgba(140,31,40,0.4); }
    .badge-ausente{    color:#f0c98a; background:rgba(224,162,74,0.14); border:1px solid rgba(224,162,74,0.4); }

    .forma-pills{ display:flex; flex-wrap:wrap; gap:0.3rem; }
    .forma-pill{
        display:inline-flex;
        align-items:center;
        justify-content:center;
        min-width:26px;
        height:22px;
        padding:0 0.4rem;
        border-radius:999px;
        font-size:10.5px;
        font-weight:700;
        letter-spacing:0.02em;
        color:#aebdd6;
        background:rgba(255,255,255,0.05);
        border:1px solid rgba(255,255,255,0.1);
        cursor:default;
    }
    .forma-pill--pendente{
        color:#9dc4f0;
        background:rgba(61,126,201,0.12);
        border:1px solid rgba(61,126,201,0.35);
        font-weight:500;
        font-size:11px;
        padding:0 0.55rem;
    }

    /* Abas de filtro por status */
    .status-tab{
        display:inline-flex;
        align-items:center;
        gap:0.4rem;
        padding:0.5rem 0.9rem;
        border-radius:0.7rem;
        font-size:12.5px;
        font-weight:500;
        color:#8294ad;
        border:1px solid rgba(255,255,255,0.07);
        background:rgba(255,255,255,0.02);
        transition:color .15s, border-color .15s, background-color .15s;
        white-space:nowrap;
    }
    .status-tab:hover{ color:var(--cream); border-color:rgba(61,126,201,0.3); }
    .status-tab.is-active{
        color:#ffffff;
        background:var(--gold);
        border-color:var(--gold);
        font-weight:600;
    }
    .status-tab__count{
        font-size:11px;
        opacity:0.75;
    }

    /* ---------- Tema claro: reforço de contraste ---------- */
    html[data-theme="light"] .badge-agendado{   color:#1e4976; }
    html[data-theme="light"] .badge-confirmado{ color:#0d6b74; }
    html[data-theme="light"] .badge-concluido{  color:#1f6b30; }
    html[data-theme="light"] .badge-cancelado{  color:#8c1f28; }
    html[data-theme="light"] .forma-pill{ color:#1e293b; }
    html[data-theme="light"] .forma-pill--pendente{ color:#1e4976; }
    html[data-theme="light"] .status-tab{ color:#475569; }
    html[data-theme="light"] .status-tab.is-active{ color:#fff; }
</style>
</head>
<body class="flex">

<?php include __DIR__ . '/../../includes/sidebar_barbeiro.php'; ?>
<?php include __DIR__ . '/../../includes/toast.php'; ?>

<!-- Modal de confirmação de exclusão em massa dos cancelados — próprio,
     sem depender de nenhuma biblioteca externa (esta página não carrega
     SweetAlert2/Swal, ao contrário de Agendamentos/paginas/agendar.php). -->
<div id="modal-excluir-cancelados" class="fixed inset-0 z-50 hidden items-center justify-center p-4" style="background:rgba(0,0,0,0.65);">
    <div class="panel-card rounded-2xl p-6 w-full" style="max-width:580px;">
        <div class="flex items-start gap-3 mb-3">
            <div style="width:36px; height:36px; border-radius:999px; background:rgba(179,65,61,0.15); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z" stroke="#e0a2a8" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <div class="min-w-0">
                <h2 class="text-base font-semibold text-[color:var(--cream)]">Excluir agendamentos cancelados?</h2>
                <p id="excluir-cancelados-resumo" class="text-xs text-zinc-400 mt-1"></p>
            </div>
        </div>

        <div style="max-height:280px; overflow-y:auto; border:1px solid rgba(255,255,255,0.08); border-radius:10px;" class="mb-5">
            <table style="width:100%; font-size:12.5px; border-collapse:collapse;">
                <tbody id="excluir-cancelados-lista"></tbody>
            </table>
        </div>

        <div class="flex gap-3 justify-end">
            <button type="button" onclick="fecharModalExcluirCancelados()" class="btn-secondary h-10 px-5 rounded-xl text-sm">
                Voltar
            </button>
            <button type="button" id="btn-confirmar-exclusao-cancelados" onclick="confirmarExclusaoCancelados()" class="h-10 px-5 rounded-xl text-sm" style="background:#b3413d; color:#fff; font-weight:600;">
                Sim, excluir tudo isso
            </button>
        </div>
    </div>
</div>

<main class="flex-1 min-w-0">

    <header class="topbar px-5 sm:px-8 py-5 sm:py-6 flex items-center gap-4">
        <button type="button" onclick="abrirMenuMobile()" class="menu-toggle-btn lg:hidden" aria-label="Abrir menu">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M4 6h16M4 12h16M4 18h16" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
            </svg>
        </button>
        <div class="min-w-0">
            <p class="eyebrow uppercase mb-1" style="color:var(--gold-light); opacity:.75">Agendamentos</p>
            <h1 class="display text-3xl sm:text-4xl text-[color:var(--cream)] truncate">Agendamentos</h1>
        </div>
    </header>

    <section class="p-5 sm:p-8">

        <!-- Barra de ferramentas: busca + abas de status -->
        <div class="panel-card rounded-2xl p-4 mb-6 flex flex-col gap-4">

            <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                <div class="relative w-full sm:max-w-sm">
                    <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="11" cy="11" r="6.5" stroke="#7f8fac" stroke-width="1.5"/>
                        <path d="M20 20l-4.3-4.3" stroke="#7f8fac" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                    <input
                        id="busca-agendamento"
                        type="text"
                        placeholder="Buscar por nome, corte ou data..."
                        autocomplete="off"
                        class="field w-full h-11 pl-10 pr-4 rounded-xl text-sm"
                        oninput="filtrarAgendamentos()"
                    >
                </div>

                <button
                    type="button"
                    id="btn-excluir-cancelados"
                    onclick="abrirConfirmacaoExclusaoCancelados()"
                    class="btn-secondary h-11 px-4 rounded-xl text-sm inline-flex items-center gap-2 shrink-0"
                    style="color:#e0a2a8; border-color:rgba(140,31,40,0.4);"
                >
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M4 7h16M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2m2 0-.87 12.14A2 2 0 0 1 16.14 21H7.86a2 2 0 0 1-1.99-1.86L5 7" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Excluir Agendamentos cancelados
                </button>
            </div>

            <div class="flex flex-wrap gap-2" id="abas-status">
                <button type="button" class="status-tab is-active" data-status="todos" onclick="selecionarAba(this)">
                    Todos <span class="status-tab__count">(<?= $totais['todos'] ?>)</span>
                </button>
                <button type="button" class="status-tab" data-status="agendado" onclick="selecionarAba(this)">
                    Agendado <span class="status-tab__count">(<?= $totais['agendado'] ?>)</span>
                </button>
                <button type="button" class="status-tab" data-status="confirmado" onclick="selecionarAba(this)">
                    Confirmado <span class="status-tab__count">(<?= $totais['confirmado'] ?>)</span>
                </button>
                <button type="button" class="status-tab" data-status="concluido" onclick="selecionarAba(this)">
                    Concluído <span class="status-tab__count">(<?= $totais['concluido'] ?>)</span>
                </button>
                <button type="button" class="status-tab" data-status="cancelado" onclick="selecionarAba(this)">
                    Cancelado <span class="status-tab__count">(<?= $totais['cancelado'] ?>)</span>
                </button>
                <button type="button" class="status-tab" data-status="ausente" onclick="selecionarAba(this)">
                    Ausente <span class="status-tab__count">(<?= $totais['ausente'] ?>)</span>
                </button>
            </div>
        </div>

        <?php if (empty($agendamentos)): ?>

            <div class="panel-card rounded-2xl p-10 text-center">
                <p class="text-sm text-zinc-400">Nenhum agendamento cadastrado ainda.</p>
                <a href="agendar.php" class="btn-secondary h-10 px-5 rounded-xl text-sm mt-4 inline-flex items-center">
                    Ir para Agendar
                </a>
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
                                <th class="px-6 py-4 font-medium">Corte</th>
                                <th class="px-6 py-4 font-medium">Data e Hora</th>
                                <th class="px-6 py-4 font-medium">Status</th>
                                <th class="px-6 py-4 font-medium">Pagamento</th>
                                <th class="px-6 py-4 font-medium text-right">Valor</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-agendamentos">
                            <?php foreach ($agendamentos as $ag):
                                $dataFmt   = (new DateTime($ag['Data']))->format('d/m/Y');
                                $horaFmt   = substr($ag['hora'], 0, 5);
                                $valorFmt  = number_format((float) $ag['Valor'], 2, ',', '.');
                                $status    = $ag['Status'];
                                $statusCfg = $STATUS_INFO[$status] ?? ['label' => ucfirst($status), 'badge' => 'badge-agendado'];

                                $formas = $ag['forma_pagamento'] ? explode(',', $ag['forma_pagamento']) : [];

                                $textoBusca = $ag['clienteNome'] . ' ' . $ag['servicoNome'] . ' ' . $dataFmt . ' ' . $horaFmt . ' ' . $statusCfg['label'];
                                $busca = function_exists('mb_strtolower')
                                    ? mb_strtolower($textoBusca, 'UTF-8')
                                    : strtolower($textoBusca);
                            ?>
                            <tr data-busca="<?= htmlspecialchars($busca) ?>" data-status="<?= htmlspecialchars($status) ?>">
                                <td class="px-6 py-4 text-[color:var(--cream)] font-medium"><?= htmlspecialchars($ag['clienteNome']) ?></td>
                                <td class="px-6 py-4 text-zinc-400">
                                    <?= htmlspecialchars($ag['servicoNome']) ?>
                                    <?php if ($ag['IncluirBarba']): ?>
                                        <span class="text-xs text-zinc-500">+ Barba</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-zinc-400">
                                    <?= $dataFmt ?> <span class="text-zinc-500">às</span> <?= $horaFmt ?>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="badge <?= $statusCfg['badge'] ?>"><?= $statusCfg['label'] ?></span>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if (!empty($formas)): ?>
                                        <div class="forma-pills">
                                            <?php foreach ($formas as $f):
                                                $sigla = FORMA_PAGAMENTO_SIGLA[$f] ?? strtoupper(substr($f, 0, 2));
                                                $label = FORMAS_PAGAMENTO_LABELS[$f] ?? $f;
                                            ?>
                                                <span class="forma-pill" title="<?= htmlspecialchars($label) ?>"><?= htmlspecialchars($sigla) ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php elseif ($status === 'concluido' && $ag['statusPagamento'] === 'pendente'): ?>
                                        <span class="forma-pill forma-pill--pendente">Fiado</span>
                                    <?php else: ?>
                                        <span class="text-xs text-zinc-500">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-right text-[color:var(--cream)] font-medium">R$ <?= $valorFmt ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <tr id="linha-sem-resultado" class="hidden">
                                <td colspan="6" class="px-6 py-10 text-center text-sm text-zinc-500">Nenhum agendamento encontrado para o filtro atual.</td>
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

<script>
    let abaAtual = 'todos';

    function selecionarAba(botao) {
        document.querySelectorAll('#abas-status .status-tab').forEach(function (b) {
            b.classList.remove('is-active');
        });
        botao.classList.add('is-active');
        abaAtual = botao.dataset.status;
        filtrarAgendamentos();
    }

    function filtrarAgendamentos() {
        const termo = document.getElementById('busca-agendamento').value.trim().toLowerCase();
        const linhas = document.querySelectorAll('#tbody-agendamentos tr[data-busca]');
        let visiveis = 0;

        linhas.forEach(function (linha) {
            const correspondeBusca  = linha.dataset.busca.includes(termo);
            const correspondeStatus = abaAtual === 'todos' || linha.dataset.status === abaAtual;
            const visivel = correspondeBusca && correspondeStatus;
            linha.classList.toggle('hidden', !visivel);
            if (visivel) visiveis++;
        });

        const linhaSemResultado = document.getElementById('linha-sem-resultado');
        if (linhaSemResultado) {
            linhaSemResultado.classList.toggle('hidden', visiveis !== 0);
        }
    }

    function escapeHtml(texto) {
        const div = document.createElement('div');
        div.textContent = texto ?? '';
        return div.innerHTML;
    }

    // Botão "Excluir Agendamentos cancelados": primeiro busca e MOSTRA a
    // lista completa dos cancelados do barbeiro (pra ele conferir se bate
    // com o que espera ver no sistema antes de decidir), só depois pede a
    // confirmação final de exclusão — dois passos de propósito, porque é
    // uma exclusão permanente e em massa. Usa o modal próprio definido no
    // topo da página (#modal-excluir-cancelados), sem depender de nenhuma
    // biblioteca externa de pop-up.
    let totalCanceladosParaExcluir = 0;

    async function abrirConfirmacaoExclusaoCancelados() {
        const btn = document.getElementById('btn-excluir-cancelados');
        btn.disabled = true;

        try {
            const resposta = await fetch('../scripts/agendamento_cancelados_listar.php');
            const dados = await resposta.json();

            if (!dados.ok) {
                toast(dados.erro || 'Não foi possível carregar os agendamentos cancelados.', 'erro');
                return;
            }

            if (dados.total === 0) {
                toast('Não há agendamentos cancelados no momento.', 'sucesso');
                return;
            }

            totalCanceladosParaExcluir = dados.total;

            document.getElementById('excluir-cancelados-resumo').textContent =
                'Confira a lista abaixo antes de confirmar — são ' + dados.total + ' agendamento(s) cancelado(s) que serão excluídos PERMANENTEMENTE.';

            document.getElementById('excluir-cancelados-lista').innerHTML = dados.itens.map(function (i) {
                return '<tr style="border-top:1px solid rgba(255,255,255,0.08);">'
                    + '<td style="padding:6px 8px; text-align:left; color:var(--cream);">' + escapeHtml(i.cliente) + '</td>'
                    + '<td style="padding:6px 8px; text-align:left; color:#9aa7bd;">' + escapeHtml(i.servico) + '</td>'
                    + '<td style="padding:6px 8px; text-align:right; color:#9aa7bd; white-space:nowrap;">' + escapeHtml(i.data) + ' ' + escapeHtml(i.hora) + '</td>'
                    + '</tr>';
            }).join('');

            const modal = document.getElementById('modal-excluir-cancelados');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        } catch (e) {
            toast('Erro de conexão. Tente novamente.', 'erro');
        } finally {
            btn.disabled = false;
        }
    }

    function fecharModalExcluirCancelados() {
        const modal = document.getElementById('modal-excluir-cancelados');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    async function confirmarExclusaoCancelados() {
        const btn = document.getElementById('btn-confirmar-exclusao-cancelados');
        btn.disabled = true;
        btn.textContent = 'Excluindo...';

        try {
            const resposta = await fetch('../scripts/agendamento_cancelados_excluir.php', { method: 'POST' });
            const dados = await resposta.json();

            if (!dados.ok) {
                toast(dados.erro || 'Não foi possível excluir os agendamentos cancelados.', 'erro');
                btn.disabled = false;
                btn.textContent = 'Sim, excluir tudo isso';
                return;
            }

            fecharModalExcluirCancelados();
            toast(dados.totalExcluido + ' agendamento(s) cancelado(s) excluído(s) com sucesso.', 'sucesso');
            setTimeout(function () { window.location.reload(); }, 900);
        } catch (e) {
            toast('Erro de conexão. Tente novamente.', 'erro');
            btn.disabled = false;
            btn.textContent = 'Sim, excluir tudo isso';
        }
    }
</script>

</body>
</html>
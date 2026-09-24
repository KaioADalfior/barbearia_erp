<?php
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/guard.php';
exigirSessao(['barbeiro']);
require_once __DIR__ . '/../../config/config.php';

$paginaAtual = 'dashboard';
$idBarbeiro  = (int) ($_SESSION['id'] ?? 0);

// =====================================================================
// MÉTRICAS DO DASHBOARD
// =====================================================================

// ---------- Clientes ao total (ativos no sistema) ----------
$totalClientes = (int) $pdo->query('SELECT COUNT(*) FROM Cliente WHERE ativo = 1')->fetchColumn();

// ---------- Agendamentos ao total (deste barbeiro) ----------
$stmt = $pdo->prepare('
    SELECT COUNT(*)
    FROM Agendamentos a
    JOIN Horario h ON h.idHorario = a.idHorario
    WHERE h.id_barbeiro = :id
');
$stmt->execute(['id' => $idBarbeiro]);
$totalAgendamentos = (int) $stmt->fetchColumn();

// ---------- Agendamentos por status (deste barbeiro) ----------
$stmt = $pdo->prepare('
    SELECT a.Status, COUNT(*) AS qtd
    FROM Agendamentos a
    JOIN Horario h ON h.idHorario = a.idHorario
    WHERE h.id_barbeiro = :id
    GROUP BY a.Status
');
$stmt->execute(['id' => $idBarbeiro]);
$porStatus = ['agendado' => 0, 'confirmado' => 0, 'concluido' => 0, 'cancelado' => 0];
foreach ($stmt->fetchAll() as $linha) {
    $porStatus[$linha['Status']] = (int) $linha['qtd'];
}
$totalConcluidos = $porStatus['concluido'];
$totalCancelados = $porStatus['cancelado'];
$totalEmAberto   = $porStatus['agendado'] + $porStatus['confirmado'];

$totalFinalizados = $totalConcluidos + $totalCancelados;
$pctConcluidos = $totalFinalizados > 0 ? round(($totalConcluidos / $totalFinalizados) * 100) : 0;
$pctCancelados = $totalFinalizados > 0 ? round(($totalCancelados / $totalFinalizados) * 100) : 0;

// ---------- Agendamentos de hoje (deste barbeiro) ----------
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM Agendamentos a
    JOIN Horario h ON h.idHorario = a.idHorario
    WHERE h.id_barbeiro = :id
      AND a.Data = CURDATE()
      AND a.Status IN ('agendado','confirmado')
");
$stmt->execute(['id' => $idBarbeiro]);
$agendamentosHoje = (int) $stmt->fetchColumn();

// ---------- Concluídos x Cancelados por mês (últimos 6 meses, deste barbeiro) ----------
$stmt = $pdo->prepare("
    SELECT DATE_FORMAT(a.Data, '%Y-%m') AS mes, a.Status, COUNT(*) AS qtd
    FROM Agendamentos a
    JOIN Horario h ON h.idHorario = a.idHorario
    WHERE h.id_barbeiro = :id
      AND a.Status IN ('concluido','cancelado')
      AND a.Data >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 5 MONTH)
    GROUP BY mes, a.Status
");
$stmt->execute(['id' => $idBarbeiro]);
$brutoMensal = [];
foreach ($stmt->fetchAll() as $linha) {
    $brutoMensal[$linha['mes']][$linha['Status']] = (int) $linha['qtd'];
}

$nomesMeses = ['01' => 'Jan', '02' => 'Fev', '03' => 'Mar', '04' => 'Abr', '05' => 'Mai', '06' => 'Jun',
               '07' => 'Jul', '08' => 'Ago', '09' => 'Set', '10' => 'Out', '11' => 'Nov', '12' => 'Dez'];

$rotulosMensal    = [];
$concluidosMensal = [];
$canceladosMensal = [];
for ($i = 5; $i >= 0; $i--) {
    $chave = date('Y-m', strtotime("-$i months"));
    $mes   = substr($chave, 5, 2);
    $rotulosMensal[]    = $nomesMeses[$mes] . '/' . substr($chave, 2, 2);
    $concluidosMensal[] = $brutoMensal[$chave]['concluido'] ?? 0;
    $canceladosMensal[] = $brutoMensal[$chave]['cancelado'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<?php include __DIR__ . '/../../includes/theme-init.php'; ?>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>BarbERP — Painel do Barbeiro</title>

<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../../assets/css/admin-theme.css?v=2">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>

<style>
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
    .kpi-clientes    .kpi-card__icon{ background:rgba(15,92,102,0.16); color:#7fd0d9; }
    .kpi-agendamentos .kpi-card__icon{ background:rgba(61,126,201,0.16); color:var(--gold-light); }
    .kpi-concluidos  .kpi-card__icon{ background:rgba(66,140,82,0.16); color:#4fae67; }
    .kpi-cancelados  .kpi-card__icon{ background:rgba(140,31,40,0.16); color:#c94550; }

    html[data-theme="light"] .kpi-clientes    .kpi-card__icon{ color:#0d6b74; }
    html[data-theme="light"] .kpi-agendamentos .kpi-card__icon{ color:#1e4976; }
    html[data-theme="light"] .kpi-concluidos  .kpi-card__icon{ color:#2c6b3c; }
    html[data-theme="light"] .kpi-cancelados  .kpi-card__icon{ color:#8c1f28; }

    .kpi-card__trend{
        font-size:11.5px;
        font-weight:600;
        padding:0.2rem 0.55rem;
        border-radius:999px;
    }
    .trend-positivo{ color:#4fae67; background:rgba(66,140,82,0.14); }
    .trend-negativo{ color:#e08b91; background:rgba(140,31,40,0.14); }
    .trend-neutro{ color:#7fd0d9; background:rgba(15,92,102,0.16); }
    html[data-theme="light"] .trend-positivo{ color:#2c6b3c; }
    html[data-theme="light"] .trend-negativo{ color:#8c1f28; }
    html[data-theme="light"] .trend-neutro{ color:#0d6b74; }

    .chart-wrap{ position:relative; height:280px; }
    .chart-wrap-sm{ position:relative; height:240px; }

    .legend-item{
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:0.75rem;
        padding:0.6rem 0;
        border-top:1px solid rgba(255,255,255,0.06);
        font-size:13px;
    }
    html[data-theme="light"] .legend-item{ border-top:1px solid rgba(0,0,0,0.06); }
    .legend-item:first-child{ border-top:none; }
    .legend-dot{ width:9px; height:9px; border-radius:999px; flex-shrink:0; }

    .empty-state{
        display:flex;
        flex-direction:column;
        align-items:center;
        justify-content:center;
        text-align:center;
        height:100%;
        color:#7f8fac;
        gap:0.5rem;
        padding:2rem 1rem;
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
            <p class="eyebrow uppercase mb-1" style="color:var(--gold-light); opacity:.75">Painel do Barbeiro</p>
            <h1 class="display text-3xl sm:text-4xl text-[color:var(--cream)] truncate">Bem-vindo, <?= htmlspecialchars($_SESSION['nome'] ?? 'Barbeiro') ?></h1>
        </div>
    </header>

    <section class="p-5 sm:p-8">

        <!-- ==================== KPIs ==================== -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">

            <div class="kpi-card kpi-clientes rounded-2xl p-5">
                <div class="kpi-card__icon mb-4">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="9" cy="8" r="3.2" stroke="currentColor" stroke-width="1.6"/>
                        <path d="M2.5 19c0-3.4 2.9-6 6.5-6s6.5 2.6 6.5 6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                        <path d="M15.5 5.3c1.5.35 2.6 1.6 2.6 3.1s-1.1 2.75-2.6 3.1M18 13.3c2.1.5 3.6 2 3.6 3.9" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                    </svg>
                </div>
                <p class="text-[11px] uppercase tracking-widest text-zinc-500 mb-1">Clientes ao total</p>
                <p class="text-xl sm:text-2xl font-semibold text-[color:var(--cream)]"><?= number_format($totalClientes, 0, ',', '.') ?></p>
            </div>

            <div class="kpi-card kpi-agendamentos rounded-2xl p-5">
                <div class="kpi-card__icon mb-4">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="3.5" y="4.5" width="17" height="16" rx="2" stroke="currentColor" stroke-width="1.6"/>
                        <path d="M3.5 9.5h17" stroke="currentColor" stroke-width="1.6"/>
                        <path d="M8 3v3M16 3v3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                    </svg>
                </div>
                <p class="text-[11px] uppercase tracking-widest text-zinc-500 mb-1">Agendamentos ao total</p>
                <p class="text-xl sm:text-2xl font-semibold text-[color:var(--cream)]"><?= number_format($totalAgendamentos, 0, ',', '.') ?></p>
            </div>

            <div class="kpi-card kpi-concluidos rounded-2xl p-5">
                <div class="kpi-card__icon mb-4">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.6"/>
                        <path d="M8 12.3l2.6 2.6L16.2 9" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <p class="text-[11px] uppercase tracking-widest text-zinc-500 mb-1">Concluídos</p>
                <div class="flex items-baseline gap-2">
                    <p class="text-xl sm:text-2xl font-semibold text-[color:var(--cream)]"><?= number_format($totalConcluidos, 0, ',', '.') ?></p>
                    <?php if ($totalFinalizados > 0): ?>
                        <span class="kpi-card__trend trend-positivo"><?= $pctConcluidos ?>%</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="kpi-card kpi-cancelados rounded-2xl p-5">
                <div class="kpi-card__icon mb-4">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.6"/>
                        <path d="M9 9l6 6M15 9l-6 6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                    </svg>
                </div>
                <p class="text-[11px] uppercase tracking-widest text-zinc-500 mb-1">Cancelados</p>
                <div class="flex items-baseline gap-2">
                    <p class="text-xl sm:text-2xl font-semibold text-[color:var(--cream)]"><?= number_format($totalCancelados, 0, ',', '.') ?></p>
                    <?php if ($totalFinalizados > 0): ?>
                        <span class="kpi-card__trend trend-negativo"><?= $pctCancelados ?>%</span>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <?php if ($agendamentosHoje > 0): ?>
        <div class="panel-card rounded-2xl px-5 py-4 mb-6 flex items-center gap-3">
            <span class="kpi-card__trend trend-neutro shrink-0">Hoje</span>
            <p class="text-sm text-[color:var(--cream)]">
                Você tem <strong><?= $agendamentosHoje ?></strong> agendamento<?= $agendamentosHoje > 1 ? 's' : '' ?> confirmado<?= $agendamentosHoje > 1 ? 's' : '' ?> para hoje.
            </p>
        </div>
        <?php endif; ?>

        <!-- ==================== GRÁFICOS ==================== -->
        <div class="grid grid-cols-1 lg:grid-cols-5 gap-5 mb-6">

            <div class="panel-card rounded-2xl p-5 sm:p-6 lg:col-span-2">
                <p class="eyebrow uppercase mb-1" style="color:var(--gold-light); opacity:.75">Visão geral</p>
                <h2 class="display text-xl sm:text-2xl text-[color:var(--cream)] mb-4">Concluídos x Cancelados</h2>

                <?php if ($totalFinalizados > 0): ?>
                    <div class="chart-wrap-sm">
                        <canvas id="chart-status"></canvas>
                    </div>
                    <div class="mt-2" id="legenda-status"></div>
                <?php else: ?>
                    <div class="chart-wrap-sm empty-state">
                        <svg width="34" height="34" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.4"/>
                            <path d="M9 12h6M12 9v6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
                        </svg>
                        <p class="text-sm">Ainda não há agendamentos concluídos ou cancelados.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="panel-card rounded-2xl p-5 sm:p-6 lg:col-span-3">
                <p class="eyebrow uppercase mb-1" style="color:var(--gold-light); opacity:.75">Últimos 6 meses</p>
                <h2 class="display text-xl sm:text-2xl text-[color:var(--cream)] mb-4">Evolução mensal</h2>

                <?php if (array_sum($concluidosMensal) + array_sum($canceladosMensal) > 0): ?>
                    <div class="chart-wrap">
                        <canvas id="chart-mensal"></canvas>
                    </div>
                <?php else: ?>
                    <div class="chart-wrap empty-state">
                        <svg width="34" height="34" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M4 19V5M4 19h16M8 15l3-3.5 3 2.5 4-6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <p class="text-sm">Sem dados suficientes nos últimos 6 meses.</p>
                    </div>
                <?php endif; ?>
            </div>

        </div>

        <!-- ==================== AÇÕES RÁPIDAS ==================== -->
        <p class="eyebrow text-yellow-500/70 uppercase mb-3">Ações rápidas</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5 max-w-5xl">

            <a href="../../Agendamentos/paginas/agendar.php" class="panel-card block rounded-2xl p-6 hover:border-yellow-600/40 transition-colors">
                <div class="w-11 h-11 rounded-xl bg-yellow-500/10 flex items-center justify-center mb-4">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="3.5" y="4.5" width="17" height="16" rx="2" stroke="#6fa8ea" stroke-width="1.5"/>
                        <path d="M3.5 9.5h17" stroke="#6fa8ea" stroke-width="1.5"/>
                        <path d="M8 3v3M16 3v3" stroke="#6fa8ea" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                </div>
                <p class="text-sm font-medium text-[color:var(--cream)] mb-1">Agendar</p>
                <p class="text-xs text-zinc-500">Ver o calendário e marcar um horário</p>
            </a>

            <a href="../../Clientes/paginas/cliente_listar.php" class="panel-card block rounded-2xl p-6 hover:border-yellow-600/40 transition-colors">
                <div class="w-11 h-11 rounded-xl bg-yellow-500/10 flex items-center justify-center mb-4">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="12" cy="8" r="3.3" stroke="#6fa8ea" stroke-width="1.5"/>
                        <path d="M5 20c0-3.6 3.1-6.5 7-6.5s7 2.9 7 6.5" stroke="#6fa8ea" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                </div>
                <p class="text-sm font-medium text-[color:var(--cream)] mb-1">Gerenciar Clientes</p>
                <p class="text-xs text-zinc-500">Listar, cadastrar, editar e inativar clientes</p>
            </a>

            <a href="../../Agendamentos/paginas/agendamento_listar.php" class="panel-card block rounded-2xl p-6 hover:border-yellow-600/40 transition-colors">
                <div class="w-11 h-11 rounded-xl bg-yellow-500/10 flex items-center justify-center mb-4">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M8 6h12M8 12h12M8 18h12" stroke="#6fa8ea" stroke-width="1.5" stroke-linecap="round"/>
                        <circle cx="4" cy="6" r="1.3" fill="#6fa8ea"/>
                        <circle cx="4" cy="12" r="1.3" fill="#6fa8ea"/>
                        <circle cx="4" cy="18" r="1.3" fill="#6fa8ea"/>
                    </svg>
                </div>
                <p class="text-sm font-medium text-[color:var(--cream)] mb-1">Agendamentos</p>
                <p class="text-xs text-zinc-500">Ver, filtrar e gerenciar todos os agendamentos</p>
            </a>

            <a href="../../Financeiro/paginas/financeiro_dashboard.php" class="panel-card block rounded-2xl p-6 hover:border-yellow-600/40 transition-colors">
                <div class="w-11 h-11 rounded-xl bg-yellow-500/10 flex items-center justify-center mb-4">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 3.5v17M16.5 7.2c0-1.6-1.6-2.7-4-2.7-2.6 0-4.3 1.2-4.3 3s1.4 2.5 4.3 3c2.9.5 4.3 1.3 4.3 3.1 0 1.8-1.8 3-4.3 3-2.2 0-4-1-4.3-2.6" stroke="#6fa8ea" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <p class="text-sm font-medium text-[color:var(--cream)] mb-1">Financeiro</p>
                <p class="text-xs text-zinc-500">Ver receitas, saídas e saldo do período</p>
            </a>

            <a href="../../Financeiro/paginas/financeiro_baixa.php" class="panel-card block rounded-2xl p-6 hover:border-yellow-600/40 transition-colors">
                <div class="w-11 h-11 rounded-xl bg-yellow-500/10 flex items-center justify-center mb-4">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="4" y="6" width="16" height="12" rx="2" stroke="#6fa8ea" stroke-width="1.5"/>
                        <path d="M4 10h16" stroke="#6fa8ea" stroke-width="1.5"/>
                        <path d="M8 14h4" stroke="#6fa8ea" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                </div>
                <p class="text-sm font-medium text-[color:var(--cream)] mb-1">Cadastrar Baixa</p>
                <p class="text-xs text-zinc-500">Lançar uma entrada ou saída manual</p>
            </a>

            <a href="../../Financeiro/paginas/financeiro_aReceber.php" class="panel-card block rounded-2xl p-6 hover:border-yellow-600/40 transition-colors">
                <div class="w-11 h-11 rounded-xl bg-yellow-500/10 flex items-center justify-center mb-4">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="12" cy="12" r="8.5" stroke="#6fa8ea" stroke-width="1.5"/>
                        <path d="M12 7.5v5l3.3 2" stroke="#6fa8ea" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <p class="text-sm font-medium text-[color:var(--cream)] mb-1">Contas a Receber</p>
                <p class="text-xs text-zinc-500">Ver e receber fiados em aberto</p>
            </a>

            <a href="../../Servicos/paginas/servico_listar.php" class="panel-card block rounded-2xl p-6 hover:border-yellow-600/40 transition-colors">
                <div class="w-11 h-11 rounded-xl bg-yellow-500/10 flex items-center justify-center mb-4">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M6.5 5.5a2.5 2.5 0 1 1 3.4 3.4L18 17.5" stroke="#6fa8ea" stroke-width="1.4" stroke-linecap="round"/>
                        <path d="M6.5 18.5a2.5 2.5 0 1 0 3.4-3.4L18 6.5" stroke="#6fa8ea" stroke-width="1.4" stroke-linecap="round"/>
                        <circle cx="6.2" cy="6.2" r="1.5" stroke="#6fa8ea" stroke-width="1.2"/>
                        <circle cx="6.2" cy="17.8" r="1.5" stroke="#6fa8ea" stroke-width="1.2"/>
                    </svg>
                </div>
                <p class="text-sm font-medium text-[color:var(--cream)] mb-1">Serviços</p>
                <p class="text-xs text-zinc-500">Gerenciar os cortes e preços cadastrados</p>
            </a>

        </div>
    </section>

</main>

<?php if (($_GET['login'] ?? '') === 'sucesso'): ?>
<script>toast('Login realizado com sucesso!');</script>
<?php endif; ?>

<script>
const ehTemaClaro = document.documentElement.getAttribute('data-theme') === 'light';
const corTexto  = ehTemaClaro ? '#1e293b' : '#7f8fac';
const corGrade  = ehTemaClaro ? 'rgba(0,0,0,0.06)' : 'rgba(255,255,255,0.05)';
const corConcluido = ehTemaClaro ? '#2c6b3c' : 'rgba(66,140,82,0.85)';
const corCancelado = ehTemaClaro ? '#8c1f28' : 'rgba(140,31,40,0.85)';

<?php if ($totalFinalizados > 0): ?>
// ---- Gráfico: Concluídos x Cancelados (doughnut) ----
const dadosStatus = {
    labels: ['Concluídos', 'Cancelados'],
    valores: [<?= $totalConcluidos ?>, <?= $totalCancelados ?>],
    cores: [corConcluido, corCancelado]
};
new Chart(document.getElementById('chart-status').getContext('2d'), {
    type: 'doughnut',
    data: {
        labels: dadosStatus.labels,
        datasets: [{
            data: dadosStatus.valores,
            backgroundColor: dadosStatus.cores,
            borderColor: ehTemaClaro ? '#ffffff' : '#131c2e',
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

const totalStatus = dadosStatus.valores.reduce((a, b) => a + b, 0) || 1;
document.getElementById('legenda-status').innerHTML = dadosStatus.labels.map((label, i) => {
    const pct = ((dadosStatus.valores[i] / totalStatus) * 100).toFixed(0);
    return '<div class="legend-item">' +
        '<span class="flex items-center gap-2" style="color:var(--cream)">' +
            '<span class="legend-dot" style="background:' + dadosStatus.cores[i] + '"></span>' +
            label +
        '</span>' +
        '<span style="color:' + corTexto + '">' + dadosStatus.valores[i] + ' (' + pct + '%)</span>' +
    '</div>';
}).join('');
<?php endif; ?>

<?php if (array_sum($concluidosMensal) + array_sum($canceladosMensal) > 0): ?>
// ---- Gráfico: Evolução mensal (barras agrupadas) ----
new Chart(document.getElementById('chart-mensal').getContext('2d'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($rotulosMensal) ?>,
        datasets: [
            { label: 'Concluídos', data: <?= json_encode($concluidosMensal) ?>, backgroundColor: corConcluido, borderRadius: 6, maxBarThickness: 30 },
            { label: 'Cancelados', data: <?= json_encode($canceladosMensal) ?>, backgroundColor: corCancelado, borderRadius: 6, maxBarThickness: 30 }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { labels: { color: corTexto, font: { family: 'Poppins' } } }
        },
        scales: {
            x: { ticks: { color: corTexto }, grid: { display: false } },
            y: { ticks: { color: corTexto, precision: 0 }, grid: { color: corGrade }, beginAtZero: true }
        }
    }
});
<?php endif; ?>
</script>

</body>
</html>
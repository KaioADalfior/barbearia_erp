<?php
/**
 * includes/comissao_view.php
 *
 * Tela de COMISSÕES compartilhada por:
 *   - Financeiro/paginas/financeiro_comissoes.php  ($cmModo = 'proprietario')
 *   - Financeiro/paginas/financeiro_meu.php        ($cmModo = 'funcionario')
 *
 * O arquivo que inclui esta view já fez a verificação de permissão e já
 * preparou: $pdo, $cmModo, $paginaAtual, $filtros (já autorizados, ver
 * ComissaoService::filtrosAutorizados). Aqui só se monta a consulta e o HTML.
 * Funcionário nunca recebe dados de outro funcionário: $filtros['funcionario']
 * já vem travado no próprio id.
 */

require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/forma_pagamento.php';
require_once __DIR__ . '/ComissaoService.php';

$ehProprietarioView = $cmModo === 'proprietario';
$rotaBase = $ehProprietarioView ? '/financeiro/comissoes' : '/financeiro/meu';

$totais       = ComissaoService::totais($pdo, $filtros);
$comissoes    = ($filtros['tipo'] ?? 'todos') === 'receita' || ($filtros['tipo'] ?? 'todos') === 'despesa'
                    ? [] : ComissaoService::listar($pdo, $filtros);
$mostrarComissoes = !in_array($filtros['tipo'] ?? 'todos', ['receita', 'despesa'], true);

if ($ehProprietarioView) {
    $visao          = ComissaoService::visaoGeral($pdo, $filtros);
    $porFuncionario = ComissaoService::porFuncionario($pdo, $filtros);
    $listaFuncionarios = ComissaoService::funcionarios($pdo);
    $mostrarExtrato = in_array($filtros['tipo'] ?? 'todos', ['todos', 'receita', 'despesa'], true);
    $extrato        = $mostrarExtrato ? ComissaoService::extratoGeral($pdo, $filtros) : [];
    $percentualVigente = ComissaoService::percentualAtual($pdo);
}

$fmt = fn(float $v) => ComissaoService::fmtMoeda($v);
$data = fn(?string $d) => $d ? date('d/m/Y', strtotime($d)) : '—';
$hora = fn(?string $h) => $h ? substr($h, 0, 5) : '—';
$formasTxt = function (?string $csv) {
    $partes = array_filter(explode(',', (string) $csv));
    if (empty($partes)) {
        return '—';
    }
    return implode(', ', array_map(fn($k) => FORMAS_PAGAMENTO_LABELS[$k] ?? $k, $partes));
};

$temFiltro = ($filtros['de'] ?? null) || ($filtros['ate'] ?? null) || ($filtros['status'] ?? null)
    || ($ehProprietarioView && (($filtros['funcionario'] ?? null) || ($filtros['tipo'] ?? 'todos') !== 'todos'));

$tituloPagina = $ehProprietarioView ? 'Comissões' : 'Meu Financeiro';
$nomeFiltrado = null;
if ($ehProprietarioView && !empty($filtros['funcionario'])) {
    foreach ($listaFuncionarios as $lf) {
        if ((int) $lf['id_barbeiro'] === (int) $filtros['funcionario']) {
            $nomeFiltrado = $lf['nome'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<?php include __DIR__ . '/theme-init.php'; ?>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($tituloPagina) ?> — Financeiro — BarbERP</title>

<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/admin-theme.css?v=2">

<style>
    table tbody tr{ border-top:1px solid rgba(255,255,255,0.05); }
    .kpi-card{
        background:linear-gradient(180deg, var(--charcoal-2), var(--charcoal-3));
        border:1px solid rgba(61,126,201,0.12);
        border-radius:1rem; padding:1rem 1.1rem; min-width:0;
    }
    .kpi-label{ font-size:11px; letter-spacing:.09em; text-transform:uppercase; color:#7f8fac; margin-bottom:.3rem; }
    .kpi-valor{ font-size:1.25rem; font-weight:600; color:var(--cream); line-height:1.25; word-break:break-word; }
    .kpi-sub{ font-size:11.5px; color:#7f8fac; margin-top:.15rem; }
    .kpi-pendente .kpi-valor{ color:#f0d18a; }
    .kpi-pago .kpi-valor{ color:#7fd696; }
    .kpi-despesa .kpi-valor{ color:#f0a2a8; }

    .badge{
        display:inline-flex; align-items:center; gap:.35rem; font-size:11px; font-weight:600;
        letter-spacing:.03em; padding:.28rem .65rem; border-radius:999px; white-space:nowrap;
    }
    .badge-pendente{  color:#f0d18a; background:rgba(184,140,24,0.14); border:1px solid rgba(184,140,24,0.45); }
    .badge-pago{      color:#bfe6c7; background:rgba(66,140,82,0.14);  border:1px solid rgba(66,140,82,0.4); }
    .badge-cancelado{ color:#c9a8ab; background:rgba(140,31,40,0.12);  border:1px solid rgba(140,31,40,0.4); }
    .badge-receita{   color:#bfe6c7; background:rgba(66,140,82,0.14);  border:1px solid rgba(66,140,82,0.4); }
    .badge-despesa{   color:#f0a2a8; background:rgba(140,31,40,0.12);  border:1px solid rgba(140,31,40,0.4); }

    .linha-cancelada td{ opacity:.55; }
    .linha-cancelada .valor-cancelado{ text-decoration:line-through; }
    .num{ text-align:right; white-space:nowrap; font-variant-numeric:tabular-nums; }
    .chk{ width:16px; height:16px; accent-color:#3d7ec9; cursor:pointer; }
    .tbl-th{ padding:.8rem .7rem; font-size:11px; text-transform:uppercase; letter-spacing:.06em; color:#7f8fac; font-weight:500; text-align:left; white-space:nowrap; }
    .tbl-td{ padding:.65rem .7rem; font-size:12.5px; color:#cdd6e6; vertical-align:middle; }
    .btn-mini{
        font-size:12px; font-weight:600; padding:.38rem .75rem; border-radius:.6rem; cursor:pointer;
        border:1px solid rgba(61,126,201,0.45); color:#9dc4f0; background:rgba(61,126,201,0.10); white-space:nowrap;
    }
    .btn-mini:hover{ background:rgba(61,126,201,0.2); }
    .btn-mini-neutro{ border-color:rgba(255,255,255,0.15); color:#aebdd6; background:rgba(255,255,255,0.04); }
    .link-func{ color:#9dc4f0; text-decoration:none; font-weight:500; }
    .link-func:hover{ text-decoration:underline; }

    html[data-theme="light"] .kpi-label, html[data-theme="light"] .kpi-sub, html[data-theme="light"] .tbl-th{ color:#475569; }
    html[data-theme="light"] .tbl-td{ color:#1e293b; }
    html[data-theme="light"] .kpi-pendente .kpi-valor{ color:#8a6508; }
    html[data-theme="light"] .kpi-pago .kpi-valor{ color:#1f6b30; }
    html[data-theme="light"] .kpi-despesa .kpi-valor{ color:#8c1f28; }
    html[data-theme="light"] .badge-pendente{ color:#7a5a07; }
    html[data-theme="light"] .badge-pago, html[data-theme="light"] .badge-receita{ color:#1f6b30; }
    html[data-theme="light"] .badge-cancelado, html[data-theme="light"] .badge-despesa{ color:#8c1f28; }
    html[data-theme="light"] .link-func, html[data-theme="light"] .btn-mini{ color:#1e4976; }
    html[data-theme="light"] .btn-mini-neutro{ color:#334155; }
</style>
</head>
<body class="flex">

<?php include __DIR__ . '/sidebar_barbeiro.php'; ?>
<?php include __DIR__ . '/toast.php'; ?>

<main class="flex-1 min-w-0">

    <header class="topbar px-5 sm:px-8 py-5 sm:py-6 flex items-center gap-4">
        <button type="button" onclick="abrirMenuMobile()" class="menu-toggle-btn lg:hidden" aria-label="Abrir menu">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M4 6h16M4 12h16M4 18h16" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
            </svg>
        </button>
        <div class="min-w-0 flex-1">
            <p class="eyebrow uppercase mb-1" style="color:var(--gold-light); opacity:.75">Financeiro</p>
            <h1 class="display text-3xl sm:text-4xl text-[color:var(--cream)] truncate">
                <?= htmlspecialchars($tituloPagina) ?><?= $nomeFiltrado ? ' — ' . htmlspecialchars($nomeFiltrado) : '' ?>
            </h1>
        </div>
    </header>

    <section class="p-5 sm:p-8">

        <!-- ==================== FILTROS ==================== -->
        <form method="GET" action="<?= $rotaBase ?>" class="panel-card rounded-2xl p-4 mb-6">
            <div class="grid grid-cols-2 lg:grid-cols-6 gap-3 items-end">
                <div>
                    <label class="field-label block mb-1.5 uppercase" for="f-de">Data inicial</label>
                    <input id="f-de" name="de" type="date" value="<?= htmlspecialchars($filtros['de'] ?? '') ?>" class="field w-full h-11 px-3 rounded-xl text-sm">
                </div>
                <div>
                    <label class="field-label block mb-1.5 uppercase" for="f-ate">Data final</label>
                    <input id="f-ate" name="ate" type="date" value="<?= htmlspecialchars($filtros['ate'] ?? '') ?>" class="field w-full h-11 px-3 rounded-xl text-sm">
                </div>
                <?php if ($ehProprietarioView): ?>
                <div>
                    <label class="field-label block mb-1.5 uppercase" for="f-func">Funcionário</label>
                    <select id="f-func" name="funcionario" class="field w-full h-11 px-3 rounded-xl text-sm">
                        <option value="">Todos</option>
                        <?php foreach ($listaFuncionarios as $lf): ?>
                            <option value="<?= (int) $lf['id_barbeiro'] ?>" <?= (int) ($filtros['funcionario'] ?? 0) === (int) $lf['id_barbeiro'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($lf['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div>
                    <label class="field-label block mb-1.5 uppercase" for="f-status">Status</label>
                    <select id="f-status" name="status" class="field w-full h-11 px-3 rounded-xl text-sm">
                        <option value="">Todos</option>
                        <option value="pendente" <?= ($filtros['status'] ?? '') === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                        <option value="pago" <?= ($filtros['status'] ?? '') === 'pago' ? 'selected' : '' ?>>Pago</option>
                        <option value="cancelado" <?= ($filtros['status'] ?? '') === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                    </select>
                </div>
                <?php if ($ehProprietarioView): ?>
                <div>
                    <label class="field-label block mb-1.5 uppercase" for="f-tipo">Tipo de lançamento</label>
                    <select id="f-tipo" name="tipo" class="field w-full h-11 px-3 rounded-xl text-sm">
                        <option value="todos" <?= ($filtros['tipo'] ?? 'todos') === 'todos' ? 'selected' : '' ?>>Todos</option>
                        <option value="receita" <?= ($filtros['tipo'] ?? '') === 'receita' ? 'selected' : '' ?>>Receitas</option>
                        <option value="despesa" <?= ($filtros['tipo'] ?? '') === 'despesa' ? 'selected' : '' ?>>Despesas</option>
                        <option value="comissao" <?= ($filtros['tipo'] ?? '') === 'comissao' ? 'selected' : '' ?>>Comissões</option>
                    </select>
                </div>
                <?php endif; ?>
                <div class="col-span-2 lg:col-span-1 flex gap-2">
                    <button type="submit" class="btn-primary h-11 px-5 rounded-xl text-sm flex-1">Filtrar</button>
                    <?php if ($temFiltro): ?>
                        <a href="<?= $rotaBase ?>" class="btn-secondary h-11 px-4 rounded-xl text-sm flex items-center justify-center">Limpar</a>
                    <?php endif; ?>
                </div>
            </div>
        </form>

        <!-- ==================== RESUMO ==================== -->
        <?php if ($ehProprietarioView): ?>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
            <div class="kpi-card">
                <p class="kpi-label">Receitas recebidas</p>
                <p class="kpi-valor"><?= $fmt($visao['receitas']) ?></p>
                <p class="kpi-sub">dinheiro que já entrou</p>
            </div>
            <div class="kpi-card kpi-despesa">
                <p class="kpi-label">Despesas</p>
                <p class="kpi-valor"><?= $fmt($visao['despesas']) ?></p>
                <p class="kpi-sub">saídas lançadas</p>
            </div>
            <div class="kpi-card">
                <p class="kpi-label">Serviços realizados</p>
                <p class="kpi-valor"><?= (int) $visao['servicos_realizados'] ?></p>
                <p class="kpi-sub">atendimentos concluídos</p>
            </div>
            <div class="kpi-card">
                <p class="kpi-label">Total faturado</p>
                <p class="kpi-valor"><?= $fmt($visao['total_faturado']) ?></p>
                <p class="kpi-sub">inclui fiado em aberto</p>
            </div>
        </div>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="kpi-card">
                <p class="kpi-label">Total de comissões</p>
                <p class="kpi-valor"><?= $fmt($totais['total_comissao']) ?></p>
                <p class="kpi-sub"><?= (int) $totais['qtd_servicos'] ?> serviços · <?= $fmt($totais['total_servicos']) ?></p>
            </div>
            <div class="kpi-card kpi-pendente">
                <p class="kpi-label">Comissões pendentes</p>
                <p class="kpi-valor"><?= $fmt($totais['total_pendente']) ?></p>
                <p class="kpi-sub">a pagar aos funcionários</p>
            </div>
            <div class="kpi-card kpi-pago">
                <p class="kpi-label">Comissões pagas</p>
                <p class="kpi-valor"><?= $fmt($totais['total_pago']) ?></p>
                <p class="kpi-sub">já acertadas</p>
            </div>
            <div class="kpi-card">
                <p class="kpi-label">Funcionários</p>
                <p class="kpi-valor"><?= count($listaFuncionarios) ?></p>
                <p class="kpi-sub">comissão atual: <?= ComissaoService::fmtPct($percentualVigente) ?></p>
            </div>
        </div>
        <?php else: ?>
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
            <div class="kpi-card">
                <p class="kpi-label">Meus atendimentos</p>
                <p class="kpi-valor"><?= (int) $totais['qtd_servicos'] ?></p>
                <p class="kpi-sub">serviços concluídos</p>
            </div>
            <div class="kpi-card">
                <p class="kpi-label">Faturamento gerado</p>
                <p class="kpi-valor"><?= $fmt($totais['total_servicos']) ?></p>
                <p class="kpi-sub">total dos meus serviços</p>
            </div>
            <div class="kpi-card">
                <p class="kpi-label">Minha comissão</p>
                <p class="kpi-valor"><?= $fmt($totais['total_comissao']) ?></p>
                <p class="kpi-sub">pendente + paga</p>
            </div>
            <div class="kpi-card kpi-pago">
                <p class="kpi-label">Comissão paga</p>
                <p class="kpi-valor"><?= $fmt($totais['total_pago']) ?></p>
                <p class="kpi-sub">já recebi</p>
            </div>
            <div class="kpi-card kpi-pendente">
                <p class="kpi-label">Comissão pendente</p>
                <p class="kpi-valor"><?= $fmt($totais['total_pendente']) ?></p>
                <p class="kpi-sub">a receber</p>
            </div>
        </div>
        <?php endif; ?>

        <!-- ==================== POR FUNCIONÁRIO (proprietário) ==================== -->
        <?php if ($ehProprietarioView && $mostrarComissoes): ?>
        <div class="panel-card rounded-2xl overflow-hidden mb-6">
            <div class="barber-stripe-thin"></div>
            <div class="px-5 pt-4 pb-1">
                <p class="text-sm font-semibold text-[color:var(--cream)]">Comissões por funcionário</p>
                <p class="text-xs text-zinc-500">Clique no nome para ver só os lançamentos dele.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr>
                            <th class="tbl-th">Funcionário</th>
                            <th class="tbl-th num">Serviços</th>
                            <th class="tbl-th num">Faturado</th>
                            <th class="tbl-th num">Comissão</th>
                            <th class="tbl-th num">Paga</th>
                            <th class="tbl-th num">Pendente</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($porFuncionario)): ?>
                        <tr><td colspan="6" class="tbl-td text-center" style="padding:1.6rem;">Nenhuma comissão no período.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($porFuncionario as $pf):
                        $q = http_build_query(array_filter([
                            'de' => $filtros['de'] ?? null, 'ate' => $filtros['ate'] ?? null,
                            'status' => $filtros['status'] ?? null, 'funcionario' => $pf['id_barbeiro'],
                        ])); ?>
                        <tr>
                            <td class="tbl-td"><a class="link-func" href="<?= $rotaBase ?>?<?= htmlspecialchars($q) ?>"><?= htmlspecialchars($pf['nome']) ?></a></td>
                            <td class="tbl-td num"><?= (int) $pf['qtd_servicos'] ?></td>
                            <td class="tbl-td num"><?= $fmt($pf['total_servicos']) ?></td>
                            <td class="tbl-td num"><?= $fmt($pf['total_comissao']) ?></td>
                            <td class="tbl-td num" style="color:#7fd696;"><?= $fmt($pf['total_pago']) ?></td>
                            <td class="tbl-td num" style="color:#f0d18a;"><?= $fmt($pf['total_pendente']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="barber-stripe-thin"></div>
        </div>
        <?php endif; ?>

        <!-- ==================== LANÇAMENTOS DE COMISSÃO ==================== -->
        <?php if ($mostrarComissoes): ?>
        <div class="panel-card rounded-2xl overflow-hidden mb-6">
            <div class="barber-stripe-thin"></div>
            <div class="px-5 pt-4 pb-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold text-[color:var(--cream)]"><?= $ehProprietarioView ? 'Lançamentos de comissão' : 'Minhas comissões' ?></p>
                    <p class="text-xs text-zinc-500">Cada atendimento concluído gera uma comissão, com a porcentagem da época.</p>
                </div>
                <?php if ($ehProprietarioView): ?>
                <div class="flex flex-wrap gap-2">
                    <button type="button" id="btn-baixar-sel" class="btn-primary h-10 px-4 rounded-xl text-sm" disabled style="opacity:.5;">Dar baixa nas selecionadas</button>
                </div>
                <?php endif; ?>
            </div>

            <form id="form-baixa" class="hidden"><?= csrf_field() ?></form>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr>
                            <?php if ($ehProprietarioView): ?><th class="tbl-th" style="width:36px;"><input type="checkbox" class="chk" id="chk-todas" title="Selecionar todas as pendentes"></th><?php endif; ?>
                            <th class="tbl-th">Data</th>
                            <th class="tbl-th">Horário</th>
                            <?php if ($ehProprietarioView): ?><th class="tbl-th">Funcionário</th><?php endif; ?>
                            <th class="tbl-th">Cliente</th>
                            <th class="tbl-th">Serviço</th>
                            <th class="tbl-th">Pagamento</th>
                            <th class="tbl-th num">Valor</th>
                            <th class="tbl-th num">%</th>
                            <th class="tbl-th num">Comissão</th>
                            <th class="tbl-th">Status</th>
                            <?php if ($ehProprietarioView): ?><th class="tbl-th"></th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($comissoes)): ?>
                        <tr><td colspan="<?= $ehProprietarioView ? 12 : 9 ?>" class="tbl-td text-center" style="padding:2rem;">
                            Nenhuma comissão encontrada<?= $temFiltro ? ' com esses filtros' : '' ?>.
                        </td></tr>
                    <?php endif; ?>
                    <?php foreach ($comissoes as $c):
                        $st = $c['status'];
                        $cancelada = $st === 'cancelado';
                        $rotuloSt = $st === 'pendente' ? 'Pendente' : ($st === 'pago' ? 'Pago' : ($c['pago_em'] ? 'Cancelada (já paga)' : 'Cancelada'));
                        $titulo = trim((string) $c['historico']);
                    ?>
                        <tr class="<?= $cancelada ? 'linha-cancelada' : '' ?>" title="<?= htmlspecialchars($titulo, ENT_QUOTES) ?>">
                            <?php if ($ehProprietarioView): ?>
                            <td class="tbl-td">
                                <?php if ($st === 'pendente'): ?><input type="checkbox" class="chk chk-linha" value="<?= (int) $c['idComissao'] ?>"><?php endif; ?>
                            </td>
                            <?php endif; ?>
                            <td class="tbl-td" style="white-space:nowrap;"><?= $data($c['data_servico']) ?></td>
                            <td class="tbl-td"><?= $hora($c['hora_servico']) ?></td>
                            <?php if ($ehProprietarioView): ?>
                            <td class="tbl-td"><?= htmlspecialchars($c['funcionario_nome'] ?? '—') ?></td>
                            <?php endif; ?>
                            <td class="tbl-td"><?= htmlspecialchars($c['cliente_nome'] ?? '—') ?></td>
                            <td class="tbl-td"><?= htmlspecialchars($c['servico_nome'] ?? '—') ?></td>
                            <td class="tbl-td"><?= htmlspecialchars($formasTxt($c['forma_pagamento'])) ?></td>
                            <td class="tbl-td num"><span class="valor-cancelado"><?= $fmt((float) $c['valor_servico']) ?></span></td>
                            <td class="tbl-td num"><?= ComissaoService::fmtPct((float) $c['percentual']) ?></td>
                            <td class="tbl-td num" style="font-weight:600;"><span class="valor-cancelado"><?= $fmt((float) $c['valor_comissao']) ?></span></td>
                            <td class="tbl-td"><span class="badge badge-<?= htmlspecialchars($st) ?>"><?= htmlspecialchars($rotuloSt) ?></span>
                                <?php if ($st === 'pago' && $c['pago_em']): ?><div class="text-[11px] text-zinc-500 mt-1">em <?= date('d/m/Y', strtotime($c['pago_em'])) ?></div><?php endif; ?>
                            </td>
                            <?php if ($ehProprietarioView): ?>
                            <td class="tbl-td num">
                                <?php if ($st === 'pendente'): ?>
                                    <button type="button" class="btn-mini js-acao" data-acao="pagar" data-id="<?= (int) $c['idComissao'] ?>">Dar baixa</button>
                                <?php elseif ($st === 'pago'): ?>
                                    <button type="button" class="btn-mini btn-mini-neutro js-acao" data-acao="reabrir" data-id="<?= (int) $c['idComissao'] ?>">Desfazer</button>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="px-5 py-4 flex flex-wrap gap-x-8 gap-y-2" style="border-top:1px solid rgba(255,255,255,0.06);">
                <div><span class="kpi-label">Total de serviços</span><div class="kpi-valor" style="font-size:1rem;"><?= (int) $totais['qtd_servicos'] ?> · <?= $fmt($totais['total_servicos']) ?></div></div>
                <div><span class="kpi-label">Total de comissão</span><div class="kpi-valor" style="font-size:1rem;"><?= $fmt($totais['total_comissao']) ?></div></div>
                <div class="kpi-pago"><span class="kpi-label">Total pago</span><div class="kpi-valor" style="font-size:1rem;"><?= $fmt($totais['total_pago']) ?></div></div>
                <div class="kpi-pendente"><span class="kpi-label">Total pendente</span><div class="kpi-valor" style="font-size:1rem;"><?= $fmt($totais['total_pendente']) ?></div></div>
                <?php if ($totais['cancelados'] > 0): ?>
                <div><span class="kpi-label">Canceladas (fora dos totais)</span><div class="kpi-valor" style="font-size:1rem;"><?= (int) $totais['cancelados'] ?></div></div>
                <?php endif; ?>
            </div>
            <div class="barber-stripe-thin"></div>
        </div>
        <?php endif; ?>

        <!-- ==================== EXTRATO GERAL (proprietário) ==================== -->
        <?php if ($ehProprietarioView && $mostrarExtrato): ?>
        <div class="panel-card rounded-2xl overflow-hidden">
            <div class="barber-stripe-thin"></div>
            <div class="px-5 pt-4 pb-3">
                <p class="text-sm font-semibold text-[color:var(--cream)]">Receitas e despesas da barbearia</p>
                <p class="text-xs text-zinc-500">Todos os recebimentos e saídas lançados, de todos os barbeiros<?= $nomeFiltrado ? ' (filtrado por ' . htmlspecialchars($nomeFiltrado) . ')' : '' ?>.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr>
                            <th class="tbl-th">Data</th>
                            <th class="tbl-th">Tipo</th>
                            <th class="tbl-th">Descrição</th>
                            <th class="tbl-th">Responsável</th>
                            <th class="tbl-th">Pagamento</th>
                            <th class="tbl-th num">Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($extrato)): ?>
                        <tr><td colspan="6" class="tbl-td text-center" style="padding:2rem;">Nenhum lançamento no período.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($extrato as $e): $receita = $e['tipo'] === 'entrada'; ?>
                        <tr>
                            <td class="tbl-td" style="white-space:nowrap;"><?= $data($e['data']) ?></td>
                            <td class="tbl-td"><span class="badge <?= $receita ? 'badge-receita' : 'badge-despesa' ?>"><?= $receita ? 'Receita' : 'Despesa' ?></span></td>
                            <td class="tbl-td"><?= htmlspecialchars($e['titulo']) ?></td>
                            <td class="tbl-td"><?= htmlspecialchars($e['responsavel'] ?? '—') ?></td>
                            <td class="tbl-td"><?= htmlspecialchars($formasTxt($e['forma_pagamento'])) ?></td>
                            <td class="tbl-td num" style="font-weight:600; color:<?= $receita ? '#7fd696' : '#f0a2a8' ?>;"><?= $receita ? '' : '− ' ?><?= $fmt((float) $e['valor']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="barber-stripe-thin"></div>
        </div>
        <?php endif; ?>

    </section>
</main>

<?php if ($ehProprietarioView && $mostrarComissoes): ?>
<script>
(function () {
    var csrf = document.querySelector('#form-baixa input[name="_csrf"]').value;
    var btnSel = document.getElementById('btn-baixar-sel');
    var chkTodas = document.getElementById('chk-todas');

    function marcadas() {
        return Array.prototype.map.call(document.querySelectorAll('.chk-linha:checked'), function (c) { return c.value; });
    }
    function atualizarBotao() {
        var n = marcadas().length;
        btnSel.disabled = n === 0;
        btnSel.style.opacity = n === 0 ? '.5' : '1';
        btnSel.textContent = n > 0 ? 'Dar baixa em ' + n + (n === 1 ? ' selecionada' : ' selecionadas') : 'Dar baixa nas selecionadas';
    }
    document.querySelectorAll('.chk-linha').forEach(function (c) { c.addEventListener('change', atualizarBotao); });
    if (chkTodas) {
        chkTodas.addEventListener('change', function () {
            document.querySelectorAll('.chk-linha').forEach(function (c) { c.checked = chkTodas.checked; });
            atualizarBotao();
        });
    }

    function enviar(acao, ids) {
        var dados = new FormData();
        dados.append('_csrf', csrf);
        dados.append('acao', acao);
        ids.forEach(function (id) { dados.append('ids[]', id); });
        return fetch('/Financeiro/scripts/comissao_baixar.php', { method: 'POST', body: dados, credentials: 'same-origin' })
            .then(function (r) { return r.json().catch(function () { return { ok: false, erro: 'Resposta inválida do servidor.' }; }); })
            .then(function (j) {
                if (!j.ok) { toast(j.erro || 'Não foi possível atualizar.', 'erro'); return; }
                toast(acao === 'pagar' ? 'Baixa registrada em ' + j.alteradas + (j.alteradas === 1 ? ' comissão.' : ' comissões.') : 'Baixa desfeita.', 'sucesso');
                setTimeout(function () { location.reload(); }, 700);
            })
            .catch(function () { toast('Erro de conexão. Tente novamente.', 'erro'); });
    }

    btnSel.addEventListener('click', function () { var ids = marcadas(); if (ids.length) enviar('pagar', ids); });
    document.querySelectorAll('.js-acao').forEach(function (b) {
        b.addEventListener('click', function () { enviar(b.dataset.acao, [b.dataset.id]); });
    });
})();
</script>
<?php endif; ?>

</body>
</html>

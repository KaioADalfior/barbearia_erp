<?php
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/guard.php';
exigirSessao(['barbeiro']);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/csrf.php';

$paginaAtual = 'servico-listar';

// ---------- Busca todos os serviços ----------
$stmt = $pdo->query('SELECT idServico, nome, duracao_minutos, valor, ativo FROM Servico ORDER BY nome ASC');
$servicos = $stmt->fetchAll();

// ---------- Mensagens de status (retorno dos scripts) ----------
$statusGet = $_GET['status'] ?? '';

// ---------- Reabertura automática de modal em caso de erro de validação ----------
$reabrirCadastro = $statusGet === 'cadastro-erro';
$reabrirEdicao   = $statusGet === 'edicao-erro';

$voltaNome     = $_GET['nome'] ?? '';
$voltaDuracao  = $_GET['duracao_minutos'] ?? '';
$voltaValor    = $_GET['valor'] ?? '';
$voltaEditId   = $_GET['edit_id'] ?? '';
$voltaAtivo    = $_GET['ativo'] ?? '1';

$mensagens = [
    'cadastro-sucesso'  => ['ok',   $voltaNome !== '' ? "Serviço $voltaNome cadastrado." : 'Serviço cadastrado com sucesso.'],
    'cadastro-erro'     => ['erro', 'Preencha nome, duração e valor corretamente.'],
    'edicao-sucesso'    => ['ok',   $voltaNome !== '' ? "Serviço $voltaNome atualizado." : 'Serviço atualizado com sucesso.'],
    'edicao-erro'       => ['erro', 'Preencha nome, duração e valor corretamente.'],
    'inativado-sucesso' => ['ok',   $voltaNome !== '' ? "Serviço $voltaNome inativado." : 'Serviço inativado com sucesso.'],
    'reativado-sucesso' => ['ok',   $voltaNome !== '' ? "Serviço $voltaNome ativado." : 'Serviço reativado com sucesso.'],
    'status-erro'       => ['erro', 'Não foi possível atualizar o status do serviço.'],
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<?php include __DIR__ . '/../../includes/theme-init.php'; ?>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Serviços — Sistema de Gestão</title>

<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../../assets/css/admin-theme.css">

<style>
    .modal-overlay{
        background:rgba(0,0,0,0.7);
        backdrop-filter:blur(3px);
    }
    .modal-card{
        background:linear-gradient(180deg, var(--charcoal-2), var(--charcoal-3));
        border:1px solid rgba(61,126,201,0.16);
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
    }
    .badge-ativo{
        color:#bfe6c7;
        background:rgba(66,140,82,0.14);
        border:1px solid rgba(66,140,82,0.4);
    }
    .badge-inativo{
        color:#c9a8ab;
        background:rgba(140,31,40,0.12);
        border:1px solid rgba(140,31,40,0.4);
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
    .icon-btn-danger:hover{
        color:#f0a2a8;
        border-color:rgba(140,31,40,0.5);
        background:rgba(140,31,40,0.1);
    }
    table tbody tr{
        border-top:1px solid rgba(255,255,255,0.05);
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

    /* ---------- Tema claro: reforço de contraste ---------- */
    html[data-theme="light"] .badge-ativo{ color:#1f6b30; }
    html[data-theme="light"] .badge-inativo{ color:#8c1f28; }
    html[data-theme="light"] .icon-btn{ color:#475569; }
    html[data-theme="light"] .icon-btn-danger:hover{ color:#8c1f28; }
    html[data-theme="light"] .view-label{ color:#475569; }
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
            <p class="eyebrow uppercase mb-1" style="color:var(--gold-light); opacity:.75">Serviços</p>
            <h1 class="display text-3xl sm:text-4xl text-[color:var(--cream)] truncate">Gerenciar Serviço</h1>
        </div>
    </header>

    <section class="p-5 sm:p-8">

        <?php if (isset($mensagens[$statusGet])): [$tipoMsg, $textoMsg] = $mensagens[$statusGet]; ?>
            <script>toast(<?= json_encode($textoMsg) ?>, <?= json_encode($tipoMsg === 'ok' ? 'sucesso' : 'erro') ?>);</script>
        <?php endif; ?>

        <!-- Barra de ferramentas: busca + cadastrar -->
        <div class="panel-card rounded-2xl p-4 mb-6 flex flex-col sm:flex-row gap-3 sm:items-center sm:justify-between">

            <div class="relative w-full sm:max-w-sm">
                <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="11" cy="11" r="6.5" stroke="#7f8fac" stroke-width="1.5"/>
                    <path d="M20 20l-4.3-4.3" stroke="#7f8fac" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
                <input
                    id="busca-servico"
                    type="text"
                    placeholder="Buscar por nome, duração, valor ou status..."
                    autocomplete="off"
                    class="field w-full h-11 pl-10 pr-4 rounded-xl text-sm"
                    oninput="filtrarServicos(this.value)"
                >
            </div>

            <button type="button" onclick="openModal('modal-cadastrar')" class="btn-primary h-11 px-5 rounded-xl text-sm flex items-center justify-center gap-2 shrink-0">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 5v14M5 12h14" stroke="#ffffff" stroke-width="2" stroke-linecap="round"/>
                </svg>
                Cadastrar Serviço
            </button>
        </div>

        <?php if (empty($servicos)): ?>

            <div class="panel-card rounded-2xl p-10 text-center">
                <p class="text-sm text-zinc-400">Nenhum serviço cadastrado ainda.</p>
                <button type="button" onclick="openModal('modal-cadastrar')" class="btn-secondary h-10 px-5 rounded-xl text-sm mt-4 inline-flex items-center">
                    Cadastrar o primeiro serviço
                </button>
            </div>

        <?php else: ?>

            <!-- Zoom de 80% na tabela inteira (conteúdo visualmente menor,
                 igual ao zoom do navegador) — não mexe na largura/espaço do
                 card, só no tamanho do que está dentro dele. -->
            <div class="panel-card rounded-2xl overflow-hidden" style="zoom:0.8;">
                <div class="barber-stripe-thin"></div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-[11px] uppercase tracking-wider text-zinc-500">
                                <th class="px-6 py-4 font-medium">Serviço</th>
                                <th class="px-6 py-4 font-medium">Duração</th>
                                <th class="px-6 py-4 font-medium">Valor</th>
                                <th class="px-6 py-4 font-medium">Status</th>
                                <th class="px-6 py-4 font-medium text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-servicos">
                            <?php foreach ($servicos as $s):
                                $ativo   = (int) $s['ativo'] === 1;
                                $nome    = $s['nome'];
                                $duracao = (int) $s['duracao_minutos'];
                                $valor   = (float) $s['valor'];
                                $valorFmt  = number_format($valor, 2, ',', '.');
                                $statusTxt = $ativo ? 'ativo' : 'inativo';
                                $textoBusca = $s['idServico'] . ' ' . $nome . ' ' . $duracao . ' min ' . $valorFmt . ' ' . $statusTxt;
                                $busca = function_exists('mb_strtolower')
                                    ? mb_strtolower($textoBusca, 'UTF-8')
                                    : strtolower($textoBusca);
                            ?>
                            <tr data-busca="<?= htmlspecialchars($busca) ?>">
                                <td class="px-6 py-4 text-[color:var(--cream)] font-medium"><?= htmlspecialchars($nome) ?></td>
                                <td class="px-6 py-4 text-zinc-400"><?= $duracao ?> min</td>
                                <td class="px-6 py-4 text-zinc-400">R$ <?= htmlspecialchars($valorFmt) ?></td>
                                <td class="px-6 py-4">
                                    <?php if ($ativo): ?>
                                        <span class="badge badge-ativo">Ativo</span>
                                    <?php else: ?>
                                        <span class="badge badge-inativo">Inativo</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center justify-end gap-2">

                                        <button type="button" title="Visualizar" class="icon-btn"
                                            data-nome="<?= htmlspecialchars($nome) ?>"
                                            data-duracao="<?= $duracao ?> min"
                                            data-valor="R$ <?= htmlspecialchars($valorFmt) ?>"
                                            data-status="<?= $ativo ? 'Ativo' : 'Inativo' ?>"
                                            onclick="openViewModal(this)">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/>
                                                <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.4"/>
                                            </svg>
                                        </button>

                                        <button type="button" title="Editar" class="icon-btn"
                                            data-id="<?= (int) $s['idServico'] ?>"
                                            data-nome="<?= htmlspecialchars($nome) ?>"
                                            data-duracao="<?= $duracao ?>"
                                            data-valor="<?= htmlspecialchars(number_format($valor, 2, '.', '')) ?>"
                                            data-ativo="<?= $ativo ? '1' : '0' ?>"
                                            onclick="openEditModal(this)">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M4 20h4L18.5 9.5a2.1 2.1 0 0 0-3-3L5 17v3Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/>
                                                <path d="M13.5 8l3 3" stroke="currentColor" stroke-width="1.4"/>
                                            </svg>
                                        </button>

                                        <?php if ($ativo): ?>
                                            <button type="button" title="Inativar" class="icon-btn icon-btn-danger"
                                                data-id="<?= (int) $s['idServico'] ?>"
                                                data-nome="<?= htmlspecialchars($nome) ?>"
                                                data-acao="inativar"
                                                onclick="openStatusModal(this)">
                                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                    <circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.4"/>
                                                    <path d="M6.5 6.5l11 11" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
                                                </svg>
                                            </button>
                                        <?php else: ?>
                                            <button type="button" title="Reativar" class="icon-btn"
                                                data-id="<?= (int) $s['idServico'] ?>"
                                                data-nome="<?= htmlspecialchars($nome) ?>"
                                                data-acao="reativar"
                                                onclick="openStatusModal(this)">
                                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                    <path d="M4 12a8 8 0 1 1 2.5 5.8" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
                                                    <path d="M4 17v-4h4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
                                                </svg>
                                            </button>
                                        <?php endif; ?>

                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>

                            <tr id="linha-sem-resultado" class="hidden">
                                <td colspan="5" class="px-6 py-10 text-center text-zinc-500 text-sm">
                                    Nenhum serviço encontrado para essa busca.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="barber-stripe-thin"></div>
            </div>

        <?php endif; ?>

    </section>

</main>

<!-- ==================== MODAL: CADASTRAR ==================== -->
<div id="modal-cadastrar" class="modal-overlay fixed inset-0 z-50 hidden flex items-center justify-center p-4" onclick="fecharAoClicarFora(event, 'modal-cadastrar')">
    <div class="modal-card rounded-3xl shadow-2xl overflow-hidden w-full max-w-md">
        <div class="barber-stripe-thin"></div>
        <div class="p-7">
            <div class="flex items-start justify-between mb-6">
                <h2 class="display text-2xl text-[color:var(--cream)]">Cadastrar Serviço</h2>
                <button type="button" onclick="closeModal('modal-cadastrar')" class="icon-btn shrink-0">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>

            <form action="../scripts/servico_salvar.php" method="POST" autocomplete="off">
                <?= csrf_field() ?>
                <div class="mb-5">
                    <label class="field-label block mb-2 uppercase" for="nome">Nome do Serviço</label>
                    <input id="nome" name="nome" type="text" placeholder="Ex: Corte, Barba, Corte + Barba" required
                           value="<?= $reabrirCadastro ? htmlspecialchars($voltaNome) : '' ?>"
                           class="field w-full h-12 px-4 rounded-xl text-sm">
                </div>

                <div class="grid grid-cols-2 gap-4 mb-7">
                    <div>
                        <label class="field-label block mb-2 uppercase" for="duracao_minutos">Duração (min)</label>
                        <input id="duracao_minutos" name="duracao_minutos" type="number" min="1" step="1" placeholder="40" required
                               value="<?= $reabrirCadastro ? htmlspecialchars($voltaDuracao) : '' ?>"
                               class="field w-full h-12 px-4 rounded-xl text-sm">
                    </div>
                    <div>
                        <label class="field-label block mb-2 uppercase" for="valor">Valor (R$)</label>
                        <input id="valor" name="valor" type="text" inputmode="decimal" placeholder="35,00" required
                               value="<?= $reabrirCadastro ? htmlspecialchars($voltaValor) : '' ?>"
                               class="field w-full h-12 px-4 rounded-xl text-sm">
                    </div>
                </div>

                <div class="flex gap-3">
                    <button type="submit" class="btn-primary h-12 px-6 rounded-xl text-sm flex-1">
                        Salvar Serviço
                    </button>
                    <button type="button" onclick="closeModal('modal-cadastrar')" class="btn-secondary h-12 px-6 rounded-xl text-sm">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
        <div class="barber-stripe-thin"></div>
    </div>
</div>

<!-- ==================== MODAL: EDITAR ==================== -->
<div id="modal-editar" class="modal-overlay fixed inset-0 z-50 hidden flex items-center justify-center p-4" onclick="fecharAoClicarFora(event, 'modal-editar')">
    <div class="modal-card rounded-3xl shadow-2xl overflow-hidden w-full max-w-md">
        <div class="barber-stripe-thin"></div>
        <div class="p-7">
            <div class="flex items-start justify-between mb-6">
                <h2 class="display text-2xl text-[color:var(--cream)]">Editar Serviço</h2>
                <button type="button" onclick="closeModal('modal-editar')" class="icon-btn shrink-0">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>

            <form action="../scripts/servico_atualizar.php" method="POST" autocomplete="off">
                <?= csrf_field() ?>
                <input type="hidden" id="edit-id" name="id" value="<?= $reabrirEdicao ? htmlspecialchars($voltaEditId) : '' ?>">

                <div class="mb-5">
                    <label class="field-label block mb-2 uppercase" for="edit-nome">Nome do Serviço</label>
                    <input id="edit-nome" name="nome" type="text" placeholder="Ex: Corte, Barba, Corte + Barba" required
                           value="<?= $reabrirEdicao ? htmlspecialchars($voltaNome) : '' ?>"
                           class="field w-full h-12 px-4 rounded-xl text-sm">
                </div>

                <div class="grid grid-cols-2 gap-4 mb-7">
                    <div>
                        <label class="field-label block mb-2 uppercase" for="edit-duracao_minutos">Duração (min)</label>
                        <input id="edit-duracao_minutos" name="duracao_minutos" type="number" min="1" step="1" placeholder="40" required
                               value="<?= $reabrirEdicao ? htmlspecialchars($voltaDuracao) : '' ?>"
                               class="field w-full h-12 px-4 rounded-xl text-sm">
                    </div>
                    <div>
                        <label class="field-label block mb-2 uppercase" for="edit-valor">Valor (R$)</label>
                        <input id="edit-valor" name="valor" type="text" inputmode="decimal" placeholder="35,00" required
                               value="<?= $reabrirEdicao ? htmlspecialchars($voltaValor) : '' ?>"
                               class="field w-full h-12 px-4 rounded-xl text-sm">
                    </div>
                </div>

                <div class="mb-7">
                    <label class="field-label block mb-2 uppercase">Status</label>
                    <div class="flex gap-5">
                        <label class="flex items-center gap-2 text-sm text-zinc-300 cursor-pointer">
                            <input type="radio" id="edit-ativo-sim" name="ativo" value="1"
                                   <?= (!$reabrirEdicao || $voltaAtivo === '1') ? 'checked' : '' ?>
                                   style="accent-color: var(--gold);" class="w-4 h-4">
                            Ativo
                        </label>
                        <label class="flex items-center gap-2 text-sm text-zinc-300 cursor-pointer">
                            <input type="radio" id="edit-ativo-nao" name="ativo" value="0"
                                   <?= ($reabrirEdicao && $voltaAtivo === '0') ? 'checked' : '' ?>
                                   style="accent-color: var(--gold);" class="w-4 h-4">
                            Inativo
                        </label>
                    </div>
                </div>

                <div class="flex gap-3">
                    <button type="submit" class="btn-primary h-12 px-6 rounded-xl text-sm flex-1">
                        Salvar Alterações
                    </button>
                    <button type="button" onclick="closeModal('modal-editar')" class="btn-secondary h-12 px-6 rounded-xl text-sm">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
        <div class="barber-stripe-thin"></div>
    </div>
</div>

<!-- ==================== MODAL: VISUALIZAR ==================== -->
<div id="modal-visualizar" class="modal-overlay fixed inset-0 z-50 hidden flex items-center justify-center p-4" onclick="fecharAoClicarFora(event, 'modal-visualizar')">
    <div class="modal-card rounded-3xl shadow-2xl overflow-hidden w-full max-w-md">
        <div class="barber-stripe-thin"></div>
        <div class="p-7">
            <div class="flex items-start justify-between mb-6">
                <h2 class="display text-2xl text-[color:var(--cream)]">Dados do Serviço</h2>
                <button type="button" onclick="closeModal('modal-visualizar')" class="icon-btn shrink-0">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>

            <div>
                <div class="view-row">
                    <span class="view-label">Nome</span>
                    <span class="view-value" id="view-nome">—</span>
                </div>
                <div class="view-row">
                    <span class="view-label">Duração</span>
                    <span class="view-value" id="view-duracao">—</span>
                </div>
                <div class="view-row">
                    <span class="view-label">Valor</span>
                    <span class="view-value" id="view-valor">—</span>
                </div>
                <div class="view-row">
                    <span class="view-label">Status</span>
                    <span class="view-value" id="view-status">—</span>
                </div>
            </div>

            <button type="button" onclick="closeModal('modal-visualizar')" class="btn-secondary h-12 px-6 rounded-xl text-sm w-full mt-7">
                Fechar
            </button>
        </div>
        <div class="barber-stripe-thin"></div>
    </div>
</div>

<!-- ==================== MODAL: INATIVAR / REATIVAR ==================== -->
<div id="modal-status" class="modal-overlay fixed inset-0 z-50 hidden flex items-center justify-center p-4" onclick="fecharAoClicarFora(event, 'modal-status')">
    <div class="modal-card rounded-3xl shadow-2xl overflow-hidden w-full max-w-sm">
        <div class="barber-stripe-thin"></div>
        <div class="p-7">
            <div class="flex items-start justify-between mb-5">
                <h2 class="display text-2xl text-[color:var(--cream)]" id="status-titulo">Confirmar</h2>
                <button type="button" onclick="closeModal('modal-status')" class="icon-btn shrink-0">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>

            <p class="text-sm text-zinc-400 mb-7" id="status-texto">
                Tem certeza que deseja alterar o status deste serviço?
            </p>

            <form action="../scripts/servico_status.php" method="POST" autocomplete="off">
                <?= csrf_field() ?>
                <input type="hidden" id="status-id" name="id" value="">
                <input type="hidden" id="status-acao" name="acao" value="">

                <div class="flex gap-3">
                    <button type="submit" id="status-botao" class="btn-primary h-12 px-6 rounded-xl text-sm flex-1">
                        Confirmar
                    </button>
                    <button type="button" onclick="closeModal('modal-status')" class="btn-secondary h-12 px-6 rounded-xl text-sm">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
        <div class="barber-stripe-thin"></div>
    </div>
</div>

<script>
    function openModal(id) {
        document.getElementById(id).classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }

    function closeModal(id) {
        document.getElementById(id).classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }

    function fecharAoClicarFora(evento, id) {
        if (evento.target.id === id) {
            closeModal(id);
        }
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            ['modal-cadastrar', 'modal-editar', 'modal-visualizar', 'modal-status'].forEach(closeModal);
        }
    });

    function openEditModal(botao) {
        const d = botao.dataset;
        document.getElementById('edit-id').value = d.id;
        document.getElementById('edit-nome').value = d.nome;
        document.getElementById('edit-duracao_minutos').value = d.duracao;
        document.getElementById('edit-valor').value = d.valor;
        document.getElementById(d.ativo === '1' ? 'edit-ativo-sim' : 'edit-ativo-nao').checked = true;
        openModal('modal-editar');
    }

    function openViewModal(botao) {
        const d = botao.dataset;
        document.getElementById('view-nome').textContent = d.nome;
        document.getElementById('view-duracao').textContent = d.duracao;
        document.getElementById('view-valor').textContent = d.valor;
        document.getElementById('view-status').textContent = d.status;
        openModal('modal-visualizar');
    }

    function openStatusModal(botao) {
        const d = botao.dataset;
        const inativando = d.acao === 'inativar';
        document.getElementById('status-id').value = d.id;
        document.getElementById('status-acao').value = d.acao;
        document.getElementById('status-titulo').textContent = inativando ? 'Inativar Serviço' : 'Reativar Serviço';
        document.getElementById('status-texto').textContent = inativando
            ? `Tem certeza que deseja inativar "${d.nome}"? Ele deixará de aparecer como opção nos agendamentos, mas o cadastro é mantido.`
            : `Deseja reativar "${d.nome}"? Ele voltará a ser listado como opção nos agendamentos.`;
        document.getElementById('status-botao').textContent = inativando ? 'Inativar' : 'Reativar';
        openModal('modal-status');
    }

    function filtrarServicos(termo) {
        const filtro = termo.trim().toLowerCase();
        const linhas = document.querySelectorAll('#tbody-servicos tr[data-busca]');
        let visiveis = 0;

        linhas.forEach(function (linha) {
            const corresponde = linha.dataset.busca.includes(filtro);
            linha.classList.toggle('hidden', !corresponde);
            if (corresponde) visiveis++;
        });

        const linhaSemResultado = document.getElementById('linha-sem-resultado');
        if (linhaSemResultado) {
            linhaSemResultado.classList.toggle('hidden', visiveis !== 0);
        }
    }

    <?php if ($reabrirCadastro): ?>
    openModal('modal-cadastrar');
    <?php endif; ?>

    <?php if ($reabrirEdicao): ?>
    openModal('modal-editar');
    <?php endif; ?>
</script>

</body>
</html>
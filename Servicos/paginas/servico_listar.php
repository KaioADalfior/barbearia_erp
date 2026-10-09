<?php
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/guard.php';
exigirSessao(['barbeiro']);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/ServicoFoto.php';
require_once __DIR__ . '/../../includes/AcessoService.php';

// Funcionário consulta os serviços; cadastrar/editar/inativar é do proprietário
// (os endpoints também recusam — ver Servicos/scripts/*).
$podeEditarServicos = AcessoService::ehProprietario($pdo);

$paginaAtual = 'servico-listar';

// ---------- Busca todos os serviços ----------
// Foto é opcional: se o banco ainda não tem a coluna, a lista funciona sem ela.
$temColunaFoto = ServicoFoto::colunaExiste($pdo);
$stmt = $pdo->query('SELECT idServico, nome, duracao_minutos, valor, ativo' . ($temColunaFoto ? ', foto' : '') . ' FROM Servico ORDER BY nome ASC');
$servicos = $stmt->fetchAll();

// ---------- Mensagens de status (retorno dos scripts) ----------
$statusGet = $_GET['status'] ?? '';

// ---------- Reabertura automática de modal em caso de erro de validação ----------
$reabrirCadastro = in_array($statusGet, ['cadastro-erro', 'cadastro-erro-foto'], true);
$reabrirEdicao   = in_array($statusGet, ['edicao-erro', 'edicao-erro-foto'], true);

$voltaNome     = $_GET['nome'] ?? '';
$voltaDuracao  = $_GET['duracao_minutos'] ?? '';
$voltaValor    = $_GET['valor'] ?? '';
$voltaEditId   = $_GET['edit_id'] ?? '';
$voltaAtivo    = $_GET['ativo'] ?? '1';

$textoErroFoto = ServicoFoto::MENSAGENS_ERRO[$_GET['foto_erro'] ?? ''] ?? 'Não foi possível enviar a foto.';
$avisoSemColuna = ' A foto não foi salva: o banco ainda não tem a coluna "foto" (execute scriptBD/atualizacao_servico_foto.sql).';

$mensagens = [
    'cadastro-sucesso'  => ['ok',   $voltaNome !== '' ? "Serviço $voltaNome cadastrado." : 'Serviço cadastrado com sucesso.'],
    'cadastro-sucesso-semfoto' => ['erro', ($voltaNome !== '' ? "Serviço $voltaNome cadastrado." : 'Serviço cadastrado.') . $avisoSemColuna],
    'cadastro-erro'     => ['erro', 'Preencha nome, duração e valor corretamente.'],
    'cadastro-erro-foto' => ['erro', $textoErroFoto . ' O serviço não foi cadastrado; escolha a foto novamente.'],
    'edicao-sucesso'    => ['ok',   $voltaNome !== '' ? "Serviço $voltaNome atualizado." : 'Serviço atualizado com sucesso.'],
    'edicao-sucesso-semfoto' => ['erro', ($voltaNome !== '' ? "Serviço $voltaNome atualizado." : 'Serviço atualizado.') . $avisoSemColuna],
    'edicao-erro'       => ['erro', 'Preencha nome, duração e valor corretamente.'],
    'edicao-erro-foto'  => ['erro', $textoErroFoto . ' O serviço não foi alterado; escolha a foto novamente.'],
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
<title>Serviços — BarbERP</title>

<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/admin-theme.css?v=3">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.css">
<script src="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.js"></script>

<style>
    .modal-overlay{
        background:rgba(0,0,0,0.7);
        backdrop-filter:blur(3px);
    }
    .modal-card{
        background:linear-gradient(180deg, var(--charcoal-2), var(--charcoal-3));
        border:1px solid rgba(var(--accent-rgb),0.16);
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
        color:var(--success-text);
        background:rgba(66,140,82,0.14);
        border:1px solid rgba(66,140,82,0.4);
    }
    .badge-inativo{
        color:var(--danger-text);
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
        border:1px solid var(--line);
        background:rgba(255,255,255,0.03);
        color:var(--text-muted);
        transition:color .15s, border-color .15s, background-color .15s;
    }
    .icon-btn:hover{
        color:var(--accent-strong);
        border-color:rgba(var(--accent-rgb),0.4);
        background:rgba(var(--accent-rgb),0.08);
    }
    .icon-btn-danger:hover{
        color:var(--danger-text);
        border-color:rgba(140,31,40,0.5);
        background:rgba(140,31,40,0.1);
    }
    table tbody tr{
        border-top:1px solid var(--line);
    }
    .view-row{
        display:flex;
        justify-content:space-between;
        gap:1rem;
        padding:0.85rem 0;
        border-top:1px solid var(--line);
    }
    .view-row:first-child{ border-top:none; }
    .view-label{
        font-size:11px;
        letter-spacing:0.1em;
        text-transform:uppercase;
        color:var(--text-muted);
    }
    .view-value{
        font-size:14px;
        color:var(--cream);
        text-align:right;
    }

    /* ---------- Foto do serviço ---------- */
    .servico-thumb{
        width:56px; height:42px; border-radius:0.6rem; overflow:hidden; flex-shrink:0;
        display:flex; align-items:center; justify-content:center;
        background:rgba(255,255,255,0.04); border:1px solid var(--line);
        color:#5b6f8c;
    }
    .servico-thumb img{ width:100%; height:100%; object-fit:cover; display:block; }
    .foto-campo{ display:flex; align-items:center; gap:14px; }
    .foto-campo__preview{
        width:96px; height:72px; border-radius:0.75rem; overflow:hidden; flex-shrink:0;
        display:flex; align-items:center; justify-content:center;
        background:var(--field-bg); border:1px dashed var(--line); color:#5b6f8c;
    }
    .foto-campo__preview img{ width:100%; height:100%; object-fit:cover; display:block; }
    .foto-campo__preview img.hidden{ display:none; }
    .foto-campo__acoes{ display:flex; flex-direction:column; align-items:flex-start; gap:6px; min-width:0; }
    .foto-campo__link{
        font-size:12px; font-weight:600; color:var(--accent-strong); cursor:pointer;
        background:none; border:none; padding:0;
    }
    .foto-campo__link:hover{ text-decoration:underline; }
    .foto-campo__link--perigo{ color:var(--danger-text); }
    .foto-campo__dica{ font-size:11px; color:var(--text-muted); }
    .view-foto{ width:100%; aspect-ratio:4/3; border-radius:0.9rem; overflow:hidden; margin-bottom:1rem; background:var(--field-bg); }
    .view-foto img{ width:100%; height:100%; object-fit:cover; display:block; }

    /* ---------- Modal de recorte da foto ---------- */
    .recorte-overlay{
        position:fixed; inset:0; z-index:200;
        display:none; align-items:center; justify-content:center;
        padding:16px; background:rgba(5,8,14,0.82);
    }
    .recorte-overlay.aberto{ display:flex; }
    .recorte-modal{
        width:100%; max-width:480px;
        background:linear-gradient(180deg, var(--charcoal-2), var(--charcoal-3));
        border:1px solid rgba(var(--accent-rgb),0.25);
        border-radius:20px; padding:20px;
        box-shadow:0 24px 60px -12px rgba(0,0,0,0.8);
    }
    .recorte-area{
        width:100%; height:min(62vw, 340px);
        background:#05080e; border-radius:12px; overflow:hidden;
    }
    .recorte-area img{ display:block; max-width:100%; }
    .recorte-modal .cropper-view-box{ outline:2px solid rgba(91,147,247,0.9); }
    .recorte-zoom{ width:100%; accent-color:var(--gold-light); }
    .recorte-btn{
        height:42px; padding:0 18px; border-radius:12px;
        font-size:14px; font-weight:600; cursor:pointer; transition:filter .15s;
    }
    .recorte-btn:hover{ filter:brightness(1.1); }
    .recorte-btn-sec{ background:rgba(255,255,255,0.06); color:var(--text-soft); border:1px solid var(--line); }
    .recorte-btn-pri{ background:linear-gradient(180deg, var(--gold-light), var(--gold)); color:var(--accent-on); border:none; }

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
            <p class="eyebrow uppercase mb-1" style="color:var(--accent-strong); opacity:.75">Serviços</p>
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

            <?php if ($podeEditarServicos): ?>
            <button type="button" onclick="openModal('modal-cadastrar')" class="btn-primary h-11 px-5 rounded-xl text-sm flex items-center justify-center gap-2 shrink-0">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 5v14M5 12h14" stroke="#ffffff" stroke-width="2" stroke-linecap="round"/>
                </svg>
                Cadastrar Serviço
            </button>
            <?php endif; ?>
        </div>

        <?php if (empty($servicos)): ?>

            <div class="panel-card rounded-2xl p-10 text-center">
                <p class="text-sm text-zinc-400">Nenhum serviço cadastrado ainda.</p>
                <?php if ($podeEditarServicos): ?>
                <button type="button" onclick="openModal('modal-cadastrar')" class="btn-secondary h-10 px-5 rounded-xl text-sm mt-4 inline-flex items-center">
                    Cadastrar o primeiro serviço
                </button>
                <?php endif; ?>
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
                                <th class="px-6 py-4 font-medium">Foto</th>
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
                                $fotoUrl = $temColunaFoto ? ServicoFoto::url($s['foto'] ?? null) : null;
                                $busca = function_exists('mb_strtolower')
                                    ? mb_strtolower($textoBusca, 'UTF-8')
                                    : strtolower($textoBusca);
                            ?>
                            <tr data-busca="<?= htmlspecialchars($busca) ?>">
                                <td class="px-6 py-4">
                                    <div class="servico-thumb">
                                        <?php if ($fotoUrl): ?>
                                            <img src="<?= htmlspecialchars($fotoUrl) ?>" alt="Foto de <?= htmlspecialchars($nome) ?>" loading="lazy">
                                        <?php else: ?>
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                                <rect x="3.5" y="5.5" width="17" height="13" rx="2" stroke="currentColor" stroke-width="1.4"/>
                                                <circle cx="9" cy="10.5" r="1.5" stroke="currentColor" stroke-width="1.3"/>
                                                <path d="M4 17l5-4.5 3.5 3 3-2.5L20 16" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/>
                                            </svg>
                                        <?php endif; ?>
                                    </div>
                                </td>
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
                                            data-foto="<?= htmlspecialchars((string) $fotoUrl) ?>"
                                            onclick="openViewModal(this)">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/>
                                                <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.4"/>
                                            </svg>
                                        </button>

                                        <?php if ($podeEditarServicos): ?>
                                        <button type="button" title="Editar" class="icon-btn"
                                            data-id="<?= (int) $s['idServico'] ?>"
                                            data-nome="<?= htmlspecialchars($nome) ?>"
                                            data-duracao="<?= $duracao ?>"
                                            data-valor="<?= htmlspecialchars(number_format($valor, 2, '.', '')) ?>"
                                            data-ativo="<?= $ativo ? '1' : '0' ?>"
                                            data-foto="<?= htmlspecialchars((string) $fotoUrl) ?>"
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
                                        <?php endif; ?>

                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>

                            <tr id="linha-sem-resultado" class="hidden">
                                <td colspan="6" class="px-6 py-10 text-center text-zinc-500 text-sm">
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
    <div class="modal-card rounded-3xl shadow-2xl overflow-x-hidden overflow-y-auto max-h-[92vh] w-full max-w-md">
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

            <form action="/Servicos/scripts/servico_salvar.php" method="POST" enctype="multipart/form-data" autocomplete="off">
                <?= csrf_field() ?>
                <div class="mb-5">
                    <label class="field-label block mb-2 uppercase" for="nome">Nome do Serviço</label>
                    <input id="nome" name="nome" type="text" placeholder="Ex: Corte, Barba, Corte + Barba" required
                           value="<?= $reabrirCadastro ? htmlspecialchars($voltaNome) : '' ?>"
                           class="field w-full h-12 px-4 rounded-xl text-sm">
                </div>

                <div class="grid grid-cols-2 gap-4 mb-5">
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

                <div class="mb-7">
                    <label class="field-label block mb-2 uppercase">Foto do serviço <span class="normal-case" style="letter-spacing:0;color:var(--text-muted)">(opcional)</span></label>
                    <div class="foto-campo">
                        <div class="foto-campo__preview">
                            <img id="cad-foto-preview" class="hidden" alt="Pré-visualização da foto">
                            <svg id="cad-foto-vazio" width="26" height="26" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <rect x="3.5" y="5.5" width="17" height="13" rx="2" stroke="currentColor" stroke-width="1.4"/>
                                <circle cx="9" cy="10.5" r="1.5" stroke="currentColor" stroke-width="1.3"/>
                                <path d="M4 17l5-4.5 3.5 3 3-2.5L20 16" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <div class="foto-campo__acoes">
                            <button type="button" class="foto-campo__link" id="cad-foto-escolher">Escolher foto</button>
                            <button type="button" class="foto-campo__link foto-campo__link--perigo hidden" id="cad-foto-remover">Remover foto</button>
                            <span class="foto-campo__dica">JPG, PNG ou WEBP. Você poderá ajustar o enquadramento.</span>
                        </div>
                        <input type="file" id="cad-foto-picker" accept="image/jpeg,image/png,image/webp" class="hidden">
                        <input type="file" id="cad-foto-input" name="foto" class="hidden">
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
    <div class="modal-card rounded-3xl shadow-2xl overflow-x-hidden overflow-y-auto max-h-[92vh] w-full max-w-md">
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

            <form action="/Servicos/scripts/servico_atualizar.php" method="POST" enctype="multipart/form-data" autocomplete="off">
                <?= csrf_field() ?>
                <input type="hidden" id="edit-id" name="id" value="<?= $reabrirEdicao ? htmlspecialchars($voltaEditId) : '' ?>">

                <div class="mb-5">
                    <label class="field-label block mb-2 uppercase" for="edit-nome">Nome do Serviço</label>
                    <input id="edit-nome" name="nome" type="text" placeholder="Ex: Corte, Barba, Corte + Barba" required
                           value="<?= $reabrirEdicao ? htmlspecialchars($voltaNome) : '' ?>"
                           class="field w-full h-12 px-4 rounded-xl text-sm">
                </div>

                <div class="grid grid-cols-2 gap-4 mb-5">
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

                <div class="mb-7">
                    <label class="field-label block mb-2 uppercase">Foto do serviço <span class="normal-case" style="letter-spacing:0;color:var(--text-muted)">(opcional)</span></label>
                    <div class="foto-campo">
                        <div class="foto-campo__preview">
                            <img id="edit-foto-preview" class="hidden" alt="Pré-visualização da foto">
                            <svg id="edit-foto-vazio" width="26" height="26" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <rect x="3.5" y="5.5" width="17" height="13" rx="2" stroke="currentColor" stroke-width="1.4"/>
                                <circle cx="9" cy="10.5" r="1.5" stroke="currentColor" stroke-width="1.3"/>
                                <path d="M4 17l5-4.5 3.5 3 3-2.5L20 16" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <div class="foto-campo__acoes">
                            <button type="button" class="foto-campo__link" id="edit-foto-escolher">Escolher foto</button>
                            <button type="button" class="foto-campo__link foto-campo__link--perigo hidden" id="edit-foto-remover">Remover foto</button>
                            <span class="foto-campo__dica">JPG, PNG ou WEBP. Você poderá ajustar o enquadramento.</span>
                        </div>
                        <input type="file" id="edit-foto-picker" accept="image/jpeg,image/png,image/webp" class="hidden">
                        <input type="file" id="edit-foto-input" name="foto" class="hidden">
                    <input type="hidden" id="edit-remover-foto" name="remover_foto" value="0">
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

            <div id="view-foto-wrap" class="view-foto hidden">
                <img id="view-foto" alt="Foto do serviço">
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

            <form action="/Servicos/scripts/servico_status.php" method="POST" autocomplete="off">
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

<!-- ==================== MODAL: AJUSTAR FOTO ==================== -->
<div id="recorte-overlay" class="recorte-overlay" role="dialog" aria-modal="true" aria-labelledby="recorte-titulo">
    <div class="recorte-modal">
        <p id="recorte-titulo" class="text-sm font-semibold text-[color:var(--cream)] mb-1">Ajustar foto do serviço</p>
        <p class="text-xs mb-3" style="color:var(--text-muted)">Arraste para posicionar e use o controle para aproximar ou afastar.</p>
        <div class="recorte-area"><img id="recorte-img" alt="Pré-visualização"></div>
        <div class="flex items-center gap-3 mt-4">
            <span class="text-xs" style="color:var(--text-muted)">&minus;</span>
            <input type="range" id="recorte-zoom" class="recorte-zoom" min="0" max="1" step="0.01" value="0" aria-label="Zoom">
            <span class="text-xs" style="color:var(--text-muted)">+</span>
        </div>
        <div class="flex justify-end gap-3 mt-5">
            <button type="button" id="recorte-cancelar" class="recorte-btn recorte-btn-sec">Cancelar</button>
            <button type="button" id="recorte-salvar" class="recorte-btn recorte-btn-pri">Usar foto</button>
        </div>
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
            // Com o recorte de foto aberto, o Esc fecha só ele (tratado mais abaixo).
            if (document.getElementById('recorte-overlay').classList.contains('aberto')) return;
            ['modal-cadastrar', 'modal-editar', 'modal-visualizar', 'modal-status'].forEach(closeModal);
        }
    });

    // ---------- Foto do serviço: escolher -> ajustar/recortar -> anexar ao formulário ----------
    // A imagem recortada (800x600, JPEG) é colocada no <input type="file" name="foto">
    // do formulário e segue junto com o "Salvar" do cadastro/edição.
    const recorte = (function () {
        const overlay  = document.getElementById('recorte-overlay');
        const imgEl    = document.getElementById('recorte-img');
        const zoomEl   = document.getElementById('recorte-zoom');
        const btnSalvar   = document.getElementById('recorte-salvar');
        const btnCancelar = document.getElementById('recorte-cancelar');
        const LARGURA = 800, ALTURA = 600; // proporção 4:3
        let cropper = null, urlTemp = null, baseRatio = 0, aoConfirmar = null;

        function fechar() {
            overlay.classList.remove('aberto');
            if (cropper) { cropper.destroy(); cropper = null; }
            baseRatio = 0; aoConfirmar = null;
            if (urlTemp) { URL.revokeObjectURL(urlTemp); urlTemp = null; }
            imgEl.removeAttribute('src');
            btnSalvar.disabled = false;
        }

        function abrir(arquivo, callback) {
            if (!/^image\/(jpeg|png|webp)$/.test(arquivo.type)) {
                toast('Formato inválido. Envie uma imagem JPG, PNG ou WEBP.', 'erro');
                return;
            }
            if (typeof Cropper === 'undefined') {
                toast('Não foi possível carregar o editor de imagem. Recarregue a página.', 'erro');
                return;
            }
            aoConfirmar = callback;
            urlTemp = URL.createObjectURL(arquivo);
            imgEl.src = urlTemp;
            overlay.classList.add('aberto');

            if (cropper) cropper.destroy();
            cropper = new Cropper(imgEl, {
                aspectRatio: LARGURA / ALTURA,
                viewMode: 1,
                dragMode: 'move',
                autoCropArea: 0.95,
                background: false,
                guides: false,
                center: false,
                highlight: false,
                cropBoxMovable: false,
                cropBoxResizable: false,
                toggleDragModeOnDblclick: false,
                ready: function () {
                    zoomEl.value = 0;
                    baseRatio = cropper.getImageData().width / cropper.getImageData().naturalWidth;
                },
                zoom: function (e) {
                    if (!baseRatio) return;
                    zoomEl.value = Math.min(1, Math.max(0, (e.detail.ratio / baseRatio - 1) / 3));
                }
            });
        }

        zoomEl.addEventListener('input', function () {
            if (!cropper || !baseRatio) return;
            cropper.zoomTo(baseRatio * (1 + parseFloat(this.value) * 3));
        });

        btnCancelar.addEventListener('click', fechar);
        overlay.addEventListener('click', function (e) { if (e.target === overlay) fechar(); });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && overlay.classList.contains('aberto')) fechar();
        });

        btnSalvar.addEventListener('click', function () {
            if (!cropper) return;
            btnSalvar.disabled = true;
            const canvas = cropper.getCroppedCanvas({
                width: LARGURA, height: ALTURA, fillColor: '#ffffff', imageSmoothingQuality: 'high'
            });
            canvas.toBlob(function (blob) {
                if (!blob) {
                    toast('Não foi possível processar a imagem.', 'erro');
                    btnSalvar.disabled = false;
                    return;
                }
                const callback = aoConfirmar;
                const arquivo = new File([blob], 'servico.jpg', { type: 'image/jpeg' });
                const previewUrl = canvas.toDataURL('image/jpeg', 0.85);
                fechar();
                if (callback) callback(arquivo, previewUrl);
            }, 'image/jpeg', 0.88);
        });

        return { abrir: abrir };
    })();

    // Liga um campo de foto (cadastro: prefixo "cad"; edição: "edit").
    function criarCampoFoto(p) {
        const picker  = document.getElementById(p + '-foto-picker');
        const input   = document.getElementById(p + '-foto-input');
        const preview = document.getElementById(p + '-foto-preview');
        const vazio   = document.getElementById(p + '-foto-vazio');
        const btnEsc  = document.getElementById(p + '-foto-escolher');
        const btnRem  = document.getElementById(p + '-foto-remover');
        const remover = document.getElementById(p + '-remover-foto'); // só existe na edição

        function mostrar(src) {
            if (src) {
                preview.src = src;
                preview.classList.remove('hidden');
                vazio.classList.add('hidden');
                if (btnRem) btnRem.classList.remove('hidden');
                btnEsc.textContent = 'Trocar foto';
            } else {
                preview.removeAttribute('src');
                preview.classList.add('hidden');
                vazio.classList.remove('hidden');
                if (btnRem) btnRem.classList.add('hidden');
                btnEsc.textContent = 'Escolher foto';
            }
        }

        btnEsc.addEventListener('click', function () { picker.click(); });

        picker.addEventListener('change', function () {
            const arquivo = this.files[0];
            this.value = '';
            if (!arquivo) return;
            recorte.abrir(arquivo, function (recortado, previewUrl) {
                const dt = new DataTransfer();
                dt.items.add(recortado);
                input.files = dt.files;
                if (remover) remover.value = '0';
                mostrar(previewUrl);
            });
        });

        if (btnRem) {
            btnRem.addEventListener('click', function () {
                input.value = '';
                if (remover) remover.value = '1';
                mostrar('');
            });
        }

        return {
            // Estado inicial ao abrir a edição: foto atual do serviço (ou nenhuma).
            definir: function (src) {
                input.value = '';
                if (remover) remover.value = '0';
                mostrar(src);
            }
        };
    }

    const campoFotoCad  = criarCampoFoto('cad');
    const campoFotoEdit = criarCampoFoto('edit');

    function openEditModal(botao) {
        const d = botao.dataset;
        document.getElementById('edit-id').value = d.id;
        document.getElementById('edit-nome').value = d.nome;
        document.getElementById('edit-duracao_minutos').value = d.duracao;
        document.getElementById('edit-valor').value = d.valor;
        document.getElementById(d.ativo === '1' ? 'edit-ativo-sim' : 'edit-ativo-nao').checked = true;
        campoFotoEdit.definir(d.foto || '');
        openModal('modal-editar');
    }

    function openViewModal(botao) {
        const d = botao.dataset;
        document.getElementById('view-nome').textContent = d.nome;
        document.getElementById('view-duracao').textContent = d.duracao;
        document.getElementById('view-valor').textContent = d.valor;
        document.getElementById('view-status').textContent = d.status;
        const wrap = document.getElementById('view-foto-wrap');
        if (d.foto) {
            document.getElementById('view-foto').src = d.foto;
            wrap.classList.remove('hidden');
        } else {
            wrap.classList.add('hidden');
            document.getElementById('view-foto').removeAttribute('src');
        }
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
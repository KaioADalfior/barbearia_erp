<?php
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/guard.php';
exigirSessao(['admin']);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/csrf.php';

$paginaAtual = 'barbeiro-listar';

// ---------- Busca todos os barbeiros ----------
$stmt = $pdo->query(
    'SELECT id_barbeiro, nome, login, telefone, foto, senha, criado_em, tipo_usuario
     FROM Barbeiro
     ORDER BY nome ASC'
);
$barbeiros = $stmt->fetchAll();

$statusGet = $_GET['status'] ?? '';
$mensagens = [
    'edicao-sucesso' => ['ok',   'Dados do barbeiro atualizados com sucesso.'],
    'edicao-erro'    => ['erro', 'Preencha nome, login e telefone corretamente.'],
    'login-existe'   => ['erro', 'Já existe outro barbeiro com esse login. Escolha outro.'],
    'nao-encontrado' => ['erro', 'Barbeiro não encontrado.'],
    'ultimo-proprietario' => ['erro', 'A barbearia precisa ter ao menos um Proprietário. Marque outro barbeiro como Proprietário antes de mudar este.'],
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<?php include __DIR__ . '/../../includes/theme-init.php'; ?>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Barbeiros — BarbERP</title>

<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/admin-theme.css?v=2">

<style>
    table tbody tr{ border-top:1px solid rgba(255,255,255,0.05); }
    .avatar{
        width:36px; height:36px; border-radius:9999px; object-fit:cover;
        border:1px solid rgba(255,255,255,0.1);
    }
    .avatar-fallback{
        width:36px; height:36px; border-radius:9999px;
        display:flex; align-items:center; justify-content:center;
        background:rgba(61,126,201,0.14); color:var(--gold-light);
        font-weight:600; font-size:13px;
        border:1px solid rgba(255,255,255,0.08);
    }
    .badge{
        display:inline-flex; align-items:center; gap:0.35rem;
        font-size:11px; font-weight:600; letter-spacing:0.04em;
        padding:0.28rem 0.65rem; border-radius:999px;
    }
    .badge-hash{
        color:#bfe6c7; background:rgba(66,140,82,0.14); border:1px solid rgba(66,140,82,0.4);
    }
    .badge-prop{ color:#f0d18a; background:rgba(184,140,24,0.14); border:1px solid rgba(184,140,24,0.45); }
    .badge-func{ color:#9dc4f0; background:rgba(61,126,201,0.14); border:1px solid rgba(61,126,201,0.4); }
    .tipo-opcoes{ display:grid; grid-template-columns:1fr; gap:.6rem; }
    @media (min-width:480px){ .tipo-opcoes{ grid-template-columns:1fr 1fr; } }
    .tipo-opcao{ position:relative; display:block; cursor:pointer; }
    .tipo-opcao input{ position:absolute; opacity:0; inset:0; width:100%; height:100%; cursor:pointer; margin:0; }
    .tipo-opcao__card{
        display:flex; gap:.6rem; align-items:flex-start; padding:.75rem .85rem; border-radius:.8rem;
        background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.10);
        transition:border-color .15s, background .15s;
    }
    .tipo-opcao__dot{
        flex:0 0 auto; width:18px; height:18px; margin-top:1px; border-radius:9999px;
        border:2px solid rgba(255,255,255,0.35); display:flex; align-items:center; justify-content:center;
    }
    .tipo-opcao__dot::after{ content:''; width:8px; height:8px; border-radius:9999px; background:transparent; transition:background .15s; }
    .tipo-opcao input:checked + .tipo-opcao__card{ border-color:rgba(61,126,201,0.75); background:rgba(61,126,201,0.12); }
    .tipo-opcao input:checked + .tipo-opcao__card .tipo-opcao__dot{ border-color:#6fa8ea; }
    .tipo-opcao input:checked + .tipo-opcao__card .tipo-opcao__dot::after{ background:#6fa8ea; }
    .tipo-opcao input:focus-visible + .tipo-opcao__card{ outline:2px solid #6fa8ea; outline-offset:2px; }
    .tipo-opcao__titulo{ font-size:13px; font-weight:600; color:var(--cream); }
    .tipo-opcao__desc{ font-size:11.5px; line-height:1.4; color:#8b97ac; margin-top:1px; }
    .badge-pendente{
        color:#f0d18a; background:rgba(184,140,24,0.14); border:1px solid rgba(184,140,24,0.45);
    }
</style>
</head>
<body class="flex">

<?php include __DIR__ . '/../../includes/sidebar.php'; ?>
<?php include __DIR__ . '/../../includes/toast.php'; ?>

<main class="flex-1 min-w-0">

    <header class="topbar px-5 sm:px-8 py-5 sm:py-6 flex items-center gap-4">
        <button type="button" onclick="abrirMenuMobile()" class="menu-toggle-btn lg:hidden" aria-label="Abrir menu">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M4 6h16M4 12h16M4 18h16" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
            </svg>
        </button>
        <div class="min-w-0 flex-1">
            <p class="eyebrow uppercase mb-1" style="color:var(--gold-light); opacity:.75">Barbeiro</p>
            <h1 class="display text-3xl sm:text-4xl text-[color:var(--cream)] truncate">Barbeiros Cadastrados</h1>
        </div>
        <a href="/barbeiros/cadastrar" class="btn-primary h-11 px-5 rounded-xl text-sm flex items-center justify-center gap-2 shrink-0">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 5v14M5 12h14" stroke="#ffffff" stroke-width="2" stroke-linecap="round"/>
            </svg>
            <span class="hidden sm:inline">Cadastrar Barbeiro</span>
        </a>
    </header>

    <section class="p-5 sm:p-8">

        <?php if (isset($mensagens[$statusGet])): [$tipoMsg, $textoMsg] = $mensagens[$statusGet]; ?>
            <script>toast(<?= json_encode($textoMsg) ?>, <?= json_encode($tipoMsg === 'ok' ? 'sucesso' : 'erro') ?>);</script>
        <?php endif; ?>

        <div class="panel-card rounded-2xl p-4 mb-6">
            <div class="relative w-full sm:max-w-sm">
                <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="11" cy="11" r="6.5" stroke="#7f8fac" stroke-width="1.5"/>
                    <path d="M20 20l-4.3-4.3" stroke="#7f8fac" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
                <input
                    id="busca-barbeiro"
                    type="text"
                    placeholder="Buscar por nome, login ou telefone..."
                    autocomplete="off"
                    class="field w-full h-11 pl-10 pr-4 rounded-xl text-sm"
                    oninput="filtrarBarbeiros(this.value)"
                >
            </div>
        </div>

        <?php if (empty($barbeiros)): ?>

            <div class="panel-card rounded-2xl p-10 text-center">
                <p class="text-sm text-zinc-400">Nenhum barbeiro cadastrado ainda.</p>
            </div>

        <?php else: ?>

            <div class="panel-card rounded-2xl overflow-hidden">
                <div class="barber-stripe-thin"></div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left">
                                <th class="px-5 py-4 text-xs uppercase tracking-wide text-zinc-500 font-medium">Barbeiro</th>
                                <th class="px-5 py-4 text-xs uppercase tracking-wide text-zinc-500 font-medium">Login</th>
                                <th class="px-5 py-4 text-xs uppercase tracking-wide text-zinc-500 font-medium">Telefone</th>
                                <th class="px-5 py-4 text-xs uppercase tracking-wide text-zinc-500 font-medium">Acesso</th>
                                <th class="px-5 py-4 text-xs uppercase tracking-wide text-zinc-500 font-medium">Cadastrado em</th>
                                <th class="px-5 py-4 text-xs uppercase tracking-wide text-zinc-500 font-medium">Senha</th>
                                <th class="px-5 py-4"></th>
                            </tr>
                        </thead>
                        <tbody id="tbody-barbeiros">
                            <?php foreach ($barbeiros as $b):
                                $busca = mb_strtolower($b['nome'] . ' ' . $b['login'] . ' ' . $b['telefone']);
                                $inicial = mb_strtoupper(mb_substr($b['nome'], 0, 1));
                                $criadoEm = $b['criado_em'] ? date('d/m/Y', strtotime($b['criado_em'])) : '—';

                                // Indicador informativo (não é dado sensível): só mostra se a
                                // senha já é um hash bcrypt ou se ainda está em texto puro
                                // aguardando o primeiro login pós-migração de segurança.
                                $infoHash = password_get_info($b['senha']);
                                $senhaJaEhHash = $infoHash['algoName'] !== 'unknown';
                            ?>
                            <tr data-busca="<?= htmlspecialchars($busca) ?>"
                                class="cursor-pointer transition-colors hover:bg-white/[0.03]"
                                onclick="openEditModal(this)"
                                data-id="<?= (int) $b['id_barbeiro'] ?>"
                                data-nome="<?= htmlspecialchars($b['nome'], ENT_QUOTES) ?>"
                                data-login="<?= htmlspecialchars($b['login'], ENT_QUOTES) ?>"
                                data-telefone="<?= htmlspecialchars($b['telefone'], ENT_QUOTES) ?>"
                                data-tipo="<?= htmlspecialchars($b['tipo_usuario'] ?? 'proprietario', ENT_QUOTES) ?>">
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-3">
                                        <?php if (!empty($b['foto'])): ?>
                                            <img src="/assets/uploads/perfil/<?= htmlspecialchars($b['foto']) ?>" alt="" class="avatar">
                                        <?php else: ?>
                                            <div class="avatar-fallback"><?= htmlspecialchars($inicial) ?></div>
                                        <?php endif; ?>
                                        <span class="text-[color:var(--cream)]"><?= htmlspecialchars($b['nome']) ?></span>
                                    </div>
                                </td>
                                <td class="px-5 py-3 text-zinc-300"><?= htmlspecialchars($b['login']) ?></td>
                                <td class="px-5 py-3 text-zinc-300"><?= htmlspecialchars($b['telefone']) ?></td>
                                <td class="px-5 py-3">
                                    <?php if (($b['tipo_usuario'] ?? 'proprietario') === 'funcionario'): ?>
                                        <span class="badge badge-func">Funcionário</span>
                                    <?php else: ?>
                                        <span class="badge badge-prop">Proprietário</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-5 py-3 text-zinc-400"><?= htmlspecialchars($criadoEm) ?></td>
                                <td class="px-5 py-3">
                                    <?php if ($senhaJaEhHash): ?>
                                        <span class="badge badge-hash">Protegida</span>
                                    <?php else: ?>
                                        <span class="badge badge-pendente" title="Vira hash automaticamente no próximo login desse barbeiro">Aguardando 1º login</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <span class="icon-btn" title="Editar" aria-hidden="true">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M4 20h4L18.5 9.5a2.1 2.1 0 0 0-3-3L5 17v3z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/>
                                        </svg>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <tr id="linha-sem-resultado" class="hidden">
                                <td colspan="7" class="px-5 py-8 text-center text-zinc-500">Nenhum barbeiro encontrado.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="barber-stripe-thin"></div>
            </div>

        <?php endif; ?>

    </section>

</main>

<!-- ==================== MODAL: EDITAR BARBEIRO ==================== -->
<div id="modal-editar" class="modal-overlay fixed inset-0 z-50 hidden flex items-center justify-center p-4" onclick="fecharAoClicarFora(event, 'modal-editar')">
    <div class="modal-card rounded-3xl shadow-2xl overflow-hidden w-full max-w-md">
        <div class="barber-stripe-thin"></div>
        <div class="p-7">
            <div class="flex items-start justify-between mb-6">
                <h2 class="display text-2xl text-[color:var(--cream)]">Editar Barbeiro</h2>
                <button type="button" onclick="closeModal('modal-editar')" class="icon-btn shrink-0">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>

            <form action="/Barbeiros/scripts/barbeiro_atualizar.php" method="POST" autocomplete="off">
                <?= csrf_field() ?>
                <input type="hidden" id="edit-id" name="id" value="">

                <div class="mb-5">
                    <label class="field-label block mb-2 uppercase" for="edit-nome">Nome</label>
                    <input id="edit-nome" name="nome" type="text" required
                           class="field w-full h-12 px-4 rounded-xl text-sm">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-5">
                    <div>
                        <label class="field-label block mb-2 uppercase" for="edit-login">Login</label>
                        <input id="edit-login" name="login" type="text" required
                               class="field w-full h-12 px-4 rounded-xl text-sm">
                    </div>
                    <div>
                        <label class="field-label block mb-2 uppercase" for="edit-telefone">Telefone</label>
                        <input id="edit-telefone" name="telefone" type="text" required
                               class="field w-full h-12 px-4 rounded-xl text-sm">
                    </div>
                </div>

                <div class="mb-5">
                    <span class="field-label block mb-2 uppercase">Tipo de acesso</span>
                    <div class="tipo-opcoes" role="radiogroup" aria-label="Tipo de acesso">
                        <label class="tipo-opcao">
                            <input type="radio" name="tipo_usuario" id="edit-tipo-proprietario" value="proprietario" required>
                            <span class="tipo-opcao__card">
                                <span class="tipo-opcao__dot"></span>
                                <span>
                                    <span class="tipo-opcao__titulo block">Proprietário</span>
                                    <span class="tipo-opcao__desc block">Acesso total ao sistema e ao financeiro.</span>
                                </span>
                            </span>
                        </label>
                        <label class="tipo-opcao">
                            <input type="radio" name="tipo_usuario" id="edit-tipo-funcionario" value="funcionario" required>
                            <span class="tipo-opcao__card">
                                <span class="tipo-opcao__dot"></span>
                                <span>
                                    <span class="tipo-opcao__titulo block">Funcionário</span>
                                    <span class="tipo-opcao__desc block">Só os próprios dados; recebe comissão.</span>
                                </span>
                            </span>
                        </label>
                    </div>
                </div>

                <div class="mb-7">
                    <label class="field-label block mb-2 uppercase" for="edit-senha">Nova senha</label>
                    <input id="edit-senha" name="senha" type="password" minlength="6"
                           placeholder="Deixe em branco para não alterar"
                           class="field w-full h-12 px-4 rounded-xl text-sm">
                    <p class="text-xs text-zinc-500 mt-2">Só preencha se quiser trocar a senha dele agora. Em branco, a senha atual continua valendo.</p>
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
        if (e.key === 'Escape') closeModal('modal-editar');
    });

    function openEditModal(linha) {
        const d = linha.dataset;
        document.getElementById('edit-id').value = d.id;
        document.getElementById('edit-nome').value = d.nome;
        document.getElementById('edit-login').value = d.login;
        document.getElementById('edit-telefone').value = d.telefone;
        document.getElementById('edit-senha').value = '';
        definirTipoEdicao(d.tipo);
        openModal('modal-editar');
    }

    function definirTipoEdicao(tipo) {
        document.getElementById(tipo === 'funcionario' ? 'edit-tipo-funcionario' : 'edit-tipo-proprietario').checked = true;
    }

    <?php if (in_array($statusGet, ['edicao-erro', 'login-existe', 'ultimo-proprietario'], true)): ?>
    // reabre o modal com os dados que a pessoa tinha digitado, pra não perder o que já preencheu
    document.getElementById('edit-id').value = <?= json_encode($_GET['edit_id'] ?? '') ?>;
    document.getElementById('edit-nome').value = <?= json_encode($_GET['nome'] ?? '') ?>;
    document.getElementById('edit-login').value = <?= json_encode($_GET['login'] ?? '') ?>;
    document.getElementById('edit-telefone').value = <?= json_encode($_GET['telefone'] ?? '') ?>;
    definirTipoEdicao(<?= json_encode($_GET['tipo_usuario'] ?? 'proprietario') ?>);
    openModal('modal-editar');
    <?php endif; ?>

    function filtrarBarbeiros(termo) {
        const filtro = termo.trim().toLowerCase();
        const linhas = document.querySelectorAll('#tbody-barbeiros tr[data-busca]');
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
</script>

</body>
</html>

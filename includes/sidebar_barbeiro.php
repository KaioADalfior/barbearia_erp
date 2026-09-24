<?php
// includes/sidebar_barbeiro.php
// Inclua dentro de <body>, passando $paginaAtual ('dashboard', 'agendar')
$paginaAtual = $paginaAtual ?? '';
?>
<aside id="app-sidebar" class="ab-sidebar">

    <!-- Marca -->
    <div class="ab-sidebar__brand">
        <div class="ab-sidebar__brand-text">
            <p class="ab-sidebar__brand-name">Barb<span>ERP</span></p>
        </div>

        <div class="ab-sidebar__spacer">
            <button type="button" onclick="fecharMenuMobile()" class="menu-toggle-btn ab-sidebar__btn ab-sidebar__btn--close" aria-label="Fechar menu">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                </svg>
            </button>

            <button type="button" onclick="alternarColapsoSidebar()" class="menu-toggle-btn ab-sidebar__btn ab-sidebar__btn--collapse" aria-label="Recolher menu">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
        </div>
    </div>

    <!-- Navegação -->
    <nav class="ab-sidebar__nav">

        <a href="/Painel/paginas/painel_barbeiro.php" title="Início"
           class="ab-nav-item <?= $paginaAtual === 'dashboard' ? 'is-active' : '' ?>">
            <span class="ab-nav-item__icon">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="3.5" y="3.5" width="7" height="7" rx="1.3" stroke="currentColor" stroke-width="1.4"/>
                    <rect x="13.5" y="3.5" width="7" height="7" rx="1.3" stroke="currentColor" stroke-width="1.4"/>
                    <rect x="3.5" y="13.5" width="7" height="7" rx="1.3" stroke="currentColor" stroke-width="1.4"/>
                    <rect x="13.5" y="13.5" width="7" height="7" rx="1.3" stroke="currentColor" stroke-width="1.4"/>
                </svg>
            </span>
            <span class="ab-nav-item__label ab-label">Início</span>
        </a>

        <!-- Grupo: Cliente -->
        <div>
            <button type="button"
                    class="ab-nav-item ab-nav-item--toggle"
                    title="Cliente"
                    onclick="alternarGrupoSidebar('grupo-cliente', this)">
                <span class="ab-nav-item__main">
                    <span class="ab-nav-item__icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="12" cy="8" r="3.3" stroke="currentColor" stroke-width="1.4"/>
                            <path d="M5 20c0-3.6 3.1-6.5 7-6.5s7 2.9 7 6.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
                        </svg>
                    </span>
                    <span class="ab-nav-item__label ab-label">Cliente</span>
                </span>
                <span class="ab-nav-item__chevron ab-label <?= $paginaAtual === 'cliente-listar' ? 'is-open' : '' ?>">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </span>
            </button>

            <div id="grupo-cliente" class="ab-subnav <?= $paginaAtual === 'cliente-listar' ? '' : 'ab-hidden' ?>">
                <a href="/Clientes/paginas/cliente_listar.php"
                   class="ab-subnav__item <?= $paginaAtual === 'cliente-listar' ? 'is-active' : '' ?>">
                    Gerenciar Clientes
                </a>
            </div>
        </div>

        <!-- Grupo: Serviços -->
        <div>
            <button type="button"
                    class="ab-nav-item ab-nav-item--toggle"
                    title="Serviços"
                    onclick="alternarGrupoSidebar('grupo-servico', this)">
                <span class="ab-nav-item__main">
                    <span class="ab-nav-item__icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M6.5 5.5a2.5 2.5 0 1 1 3.4 3.4L18 17.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
                            <path d="M6.5 18.5a2.5 2.5 0 1 0 3.4-3.4L18 6.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
                            <circle cx="6.2" cy="6.2" r="1.5" stroke="currentColor" stroke-width="1.2"/>
                            <circle cx="6.2" cy="17.8" r="1.5" stroke="currentColor" stroke-width="1.2"/>
                        </svg>
                    </span>
                    <span class="ab-nav-item__label ab-label">Serviços</span>
                </span>
                <span class="ab-nav-item__chevron ab-label <?= $paginaAtual === 'servico-listar' ? 'is-open' : '' ?>">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </span>
            </button>

            <div id="grupo-servico" class="ab-subnav <?= $paginaAtual === 'servico-listar' ? '' : 'ab-hidden' ?>">
                <a href="/Servicos/paginas/servico_listar.php"
                   class="ab-subnav__item <?= $paginaAtual === 'servico-listar' ? 'is-active' : '' ?>">
                    Gerenciar Serviço
                </a>
            </div>
        </div>

        <!-- Grupo: Agendamento -->
        <div>
            <button type="button"
                    class="ab-nav-item ab-nav-item--toggle"
                    title="Agendamento"
                    onclick="alternarGrupoSidebar('grupo-agendamento', this)">
                <span class="ab-nav-item__main">
                    <span class="ab-nav-item__icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <rect x="3.5" y="4.5" width="17" height="16" rx="2" stroke="currentColor" stroke-width="1.4"/>
                            <path d="M3.5 9.5h17" stroke="currentColor" stroke-width="1.4"/>
                            <path d="M8 3v3M16 3v3" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
                        </svg>
                    </span>
                    <span class="ab-nav-item__label ab-label">Agendamento</span>
                </span>
                <span class="ab-nav-item__chevron ab-label <?= in_array($paginaAtual, ['agendar', 'agendamento-listar']) ? 'is-open' : '' ?>">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </span>
            </button>

            <div id="grupo-agendamento" class="ab-subnav <?= in_array($paginaAtual, ['agendar', 'agendamento-listar']) ? '' : 'ab-hidden' ?>">
                <a href="/Agendamentos/paginas/agendar.php"
                   class="ab-subnav__item <?= $paginaAtual === 'agendar' ? 'is-active' : '' ?>">
                    Agendar
                </a>
                <a href="/Agendamentos/paginas/agendamento_listar.php"
                   class="ab-subnav__item <?= $paginaAtual === 'agendamento-listar' ? 'is-active' : '' ?>">
                    Agendamentos
                </a>
            </div>
        </div>

        <!-- Grupo: Financeiro -->
        <div>
            <button type="button"
                    class="ab-nav-item ab-nav-item--toggle"
                    title="Financeiro"
                    onclick="alternarGrupoSidebar('grupo-financeiro', this)">
                <span class="ab-nav-item__main">
                    <span class="ab-nav-item__icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 3.5v17M16.5 7.2c0-1.6-1.6-2.7-4-2.7-2.6 0-4.3 1.2-4.3 3s1.4 2.5 4.3 3c2.9.5 4.3 1.3 4.3 3.1 0 1.8-1.8 3-4.3 3-2.2 0-4-1-4.3-2.6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                    <span class="ab-nav-item__label ab-label">Financeiro</span>
                </span>
                <span class="ab-nav-item__chevron ab-label <?= in_array($paginaAtual, ['financeiro-dashboard', 'financeiro-baixa', 'financeiro-fiados', 'financeiro-relatorios']) ? 'is-open' : '' ?>">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </span>
            </button>

            <div id="grupo-financeiro" class="ab-subnav <?= in_array($paginaAtual, ['financeiro-dashboard', 'financeiro-baixa', 'financeiro-fiados', 'financeiro-relatorios']) ? '' : 'ab-hidden' ?>">
                <a href="/Financeiro/paginas/financeiro_dashboard.php"
                   class="ab-subnav__item <?= $paginaAtual === 'financeiro-dashboard' ? 'is-active' : '' ?>">
                    Dashboard
                </a>
                <a href="/Financeiro/paginas/financeiro_baixa.php"
                   class="ab-subnav__item <?= $paginaAtual === 'financeiro-baixa' ? 'is-active' : '' ?>">
                    Cadastrar Baixa
                </a>
                <a href="/Financeiro/paginas/financeiro_aReceber.php"
                   class="ab-subnav__item <?= $paginaAtual === 'financeiro-fiados' ? 'is-active' : '' ?>">
                    Contas a Receber
                </a>
                <a href="/Financeiro/paginas/financeiro_relatorios.php"
                   class="ab-subnav__item <?= $paginaAtual === 'financeiro-relatorios' ? 'is-active' : '' ?>">
                    Relatórios
                </a>
            </div>
        </div>

        <a href="/Perfil/paginas/perfil.php" title="Perfil"
           class="ab-nav-item <?= $paginaAtual === 'perfil' ? 'is-active' : '' ?>">
            <span class="ab-nav-item__icon">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="12" cy="8" r="3.3" stroke="currentColor" stroke-width="1.4"/>
                    <path d="M5 20c0-3.6 3.1-6.5 7-6.5s7 2.9 7 6.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
                </svg>
            </span>
            <span class="ab-nav-item__label ab-label">Perfil</span>
        </a>

        <a href="/configuracoes" title="Configurações"
           class="ab-nav-item <?= $paginaAtual === 'configuracoes' ? 'is-active' : '' ?>">
            <span class="ab-nav-item__icon">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.4"/>
                    <path d="M12 2.5v2.4M12 19.1v2.4M4.2 4.2l1.7 1.7M18.1 18.1l1.7 1.7M2.5 12h2.4M19.1 12h2.4M4.2 19.8l1.7-1.7M18.1 5.9l1.7-1.7" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
                </svg>
            </span>
            <span class="ab-nav-item__label ab-label">Configurações</span>
        </a>

        <!-- "Suporte": ainda não tem tela própria, só avisa que está sendo
             implementado (ver modal #modal-suporte-em-breve no fim deste
             arquivo). Fica igual aos outros itens de menu visualmente, mas
             é um <button>, não um <a>, já que não navega pra lugar nenhum. -->
        <button type="button" onclick="abrirModalSuporteEmBreve()" title="Suporte" class="ab-nav-item" style="width:100%; text-align:left; background:none; border:none; cursor:pointer;">
            <span class="ab-nav-item__icon">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.4"/>
                    <path d="M9.2 9.3a2.8 2.8 0 1 1 3.9 2.6c-.7.35-1.1.75-1.1 1.6v.4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
                    <circle cx="12" cy="17" r="0.9" fill="currentColor"/>
                </svg>
            </span>
            <span class="ab-nav-item__label ab-label">Suporte</span>
        </button>

    </nav>

    <?php
    // Foto de perfil (se existir e o arquivo ainda estiver no disco), usada
    // no avatar do rodapé. Cai para a inicial do nome quando não há foto.
    $ab_fotoRodape = null;
    if (!empty($_SESSION['foto'])) {
        $ab_caminhoFoto = __DIR__ . '/../assets/uploads/perfil/' . $_SESSION['foto'];
        if (is_file($ab_caminhoFoto)) {
            $ab_fotoRodape = '/assets/uploads/perfil/' . rawurlencode($_SESSION['foto']);
        }
    }
    ?>

    <!-- Rodapé: usuário logado -->
    <div class="ab-sidebar__footer">
        <div class="ab-sidebar__user">
            <?php if ($ab_fotoRodape): ?>
                <img src="<?= htmlspecialchars($ab_fotoRodape) ?>" alt="" class="ab-sidebar__avatar ab-sidebar__avatar--foto">
            <?php else: ?>
                <div class="ab-sidebar__avatar">
                    <?= strtoupper(substr($_SESSION['nome'] ?? 'B', 0, 1)) ?>
                </div>
            <?php endif; ?>
            <div class="ab-sidebar__user-info ab-label">
                <p class="ab-sidebar__user-name"><?= htmlspecialchars($_SESSION['nome'] ?? 'Barbeiro') ?></p>
                <p class="ab-sidebar__user-role">Barbeiro</p>
            </div>
        </div>
        <a href="/Autenticacao/scripts/logout.php" title="Sair" class="ab-nav-item">
            <span class="ab-nav-item__icon">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M9 20H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M15 16l4-4-4-4M19 12H9" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </span>
            <span class="ab-nav-item__label ab-label">Sair</span>
        </a>
    </div>

</aside>

<div id="sidebar-backdrop" class="ab-sidebar-backdrop ab-hidden" onclick="fecharMenuMobile()"></div>

<?php
// ---------- Rodapé fixo (barbeiro logado + relógio em tempo real) ----------
// Fica FORA da <aside> de propósito, com position:fixed próprio — assim
// aparece em toda página que inclui este arquivo, sem depender da largura/
// estado (aberta, recolhida, mobile) da sidebar. A hora inicial vem do
// PHP (já usando o fuso America/Sao_Paulo, corrigido em
// includes/session.php via date_default_timezone_set) e o JavaScript só
// vai contando os segundos a partir daí — nunca lê o relógio do sistema
// operacional do navegador, então mostra a hora certa mesmo que o PC do
// barbeiro esteja com o fuso errado.
$ab_agoraRodape = new DateTime();
?>
<style>
    .ab-rodape-fixo{
        position: fixed;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: 60;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        flex-wrap: wrap;
        padding: 0.45rem 1rem;
        font-size: 11.5px;
        font-weight: 500;
        letter-spacing: 0.02em;
        color: #aebdd6;
        background: rgba(10,14,24,0.85);
        backdrop-filter: blur(6px);
        border-top: 1px solid rgba(255,255,255,0.08);
        pointer-events: none;
    }
    .ab-rodape-fixo strong{ color: var(--cream, #f4ede0); font-weight: 600; }
    .ab-rodape-fixo__separador{ opacity: 0.4; }
    html[data-theme="light"] .ab-rodape-fixo{
        color:#475569;
        background: rgba(255,255,255,0.85);
        border-top: 1px solid rgba(0,0,0,0.08);
    }
    /* Espaço reservado no fim da página pra nada ficar escondido atrás do
       rodapé fixo (ele não ocupa espaço no fluxo normal, por ser fixed). */
    body{ padding-bottom: 34px; }
</style>
<footer class="ab-rodape-fixo">
    <span>Barbeiro conectado: <strong><?= htmlspecialchars($_SESSION['nome'] ?? 'Barbeiro') ?></strong></span>
    <span class="ab-rodape-fixo__separador">|</span>
    <span>Data e Hora: <strong id="ab-rodape-relogio">--/--/---- - --:--:--</strong></span>
</footer>
<script>
(function () {
    // A sidebar (#app-sidebar) preenche 100% da altura da tela por conta
    // própria, com o rodapé DELA (usuário logado + Sair) grudado no fim
    // via flexbox — sem ajuste, ele ficaria bem atrás/embaixo do rodapé
    // fixo novo (ver .ab-rodape-fixo acima), como aconteceu antes desta
    // correção. Mede a altura REAL do rodapé fixo (robusto a qualquer
    // mudança futura de fonte/padding) e desconta ela da altura da
    // sidebar, então o "Sair" sobe pra ficar sempre visível, acima do
    // rodapé fixo.
    var sidebar = document.getElementById('app-sidebar');
    var rodape  = document.querySelector('.ab-rodape-fixo');
    if (sidebar && rodape) {
        var ajustar = function () {
            var altura = rodape.offsetHeight;
            sidebar.style.height = 'calc(100vh - ' + altura + 'px)';
            sidebar.style.boxSizing = 'border-box';
        };
        ajustar();
        window.addEventListener('resize', ajustar);
    }
})();
</script>
<script>
(function () {
    var el = document.getElementById('ab-rodape-relogio');
    if (!el) return;

    // Semente vinda do servidor (ver comentário PHP acima) — construída com
    // o construtor de Date "local" (ano, mês, dia, hora, min, seg), então
    // representa exatamente esses números, sem nenhuma conversão de fuso.
    var agora = new Date(
        <?= (int) $ab_agoraRodape->format('Y') ?>,
        <?= (int) $ab_agoraRodape->format('n') - 1 ?>,
        <?= (int) $ab_agoraRodape->format('j') ?>,
        <?= (int) $ab_agoraRodape->format('G') ?>,
        <?= (int) $ab_agoraRodape->format('i') ?>,
        <?= (int) $ab_agoraRodape->format('s') ?>
    );

    function dois(n) { return String(n).padStart(2, '0'); }

    function render() {
        var d = dois(agora.getDate()), m = dois(agora.getMonth() + 1), y = agora.getFullYear();
        var hh = dois(agora.getHours()), mm = dois(agora.getMinutes()), ss = dois(agora.getSeconds());
        el.textContent = d + '/' + m + '/' + y + ' - ' + hh + ':' + mm + ':' + ss;
    }

    render();
    setInterval(function () {
        agora.setSeconds(agora.getSeconds() + 1);
        render();
    }, 1000);
})();
</script>

<!-- Modal "Suporte" (ver botão no <nav> acima) — sem lógica de backend
     nenhuma, só avisa que a tela ainda está sendo implementada. Fica aqui
     (fora da <aside>) pra funcionar em qualquer página que inclua este
     arquivo, igual o rodapé fixo. -->
<div id="modal-suporte-em-breve" class="modal-overlay fixed inset-0 z-50 hidden flex items-center justify-center p-4" onclick="if(event.target.id==='modal-suporte-em-breve') fecharModalSuporteEmBreve()">
    <div class="modal-card rounded-3xl shadow-2xl overflow-hidden w-full max-w-sm">
        <div class="barber-stripe-thin"></div>
        <div class="p-7 text-center">
            <div style="width:48px; height:48px; border-radius:999px; background:rgba(47,111,237,0.14); display:flex; align-items:center; justify-content:center; margin:0 auto 1rem;">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="12" cy="12" r="8.5" stroke="#6fa8ea" stroke-width="1.5"/>
                    <path d="M9.2 9.3a2.8 2.8 0 1 1 3.9 2.6c-.7.35-1.1.75-1.1 1.6v.4" stroke="#6fa8ea" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <circle cx="12" cy="17" r="0.9" fill="#6fa8ea"/>
                </svg>
            </div>
            <h2 class="display text-xl text-[color:var(--cream)] mb-2">Suporte</h2>
            <p class="text-sm text-zinc-400 mb-6">Ainda sendo implementado.</p>
            <button type="button" onclick="fecharModalSuporteEmBreve()" class="btn-primary h-11 px-6 rounded-xl text-sm w-full">
                Entendi
            </button>
        </div>
        <div class="barber-stripe-thin"></div>
    </div>
</div>
<script>
    window.abrirModalSuporteEmBreve = function () {
        var modal = document.getElementById('modal-suporte-em-breve');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    };
    window.fecharModalSuporteEmBreve = function () {
        var modal = document.getElementById('modal-suporte-em-breve');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    };
</script>

<?php include __DIR__ . '/sidebar-script.php'; ?>
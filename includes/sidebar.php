<?php
// includes/sidebar.php
// Inclua este arquivo dentro de <body>, passando a variável $paginaAtual
// (ex: 'dashboard', 'barbeiro-cadastrar') para destacar o item ativo.
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

        <a href="/Painel/paginas/painel_admin.php" title="Início"
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

        <!-- Grupo: Barbeiro -->
        <div>
            <button type="button"
                    class="ab-nav-item ab-nav-item--toggle"
                    title="Barbeiro"
                    onclick="alternarGrupoSidebar('grupo-barbeiro', this)">
                <span class="ab-nav-item__main">
                    <span class="ab-nav-item__icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M6.5 5.5a2.5 2.5 0 1 1 3.4 3.4L18 17.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
                            <path d="M6.5 18.5a2.5 2.5 0 1 0 3.4-3.4L18 6.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
                            <circle cx="6.2" cy="6.2" r="1.5" stroke="currentColor" stroke-width="1.2"/>
                            <circle cx="6.2" cy="17.8" r="1.5" stroke="currentColor" stroke-width="1.2"/>
                        </svg>
                    </span>
                    <span class="ab-nav-item__label ab-label">Barbeiro</span>
                </span>
                <span class="ab-nav-item__chevron ab-label <?= in_array($paginaAtual, ['barbeiro-cadastrar', 'barbeiro-listar'], true) ? 'is-open' : '' ?>">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </span>
            </button>

            <div id="grupo-barbeiro" class="ab-subnav <?= in_array($paginaAtual, ['barbeiro-cadastrar', 'barbeiro-listar'], true) ? '' : 'ab-hidden' ?>">
                <a href="/Barbeiros/paginas/barbeiro_listar.php"
                   class="ab-subnav__item <?= $paginaAtual === 'barbeiro-listar' ? 'is-active' : '' ?>">
                    Listar Barbeiros
                </a>
                <a href="/Barbeiros/paginas/barbeiro_cadastrar.php"
                   class="ab-subnav__item <?= $paginaAtual === 'barbeiro-cadastrar' ? 'is-active' : '' ?>">
                    Cadastrar Barbeiro
                </a>
            </div>
        </div>

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

        <a href="/Configuracoes/paginas/login_tentativas.php" title="Logins Bloqueados"
           class="ab-nav-item <?= $paginaAtual === 'login-tentativas' ? 'is-active' : '' ?>">
            <span class="ab-nav-item__icon">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="5" y="10.5" width="14" height="9" rx="1.6" stroke="currentColor" stroke-width="1.4"/>
                    <path d="M8 10.5V7.5a4 4 0 0 1 8 0v3" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
                </svg>
            </span>
            <span class="ab-nav-item__label ab-label">Logins Bloqueados</span>
        </a>

    </nav>

    <!-- Rodapé: usuário logado -->
    <div class="ab-sidebar__footer">
        <div class="ab-sidebar__user">
            <div class="ab-sidebar__avatar">
                <?= strtoupper(substr($_SESSION['nome'] ?? 'A', 0, 1)) ?>
            </div>
            <div class="ab-sidebar__user-info ab-label">
                <p class="ab-sidebar__user-name"><?= htmlspecialchars($_SESSION['nome'] ?? 'Administrador') ?></p>
                <p class="ab-sidebar__user-role">Administrador</p>
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

<?php include __DIR__ . '/sidebar-script.php'; ?>
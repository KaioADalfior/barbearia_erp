<?php
/**
 * includes/sidebar-script.php
 * Controla três comportamentos da sidebar, compartilhados entre
 * includes/sidebar.php (admin) e includes/sidebar_barbeiro.php:
 *  - Recolher/expandir no desktop (ícone rail, estado salvo em localStorage)
 *  - Abrir/fechar como menu off-canvas no mobile (com backdrop)
 *  - Expandir automaticamente um grupo com submenu, mesmo se a sidebar
 *    estiver recolhida (evita perder acesso às páginas do submenu)
 * Inclua uma vez, no final do arquivo de sidebar.
 */
?>
<script>
(function () {
    function aplicarColapso(colapsado) {
        document.documentElement.setAttribute('data-sidebar', colapsado ? 'collapsed' : 'expanded');
    }

    window.alternarColapsoSidebar = function () {
        var colapsadoAtual = document.documentElement.getAttribute('data-sidebar') === 'collapsed';
        var novo = !colapsadoAtual;
        aplicarColapso(novo);
        try { localStorage.setItem('ab_sidebar_collapsed', novo ? '1' : '0'); } catch (e) { /* localStorage indisponível */ }
    };

    window.alternarGrupoSidebar = function (id, botao) {
        if (document.documentElement.getAttribute('data-sidebar') === 'collapsed') {
            aplicarColapso(false);
            try { localStorage.setItem('ab_sidebar_collapsed', '0'); } catch (e) { /* localStorage indisponível */ }
        }
        var grupo = document.getElementById(id);
        if (grupo) grupo.classList.toggle('ab-hidden');
        var chevron = botao.querySelector('.ab-nav-item__chevron');
        if (chevron) chevron.classList.toggle('is-open');
    };

    var scrollYAoAbrir = 0;

    window.abrirMenuMobile = function () {
        var sidebar = document.getElementById('app-sidebar');
        var backdrop = document.getElementById('sidebar-backdrop');
        if (sidebar) sidebar.classList.add('is-open');
        if (backdrop) backdrop.classList.remove('ab-hidden');

        // overflow:hidden sozinho não trava o scroll no Safari iOS (bug conhecido);
        // a técnica confiável é "congelar" o body na posição atual com position:fixed.
        scrollYAoAbrir = window.scrollY || window.pageYOffset || 0;
        document.body.style.position = 'fixed';
        document.body.style.top = (-scrollYAoAbrir) + 'px';
        document.body.style.left = '0';
        document.body.style.right = '0';
        document.body.classList.add('ab-body-lock');
    };

    window.fecharMenuMobile = function () {
        var sidebar = document.getElementById('app-sidebar');
        var backdrop = document.getElementById('sidebar-backdrop');
        if (sidebar) sidebar.classList.remove('is-open');
        if (backdrop) backdrop.classList.add('ab-hidden');

        document.body.classList.remove('ab-body-lock');
        document.body.style.position = '';
        document.body.style.top = '';
        document.body.style.left = '';
        document.body.style.right = '';
        window.scrollTo(0, scrollYAoAbrir);
    };

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') fecharMenuMobile();
    });

    var mql = window.matchMedia('(min-width: 1024px)');
    function aoCruzarBreakpoint(e) {
        if (e.matches) fecharMenuMobile();
    }
    if (mql.addEventListener) {
        mql.addEventListener('change', aoCruzarBreakpoint);
    } else if (mql.addListener) {
        mql.addListener(aoCruzarBreakpoint);
    }
})();
</script>

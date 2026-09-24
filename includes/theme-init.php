<?php
/**
 * includes/theme-init.php
 * Aplica, antes da página renderizar, o tema (claro/escuro) e o estado da
 * sidebar (recolhida/expandida) salvos no localStorage — evita o "flash"
 * da cor ou do menu errados. Inclua logo após o <meta charset> no <head>
 * de toda página interna (pós-login).
 */
?>
<script>
(function () {
    try {
        var tema = localStorage.getItem('ab_theme');
        if (tema === 'light') {
            document.documentElement.setAttribute('data-theme', 'light');
        }
    } catch (e) { /* localStorage indisponível */ }

    try {
        var colapsado = localStorage.getItem('ab_sidebar_collapsed');
        if (colapsado === '1' && window.innerWidth >= 1024) {
            document.documentElement.setAttribute('data-sidebar', 'collapsed');
        }
    } catch (e) { /* localStorage indisponível */ }
})();
</script>
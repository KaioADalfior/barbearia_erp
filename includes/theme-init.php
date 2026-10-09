<?php
/**
 * includes/theme-init.php
 * Aplica, antes da página renderizar, o tema (claro/escuro) e o estado da
 * sidebar (recolhida/expandida) salvos no localStorage — evita o "flash"
 * da cor ou do menu errados. Inclua logo após o <meta charset> no <head>
 * de toda página interna (pós-login).
 *
 * Também emite (só quando a barbearia personalizou a cor de destaque) o
 * <style id="ab-tema"> com as variáveis da cor — ver includes/TemaService.php.
 * Sem cor salva (ou valor inválido) nada é emitido: vale o padrão do CSS.
 */
// Usa a conexão da própria página; se ela ainda não abriu uma (ex.: login),
// abre a do sistema. Qualquer falha aqui cai no tema padrão, sem quebrar a página.
if (!isset($pdo)) {
    try {
        require_once __DIR__ . '/../config/config.php';
    } catch (Throwable $e) {
        $pdo = null;
    }
}
require_once __DIR__ . '/TemaService.php';
echo TemaService::css(isset($pdo) && $pdo instanceof PDO ? $pdo : null);
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
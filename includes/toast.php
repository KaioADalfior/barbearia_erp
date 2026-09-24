<?php
/**
 * includes/toast.php
 * Componente de notificação "toast" (popup no canto inferior direito) com som.
 * Inclua este arquivo uma vez em qualquer página (antes de </body> ou logo após o <body>)
 * e chame toast('mensagem', 'sucesso' | 'erro') via JavaScript para disparar o popup.
 *
 * Requer que a página já defina as variáveis CSS --gold, --cream, --charcoal, --charcoal-2
 * (login.php as define inline; as demais páginas herdam de assets/css/admin-theme.css).
 */
?>
<div id="toast-container" style="position:fixed; bottom:20px; right:20px; z-index:9999; display:flex; flex-direction:column; gap:10px; pointer-events:none;"></div>

<audio id="toast-sound" src="../../assets/sound/SomPop.mp3" preload="auto"></audio>

<script>
function toast(mensagem, tipo = 'sucesso') {
    const estilos = {
        sucesso: { borda: 'var(--success, #22c55e)',  icone: '✓' },
        erro:    { borda: 'var(--danger, #ef4444)',   icone: '!' }
    };
    const c = estilos[tipo] || estilos.sucesso;

    const el = document.createElement('div');
    el.style.cssText = `
        pointer-events:auto;
        min-width:260px;
        max-width:340px;
        background:linear-gradient(180deg, var(--charcoal), var(--charcoal-2));
        border:1px solid rgba(47,111,237,0.14);
        border-left:4px solid ${c.borda};
        color:var(--cream);
        font-family:'Poppins', sans-serif;
        font-size:13.5px;
        padding:14px 16px;
        border-radius:12px;
        box-shadow:0 12px 30px -8px rgba(0,0,0,0.6);
        display:flex;
        align-items:center;
        gap:10px;
        opacity:0;
        transform:translateX(24px);
        transition:opacity .25s ease, transform .25s ease;
    `;

    const iconeEl = document.createElement('span');
    iconeEl.style.cssText = `font-weight:700; color:${c.borda};`;
    iconeEl.textContent = c.icone;

    const textoEl = document.createElement('span');
    textoEl.textContent = mensagem;

    el.appendChild(iconeEl);
    el.appendChild(textoEl);
    document.getElementById('toast-container').appendChild(el);

    requestAnimationFrame(() => {
        el.style.opacity = '1';
        el.style.transform = 'translateX(0)';
    });

    let somAtivo = true;
    try { somAtivo = localStorage.getItem('ab_som') !== '0'; } catch (e) { /* localStorage indisponível */ }

    const som = document.getElementById('toast-sound');
    if (som && somAtivo) {
        som.currentTime = 0;
        som.play().catch(() => { /* navegador pode bloquear autoplay sem interação prévia */ });
    }

    setTimeout(() => {
        el.style.opacity = '0';
        el.style.transform = 'translateX(24px)';
        setTimeout(() => el.remove(), 300);
    }, 3500);
}
</script>
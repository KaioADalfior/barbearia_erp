/* assets/js/tema-cor.js
 * Mesma derivação de cor de includes/TemaService.php (PHP), usada só na
 * pré-visualização ao vivo de Configurações > Cor da barbearia. A fonte da
 * verdade continua sendo o PHP; um teste automatizado compara os dois. */
(function (global) {
    'use strict';

    var SUPERFICIE = { escuro: '#16120f', claro: '#fbf8f2' };
    var TEXTO_ESCURO = '#1b1408', TEXTO_CLARO = '#ffffff';

    function validar(v) {
        if (typeof v !== 'string') return null;
        v = v.trim().toLowerCase();
        return /^#[0-9a-f]{6}$/.test(v) ? v : null;
    }
    function rgb(hex) {
        var h = hex.replace('#', '');
        return [parseInt(h.substr(0, 2), 16), parseInt(h.substr(2, 2), 16), parseInt(h.substr(4, 2), 16)];
    }
    function toHex(c) {
        return '#' + c.map(function (n) {
            n = Math.max(0, Math.min(255, phpRound(n)));
            return (n < 16 ? '0' : '') + n.toString(16);
        }).join('');
    }
    // PHP round(): meio para longe do zero (JS Math.round arredonda .5 para cima).
    function phpRound(n) { return n < 0 ? -Math.round(-n) : Math.round(n); }
    function luminancia(hex) {
        var c = rgb(hex).map(function (v) {
            var s = v / 255;
            return s <= 0.03928 ? s / 12.92 : Math.pow((s + 0.055) / 1.055, 2.4);
        });
        return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
    }
    function contraste(a, b) {
        var la = luminancia(a), lb = luminancia(b);
        return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05);
    }
    function misturar(a, b, t) {
        var x = rgb(a), y = rgb(b);
        return toHex([x[0] + (y[0] - x[0]) * t, x[1] + (y[1] - x[1]) * t, x[2] + (y[2] - x[2]) * t]);
    }
    function ajustar(cor, superficie, min, clarear) {
        var alvo = clarear ? '#ffffff' : '#000000', atual = cor;
        for (var i = 1; i <= 40 && contraste(atual, superficie) < min; i++) {
            atual = misturar(cor, alvo, i * 0.025);
        }
        return atual;
    }
    // Espelho de TemaService::conciliar (texto sobre o preenchimento >= 4,5:1).
    function conciliar(fill, superficie) {
        var melhor = null, opcoes = [[TEXTO_ESCURO, '#ffffff'], [TEXTO_CLARO, '#000000']];
        for (var k = 0; k < opcoes.length; k++) {
            for (var i = 0; i <= 40; i++) {
                var f = i === 0 ? fill : misturar(fill, opcoes[k][1], i * 0.02);
                if (contraste(f, opcoes[k][0]) >= 4.5 && contraste(f, superficie) >= 2.2) {
                    if (melhor === null || i < melhor[0]) { melhor = [i, f, opcoes[k][0]]; }
                    break;
                }
            }
        }
        if (melhor) { return [melhor[1], melhor[2]]; }
        return [fill, contraste(fill, TEXTO_ESCURO) >= contraste(fill, TEXTO_CLARO) ? TEXTO_ESCURO : TEXTO_CLARO];
    }
    function tokens(cor, escuro) {
        cor = validar(cor) || '#c9a14a';
        var sup = escuro ? SUPERFICIE.escuro : SUPERFICIE.claro;
        var fill = ajustar(cor, sup, 2.2, escuro);
        var par = conciliar(fill, sup);
        fill = par[0];
        var on = par[1];
        var strong = ajustar(fill, sup, 4.5, escuro);
        var c = rgb(fill);
        return {
            'accent': fill,
            'accent-rgb': c[0] + ',' + c[1] + ',' + c[2],
            'accent-hi': misturar(fill, '#ffffff', 0.2),
            'accent-lo': misturar(fill, '#000000', 0.22),
            'accent-on': on,
            'accent-strong': strong
        };
    }
    /** Aplica os tokens (do tema ativo) direto no <html> — só para a prévia. */
    function aplicarPrevia(cor) {
        var el = document.documentElement;
        var escuro = el.getAttribute('data-theme') !== 'light';
        var t = tokens(cor, escuro);
        Object.keys(t).forEach(function (k) { el.style.setProperty('--' + k, t[k]); });
    }
    function limparPrevia() {
        var el = document.documentElement;
        ['accent', 'accent-rgb', 'accent-hi', 'accent-lo', 'accent-on', 'accent-strong'].forEach(function (k) { el.style.removeProperty('--' + k); });
    }

    global.TemaCor = { validar: validar, tokens: tokens, contraste: contraste, aplicarPrevia: aplicarPrevia, limparPrevia: limparPrevia };
})(window);

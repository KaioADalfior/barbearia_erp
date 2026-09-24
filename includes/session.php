<?php
/**
 * includes/session.php
 *
 * Bootstrap centralizado de sessao + headers de seguranca + exibicao
 * segura de erros. TODAS as paginas/scripts do sistema incluem este
 * arquivo antes de qualquer outra coisa - por isso os headers/hardening
 * ficam aqui, e nao em config/config.php: varias paginas (dashboards que
 * so buscam dados via fetch/JS, ex. Painel/paginas/painel_admin.php,
 * Financeiro/paginas/*) nunca chegam a incluir config.php, entao essas
 * protecoes ficariam de fora se estivessem so la.
 */

// Fuso horario oficial do sistema: America/Sao_Paulo (Brasilia, UTC-3).
// O servidor (container Railway/Nixpacks) roda em UTC por padrao, o que
// fazia date(), DateTimeImmutable() sem timezone explicito e afins
// gerarem horarios 3h adiantados (relatorios, recebimentos, etc). Isso
// tem que ser setado aqui porque session.php e o primeiro arquivo
// incluido em toda pagina/script do sistema (ver comentario acima).
date_default_timezone_set('America/Sao_Paulo');

// Em producao, erro nunca deve aparecer pro navegador (stack trace,
// caminho do servidor etc.) - so deve ser logado. Aplicado aqui pra valer
// em toda pagina, mesmo as que nao incluem config.php.
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

// Garante UTF-8 como charset padrao do PHP (afeta o header Content-Type
// automatico e funcoes como htmlspecialchars()), reforcando o
// header('Content-Type: ...; charset=UTF-8') explicito logo abaixo.
ini_set('default_charset', 'UTF-8');

// Headers de seguranca basicos, em toda resposta do sistema.
if (!headers_sent()) {
    // Charset explicito no header HTTP (nao so na tag <meta charset> do
    // HTML): sem isso, dependendo do default_charset do php.ini do
    // servidor, o navegador pode ignorar a <meta charset="UTF-8"> da
    // pagina e adivinhar um encoding errado pros acentos/emoji, mesmo com
    // o arquivo .php certinho em UTF-8 no servidor (foi isso que causou o
    // "Hor?rios do dia" / "Indispon?vel" aparecendo quebrado no navegador,
    // mesmo depois do arquivo já estar salvo em UTF-8 correto). Endpoints
    // JSON (agendamento_cancelar.php etc.) chamam seu próprio
    // header('Content-Type: application/json...') logo depois de incluir
    // este arquivo, o que substitui este valor normalmente.
    header('Content-Type: text/html; charset=UTF-8');
    header("Content-Security-Policy: frame-ancestors 'self' https://intranet.daksolucoes.tech");
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    if (!empty($_SERVER['HTTPS']) || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')) {
        header('Strict-Transport-Security: max-age=15552000; includeSubDomains');
    }
}

if (session_status() !== PHP_SESSION_ACTIVE) {

    $https = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['SERVER_PORT'] ?? null) == 443);

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_name('alexbarbearia_sess');

    session_start();
}
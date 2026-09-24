<?php
/**
 * includes/csrf.php
 *
 * Proteção CSRF central do sistema. Um único token por sessão, gerado com
 * random_bytes (imprevisível), guardado em $_SESSION e validado com
 * hash_equals (comparação em tempo constante, evita timing attack).
 *
 * Uso em uma página com formulário:
 *   require_once __DIR__ . '/../../includes/csrf.php';
 *   ... dentro do <form>, imprimir o retorno de csrf_field() (que já
 *   devolve a tag <input type="hidden"> pronta) ...
 *
 * Uso no script que recebe o POST (logo após exigirSessao(), antes de
 * processar qualquer outro dado do formulário):
 *   require_once __DIR__ . '/../../includes/csrf.php';
 *   csrf_verificar();                 // formulário tradicional (redirect em caso de falha)
 *   csrf_verificar(json: true);       // endpoint AJAX que responde JSON
 */

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/DiscordLogger.php';

/**
 * Retorna o token CSRF da sessão atual, gerando um novo na primeira vez.
 */
function csrf_token(): string
{
    if (empty($_SESSION['_csrf_token']) || !is_string($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf_token'];
}

/**
 * Campo <input hidden> pronto para colocar dentro de qualquer <form>.
 * Já vem com htmlspecialchars aplicado ao valor (defesa em profundidade,
 * mesmo o token sendo hexadecimal e não precisar de escaping).
 */
function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Valida o token CSRF enviado no POST atual contra o da sessão.
 *
 * - Se válido: retorna normalmente, o script continua.
 * - Se inválido/ausente: registra a tentativa (canal logs-alertas) e
 *   interrompe a execução — 403 + JSON para endpoints AJAX, redirect com
 *   ?csrf_erro=1 para formulários tradicionais.
 *
 * Chamar DEPOIS de exigirSessao() (precisamos do usuário autenticado para
 * o log) e ANTES de ler qualquer outro campo de $_POST.
 */
function csrf_verificar(bool $json = false, ?string $redirecionarPara = null): void
{
    $tokenEnviado = $_POST['_csrf'] ?? '';
    $tokenSessao  = $_SESSION['_csrf_token'] ?? '';

    if ($tokenSessao !== '' && is_string($tokenEnviado) && hash_equals($tokenSessao, $tokenEnviado)) {
        return; // válido
    }

    DiscordLogger::alerta('🚫 Token CSRF inválido/ausente', [
        ['name' => '📄 Recurso', 'value' => $_SERVER['REQUEST_URI'] ?? '—', 'inline' => false],
        ['name' => '👤 Sessão',  'value' => $_SESSION['login'] ?? 'não autenticado', 'inline' => true],
        ['name' => '🌐 IP',      'value' => $_SERVER['REMOTE_ADDR'] ?? '—', 'inline' => true],
    ]);

    if ($json) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'erro' => 'Sessão expirada ou inválida. Recarregue a página e tente novamente.']);
        exit;
    }

    $destino = $redirecionarPara ?? ($_SERVER['HTTP_REFERER'] ?? '/');
    $separador = strpos($destino, '?') === false ? '?' : '&';
    header('Location: ' . $destino . $separador . 'csrf_erro=1');
    exit;
}

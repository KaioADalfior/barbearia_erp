<?php
// includes/guard.php
// Helper central de controle de acesso: garante que a sessão atual tem um
// dos tipos permitidos para a função chamada. Se não tiver, bloqueia
// (redirect para páginas, JSON 401 para endpoints AJAX) e registra a
// tentativa no canal logs-alertas.
//
// Uso:
//   exigirSessao(['barbeiro']);                 // páginas/scripts normais
//   exigirSessao(['barbeiro'], json: true);      // endpoints AJAX (retornam JSON)
//   exigirSessao(['admin', 'barbeiro']);         // mais de um tipo permitido

require_once __DIR__ . '/DiscordLogger.php';
require_once __DIR__ . '/../config/security.php';

function exigirSessao(array $tiposPermitidos, bool $json = false): void
{
    $tipo = $_SESSION['tipo'] ?? null;

    // Timeout por inatividade: mesmo com o navegador aberto, uma sessão
    // autenticada sem nenhuma requisição há mais de SESSION_INACTIVITY_MINUTES
    // é encerrada e tratada como não autenticada.
    if ($tipo !== null) {
        $ultimaAtividade = $_SESSION['ultima_atividade'] ?? null;
        if ($ultimaAtividade !== null && (time() - $ultimaAtividade) > (SESSION_INACTIVITY_MINUTES * 60)) {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $parametros = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $parametros['path'], $parametros['domain'], $parametros['secure'], $parametros['httponly']);
            }
            session_destroy();
            $tipo = null;
        }
    }

    if ($tipo !== null && in_array($tipo, $tiposPermitidos, true)) {
        $_SESSION['ultima_atividade'] = time(); // renova a cada requisição autenticada válida
        return; // acesso permitido, segue o fluxo normal
    }

    DiscordLogger::alerta('🚫 Acesso negado', [
        ['name' => '📄 Recurso',       'value' => $_SERVER['REQUEST_URI'] ?? '—', 'inline' => false],
        ['name' => '👤 Sessão atual',  'value' => $tipo ?? 'não autenticado', 'inline' => true],
        ['name' => '✅ Permitido para', 'value' => implode(', ', $tiposPermitidos), 'inline' => true],
        ['name' => '🌐 IP',            'value' => $_SERVER['REMOTE_ADDR'] ?? '—', 'inline' => true],
    ]);

    if ($json) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'erro' => 'Não autenticado.']);
        exit;
    }

    header('Location: ../../Autenticacao/paginas/login.php');
    exit;
}

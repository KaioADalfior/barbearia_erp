<?php
// Publico/scripts/publico_csrf.php
// Endpoint AJAX (GET). Devolve o token CSRF da sessão atual (criando a
// sessão se preciso). A tela pública de agendamento chama isto UMA vez de
// forma automática quando a confirmação volta "sessão expirada" (página
// aberta por muito tempo, cookie de sessão descartado pelo navegador do
// Instagram/WhatsApp, HTML antigo em cache etc.) e então repete o envio —
// o cliente não precisa recarregar a página nem refazer a escolha.
// Não expõe nada além do token da própria sessão de quem pede.

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/csrf.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

echo json_encode(['ok' => true, 'csrf' => csrf_token()]);

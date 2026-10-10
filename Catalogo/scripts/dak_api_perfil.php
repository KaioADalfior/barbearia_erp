<?php
// Catalogo/scripts/dak_api_perfil.php
// API de integração com o catálogo central DAK Barber:  GET /api/catalogo/v1/perfil
//
// Devolve o PERFIL PÚBLICO desta barbearia (mesmo conteúdo já público em /c/agendar),
// assinado com a chave da instalação (cabeçalho X-DAK-Assinatura, Ed25519 sobre o corpo
// exato). Só responde se o proprietário autorizou a participação; caso contrário 404.
// Somente leitura, sem sessão, com limite de requisições por IP e ETag.
// Nada de clientes, financeiro, agenda ou dados administrativos passa por aqui.

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/IpCliente.php';
require_once __DIR__ . '/../../includes/DakIntegracao.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function dakResponder(int $status, array $corpo): never
{
    http_response_code($status);
    echo json_encode($corpo, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    dakResponder(405, ['ok' => false, 'erro' => 'Método não permitido.']);
}

if (!DakIntegracao::dentroDoLimite($pdo, IpCliente::obter())) {
    header('Retry-After: 60');
    dakResponder(429, ['ok' => false, 'erro' => 'Muitas requisições.']);
}

try {
    $perfil = DakIntegracao::perfil($pdo);
} catch (Throwable $e) {
    error_log('dak_api_perfil: ' . $e->getMessage());
    dakResponder(500, ['ok' => false, 'erro' => 'Falha interna.']);
}
if ($perfil === null) {
    dakResponder(404, ['ok' => false, 'codigo' => 'nao_participa', 'erro' => 'Não encontrado.']);
}

$etag = '"' . substr($perfil['versao'], 0, 32) . '"';
if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
    http_response_code(304);
    header('ETag: ' . $etag);
    exit;
}

$corpo = json_encode($perfil, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$sig   = DakIntegracao::assinar($pdo, $corpo);
if ($sig === null) {
    dakResponder(500, ['ok' => false, 'erro' => 'Falha interna.']);
}
header('ETag: ' . $etag);
header('X-DAK-Instalacao: ' . $perfil['instalacao_id']);
header('X-DAK-Assinatura: ' . $sig);
echo $corpo;

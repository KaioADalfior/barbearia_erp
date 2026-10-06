<?php
// Uploads/scripts/imagem_servir.php
// Entrega, direto do banco (tabela ArquivoUpload), uma imagem enviada
// (foto de perfil, de serviço, logo/capa do catálogo) que NÃO está na pasta
// assets/uploads/ — o que acontece depois de cada deploy, já que o container
// novo nasce com a pasta vazia. O nginx/Apache chama este script sozinho
// quando o arquivo pedido não existe em disco (ver .nixpacks/assets/
// nginx.template.conf e .htaccess); quem acessa /assets/uploads/... não nota
// diferença. Só serve caminhos de imagem válidos (ver ImagemPersistente).
// Sem login de propósito: as imagens são as mesmas que a vitrine pública mostra.

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/ImagemPersistente.php';

$rel = (string) ($_GET['c'] ?? '');

if (!ImagemPersistente::enviarDoBanco($rel)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Imagem não encontrada.';
}

<?php
// Publico/paginas/escolher_barbeiro.php
// Link GERAL da barbearia (sem login), diferente do link pessoal de cada
// barbeiro: acessado em /c/agendar (sem token nenhum — ver .htaccess e
// .nixpacks/assets/nginx.template.conf). Mostra a vitrine da barbearia
// (configurada em Catálogo > Configurar) e o fluxo Serviço -> Profissional
// -> Data e horário -> Confirmação (includes/publico_catalogo_view.php).
//
// Os links pessoais de cada barbeiro (/c/agendar/<token>) continuam
// funcionando exatamente como antes, sem depender desta página. Com apenas
// 1 profissional visível, a etapa "escolha o profissional" é pulada pela
// própria tela (o endereço /c/agendar permanece o mesmo, sem redirecionar).

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../config/config.php';

$tokenFixo = null;
$barbeiroFixo = null;
require __DIR__ . '/../../includes/publico_catalogo_view.php';

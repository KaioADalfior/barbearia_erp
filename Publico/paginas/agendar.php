<?php
// Publico/paginas/agendar.php
// Agendamento público por link PESSOAL (sem login). Recebe o token do
// barbeiro pela query string "t" (a URL bonita /c/agendar/<token> é
// reescrita para cá pelo servidor — ver .htaccess e
// .nixpacks/assets/nginx.template.conf) e resolve o token; se for inválido
// mostra "link inválido". A tela em si (vitrine + fluxo Serviço ->
// Profissional -> Data/Horário -> Confirmação) fica em
// includes/publico_catalogo_view.php, compartilhada com o link geral. Todo o
// agendamento de verdade acontece via AJAX em Publico/scripts/*,
// reaproveitando as regras já validadas (HorarioService, FinanceiroService).

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/PublicoTokenService.php';

$token = trim($_GET['t'] ?? '');
$barbeiro = PublicoTokenService::resolverBarbeiro($pdo, $token);

if ($barbeiro === null) {
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="pt-br">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Link inválido</title>
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
        <style>
            body{ margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center; background:#0b0f17; color:#e7ebf3; font-family:'Poppins',sans-serif; text-align:center; padding:24px; }
            .box{ max-width:420px; }
            h1{ font-size:22px; margin-bottom:8px; }
            p{ color:#8b97ac; font-size:14px; line-height:1.5; }
        </style>
    </head>
    <body>
        <div class="box">
            <h1>Link de agendamento inválido</h1>
            <p>Esse link não existe mais ou foi desativado pelo profissional. Peça um link atualizado a ele.</p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Link pessoal: a vitrine abre já com este profissional escolhido.
$tokenFixo = $token;
$barbeiroFixo = $barbeiro;
require __DIR__ . '/../../includes/publico_catalogo_view.php';

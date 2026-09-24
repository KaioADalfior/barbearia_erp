<?php
// config/webhooks_discord.php
// URLs dos webhooks do Discord — uma por canal de log.
//
// IMPORTANTE — estas URLs não chamam discord.com diretamente. Elas
// chamam o Worker do Cloudflare (autumn-snowflake-3654), que é um proxy
// transparente: repassa a chamada, com o mesmo caminho (/api/webhooks/ID/TOKEN),
// para o discord.com de verdade. Veja cloudflare-worker/worker.js.
//
// Como pegar a URL de cada canal no Discord (se precisar recriar algum):
//   Configurações do canal > Integrações > Webhooks > Criar Webhook > Copiar URL
//   Depois troque só o começo "https://discord.com" por
//   "https://autumn-snowflake-3654.kaio-andriao-dalfior.workers.dev",
//   mantendo o resto do caminho (/api/webhooks/ID/TOKEN) igual.
//
// ATENÇÃO — SEGURANÇA:
// O ID/token de cada webhook continua indo na própria URL (o proxy é
// transparente, não esconde o token) — só o domínio muda de discord.com
// para o seu Worker. Este arquivo continua sendo um segredo: qualquer
// pessoa com essas URLs pode postar no seu Discord. NÃO deixe a pasta
// /config acessível publicamente pelo navegador e não suba este arquivo
// preenchido para um repositório público.

define('DISCORD_PROXY_URL', 'https://autumn-snowflake-3654.kaio-andriao-dalfior.workers.dev');

// Sistema
define('WEBHOOK_LOGS_SISTEMA', DISCORD_PROXY_URL . '/api/webhooks/1534435083635789824/_3WNVIPNTgnLI_B4MBUiJGaEo0Jcfaa0JhJ976tHBRBb9E13-KfBwMJTS8iePpormTLa'); // canal: logs-sistema
define('WEBHOOK_LOGS_ERROS',   DISCORD_PROXY_URL . '/api/webhooks/1534435131857571903/SSvwb9aGDq_P2Gpy39CCWdmyLVA-JuTtZ726TAg-c9HZpTAXprlUun-8QbY9RBqnLDp3'); // canal: logs-erros
define('WEBHOOK_LOGS_ALERTAS', DISCORD_PROXY_URL . '/api/webhooks/1534435183288123484/SkcMJ2gnX2DjEK1YUVHha-2mG-lh7SHg_smdZKPha2vGdyU9i6diJRPAZrEO5bXvMyVR'); // canal: logs-alertas

// Autenticação
define('WEBHOOK_LOGIN',   DISCORD_PROXY_URL . '/api/webhooks/1534435235121332234/ms5Tut10H4klCNs4jSGz5js0Wp8eJM26751jDV7SE-8ig9arT3G9frpP151gh2MzkxkZ'); // canal: login
define('WEBHOOK_SESSOES', DISCORD_PROXY_URL . '/api/webhooks/1534435281313333258/NyN-dLw9AkunkgKuHXr93RHnrICLtHZP_5cdofMN63J3i8Ln59jFLNLirb-qzIWJ7FoK'); // canal: sessoes

// Clientes
define('WEBHOOK_CLIENTES', DISCORD_PROXY_URL . '/api/webhooks/1534435329572864000/NBuwF7rmkUn_IWahzgu5aA0J0w6fgjfcPXM8Yfp6cdvI02MWyk4ZnOihHIxOzq0uuj36'); // canal: clientes

// Financeiro
define('WEBHOOK_FINANCEIRO_LANCAMENTOS', DISCORD_PROXY_URL . '/api/webhooks/1535166351092482118/eAHKXF_rG7oqZrEmfHj8DBP-xbdgkhI5f28jWk5I3rv5kuF5K22Nx0KSeZ62bn0Yrc4y'); // canal: financeiro-lancamentos
define('WEBHOOK_FINANCEIRO_RELATORIOS',  DISCORD_PROXY_URL . '/api/webhooks/1535166289583013929/ffp9vQWAUL_uGg7s0dsj3FlhtaFLrLuE7W0wHOirjY30EG93jPOK8qx4lVuIaHmrj01I'); // canal: financeiro-relatorios

// Serviços
define('WEBHOOK_SERVICOS', DISCORD_PROXY_URL . '/api/webhooks/1534442662399180841/BmUgqnOGcw_Ghjaw7ioexEsMDSL9PSBjhac24ZB3Uql1VJnj7MNnIBz_dr3MwKFVcObW'); // canal: servicos

// Agendamentos
define('WEBHOOK_AGENDAMENTOS', DISCORD_PROXY_URL . '/api/webhooks/1534435387353862154/7cAsZ90WElX3KfaKveb6nT0F-hjjrat1haVK5YTY238rWSI8GcuJPKHS7cXk1hicWort'); // canal: agendamentos

// Uploads
define('WEBHOOK_UPLOADS', DISCORD_PROXY_URL . '/api/webhooks/1534435429665734797/gFlqtsJalQ9ECkwtkIvwwMasD2iIHDDyQITmNfrucLS0dPQtpOZreM0f-JLcHHUnB8ry'); // canal: uploads

// Configurações
define('WEBHOOK_CONFIGURACOES', DISCORD_PROXY_URL . '/api/webhooks/1534435495512113192/FdjX8QXLmDn6Wx7NQt1CAjkSelVfqbwPe1X64Nur_TgOXoiCJ5Tb7t1uJRAtie7OsgHJ'); // canal: configuracoes

// Banco de Dados
define('WEBHOOK_DATABASE', DISCORD_PROXY_URL . '/api/webhooks/1534435536511696906/U7O3zHZphVwPal-ZOCg1qrlTgpYs4nirXfdaQPQDq4GAlvGg33tWenNGOeKGNEJQXk7y'); // canal: database

// Administração
define('WEBHOOK_ADMIN', DISCORD_PROXY_URL . '/api/webhooks/1534435579457048628/04ZHPTCt6iVj3NovXCxzrGUy_b7dBdHCu4CJB7-6DEnZI5ZGXRn4oIop8_PegrSnMEET'); // canal: admin

// Avatar do bot (opcional) — URL pública de uma imagem (png/jpg/webp) para
// aparecer como o ícone das mensagens no Discord. Deixe vazio para usar o
// ícone padrão do webhook configurado direto no Discord.
define('WEBHOOK_AVATAR_URL', 'https://media.discordapp.net/attachments/1533755632551854150/1535166516046332044/logo.png?ex=6a76c6bb&is=6a75753b&hm=8c7763572ec216ad94f1ff149f1db0dd46e5402ff37455fcbc5ac8ee040bed39&=&format=webp&quality=lossless');

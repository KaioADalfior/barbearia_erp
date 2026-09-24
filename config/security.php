<?php
// config/security.php
// Configurações centrais de segurança do sistema. Ajuste aqui em vez de
// espalhar números mágicos pelo código.

// ---------- Login / Brute force ----------
// Após MAX_LOGIN_ATTEMPTS tentativas incorretas (contadas por combinação
// IP + login, para não permitir que um atacante bloqueie a conta de uma
// vítima só de bater tentativas erradas de qualquer IP), novas tentativas
// para aquela combinação ficam bloqueadas por LOGIN_LOCKOUT_MINUTES.
define('MAX_LOGIN_ATTEMPTS', 3);
define('LOGIN_LOCKOUT_MINUTES', 15);

// ---------- Sessão ----------
// Tempo de inatividade (sem nenhuma requisição autenticada) após o qual a
// sessão é encerrada automaticamente, mesmo com o navegador aberto.
define('SESSION_INACTIVITY_MINUTES', 120);

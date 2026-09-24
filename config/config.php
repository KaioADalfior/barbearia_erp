<?php
// config.php
// Bootstrap de banco de dados + tratamento/registro de erros. Os headers
// de segurança e o display_errors=0 já são aplicados em
// includes/session.php (carregado antes deste arquivo em toda
// página/script), porque algumas páginas nunca chegam a incluir
// config.php mas ainda assim precisam dos headers.

// Configuração da conexão com o banco de dados MySQL
require_once __DIR__ . '/../includes/DiscordLogger.php';

// Credenciais: preferem variáveis de ambiente (getenv) — é assim que o
// Railway (ver .nixpacks/) injeta segredos sem precisar deles no código
// fonte. Se a variável de ambiente não estiver definida, cai no valor
// fixo abaixo, só para não quebrar o ambiente atual enquanto a migração
// pra env vars não é feita.
//
// AÇÃO RECOMENDADA: configure DB_HOST/DB_NAME/DB_USER/DB_PASS nas
// variáveis de ambiente do Railway (ou do host atual), troque a senha do
// MySQL (a atual já circulou em texto puro no código-fonte e em
// config/config.zip, então deve ser tratada como comprometida) e então
// apague os valores fixos abaixo, deixando só o getenv().
$DB_HOST = getenv('DB_HOST') ?: 'bdclientes';
$DB_NAME = getenv('DB_NAME') ?: 'alexbarber';
$DB_USER = getenv('DB_USER') ?: 'bd_clientes';
$DB_PASS = getenv('DB_PASS') ?: 'nsKUsUMgUIUqTOfIf6ntK92Swq4CtZIe';
try {
    $pdo = new PDO(
        "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // O MySQL roda no fuso do container (UTC), independente do
            // date_default_timezone_set() do PHP (ver includes/session.php).
            // Sem isso, NOW()/CURDATE() e as colunas com DEFAULT
            // CURRENT_TIMESTAMP (criado_em, pago_em, gerado_em, etc.)
            // continuam saindo 3h adiantadas mesmo depois do fix do PHP.
            // Brasil não tem mais horário de verão desde 2019, então o
            // offset fixo -03:00 é seguro (não precisa das tabelas de
            // timezone nomeadas do MySQL, que muitas vezes não estão
            // carregadas no servidor).
            //
            // As variáveis character_set_* junto (equivalentes a um "SET
            // NAMES utf8mb4", mas combináveis num único SET com o
            // time_zone acima) garantem que acentos/emoji nunca saiam
            // trocados por caracteres quebrados em nomes de cliente, serviços,
            // observações etc. — mesmo se algum driver/versão de PHP
            // aplicar esse INIT_COMMAND antes de processar o charset da
            // DSN, a conexão já força o encoding certo aqui, sem depender
            // dessa ordem.
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '-03:00', character_set_client = utf8mb4, character_set_connection = utf8mb4, character_set_results = utf8mb4",
        ]
    );
} catch (PDOException $e) {
    DiscordLogger::database('🔴 Falha na conexão com o banco de dados', $e);
    http_response_code(500);
    die('Erro ao conectar ao banco de dados. Já registramos o problema.');
}
// A partir daqui, qualquer erro/exceção que nenhuma função do sistema tratar
// é registrado automaticamente em logs-erros, sem precisar mexer em cada
// função individualmente. Nunca mostra detalhes técnicos pro usuário.
set_exception_handler(function (\Throwable $e) {
    DiscordLogger::erro('💥 Exceção não tratada', $e);
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo 'Ocorreu um erro inesperado. Já registramos o problema.';
});

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) {
        return false; // erro suprimido com @ ou fora do nível configurado — ignora
    }
    DiscordLogger::erro('⚠️ Erro PHP', $errstr, [
        ['name' => '📄 Local', 'value' => "{$errfile}:{$errline}", 'inline' => false],
    ]);
    return false; // mantém o comportamento padrão do PHP (log local) também
});

// Erros fatais (E_ERROR, E_PARSE, out-of-memory, etc.) NÃO passam pelo
// set_error_handler acima — só um shutdown function consegue pegá-los.
// Sem isso, um erro fatal com display_errors=0 (config certa em produção)
// simplesmente gerava uma página em branco pro usuário e nada no log.
register_shutdown_function(function () {
    $erro = error_get_last();
    if ($erro !== null && in_array($erro['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        DiscordLogger::erro('💥 Erro fatal', $erro['message'], [
            ['name' => '📄 Local', 'value' => "{$erro['file']}:{$erro['line']}", 'inline' => false],
        ]);
        if (!headers_sent()) {
            http_response_code(500);
            echo 'Ocorreu um erro inesperado. Já registramos o problema.';
        }
    }
});

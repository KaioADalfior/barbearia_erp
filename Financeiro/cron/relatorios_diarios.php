<?php
/**
 * Financeiro/cron/relatorios_diarios.php
 *
 * Rotina de linha de comando que gera os relatórios financeiros automáticos
 * (diário do dia anterior; semanal/mensal/anual quando o período fecha) —
 * ver includes/RelatorioAutomatico.php. NÃO é uma página web: se for
 * chamada por HTTP responde 404 e não faz nada.
 *
 * Uso:
 *   php Financeiro/cron/relatorios_diarios.php            # rodada normal (idempotente)
 *   php Financeiro/cron/relatorios_diarios.php --dry-run  # só mostra o que geraria
 *   php Financeiro/cron/relatorios_diarios.php --forcar   # ignora espera/limite de tentativas
 *   php Financeiro/cron/relatorios_diarios.php --dias=7   # recuperar até 7 dias atrás
 *   php Financeiro/cron/relatorios_diarios.php --agora="2026-10-10 00:00:05"   # (testes) hora de São Paulo simulada
 *   php Financeiro/cron/relatorios_diarios.php --barbeiro=3
 *
 * Códigos de saída: 0 = tudo certo · 1 = algum relatório falhou (os demais
 * foram gerados) · 2 = sem conexão com o banco · 64 = argumento inválido.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

date_default_timezone_set('America/Sao_Paulo');
ini_set('display_errors', '0');
ini_set('log_errors', '1');
set_time_limit(0);

$opts = [];
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--forcar') {
        $opts['forcar'] = true;
    } elseif ($arg === '--dry-run' || $arg === '--simular') {
        $opts['simular'] = true;
    } elseif (preg_match('/^--dias=(\d{1,2})$/', $arg, $m)) {
        $opts['dias'] = (int) $m[1];
    } elseif (preg_match('/^--barbeiro=(\d+)$/', $arg, $m)) {
        $opts['idBarbeiro'] = (int) $m[1];
    } elseif (preg_match('/^--agora=(.+)$/', $arg, $m)) {
        $opts['agora'] = $m[1];
    } else {
        fwrite(STDERR, "Argumento inválido: {$arg}\n");
        exit(64);
    }
}

$fuso = new DateTimeZone('America/Sao_Paulo');
try {
    $agora = isset($opts['agora'])
        ? new DateTimeImmutable($opts['agora'], $fuso)
        : new DateTimeImmutable('now', $fuso);
} catch (Throwable $e) {
    fwrite(STDERR, "--agora inválido\n");
    exit(64);
}
unset($opts['agora']);

$log = function (string $linha) use ($fuso): void {
    echo (new DateTimeImmutable('now', $fuso))->format('Y-m-d H:i:s') . ' [relatorios-auto] ' . $linha . PHP_EOL;
};

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/RelatorioAutomatico.php';

$log('início referência=' . $agora->format('Y-m-d H:i:s') . ' fuso=America/Sao_Paulo');

try {
    $res = RelatorioAutomatico::executar($pdo, $agora, $opts, $log);
} catch (Throwable $e) {
    $log('ERRO FATAL na rotina: ' . RelatorioAutomatico::mensagemSegura($e));
    DiscordLogger::erro('🔴 Relatórios automáticos: rotina interrompida', RelatorioAutomatico::mensagemSegura($e));
    exit(1);
}

$log(sprintf(
    'fim gerados=%d existentes=%d falhas=%d ignorados=%d',
    $res['gerados'], $res['existentes'], $res['falhas'], $res['ignorados']
));

// Um único aviso por rodada, só com contagens (sem nomes nem valores).
if ($res['gerados'] > 0 || $res['falhas'] > 0) {
    try {
        DiscordLogger::financeiroRelatorios(
            $res['falhas'] > 0 ? '⚠️ Relatórios automáticos (com falhas)' : '🗓️ Relatórios automáticos gerados',
            [
                ['name' => 'Gerados', 'value' => (string) $res['gerados'], 'inline' => true],
                ['name' => 'Falhas', 'value' => (string) $res['falhas'], 'inline' => true],
                ['name' => 'Referência', 'value' => $agora->format('d/m/Y H:i'), 'inline' => true],
            ],
            $res['falhas'] > 0 ? 0xF59E0B : null
        );
    } catch (Throwable $e) {
        $log('aviso ao Discord falhou (ignorado)');
    }
}

exit($res['falhas'] > 0 ? 1 : 0);

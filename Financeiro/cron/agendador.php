<?php
/**
 * Financeiro/cron/agendador.php
 *
 * Agendador dedicado (sem cron do sistema): processo PHP de longa duração,
 * iniciado junto com o container (ver nixpacks.toml / .nixpacks/Dockerfile),
 * que dispara Financeiro/cron/relatorios_diarios.php:
 *   - à meia-noite de São Paulo (00:00) e a cada 15 minutos depois (reparar
 *     falhas e recuperar o que ficou pendente por queda/deploy);
 *   - ~45 s após subir (recupera uma virada de dia perdida durante um deploy).
 * O relógio é sempre convertido para America/Sao_Paulo, qualquer que seja o
 * fuso do container (UTC no EasyPanel).
 *
 * Instância única: flock exclusivo num arquivo de trava. Uma segunda cópia
 * (ex.: o loop de reinício do container) sai com código 3 sem fazer nada.
 * Além disso a rotina usa GET_LOCK no MySQL, então até dois containers
 * (réplicas) nunca geram ao mesmo tempo, e a geração é idempotente.
 *
 * Variáveis de ambiente:
 *   RELATORIOS_AGENDADOR=0   desliga o agendador (use se preferir o Cron Job do EasyPanel)
 *   RELATORIOS_TICK=5        segundos entre verificações (padrão 5; só para testes)
 *   RELATORIOS_ATRASO_INICIAL=45  segundos após subir até a rodada de recuperação (padrão 45)
 *   RELATORIOS_TRAVA=/caminho arquivo de trava (padrão: pasta temporária do sistema)
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

date_default_timezone_set('America/Sao_Paulo');
require_once __DIR__ . '/../../includes/RelatorioAutomatico.php';

$atrasoInicial = max(0, (int) (getenv('RELATORIOS_ATRASO_INICIAL') !== false && getenv('RELATORIOS_ATRASO_INICIAL') !== '' ? getenv('RELATORIOS_ATRASO_INICIAL') : 45));

function agendadorLog(string $m): void
{
    echo (new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d H:i:s')
        . ' [agendador] ' . $m . PHP_EOL;
}

if (getenv('RELATORIOS_AGENDADOR') === '0') {
    agendadorLog('desligado por RELATORIOS_AGENDADOR=0');
    exit(0);
}

$arquivoTrava = getenv('RELATORIOS_TRAVA') ?: (sys_get_temp_dir() . '/barberp_relatorios_agendador.lock');
$trava = @fopen($arquivoTrava, 'c');
if ($trava === false || !flock($trava, LOCK_EX | LOCK_NB)) {
    agendadorLog('já existe um agendador em execução; saindo');
    exit(3);
}
ftruncate($trava, 0);
fwrite($trava, (string) getmypid());

$tick = max(1, (int) (getenv('RELATORIOS_TICK') ?: 5));
$job  = __DIR__ . '/relatorios_diarios.php';
$fuso = new DateTimeZone('America/Sao_Paulo');
$ultimoMinuto = null;
$inicio = time();
$partidaFeita = false;

$continuar = true;
if (function_exists('pcntl_async_signals') && function_exists('pcntl_signal')) {
    pcntl_async_signals(true);
    $parar = function () use (&$continuar) { $continuar = false; };
    pcntl_signal(SIGTERM, $parar);
    pcntl_signal(SIGINT, $parar);
}

function rodarJob(string $job, string $motivo): void
{
    agendadorLog("disparando relatórios ({$motivo})");
    $cmd = [PHP_BINARY, $job];
    // A saída da rotina é repassada linha a linha por este processo (um único
    // escritor no log do container: nada se sobrescreve nem se mistura).
    $proc = @proc_open($cmd, [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['redirect', 1]], $pipes);
    if (!is_resource($proc)) {
        agendadorLog('não foi possível iniciar a rotina (proc_open indisponível)');
        return;
    }
    while (($linha = fgets($pipes[1])) !== false) {
        echo $linha;
    }
    fclose($pipes[1]);
    $codigo = proc_close($proc);
    agendadorLog("rotina terminou com código {$codigo}");
}

agendadorLog('iniciado (pid ' . getmypid() . ', fuso America/Sao_Paulo)');

while ($continuar) {
    try {
        $agora = new DateTimeImmutable('now', $fuso);

        if (!$partidaFeita && (time() - $inicio) >= $atrasoInicial) {
            $partidaFeita = true;
            $ultimoMinuto = $agora->format('Y-m-d H:i');
            rodarJob($job, 'recuperação após iniciar');
        } elseif (RelatorioAutomatico::deveExecutar($agora, $ultimoMinuto)) {
            $ultimoMinuto = $agora->format('Y-m-d H:i');
            rodarJob($job, $agora->format('H:i') === '00:00' ? 'meia-noite' : 'verificação periódica');
        }
    } catch (Throwable $e) {
        // O agendador nunca morre por causa de uma rodada.
        agendadorLog('erro no ciclo: ' . get_class($e));
    }
    sleep($tick);
}

agendadorLog('encerrado');

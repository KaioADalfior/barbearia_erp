<?php
/**
 * includes/RelatorioAutomatico.php
 *
 * Geração AUTOMÁTICA dos relatórios financeiros, todo dia à meia-noite
 * (America/Sao_Paulo), sem depender de ninguém abrir o sistema.
 *
 * O que é gerado (por profissional — cada barbeiro/funcionário tem o seu
 * próprio relatório, com as mesmas regras do módulo manual):
 *   - DIÁRIO   todo dia, referente ao dia ANTERIOR completo (00:00:00–23:59:59);
 *   - SEMANAL  na segunda-feira, referente à semana anterior (seg–dom);
 *   - MENSAL   no dia 1º, referente ao mês anterior inteiro;
 *   - ANUAL    em 1º de janeiro, referente ao ano anterior inteiro.
 * O tipo "período" (intervalo livre) continua sendo só manual.
 *
 * Confiabilidade:
 *   - a rotina é IDEMPOTENTE: roda quantas vezes for preciso (a cada 15 min
 *     pelo agendador) e só gera o que falta; relatório já existente nunca é
 *     duplicado (chave única no banco: barbeiro + tipo + período);
 *   - o que faltar por queda do servidor/deploy na virada do dia é recuperado
 *     (janela de recuperação de alguns dias) — no primeiro uso, só o dia anterior;
 *   - falha em um relatório é registrada (FinanceiroRelatoriosAuto) e NÃO
 *     impede os demais; nova tentativa automática com intervalo mínimo e
 *     limite de tentativas; depois disso fica visível na tela de Relatórios
 *     com o botão "Tentar novamente";
 *   - trava de execução única (GET_LOCK do MySQL) — duas instâncias do
 *     container nunca rodam ao mesmo tempo;
 *   - logs só com ids e contagens: sem nomes, valores ou credenciais.
 */

require_once __DIR__ . '/RelatorioService.php';

final class RelatorioAutomatico
{
    public const FUSO               = 'America/Sao_Paulo';
    public const MAX_TENTATIVAS     = 5;
    public const INTERVALO_RETRY_MIN = 10;
    public const JANELA_DIAS        = 3;
    private const TRAVA             = 'barberp_relatorios_automaticos';

    // ---------------------------------------------------------------- períodos

    /**
     * Relatórios que "vencem" em $dia (fim do período = $dia, que já terminou).
     *
     * @return array<int, array{0:string,1:string,2:string}> [tipo, início, fim]
     */
    public static function periodosQueTerminamEm(DateTimeImmutable $dia): array
    {
        $d   = $dia->format('Y-m-d');
        $out = [['diario', $d, $d]];

        if ($dia->format('N') === '7') { // domingo fecha a semana seg–dom
            $out[] = ['semanal', $dia->modify('-6 days')->format('Y-m-d'), $d];
        }
        if ($dia->format('Y-m-d') === $dia->modify('last day of this month')->format('Y-m-d')) {
            $out[] = ['mensal', $dia->modify('first day of this month')->format('Y-m-d'), $d];
        }
        if ($dia->format('m-d') === '12-31') {
            $out[] = ['anual', $dia->format('Y') . '-01-01', $d];
        }
        return $out;
    }

    /**
     * Todos os períodos a garantir quando a rotina roda em $agora: os que
     * terminaram nos últimos $dias dias (sempre inclui o dia anterior).
     */
    public static function periodosPendentes(DateTimeImmutable $agora, int $dias): array
    {
        $dias  = max(1, min(31, $dias));
        $hoje  = $agora->setTime(0, 0, 0);
        $saida = [];
        for ($i = $dias; $i >= 1; $i--) {
            foreach (self::periodosQueTerminamEm($hoje->modify("-{$i} days")) as $p) {
                $saida[] = $p;
            }
        }
        return $saida;
    }

    // --------------------------------------------------------------- execução

    /**
     * Executa uma rodada.
     *
     * @param array{dias?:int, forcar?:bool, simular?:bool, idBarbeiro?:?int} $opcoes
     * @param callable(string):void $log recebe uma linha de log (sem dados pessoais)
     * @return array{gerados:int, existentes:int, falhas:int, ignorados:int, executou:bool}
     */
    public static function executar(PDO $pdo, DateTimeImmutable $agora, array $opcoes, callable $log): array
    {
        $res = ['gerados' => 0, 'existentes' => 0, 'falhas' => 0, 'ignorados' => 0, 'executou' => false];
        $agora = $agora->setTimezone(new DateTimeZone(self::FUSO));

        $trava = $pdo->prepare('SELECT GET_LOCK(:n, 0)');
        $trava->execute(['n' => self::TRAVA]);
        if ((int) $trava->fetchColumn() !== 1) {
            $log('outra execução em andamento; nada a fazer');
            return $res;
        }

        try {
            $res['executou'] = true;
            RelatorioService::garantirEstrutura($pdo);

            $sql = 'SELECT id_barbeiro, criado_em FROM Barbeiro';
            $par = [];
            if (!empty($opcoes['idBarbeiro'])) {
                $sql .= ' WHERE id_barbeiro = :b';
                $par['b'] = (int) $opcoes['idBarbeiro'];
            }
            $st = $pdo->prepare($sql . ' ORDER BY id_barbeiro');
            $st->execute($par);
            $barbeiros = $st->fetchAll();

            // Janela de recuperação. No primeiro uso (nada registrado ainda) só o dia
            // anterior — não "inventa" histórico — salvo se --dias for pedido explicitamente.
            $jaUsou = (int) $pdo->query('SELECT COUNT(*) FROM FinanceiroRelatoriosAuto')->fetchColumn() > 0;
            if (isset($opcoes['dias'])) {
                $janela = (int) $opcoes['dias'];
            } else {
                $janela = $jaUsou ? self::JANELA_DIAS : 1;
            }
            $periodos = self::periodosPendentes($agora, $janela);
            if ($jaUsou && !isset($opcoes['dias'])) {
                // Recuperação nunca volta antes do 1º dia já processado pela rotina:
                // não cria "histórico" de dias que ela nunca deveria ter coberto.
                $primeiro = (string) $pdo->query('SELECT MIN(data_fim) FROM FinanceiroRelatoriosAuto')->fetchColumn();
                if ($primeiro !== '') {
                    $periodos = array_values(array_filter($periodos, static fn(array $p): bool => $p[2] >= $primeiro));
                }
            }

            foreach ($barbeiros as $b) {
                $id = (int) $b['id_barbeiro'];
                $cadastro = substr((string) $b['criado_em'], 0, 10);
                foreach ($periodos as [$tipo, $ini, $fim]) {
                    if ($cadastro !== '' && $fim < $cadastro) {
                        $res['ignorados']++; // período anterior à existência do profissional
                        continue;
                    }
                    self::processar($pdo, $agora, $id, $tipo, $ini, $fim, $opcoes, $res, $log);
                }
            }
        } finally {
            $pdo->prepare('SELECT RELEASE_LOCK(:n)')->execute(['n' => self::TRAVA]);
        }

        return $res;
    }

    /**
     * "Tentar novamente" pela tela: refaz UMA falha do próprio profissional,
     * ignorando a espera e o limite de tentativas automáticas.
     *
     * @return array{ok:bool, erro?:string}
     */
    public static function tentarNovamente(PDO $pdo, int $idBarbeiro, string $chave, DateTimeImmutable $agora): array
    {
        RelatorioService::garantirEstrutura($pdo);
        $st = $pdo->prepare(
            "SELECT tipo, data_inicio, data_fim FROM FinanceiroRelatoriosAuto
             WHERE chave = :c AND id_barbeiro = :b AND status = 'erro'"
        );
        $st->execute(['c' => $chave, 'b' => $idBarbeiro]);
        $f = $st->fetch();
        if (!$f) {
            return ['ok' => false, 'erro' => 'Falha não encontrada (ou já resolvida).'];
        }

        $trava = $pdo->prepare('SELECT GET_LOCK(:n, 0)');
        $trava->execute(['n' => self::TRAVA]);
        if ((int) $trava->fetchColumn() !== 1) {
            return ['ok' => false, 'erro' => 'A geração automática está em andamento. Tente novamente em instantes.'];
        }
        try {
            $res = ['gerados' => 0, 'existentes' => 0, 'falhas' => 0, 'ignorados' => 0];
            self::processar(
                $pdo, $agora->setTimezone(new DateTimeZone(self::FUSO)), $idBarbeiro,
                (string) $f['tipo'], (string) $f['data_inicio'], (string) $f['data_fim'],
                ['forcar' => true], $res, static function (string $l): void { error_log('[relatorios-auto] ' . $l); }
            );
        } finally {
            $pdo->prepare('SELECT RELEASE_LOCK(:n)')->execute(['n' => self::TRAVA]);
        }
        return $res['falhas'] > 0
            ? ['ok' => false, 'erro' => 'A geração falhou novamente. Veja o motivo na lista.']
            : ['ok' => true];
    }

    private static function processar(
        PDO $pdo,
        DateTimeImmutable $agora,
        int $id,
        string $tipo,
        string $ini,
        string $fim,
        array $opcoes,
        array &$res,
        callable $log
    ): void {
        $chave = RelatorioService::chaveAuto($id, $tipo, $ini, $fim);
        $forcar = !empty($opcoes['forcar']);

        $st = $pdo->prepare('SELECT status, tentativas, ultima_tentativa FROM FinanceiroRelatoriosAuto WHERE chave = :c');
        $st->execute(['c' => $chave]);
        $estado = $st->fetch();

        // Já gerado (e o relatório ainda existe, ou foi apagado de propósito pelo usuário).
        if ($estado && $estado['status'] === 'ok') {
            $res['existentes']++;
            return;
        }
        if ($estado && !$forcar) {
            if ((int) $estado['tentativas'] >= self::MAX_TENTATIVAS) {
                $res['ignorados']++;
                return;
            }
            $ultima = $estado['ultima_tentativa'] ? new DateTimeImmutable((string) $estado['ultima_tentativa'], new DateTimeZone(self::FUSO)) : null;
            if ($ultima !== null && ($agora->getTimestamp() - $ultima->getTimestamp()) < self::INTERVALO_RETRY_MIN * 60) {
                $res['ignorados']++;
                return;
            }
        }
        if (!empty($opcoes['simular'])) {
            $log("simulação: geraria barbeiro={$id} tipo={$tipo} periodo={$ini}..{$fim}");
            return;
        }

        $t0 = microtime(true);
        try {
            $r = RelatorioService::emitir($pdo, $id, $tipo, $ini, $fim, RelatorioService::ORIGEM_AUTOMATICO, $agora);
            self::marcar($pdo, $chave, $id, $tipo, $ini, $fim, 'ok', ($estado ? (int) $estado['tentativas'] : 0) + 1, null, $r['id'], $agora);
            $ms = (int) round((microtime(true) - $t0) * 1000);
            if ($r['duplicado']) {
                $res['existentes']++;
                $log("ok-existente barbeiro={$id} tipo={$tipo} periodo={$ini}..{$fim} relatorio={$r['id']}");
            } else {
                $res['gerados']++;
                $log("ok barbeiro={$id} tipo={$tipo} periodo={$ini}..{$fim} relatorio={$r['id']} ms={$ms}");
            }
        } catch (Throwable $e) {
            $res['falhas']++;
            $msg = self::mensagemSegura($e);
            $tent = ($estado ? (int) $estado['tentativas'] : 0) + 1;
            try {
                self::marcar($pdo, $chave, $id, $tipo, $ini, $fim, 'erro', $tent, $msg, null, $agora);
            } catch (Throwable $e2) {
                $log('ERRO ao registrar falha: ' . self::mensagemSegura($e2));
            }
            $log("ERRO barbeiro={$id} tipo={$tipo} periodo={$ini}..{$fim} tentativa={$tent}/" . self::MAX_TENTATIVAS . " motivo={$msg}");
        }
    }

    private static function marcar(PDO $pdo, string $chave, int $id, string $tipo, string $ini, string $fim, string $status, int $tentativas, ?string $msg, ?int $idRel, DateTimeImmutable $agora): void
    {
        $pdo->prepare(
            'INSERT INTO FinanceiroRelatoriosAuto (chave, id_barbeiro, tipo, data_inicio, data_fim, status, tentativas, ultima_tentativa, mensagem, idRelatorio)
             VALUES (:c, :b, :t, :i, :f, :s, :n, :u, :m, :r)
             ON DUPLICATE KEY UPDATE status = VALUES(status), tentativas = VALUES(tentativas),
                 ultima_tentativa = VALUES(ultima_tentativa), mensagem = VALUES(mensagem), idRelatorio = VALUES(idRelatorio)'
        )->execute([
            'c' => $chave, 'b' => $id, 't' => $tipo, 'i' => $ini, 'f' => $fim, 's' => $status, 'n' => $tentativas,
            'u' => $agora->format('Y-m-d H:i:s'), 'm' => $msg, 'r' => $idRel,
        ]);
    }

    /**
     * Mensagem de erro para log/tela: classe + trecho curto SEM o conteúdo
     * entre aspas (valores, nomes, SQL com dados) e sem caminhos de servidor.
     */
    public static function mensagemSegura(Throwable $e): string
    {
        $m = (string) $e->getMessage();
        $m = preg_replace("/'[^']*'|\"[^\"]*\"/u", '?', $m) ?? '';
        $m = preg_replace('#(/[\w.\-]+){2,}#', '[caminho]', $m) ?? '';
        $m = trim(preg_replace('/\s+/', ' ', $m) ?? '');
        return mb_substr(get_class($e) . ': ' . $m, 0, 200, 'UTF-8');
    }

    // --------------------------------------------------------------- agendador

    /**
     * O agendador (Financeiro/cron/agendador.php) chama isto a cada tick.
     * Dispara a rotina à meia-noite (00:00 de São Paulo) e, depois, a cada
     * 15 minutos — para repetir falhas e recuperar o que ficou pendente.
     * $ultimaMinuto evita disparar duas vezes no mesmo minuto.
     */
    public static function deveExecutar(DateTimeImmutable $agora, ?string $ultimoMinuto): bool
    {
        $agora = $agora->setTimezone(new DateTimeZone(self::FUSO));
        $minuto = $agora->format('Y-m-d H:i');
        if ($ultimoMinuto === $minuto) {
            return false;
        }
        return ((int) $agora->format('i')) % 15 === 0;
    }
}

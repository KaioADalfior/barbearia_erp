<?php
/**
 * includes/LoginThrottle.php
 *
 * Proteção contra força bruta no login. Regra:
 *   3 tentativas incorretas (MAX_LOGIN_ATTEMPTS, config/security.php)
 *   → bloqueio de 15 minutos (LOGIN_LOCKOUT_MINUTES) para aquela mesma
 *     combinação de IP + login tentado.
 *
 * Combinar IP + login (em vez de só o login) é proposital: um atacante
 * não consegue bloquear a conta de uma vítima de propósito só batendo
 * senha errada de qualquer lugar — ele precisaria fazer isso a partir do
 * mesmo IP da vítima. E também não é só por IP, senão uma rede
 * compartilhada (ex.: NAT/4G/escritório) bloquearia todo mundo por causa
 * de uma pessoa só.
 *
 * Usa a tabela LoginTentativas (ver scriptBD/atualizacao_login_lockout.sql).
 * Precisa rodar essa migração antes de usar esta classe.
 */

require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/IpCliente.php';

class LoginThrottle
{
    private static function chave(string $login): string
    {
        $ip = IpCliente::obter();
        // Normaliza o login como o banco compara (sem diferença de
        // maiúsculas/minúsculas nem de acentos): "Admin", "admin" e "admín"
        // contam como a MESMA conta — senão bastava variar acento para
        // ganhar tentativas novas.
        $norm = mb_strtolower(trim($login));
        if (function_exists('iconv')) {
            $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $norm);
            if ($ascii !== false && $ascii !== '') {
                $norm = preg_replace('/[^a-z0-9._@-]/', '', strtolower($ascii)) ?: $norm;
            }
        }
        return $ip . ':' . substr($norm, 0, 80);
    }

    /** Apaga tentativas antigas (a tabela só cresceria) — chamado de vez em quando. */
    private static function limparAntigas(PDO $pdo): void
    {
        if (random_int(1, 50) !== 1) {
            return;
        }
        try {
            $pdo->exec('DELETE FROM LoginTentativas WHERE criado_em < DATE_SUB(NOW(), INTERVAL 7 DAY)');
        } catch (Throwable $e) {
            // sem importância: tenta de novo na próxima
        }
    }

    /**
     * @return array{bloqueado: bool, minutosRestantes: int}
     */
    public static function verificarBloqueio(PDO $pdo, string $login): array
    {
        $chave = self::chave($login);

        $stmt = $pdo->prepare(
            'SELECT COUNT(*) AS tentativas, MAX(criado_em) AS ultima
             FROM LoginTentativas
             WHERE chave = :chave AND sucesso = 0
               AND criado_em >= DATE_SUB(NOW(), INTERVAL :janela MINUTE)'
        );
        $stmt->execute(['chave' => $chave, 'janela' => LOGIN_LOCKOUT_MINUTES]);
        $linha = $stmt->fetch();

        $tentativas = (int) ($linha['tentativas'] ?? 0);

        if ($tentativas < MAX_LOGIN_ATTEMPTS) {
            return ['bloqueado' => false, 'minutosRestantes' => 0];
        }

        // Ainda dentro da janela de bloqueio: calcula quanto falta
        $ultima = new DateTimeImmutable((string) $linha['ultima']);
        $liberaEm = $ultima->modify('+' . LOGIN_LOCKOUT_MINUTES . ' minutes');
        $agora = new DateTimeImmutable();

        if ($agora >= $liberaEm) {
            return ['bloqueado' => false, 'minutosRestantes' => 0];
        }

        $minutosRestantes = (int) ceil(($liberaEm->getTimestamp() - $agora->getTimestamp()) / 60);

        return ['bloqueado' => true, 'minutosRestantes' => max(1, $minutosRestantes)];
    }

    public static function registrarFalha(PDO $pdo, string $login): void
    {
        self::limparAntigas($pdo);
        $stmt = $pdo->prepare(
            'INSERT INTO LoginTentativas (chave, sucesso, criado_em) VALUES (:chave, 0, NOW())'
        );
        $stmt->execute(['chave' => self::chave($login)]);
    }

    /**
     * Limpa o histórico de falhas dessa combinação IP+login após um login
     * bem-sucedido (o usuário não deve continuar "gastando" tentativas de
     * um bloqueio antigo depois de provar quem é).
     */
    public static function limparFalhas(PDO $pdo, string $login): void
    {
        $stmt = $pdo->prepare('DELETE FROM LoginTentativas WHERE chave = :chave');
        $stmt->execute(['chave' => self::chave($login)]);
    }
}

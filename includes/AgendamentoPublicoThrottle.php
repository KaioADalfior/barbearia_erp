<?php
/**
 * includes/AgendamentoPublicoThrottle.php
 *
 * Limita quantas tentativas de agendamento pelo link público (sem login)
 * um mesmo IP pode fazer numa janela de tempo. O formulário de
 * Publico/paginas/agendar.php não tem senha nem CAPTCHA, então esta é a
 * principal defesa contra spam/abuso automatizado nele. Mesmo padrão de
 * includes/LoginThrottle.php, mas em tabela própria: aqui não existe
 * "sucesso/falha" de autenticação, só volume de tentativas de um mesmo IP.
 *
 * Usa a tabela AgendamentoPublicoTentativas (ver
 * scriptBD/atualizacao_agendamento_publico.sql).
 */

class AgendamentoPublicoThrottle
{
    // Máximo de tentativas de agendamento (bem ou mal-sucedidas) por IP
    // dentro da janela abaixo.
    private const MAX_TENTATIVAS = 10;
    private const JANELA_MINUTOS = 60;

    /**
     * IP real do cliente. Em produção o app roda atrás de um proxy reverso
     * (EasyPanel/Traefik, às vezes Cloudflare): nesse caso REMOTE_ADDR é o IP
     * do PROXY, o mesmo para todos os visitantes — e o limite abaixo passava
     * a valer para a barbearia inteira, bloqueando todo mundo depois de
     * poucos agendamentos ("Muitas tentativas"). Só confiamos nos cabeçalhos
     * encaminhados quando a conexão vem de um endereço privado/loopback
     * (ou seja, de um proxy nosso); fora disso vale o REMOTE_ADDR.
     */
    public static function ipCliente(): string
    {
        $remoto = $_SERVER['REMOTE_ADDR'] ?? '';
        $ehProxy = $remoto === ''
            || filter_var($remoto, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;

        if ($ehProxy) {
            $candidatos = [];
            foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP'] as $chave) {
                if (!empty($_SERVER[$chave])) { $candidatos[] = $_SERVER[$chave]; }
            }
            if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                foreach (explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']) as $parte) { $candidatos[] = $parte; }
            }
            foreach ($candidatos as $c) {
                $c = trim($c);
                if (filter_var($c, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $c;
                }
            }
        }

        return $remoto !== '' ? $remoto : 'desconhecido';
    }

    public static function bloqueado(PDO $pdo, string $ip): bool
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM AgendamentoPublicoTentativas
             WHERE ip = :ip AND criado_em >= DATE_SUB(NOW(), INTERVAL :janela MINUTE)'
        );
        $stmt->execute(['ip' => $ip, 'janela' => self::JANELA_MINUTOS]);

        return (int) $stmt->fetchColumn() >= self::MAX_TENTATIVAS;
    }

    public static function registrarTentativa(PDO $pdo, string $ip): void
    {
        $stmt = $pdo->prepare('INSERT INTO AgendamentoPublicoTentativas (ip, criado_em) VALUES (:ip, NOW())');
        $stmt->execute(['ip' => $ip]);
    }
}

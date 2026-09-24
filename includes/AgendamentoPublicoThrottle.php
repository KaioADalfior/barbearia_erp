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
    private const MAX_TENTATIVAS = 8;
    private const JANELA_MINUTOS = 60;

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

<?php
/**
 * includes/FidelidadeService.php
 *
 * Regras do "Agendamento de Fidelidade": um cliente escolhe uma frequência
 * fixa e o sistema cria automaticamente as próximas ocorrências, sempre no
 * MESMO DIA DA SEMANA e no mesmo horário do primeiro agendamento, por até
 * 1 ano — sem gerar nada indefinidamente.
 *
 * IMPORTANTE: o intervalo interno é sempre em SEMANAS, nunca em dias
 * corridos. Somar dias corridos (ex.: +20 dias, +30 dias) pode deslocar o
 * agendamento para outro dia da semana; somar semanas (+1, +2, +3, +4)
 * garante, por construção, que o dia da semana nunca muda — qualquer
 * múltiplo de 7 dias cai exatamente no mesmo dia da semana.
 *
 * Os códigos abaixo ('1' a '4') representam o número de semanas entre
 * ocorrências. Os RÓTULOS exibidos na interface usam uma linguagem mais
 * comercial de barbearia ("15 em 15 dias", "20 em 20 dias", "30 em 30
 * dias") mas isso é só o texto mostrado — o cálculo real nunca usa esses
 * números de dias, só as semanas.
 */

class FidelidadeService
{
    // Código => número de SEMANAS entre cada ocorrência.
    private const SEMANAS = [
        '1' => 1, // toda semana
        '2' => 2, // a cada 2 semanas
        '3' => 3, // a cada 3 semanas
        '4' => 4, // a cada 4 semanas
    ];

    // Rótulo comercial exibido na interface (não é o intervalo real em
    // dias — o intervalo real é sempre em semanas, ver SEMANAS acima).
    private const ROTULOS = [
        '1' => '7 em 7 dias',
        '2' => '15 em 15 dias',
        '3' => '20 em 20 dias',
        '4' => '30 em 30 dias',
    ];

    public static function codigoValido(?string $codigo): bool
    {
        return $codigo !== null && isset(self::SEMANAS[$codigo]);
    }

    public static function rotulo(string $codigo): string
    {
        return self::ROTULOS[$codigo] ?? $codigo;
    }

    /**
     * Gera um identificador único para agrupar todas as ocorrências da
     * mesma sequência de fidelidade (Agendamentos.grupo_recorrencia).
     */
    public static function novoGrupoRecorrencia(): string
    {
        return bin2hex(random_bytes(16)); // 32 caracteres hexadecimais
    }

    /**
     * Calcula as datas das ocorrências FUTURAS (não inclui a data inicial,
     * que já corresponde ao primeiro agendamento, criado normalmente pelo
     * fluxo existente), no formato Y-m-d, respeitando o limite de 1 ano a
     * partir da data inicial. Cada ocorrência é a anterior + N SEMANAS
     * (nunca dias corridos), preservando sempre o mesmo dia da semana.
     *
     * @return string[] lista de datas (Y-m-d), em ordem cronológica
     */
    public static function gerarDatasFuturas(string $dataInicial, string $codigoFidelidade): array
    {
        if (!self::codigoValido($codigoFidelidade)) {
            return [];
        }

        $intervaloSemanas = self::SEMANAS[$codigoFidelidade];

        $dataAtual = DateTime::createFromFormat('Y-m-d', $dataInicial);
        if (!$dataAtual) {
            return [];
        }

        // Limite explícito: 1 ano a partir da data inicial — condição de
        // parada do loop abaixo, nunca gera indefinidamente.
        $dataLimite = (clone $dataAtual)->modify('+1 year');

        $datas = [];
        $proxima = (clone $dataAtual)->modify("+{$intervaloSemanas} weeks");

        while ($proxima <= $dataLimite) {
            $datas[] = $proxima->format('Y-m-d');
            $proxima = (clone $proxima)->modify("+{$intervaloSemanas} weeks");
        }

        return $datas;
    }
}
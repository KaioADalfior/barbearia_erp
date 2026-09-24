<?php
/**
 * includes/HorarioService.php
 *
 * Centraliza a geração da grade padrão de horários de um dia (usada por
 * Agendamentos/scripts/horarios_buscar.php ao abrir o calendário, e também
 * pelo FidelidadeService ao criar as ocorrências futuras de um Agendamento
 * de Fidelidade) — assim as duas telas sempre enxergam a mesma grade,
 * gerada uma única vez por dia/barbeiro.
 */

class HorarioService
{
    // Passo entre horários (minutos) e turnos padrão do dia (pula o
    // intervalo de almoço, das 11:20 às 13:00).
    private const PASSO_MINUTOS = 40;
    private const TURNOS = [
        ['inicio' => 8 * 60,  'fim' => 11 * 60 + 20], // 08:00 - 11:20
        ['inicio' => 13 * 60, 'fim' => 19 * 60 + 40], // 13:00 - 19:40
    ];

    /**
     * Garante que a grade padrão do dia já existe na tabela Horario para o
     * barbeiro informado. Não faz nada se o dia já tiver algum horário
     * cadastrado (evita sobrescrever grades já ajustadas manualmente).
     */
    public static function garantirGradeDoDia(PDO $pdo, int $idBarbeiro, string $data): void
    {
        $stmtConfere = $pdo->prepare('SELECT COUNT(*) FROM Horario WHERE id_barbeiro = :b AND data = :d');
        $stmtConfere->execute(['b' => $idBarbeiro, 'd' => $data]);

        if ((int) $stmtConfere->fetchColumn() > 0) {
            return;
        }

        $stmtInsere = $pdo->prepare(
            'INSERT IGNORE INTO Horario (id_barbeiro, data, hora, disponivel) VALUES (:b, :d, :h, 1)'
        );

        foreach (self::TURNOS as $turno) {
            for ($min = $turno['inicio']; $min <= $turno['fim']; $min += self::PASSO_MINUTOS) {
                $hora = sprintf('%02d:%02d:00', intdiv($min, 60), $min % 60);
                $stmtInsere->execute(['b' => $idBarbeiro, 'd' => $data, 'h' => $hora]);
            }
        }
    }

    /**
     * Retorna o idHorario da linha (id_barbeiro, data, hora), criando a
     * grade do dia primeiro se ainda não existir. Usado pela recorrência de
     * fidelidade para conseguir um idHorario válido em datas futuras que o
     * barbeiro ainda não tinha aberto no calendário.
     */
    public static function obterOuCriarHorario(PDO $pdo, int $idBarbeiro, string $data, string $hora): ?int
    {
        self::garantirGradeDoDia($pdo, $idBarbeiro, $data);

        $horaNormalizada = strlen($hora) === 5 ? $hora . ':00' : $hora;

        $stmt = $pdo->prepare('SELECT idHorario FROM Horario WHERE id_barbeiro = :b AND data = :d AND hora = :h');
        $stmt->execute(['b' => $idBarbeiro, 'd' => $data, 'h' => $horaNormalizada]);
        $idHorario = $stmt->fetchColumn();

        if ($idHorario !== false) {
            return (int) $idHorario;
        }

        // Fallback de segurança: horário fora da grade padrão (ex.: um
        // horário avulso criado manualmente) — cria a linha na hora certa.
        $stmtInsere = $pdo->prepare(
            'INSERT IGNORE INTO Horario (id_barbeiro, data, hora, disponivel) VALUES (:b, :d, :h, 1)'
        );
        $stmtInsere->execute(['b' => $idBarbeiro, 'd' => $data, 'h' => $horaNormalizada]);

        $stmt->execute(['b' => $idBarbeiro, 'd' => $data, 'h' => $horaNormalizada]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Quantidade total de slots que a grade padrão de um dia sempre tem —
     * calculado dinamicamente a partir dos mesmos TURNOS/PASSO_MINUTOS
     * usados para gerar a grade, nunca um valor fixo "no código". Usada
     * pelo indicador de disponibilidade (dots) do calendário para saber
     * quantos slots um dia AINDA SEM grade gerada vai ter, sem precisar
     * criar a grade só para contar.
     */
    public static function totalSlotsPadrao(): int
    {
        $total = 0;
        foreach (self::TURNOS as $turno) {
            $total += intdiv($turno['fim'] - $turno['inicio'], self::PASSO_MINUTOS) + 1;
        }
        return $total;
    }

    /**
     * Diz se um DIA INTEIRO está bloqueado para o barbeiro (tabela
     * DiaBloqueado — ver scriptBD/atualizacao_dia_bloqueado.sql). Diferente
     * de um horário inativado (Horario.disponivel = 0): o bloqueio de dia
     * não mexe em nenhuma linha de Horario/Agendamentos, só impede a
     * criação de NOVOS agendamentos naquele dia. Centralizado aqui e
     * reutilizado por todo endpoint que cria ou move um agendamento para
     * uma data (agendamento_salvar.php, agendamento_reagendar_unico.php,
     * agendamento_reagendar_fidelidade.php) e pelos endpoints que alimentam
     * o calendário (horarios_buscar.php, mes_disponibilidade.php), pra
     * nunca depender só do frontend pra essa regra.
     */
    public static function diaBloqueado(PDO $pdo, int $idBarbeiro, string $data): bool
    {
        $stmt = $pdo->prepare('SELECT 1 FROM DiaBloqueado WHERE id_barbeiro = :b AND data = :d LIMIT 1');
        $stmt->execute(['b' => $idBarbeiro, 'd' => $data]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Conjunto de datas (Y-m-d) bloqueadas num intervalo, numa única
     * consulta — usado pelo indicador do calendário mensal
     * (mes_disponibilidade.php), nunca uma consulta por dia/célula.
     *
     * @return array<string, true> datas bloqueadas como chaves, pra lookup O(1) (isset)
     */
    public static function diasBloqueadosNoIntervalo(PDO $pdo, int $idBarbeiro, string $inicio, string $fim): array
    {
        $stmt = $pdo->prepare('SELECT data FROM DiaBloqueado WHERE id_barbeiro = :b AND data BETWEEN :i AND :f');
        $stmt->execute(['b' => $idBarbeiro, 'i' => $inicio, 'f' => $fim]);

        $datas = [];
        foreach ($stmt->fetchAll() as $linha) {
            $datas[$linha['data']] = true;
        }

        return $datas;
    }
}
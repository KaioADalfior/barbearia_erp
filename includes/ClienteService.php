<?php
/**
 * includes/ClienteService.php
 *
 * Centraliza a limpeza de agendamentos quando um cliente é inativado — para
 * nunca existir duas lógicas divergentes fazendo a mesma coisa. Usado por:
 * - Clientes/scripts/cliente_atualizar.php (fluxo REAL usado pela tela de
 *   Clientes: editar o cliente e trocar o Status para "Inativo");
 * - Clientes/scripts/cliente_status.php (endpoint dedicado de
 *   inativar/reativar, caso venha a ser usado por algum botão futuro).
 */

class ClienteService
{
    /**
     * Remove (DELETE) da tabela Agendamentos todos os agendamentos ainda
     * ATIVOS (Status IN 'agendado','confirmado') do cliente — qualquer tipo
     * de fidelidade (semanal/quinzenal/mensal/20 em 20) ou avulso — e libera
     * (disponivel = 1) os respectivos Horario, deixando o quadro de
     * horários vago de novo (o mesmo efeito de "Cancelar", só que disparado
     * pela inativação do cliente).
     *
     * NUNCA remove agendamentos 'concluido' (histórico real de
     * atendimentos) nem os que já estavam 'cancelado' antes desta chamada —
     * o histórico do cliente permanece intacto.
     *
     * Deve ser chamada dentro de uma transação já aberta pelo chamador
     * (o próprio chamador dá commit/rollback).
     *
     * @return int Quantidade de agendamentos removidos.
     */
    public static function cancelarAgendamentosAtivos(PDO $pdo, int $idCliente): int
    {
        $stmtAtivos = $pdo->prepare(
            "SELECT idAgendamento, idHorario
             FROM Agendamentos
             WHERE idCliente = :c AND Status IN ('agendado', 'confirmado')
             FOR UPDATE"
        );
        $stmtAtivos->execute(['c' => $idCliente]);
        $ativos = $stmtAtivos->fetchAll();

        if (empty($ativos)) {
            return 0;
        }

        $idsAgendamentos = array_column($ativos, 'idAgendamento');
        $idsHorarios      = array_column($ativos, 'idHorario');

        $marcadoresAg = implode(',', array_fill(0, count($idsAgendamentos), '?'));
        $stmtRemove = $pdo->prepare("DELETE FROM Agendamentos WHERE idAgendamento IN ($marcadoresAg)");
        $stmtRemove->execute($idsAgendamentos);

        $marcadoresHor = implode(',', array_fill(0, count($idsHorarios), '?'));
        $stmtLibera = $pdo->prepare("UPDATE Horario SET disponivel = 1 WHERE idHorario IN ($marcadoresHor)");
        $stmtLibera->execute($idsHorarios);

        return count($ativos);
    }
}
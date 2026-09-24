-- atualizacao_grade_horarios.sql
-- Ajusta dias que já tiveram a grade gerada pelo loop antigo (08:00-19:00,
-- a cada 40min, sem intervalo de almoço) para a nova grade
-- (08:00-11:20 e 13:00-19:40, com intervalo de almoço).
--
-- IMPORTANTE:
-- - Rode isso apenas para datas FUTURAS (sem agendamentos confirmados),
--   pois horários com agendamento vinculado (tabela Agendamentos) não
--   devem ser removidos.
-- - Ajuste o filtro de data (>= CURDATE()) conforme sua necessidade.

SET SQL_SAFE_UPDATES = 0;

-- 1) Remove horários das 11:40h e 12:00h a 12:40h (intervalo de almoço)
--    que não têm nenhum agendamento vinculado.
--    Reescrito com subquery por idHorario (chave primária) para não
--    esbarrar no modo seguro do Workbench mesmo sem desativá-lo.
DELETE FROM Horario
WHERE idHorario IN (
    SELECT idHorario FROM (
        SELECT h.idHorario
        FROM Horario h
        LEFT JOIN Agendamentos a ON a.idHorario = h.idHorario
        WHERE a.idAgendamento IS NULL
          AND h.data >= CURDATE()
          AND h.hora IN ('11:40:00', '12:00:00', '12:20:00', '12:40:00')
    ) AS tmp
);

-- 2) Insere o horário de 19:40 nos dias futuros que ainda não o têm
--    (para os dias que a grade antiga só ia até 18:40).
INSERT IGNORE INTO Horario (id_barbeiro, data, hora, disponivel)
SELECT DISTINCT id_barbeiro, data, '19:40:00', 1
FROM Horario
WHERE data >= CURDATE();

SET SQL_SAFE_UPDATES = 1;

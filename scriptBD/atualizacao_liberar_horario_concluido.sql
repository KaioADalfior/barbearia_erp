-- atualizacao_liberar_horario_concluido.sql
-- Ajusta a trava de duplicidade de horário para também liberar o slot
-- quando o agendamento é marcado como CONCLUÍDO (Status = 'concluido'),
-- e não só quando é cancelado.
--
-- Contexto: a migração anterior (atualizacao_unique_horario.sql) trocou a
-- unique key simples por uma coluna gerada "idHorario_ativo", que ficava
-- NULL apenas quando Status = 'cancelado'. Isso significava que, mesmo
-- depois de concluído, o horário continuava "travado" para sempre,
-- impedindo reagendar aquele mesmo slot. Agora o slot é liberado tanto ao
-- cancelar quanto ao concluir; o registro do agendamento continua no
-- banco normalmente (nada é apagado), só deixa de contar como ocupante
-- daquele idHorario.
--
-- Script idempotente: pode ser executado mais de uma vez sem erro.

USE alexbarber;

-- 1) Remove a unique key atual, se existir
SET @indice_existe := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'Agendamentos'
      AND INDEX_NAME   = 'uk_horario_ativo_unico'
);

SET @sql := IF(
    @indice_existe > 0,
    'ALTER TABLE Agendamentos DROP INDEX uk_horario_ativo_unico;',
    'SELECT "Índice uk_horario_ativo_unico já não existe — nada a fazer." AS aviso;'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2) Remove a coluna gerada atual, se existir (precisa recriar para
--    trocar a expressão — MySQL/MariaDB não deixam alterar o CASE de
--    uma coluna gerada com ALTER ... MODIFY em todas as versões)
SET @coluna_existe := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'Agendamentos'
      AND COLUMN_NAME  = 'idHorario_ativo'
);

SET @sql := IF(
    @coluna_existe > 0,
    'ALTER TABLE Agendamentos DROP COLUMN idHorario_ativo;',
    'SELECT "Coluna idHorario_ativo já não existe — nada a fazer." AS aviso;'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3) Recria a coluna gerada com a nova regra: só conta como "ocupando"
--    o horário quando o agendamento está agendado ou confirmado.
ALTER TABLE Agendamentos
    ADD COLUMN idHorario_ativo INT
    GENERATED ALWAYS AS (CASE WHEN Status IN ('agendado', 'confirmado') THEN idHorario ELSE NULL END) STORED
    AFTER idHorario;

-- 4) Recria a unique key sobre a coluna gerada
ALTER TABLE Agendamentos ADD UNIQUE KEY uk_horario_ativo_unico (idHorario_ativo);

-- atualizacao_unique_horario.sql
-- Corrige o erro "SQLSTATE[23000]... Duplicate entry 'X' for key
-- 'uk_horario_unico'" ao tentar agendar em um horário que já teve um
-- agendamento CANCELADO anteriormente.
--
-- Causa: UNIQUE KEY uk_horario_unico (idHorario) impedia qualquer segunda
-- linha com o mesmo idHorario, mesmo quando a primeira estava cancelada
-- (Agendamentos.Status = 'cancelado'). O agendamento_cancelar.php libera o
-- horário (Horario.disponivel = 1) mas não apaga a linha cancelada, então
-- a constraint antiga barrava o reagendamento mesmo sendo uma operação
-- válida.
--
-- Correção: substitui a unique key simples por uma unique key sobre uma
-- coluna gerada que só recebe o idHorario quando o agendamento NÃO está
-- cancelado (fica NULL quando cancelado). Como o MySQL/MariaDB não conta
-- NULLs como duplicados em unique index, múltiplos cancelados no mesmo
-- horário passam a ser permitidos, e continua garantido que só existe
-- 1 agendamento ATIVO por horário.
--
-- Script idempotente: pode ser executado mais de uma vez sem erro.

USE alexbarber;

-- 1) Remove a unique key antiga, se existir
SET @indice_existe := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'Agendamentos'
      AND INDEX_NAME   = 'uk_horario_unico'
);

SET @sql := IF(
    @indice_existe > 0,
    'ALTER TABLE Agendamentos DROP INDEX uk_horario_unico;',
    'SELECT "Índice uk_horario_unico já não existe — nada a fazer." AS aviso;'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2) Cria a coluna gerada, se ainda não existir
SET @coluna_existe := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'Agendamentos'
      AND COLUMN_NAME  = 'idHorario_ativo'
);

SET @sql := IF(
    @coluna_existe = 0,
    'ALTER TABLE Agendamentos
        ADD COLUMN idHorario_ativo INT
        GENERATED ALWAYS AS (CASE WHEN Status <> ''cancelado'' THEN idHorario ELSE NULL END) STORED
        AFTER idHorario;',
    'SELECT "Coluna idHorario_ativo já existe — nada a fazer." AS aviso;'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3) Cria a nova unique key sobre a coluna gerada, se ainda não existir
SET @indice_novo_existe := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'Agendamentos'
      AND INDEX_NAME   = 'uk_horario_ativo_unico'
);

SET @sql := IF(
    @indice_novo_existe = 0,
    'ALTER TABLE Agendamentos ADD UNIQUE KEY uk_horario_ativo_unico (idHorario_ativo);',
    'SELECT "Índice uk_horario_ativo_unico já existe — nada a fazer." AS aviso;'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

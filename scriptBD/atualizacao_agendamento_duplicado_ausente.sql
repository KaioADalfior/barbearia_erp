-- atualizacao_agendamento_duplicado_ausente.sql
-- Colunas e valor de status que o código já usa (agendamento "duplicado" —
-- dois horários vinculados para um atendimento — e status "ausente"), com
-- migração versionada em scriptBD/. Versão IDEMPOTENTE de
-- add_agendamentos_duplicado_ausente.sql: só altera o que ainda falta,
-- pode ser executada várias vezes e não apaga nem reescreve dados.

USE alexbarber;

SET @t := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Agendamentos' AND COLUMN_NAME = 'grupo_agendamento');
SET @sql := IF(@t = 0, 'ALTER TABLE Agendamentos ADD COLUMN grupo_agendamento CHAR(32) NULL AFTER gerado_automaticamente, ADD INDEX idx_grupo_agendamento (grupo_agendamento)', 'SELECT "grupo_agendamento ja existe" AS status');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @t := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Agendamentos' AND COLUMN_NAME = 'eh_principal_agendamento');
SET @sql := IF(@t = 0, 'ALTER TABLE Agendamentos ADD COLUMN eh_principal_agendamento TINYINT(1) NOT NULL DEFAULT 1', 'SELECT "eh_principal_agendamento ja existe" AS status');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @t := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Agendamentos' AND COLUMN_NAME = 'cobranca_duplicado');
SET @sql := IF(@t = 0, 'ALTER TABLE Agendamentos ADD COLUMN cobranca_duplicado VARCHAR(10) NULL', 'SELECT "cobranca_duplicado ja existe" AS status');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Status 'ausente' (Cliente Ausente): acrescenta o valor ao ENUM só se faltar.
SET @t := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Agendamentos' AND COLUMN_NAME = 'Status' AND COLUMN_TYPE LIKE '%''ausente''%');
SET @sql := IF(@t = 0, 'ALTER TABLE Agendamentos MODIFY COLUMN Status ENUM(''agendado'',''confirmado'',''concluido'',''cancelado'',''ausente'') NOT NULL DEFAULT ''agendado''', 'SELECT "Status ja inclui ausente" AS status');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

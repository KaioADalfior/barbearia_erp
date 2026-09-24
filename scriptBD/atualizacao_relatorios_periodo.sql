-- atualizacao_relatorios_periodo.sql
-- Adiciona o novo tipo de relatório "periodo" (intervalo de datas escolhido
-- livremente pelo barbeiro, em vez de uma data de referência única) ao
-- ENUM da coluna `tipo` em FinanceiroRelatorios.
--
-- Necessário porque a tabela já existia com
-- ENUM('diario','semanal','mensal','anual') em bancos criados antes desta
-- atualização — o CREATE TABLE IF NOT EXISTS de atualizacao_relatorios.sql
-- não altera colunas de tabelas já existentes.
--
-- Pode ser executado com segurança mais de uma vez (idempotente).

USE alexbarber;

SET @tabela_existe := (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'FinanceiroRelatorios'
);

SET @enum_ja_atualizado := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'FinanceiroRelatorios'
      AND COLUMN_NAME  = 'tipo'
      AND COLUMN_TYPE  = "enum('diario','semanal','mensal','anual','periodo')"
);

SET @sql := IF(
    @tabela_existe = 0,
    'SELECT "Tabela FinanceiroRelatorios nao existe ainda — rode primeiro atualizacao_relatorios.sql." AS status',
    IF(
        @enum_ja_atualizado = 0,
        'ALTER TABLE FinanceiroRelatorios MODIFY COLUMN tipo ENUM(''diario'',''semanal'',''mensal'',''anual'',''periodo'') NOT NULL',
        'SELECT "Coluna tipo ja inclui periodo, nada a fazer." AS status'
    )
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- atualizacao_servico_ativo.sql
-- Corrige o erro "Unknown column 'ativo' in 'field list'" na tela de
-- Serviços (pages/servico_listar.php e scripts servico_*.php).
-- A tabela Servico foi criada originalmente sem a coluna "ativo", usada
-- para inativar/reativar um serviço sem excluí-lo.
--
-- Este script adiciona a coluna somente se ela ainda não existir, então
-- pode ser executado com segurança mesmo mais de uma vez.

USE alexbarber;

SET @coluna_existe := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'Servico'
      AND COLUMN_NAME  = 'ativo'
);

SET @sql := IF(
    @coluna_existe = 0,
    'ALTER TABLE Servico ADD COLUMN ativo TINYINT(1) NOT NULL DEFAULT 1 AFTER valor;',
    'SELECT "Coluna ativo já existe em Servico — nada a fazer." AS aviso;'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Garante que nenhum serviço já cadastrado fique com ativo NULL
UPDATE Servico SET ativo = 1 WHERE ativo IS NULL;

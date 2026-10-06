-- Foto opcional nos serviços (ex.: foto de um degradê).
-- O arquivo da imagem fica em assets/uploads/servicos/; o banco guarda
-- somente o nome do arquivo.
--
-- O sistema tenta criar esta coluna sozinho no primeiro acesso à tela de
-- Serviços; rode este script manualmente apenas se o usuário do banco não
-- tiver permissão de ALTER TABLE. Pode ser executado mais de uma vez.

SET @existe := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Servico' AND COLUMN_NAME = 'foto'
);
SET @sql := IF(@existe = 0,
    'ALTER TABLE Servico ADD COLUMN foto VARCHAR(255) NULL AFTER ativo',
    'SELECT ''Coluna Servico.foto ja existe'' AS aviso');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- atualizacao_historico_fiado.sql
-- Liga ClienteHistorico a FinanceiroLancamentos (coluna idLancamento), para
-- que a conclusão de um agendamento como FIADO registre automaticamente uma
-- entrada na aba "Histórico" do cliente (Clientes/paginas/cliente_listar.php),
-- e o recebimento do fiado (Financeiro > Fiados > Receber) marque essa mesma
-- entrada como quitada (PAGO) — sem precisar duplicar lógica/registro.
--
-- Pode ser executado com segurança mais de uma vez (idempotente).

USE alexbarber;

-- 1) Coluna idLancamento, se ainda não existir
SET @coluna_existe := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'ClienteHistorico'
      AND COLUMN_NAME  = 'idLancamento'
);

SET @sql := IF(
    @coluna_existe = 0,
    'ALTER TABLE ClienteHistorico ADD COLUMN idLancamento INT NULL AFTER id_barbeiro;',
    'SELECT "Coluna idLancamento já existe em ClienteHistorico — nada a fazer." AS aviso;'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2) Foreign key para FinanceiroLancamentos, se ainda não existir
SET @fk_existe := (
    SELECT COUNT(*)
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'ClienteHistorico'
      AND COLUMN_NAME  = 'idLancamento'
      AND REFERENCED_TABLE_NAME = 'FinanceiroLancamentos'
);

SET @sql := IF(
    @fk_existe = 0,
    'ALTER TABLE ClienteHistorico ADD CONSTRAINT fk_historico_lancamento FOREIGN KEY (idLancamento) REFERENCES FinanceiroLancamentos(idLancamento);',
    'SELECT "FK fk_historico_lancamento já existe — nada a fazer." AS aviso;'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

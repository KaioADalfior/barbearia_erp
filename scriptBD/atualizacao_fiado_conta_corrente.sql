-- atualizacao_fiado_conta_corrente.sql
-- "Conta de fiado única por cliente": permite juntar todos os cortes fiados
-- de um mesmo cliente em um único saldo devedor (em vez de um lançamento
-- avulso por corte) e receber um valor livre, menor que o total devido
-- (pagamento parcial), mantendo o restante em aberto.
--
-- 1) FinanceiroLancamentos ganha a coluna valor_pago: quanto já foi pago
--    daquele lançamento. Saldo em aberto = valor - valor_pago.
--    - Lançamentos já 'pago' são retroativamente marcados como quitados
--      (valor_pago = valor), pois sempre foram pagos de uma vez só.
--    - Lançamentos 'pendente' (fiado em aberto) ficam com valor_pago = 0.
-- 2) ClienteHistorico ganha o tipo 'pagamento', usado para registrar
--    automaticamente cada recebimento (total ou parcial) de fiado.
--
-- Pode ser executado com segurança mais de uma vez (idempotente).

USE alexbarber;

-- 1) Coluna valor_pago, se ainda não existir
SET @coluna_existe := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'FinanceiroLancamentos'
      AND COLUMN_NAME  = 'valor_pago'
);

SET @sql := IF(
    @coluna_existe = 0,
    'ALTER TABLE FinanceiroLancamentos ADD COLUMN valor_pago DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER valor;',
    'SELECT "Coluna valor_pago já existe em FinanceiroLancamentos — nada a fazer." AS aviso;'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2) Backfill: lançamentos já pagos (status='pago') são considerados
--    quitados integralmente. Só atualiza quem ainda está com valor_pago = 0
--    (não sobrescreve se a migration já rodou antes e algum pagamento
--    parcial já tiver sido registrado nesse meio tempo).
UPDATE FinanceiroLancamentos
SET valor_pago = valor
WHERE status = 'pago' AND valor_pago = 0;

-- 3) Tipo 'pagamento' no ENUM de ClienteHistorico, se ainda não existir
SET @tipo_existe := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'ClienteHistorico'
      AND COLUMN_NAME  = 'tipo'
      AND COLUMN_TYPE LIKE '%''pagamento''%'
);

SET @sql := IF(
    @tipo_existe = 0,
    "ALTER TABLE ClienteHistorico MODIFY COLUMN tipo ENUM('fiado','observacao','atendimento','outro','pagamento') NOT NULL DEFAULT 'observacao';",
    'SELECT "Tipo \'pagamento\' já existe em ClienteHistorico.tipo — nada a fazer." AS aviso;'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

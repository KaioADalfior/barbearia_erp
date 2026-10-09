-- atualizacao_relatorios_automaticos.sql
-- Estrutura dos RELATÓRIOS AUTOMÁTICOS (geração diária à 00h, America/Sao_Paulo).
--
--   * FinanceiroRelatorios.origem      'manual' | 'automatico' (relatórios antigos = 'manual')
--   * FinanceiroRelatorios.chave_auto  "b<id>|<tipo>|<inicio>|<fim>" — ÚNICA: impede o mesmo
--                                      profissional/tipo/período de ser gerado duas vezes
--   * FinanceiroRelatoriosAuto         controle da rotina: sucesso/falha, tentativas e motivo
--
-- É 100% ADITIVA (não remove nem altera dados). Idempotente: pode rodar mais de uma vez.
-- O próprio sistema também cria isto sozinho na primeira execução (RelatorioService::garantirEstrutura);
-- rode este script se o usuário do banco da aplicação não tiver permissão de ALTER/CREATE.
-- Requer que atualizacao_relatorios.sql já tenha sido executado.

USE alexbarber;

-- ---- coluna origem
SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'FinanceiroRelatorios' AND COLUMN_NAME = 'origem') = 0,
    'ALTER TABLE FinanceiroRelatorios ADD COLUMN origem ENUM(''manual'',''automatico'') NOT NULL DEFAULT ''manual''',
    'SELECT "coluna origem ja existe" AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---- coluna chave_auto
SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'FinanceiroRelatorios' AND COLUMN_NAME = 'chave_auto') = 0,
    'ALTER TABLE FinanceiroRelatorios ADD COLUMN chave_auto VARCHAR(80) NULL',
    'SELECT "coluna chave_auto ja existe" AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---- índice único (NULL não conflita: relatórios manuais continuam livres)
SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'FinanceiroRelatorios' AND INDEX_NAME = 'uk_relatorio_auto') = 0,
    'ALTER TABLE FinanceiroRelatorios ADD UNIQUE INDEX uk_relatorio_auto (chave_auto)',
    'SELECT "indice uk_relatorio_auto ja existe" AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---- controle da rotina automática
CREATE TABLE IF NOT EXISTS FinanceiroRelatoriosAuto (
    chave             VARCHAR(80) NOT NULL PRIMARY KEY,
    id_barbeiro       INT NOT NULL,
    tipo              VARCHAR(10) NOT NULL,
    data_inicio       DATE NOT NULL,
    data_fim          DATE NOT NULL,
    status            ENUM('ok','erro') NOT NULL DEFAULT 'erro',
    tentativas        INT NOT NULL DEFAULT 0,
    ultima_tentativa  DATETIME NULL,
    mensagem          VARCHAR(255) NULL,
    idRelatorio       INT NULL,
    atualizado_em     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_rel_auto_barbeiro (id_barbeiro, status, data_fim)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

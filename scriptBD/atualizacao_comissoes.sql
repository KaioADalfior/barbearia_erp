-- scriptBD/atualizacao_comissoes.sql
-- Migração idempotente: Proprietário / Funcionário e Comissões.
--
-- O próprio sistema já cria isto sozinho no primeiro acesso depois do deploy
-- (includes/ComissaoService.php::garantirEstrutura, chamado por
-- config/config.php). Este arquivo existe como alternativa manual, caso o
-- usuário do banco da hospedagem não tenha permissão de ALTER/CREATE TABLE.
-- Pode ser executado mais de uma vez (a coluna só é adicionada se faltar).

-- 1) Tipo de acesso do barbeiro. DEFAULT 'proprietario': quem já existia
--    continua com acesso total; o administrador marca como 'funcionario'
--    em Barbeiros > Editar.
SET @existe := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Barbeiro' AND COLUMN_NAME = 'tipo_usuario');
SET @sql := IF(@existe = 0,
    "ALTER TABLE Barbeiro ADD COLUMN tipo_usuario VARCHAR(15) NOT NULL DEFAULT 'proprietario'",
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2) Configurações gerais (chave/valor). Hoje guarda 'comissao_percentual'
--    (porcentagem aplicada nos PRÓXIMOS atendimentos de funcionários).
CREATE TABLE IF NOT EXISTS ConfiguracaoSistema (
    chave VARCHAR(60) NOT NULL PRIMARY KEY,
    valor VARCHAR(255) NOT NULL,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3) Comissões: UMA por atendimento (UNIQUE idAgendamento). Guarda a
--    porcentagem aplicada no momento da conclusão, então mudar a
--    configuração depois nunca altera comissões antigas.
--    status: 'pendente' | 'pago' | 'cancelado' (nada é apagado).
CREATE TABLE IF NOT EXISTS Comissoes (
    idComissao      INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    idAgendamento   INT NOT NULL,
    idLancamento    INT NULL,
    id_barbeiro     INT NOT NULL,
    idCliente       INT NULL,
    cliente_nome    VARCHAR(120) NULL,
    idServico       INT NULL,
    servico_nome    VARCHAR(190) NULL,
    forma_pagamento VARCHAR(100) NULL,
    data_servico    DATE NOT NULL,
    hora_servico    TIME NULL,
    valor_servico   DECIMAL(10,2) NOT NULL,
    percentual      DECIMAL(5,2) NOT NULL,
    valor_comissao  DECIMAL(10,2) NOT NULL,
    status          VARCHAR(10) NOT NULL DEFAULT 'pendente',
    pago_em         DATETIME NULL,
    cancelado_em    DATETIME NULL,
    historico       TEXT NULL,
    criado_em       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_comissao_agendamento (idAgendamento),
    KEY idx_comissao_barbeiro_status (id_barbeiro, status),
    KEY idx_comissao_data (data_servico),
    KEY idx_comissao_lancamento (idLancamento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

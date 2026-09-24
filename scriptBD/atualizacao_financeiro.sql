-- atualizacao_financeiro.sql
-- Cria o backend real do módulo Financeiro (até então 100% demonstrativo,
-- ver comentários em Financeiro/paginas/financeiro_baixa.php e
-- financeiro_dashboard.php), e dá suporte às novas regras de:
--   1) Restrição de 1 agendamento ativo por cliente/dia;
--   2) Conclusão de agendamento com lançamento financeiro automático;
--   3) Fiado (lançamento financeiro com Status = PENDENTE).
--
-- Uma única tabela (FinanceiroLancamentos) alimenta as 3 telas do Financeiro
-- (Dashboard, Cadastrar Baixa/Extrato e Fiados), diferenciando a origem
-- (manual x agendamento) e o status (pago x pendente). Isso evita duplicar
-- estrutura/consulta entre as telas.
--
-- Pode ser executado com segurança mais de uma vez (idempotente).

USE alexbarber;

CREATE TABLE IF NOT EXISTS FinanceiroLancamentos (
    idLancamento    INT AUTO_INCREMENT PRIMARY KEY,
    id_barbeiro     INT NOT NULL,
    idAgendamento   INT NULL,
    idCliente       INT NULL,

    -- 'agendamento' = criado automaticamente ao concluir um agendamento
    -- (fiado ou não). 'manual' = lançado via Financeiro > Cadastrar Baixa.
    origem          ENUM('manual','agendamento') NOT NULL DEFAULT 'manual',
    tipo            ENUM('entrada','saida') NOT NULL DEFAULT 'entrada',

    titulo          VARCHAR(120) NOT NULL,
    descricao       VARCHAR(300) NULL,
    quantidade      INT NULL,
    valor           DECIMAL(10,2) NOT NULL,

    -- Uma ou mais formas de pagamento, mesmas opções do componente de
    -- Cadastrar Baixa (pix, credito, debito, dinheiro, boleto), separadas
    -- por vírgula — mesmo formato do campo forma[] já usado ali.
    forma_pagamento VARCHAR(120) NULL,

    -- PAGO entra nas receitas/saldo/dashboard normalmente. PENDENTE é o
    -- fiado: fica fora do cálculo de receitas até ser recebido.
    status          ENUM('pago','pendente') NOT NULL DEFAULT 'pago',

    data            DATE NOT NULL,
    criado_em       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    pago_em         TIMESTAMP NULL,

    FOREIGN KEY (id_barbeiro)   REFERENCES Barbeiro(id_barbeiro),
    FOREIGN KEY (idAgendamento) REFERENCES Agendamentos(idAgendamento),
    FOREIGN KEY (idCliente)     REFERENCES Cliente(idCliente),

    -- No máximo um lançamento financeiro por agendamento (evita duplicar
    -- ao clicar "Concluir" mais de uma vez). NULL (lançamentos manuais)
    -- pode se repetir livremente — MySQL não aplica UNIQUE entre NULLs.
    UNIQUE KEY uk_lancamento_agendamento (idAgendamento),

    INDEX idx_financeiro_status (id_barbeiro, status, data),
    INDEX idx_financeiro_data (id_barbeiro, tipo, data)
);

-- ---------------------------------------------------------------------
-- Índice de apoio para a regra "1 agendamento por cliente por dia": a
-- verificação (Agendamentos/scripts/agendamento_salvar.php) filtra por
-- idCliente + Data + Status, então um índice composto evita varredura
-- completa da tabela conforme a base cresce.
-- ---------------------------------------------------------------------
SET @indice_existe := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'Agendamentos'
      AND INDEX_NAME   = 'idx_cliente_data_status'
);

SET @sql := IF(
    @indice_existe = 0,
    'ALTER TABLE Agendamentos ADD INDEX idx_cliente_data_status (idCliente, Data, Status);',
    'SELECT "Índice idx_cliente_data_status já existe em Agendamentos — nada a fazer." AS aviso;'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

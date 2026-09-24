-- ScriptBancoMYSQL.sql
-- Script COMPLETO de criação do banco "alexbarber" (instalação do zero).
-- Consolida a estrutura original + todas as migrações incrementais já
-- aplicadas (scriptBD/atualizacao_*.sql) + a nova tabela FinanceiroLancamentos
-- (módulo Financeiro / Fiados). Se você já tem o banco criado e só quer
-- adicionar o módulo Financeiro/Fiados, rode apenas
-- scriptBD/atualizacao_financeiro.sql — não precisa recriar tudo.

DROP DATABASE IF EXISTS alexbarber;
CREATE DATABASE alexbarber;
USE alexbarber;

-- ========== Administrador ==========
CREATE TABLE Administrador (
    id_Admin INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    login VARCHAR(50) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL
);

INSERT INTO Administrador (nome, login, senha) VALUES ("Admin Admin", "admin", "Kaio@123");

-- ========== Barbeiro ==========
CREATE TABLE Barbeiro (
    id_barbeiro INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    login VARCHAR(50) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    telefone VARCHAR(20) NOT NULL,
    foto VARCHAR(255) NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO Barbeiro (nome, login, senha, telefone) VALUES ("Barbeiro Barbeiro", "barbeiro", "Barbeiro@123", "");

-- ========== Cliente ==========
CREATE TABLE Cliente (
    idCliente INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    telefone VARCHAR(20) NOT NULL,
    email VARCHAR(120) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ========== Serviço ==========
-- Coluna "ativo" incorporada aqui (originalmente adicionada via
-- scriptBD/atualizacao_servico_ativo.sql), usada para inativar/reativar
-- um serviço sem excluí-lo.
CREATE TABLE Servico (
    idServico INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(80) NOT NULL,
    duracao_minutos INT NOT NULL DEFAULT 40,
    valor DECIMAL(10,2) NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1
);

-- Serviços padrão (originalmente scriptBD/atualizacao_servicos.sql),
-- necessários para o formulário de agendamento.
INSERT INTO Servico (nome, duracao_minutos, valor) VALUES
    ('Corte', 40, 35.00),
    ('Barba', 20, 25.00),
    ('Corte + Barba', 60, 55.00),
    ('Sobrancelha', 15, 15.00);

-- ========== Horário ==========
-- Representa um horário específico de um barbeiro (é o que alimenta
-- o calendário do agendar.php). Cada linha = 1 slot de agenda.
CREATE TABLE Horario (
    idHorario INT AUTO_INCREMENT PRIMARY KEY,
    id_barbeiro INT NOT NULL,
    data DATE NOT NULL,
    hora TIME NOT NULL,
    disponivel BOOLEAN NOT NULL DEFAULT TRUE,
    FOREIGN KEY (id_barbeiro) REFERENCES Barbeiro(id_barbeiro),
    UNIQUE KEY uk_barbeiro_data_hora (id_barbeiro, data, hora)
);

-- ========== Agendamentos ==========
CREATE TABLE Agendamentos (
    idAgendamento INT AUTO_INCREMENT PRIMARY KEY,
    idCliente INT NOT NULL,
    idServico INT NOT NULL,
    idHorario INT NOT NULL,
    idHorario_ativo INT GENERATED ALWAYS AS (CASE WHEN Status IN ('agendado', 'confirmado') THEN idHorario ELSE NULL END) STORED,
    Data DATE NOT NULL,
    Valor DECIMAL(10,2) NOT NULL,
    IncluirBarba BOOLEAN NOT NULL DEFAULT FALSE,
    Observacao VARCHAR(255) NULL,

    -- Agendamento de Fidelidade: recorrência automática por SEMANAS (nunca
    -- dias corridos, pois isso poderia deslocar o dia da semana), por até
    -- 1 ano a partir do primeiro agendamento (ver includes/FidelidadeService.php).
    -- Códigos: '1' = toda semana, '2' = a cada 2 semanas, '3' = a cada 3
    -- semanas, '4' = a cada 4 semanas — exibidos na interface com rótulo
    -- comercial ("7 em 7 dias" / "15 em 15 dias" / "20 em 20 dias" /
    -- "30 em 30 dias"), mas o cálculo real é sempre em semanas.
    -- Cada ocorrência é uma linha independente; "grupo_recorrencia" agrupa
    -- todas as ocorrências da mesma sequência sem impedir cancelar/editar
    -- uma ocorrência isolada no futuro.
    fidelidade ENUM('1','2','3','4') NULL,
    grupo_recorrencia CHAR(32) NULL,
    gerado_automaticamente TINYINT(1) NOT NULL DEFAULT 0,

    Status ENUM('agendado','confirmado','concluido','cancelado') NOT NULL DEFAULT 'agendado',

    FOREIGN KEY (idCliente) REFERENCES Cliente(idCliente),
    FOREIGN KEY (idServico) REFERENCES Servico(idServico),
    FOREIGN KEY (idHorario) REFERENCES Horario(idHorario),

    -- impede dois agendamentos ATIVOS (agendado/confirmado) usando o mesmo horário
    UNIQUE KEY uk_horario_ativo_unico (idHorario_ativo)
);

-- Índice de apoio para a regra "1 agendamento ativo por cliente por dia"
-- (Agendamentos/scripts/agendamento_salvar.php via FinanceiroService).
CREATE INDEX idx_cliente_data_status ON Agendamentos (idCliente, Data, Status);

-- Índice de apoio para localizar/cancelar toda uma sequência de fidelidade.
CREATE INDEX idx_grupo_recorrencia ON Agendamentos (grupo_recorrencia);

-- ========== Financeiro (Dashboard / Cadastrar Baixa / Extrato / Fiados) ==========
-- Uma única tabela alimenta as 3 telas do módulo Financeiro, diferenciando
-- a origem (manual x agendamento) e o status (pago x pendente/fiado).
CREATE TABLE FinanceiroLancamentos (
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

    -- Quanto já foi pago deste lançamento. Para lançamentos 'pago' é igual
    -- a `valor` (sempre quitados de uma vez). Para fiado ('pendente') pode
    -- ficar entre 0 e `valor` — o saldo em aberto é sempre `valor - valor_pago`.
    -- Isso permite recebimento parcial: o barbeiro recebe menos do que o
    -- cliente deve e o restante continua pendente até ser quitado.
    valor_pago      DECIMAL(10,2) NOT NULL DEFAULT 0,

    -- Uma ou mais formas de pagamento (pix, credito, debito, dinheiro,
    -- boleto), separadas por vírgula — mesmas opções do componente
    -- reutilizável de Forma de Pagamento usado em todo o sistema.
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

-- ========== Histórico do Cliente (aba "Histórico" no detalhe do cliente) ==========
-- Anotações livres feitas pelo barbeiro sobre o cliente: FIADO (valor em
-- aberto), observação, atendimento avulso, etc. Quando o agendamento é
-- concluído como FIADO, uma entrada é criada aqui automaticamente
-- (idLancamento aponta para o lançamento em FinanceiroLancamentos), e o
-- recebimento do fiado marca essa mesma entrada como quitada (PAGO).
CREATE TABLE ClienteHistorico (
    idHistorico INT AUTO_INCREMENT PRIMARY KEY,
    idCliente INT NOT NULL,
    id_barbeiro INT NULL,
    -- Liga a entrada de histórico ao lançamento financeiro de origem (quando
    -- gerada automaticamente pela conclusão de um agendamento como FIADO).
    -- NULL para anotações livres feitas manualmente pelo barbeiro.
    idLancamento INT NULL,
    -- 'pagamento' = registrado automaticamente sempre que o barbeiro recebe
    -- um fiado (total ou parcialmente) em Financeiro > Fiados.
    tipo ENUM('fiado','observacao','atendimento','outro','pagamento') NOT NULL DEFAULT 'observacao',
    descricao VARCHAR(500) NOT NULL,
    valor DECIMAL(10,2) NULL,
    quitado TINYINT(1) NOT NULL DEFAULT 0,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (idCliente) REFERENCES Cliente(idCliente),
    FOREIGN KEY (id_barbeiro) REFERENCES Barbeiro(id_barbeiro),
    FOREIGN KEY (idLancamento) REFERENCES FinanceiroLancamentos(idLancamento)
);

-- ========== Uploads / Versões (painel Admin -> Configurações) ==========
-- Cada linha representa uma versão/upload lançado pelo desenvolvedor (admin).
-- A página pages/uploads.php (pública, acessada a partir do login) exibe esses
-- registros para os barbeiros acompanharem as novidades de cada versão.
CREATE TABLE UploadVersao (
    idUpload INT AUTO_INCREMENT PRIMARY KEY,
    versao VARCHAR(30) NOT NULL UNIQUE,
    descricao VARCHAR(500) NOT NULL,
    data_hora DATETIME NOT NULL,
    id_admin INT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_admin) REFERENCES Administrador(id_Admin)
);

INSERT INTO Cliente (nome, telefone, email, ativo)
SELECT
    CONCAT(
        ELT((n % 20) + 1,
            'João','Mario','José','Gustavo','Carlos','Paulo','Lucas','Pedro','Marcos','Juliano',
            'Fernando','Patrício','Gabriel','Rafael','Josileno','Camilo','Bruno','Ricardo','Felipe','Luiz'
        ),
        ' ',
        ELT(((n DIV 20) % 20) + 1,
            'Silva','Souza','Oliveira','Santos','Pereira','Costa','Rodrigues','Almeida','Nascimento','Lima',
            'Gomes','Martins','Rocha','Barbosa','Ribeiro','Carvalho','Ferreira','Dias','Teixeira','Araujo'
        )
    ) AS nome,
    CONCAT(
        '(',
        LPAD((n % 27) + 11, 2, '0'),
        ') 9',
        LPAD(10000000 + n, 8, '0')
    ) AS telefone,
    CONCAT('cliente', n, '@teste.com') AS email,
    1 AS ativo
FROM (
    SELECT
        a.N + b.N * 10 + 1 AS n
    FROM
        (SELECT 0 N UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL
         SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) a
    CROSS JOIN
        (SELECT 0 N UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL
         SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) b
) t
WHERE n <= 100;



USE alexbarber;

-- 1) Remove a unique key atual, se existir
SET @indice_existe := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'Agendamentos'
      AND INDEX_NAME   = 'uk_horario_ativo_unico'
);

SET @sql := IF(
    @indice_existe > 0,
    'ALTER TABLE Agendamentos DROP INDEX uk_horario_ativo_unico;',
    'SELECT "Índice uk_horario_ativo_unico já não existe — nada a fazer." AS aviso;'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2) Remove a coluna gerada atual, se existir (precisa recriar para
--    trocar a expressão — MySQL/MariaDB não deixam alterar o CASE de
--    uma coluna gerada com ALTER ... MODIFY em todas as versões)
SET @coluna_existe := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'Agendamentos'
      AND COLUMN_NAME  = 'idHorario_ativo'
);

SET @sql := IF(
    @coluna_existe > 0,
    'ALTER TABLE Agendamentos DROP COLUMN idHorario_ativo;',
    'SELECT "Coluna idHorario_ativo já não existe — nada a fazer." AS aviso;'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3) Recria a coluna gerada com a nova regra: só conta como "ocupando"
--    o horário quando o agendamento está agendado ou confirmado.
ALTER TABLE Agendamentos
    ADD COLUMN idHorario_ativo INT
    GENERATED ALWAYS AS (CASE WHEN Status IN ('agendado', 'confirmado') THEN idHorario ELSE NULL END) STORED
    AFTER idHorario;

-- 4) Recria a unique key sobre a coluna gerada
ALTER TABLE Agendamentos ADD UNIQUE KEY uk_horario_ativo_unico (idHorario_ativo);
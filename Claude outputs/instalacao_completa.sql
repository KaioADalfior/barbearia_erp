-- =============================================================================
-- instalacao_completa.sql
-- Script único de instalação do zero do banco "alexbarber".
--
-- Por que este arquivo existe (em vez de usar scriptBD/ScriptBancoMYSQL.sql
-- direto): ScriptBancoMYSQL.sql se apresenta como "consolida TODAS as
-- migrações incrementais já aplicadas", mas na prática está faltando duas
-- tabelas que o sistema também exige em tempo de execução:
--
--   1) LoginTentativas  (scriptBD/atualizacao_login_lockout.sql)
--      Toda tentativa de login passa por includes/LoginThrottle.php, que
--      faz SELECT/INSERT/DELETE nesta tabela. Sem ela, a PRIMEIRA tentativa
--      de login (certa ou errada) já derruba com erro de SQL
--      ("Table 'alexbarber.LoginTentativas' doesn't exist").
--
--   2) FinanceiroRelatorios (scriptBD/atualizacao_relatorios.sql +
--      atualizacao_relatorios_periodo.sql)
--      Usada por Financeiro/scripts/relatorio_gerar.php e
--      Financeiro/paginas/financeiro_relatorios.php. Sem ela, gerar/listar
--      relatórios em PDF quebra.
--
-- Este script contém a mesma estrutura final do ScriptBancoMYSQL.sql (já
-- incorporando todas as colunas que suas migrações incrementais adicionam:
-- Barbeiro.foto, Servico.ativo, Agendamentos.fidelidade/grupo_recorrencia/
-- gerado_automaticamente, FinanceiroLancamentos.valor_pago,
-- ClienteHistorico.idLancamento + tipo 'pagamento') MAIS as duas tabelas
-- acima, para não faltar nada numa instalação nova.
--
-- COMO RODAR (banco já provisionado pelo Easypanel: "barbearia-padrao-bd"):
-- Este script NÃO cria nem apaga o banco de dados em si — o usuário
-- "barbearia_user" do Easypanel normalmente só tem permissão dentro do
-- banco que já foi criado pra ele, não CREATE/DROP DATABASE. Rode o script
-- já com esse banco selecionado, por exemplo:
--   mysql -h <host> -P 3306 -u barbearia_user -p barbearia-padrao-bd < instalacao_completa.sql
-- ou, pelo phpMyAdmin/Adminer do Easypanel: selecione o banco
-- "barbearia-padrao-bd" na barra lateral e cole este script na aba SQL.
--
-- Seguro rodar mais de uma vez: os DROP TABLE abaixo limpam qualquer
-- tentativa anterior antes de recriar do zero.
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS FinanceiroRelatorios, LoginTentativas, ClienteHistorico,
    FinanceiroLancamentos, Agendamentos, UploadVersao, Horario, Servico,
    Cliente, Barbeiro, Administrador;
SET FOREIGN_KEY_CHECKS = 1;

-- ========== Administrador ==========
CREATE TABLE Administrador (
    id_Admin INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    login VARCHAR(50) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Login de teste padrão — TROQUE a senha antes de usar em produção real
-- com um cliente (ver aviso de segurança no final deste arquivo).
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO Barbeiro (nome, login, senha, telefone) VALUES ("Barbeiro Barbeiro", "barbeiro", "Barbeiro@123", "");

-- ========== Cliente ==========
CREATE TABLE Cliente (
    idCliente INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    telefone VARCHAR(20) NOT NULL,
    email VARCHAR(120) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== Serviço ==========
CREATE TABLE Servico (
    idServico INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(80) NOT NULL,
    duracao_minutos INT NOT NULL DEFAULT 40,
    valor DECIMAL(10,2) NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO Servico (nome, duracao_minutos, valor) VALUES
    ('Corte', 40, 35.00),
    ('Barba', 20, 25.00),
    ('Corte + Barba', 60, 55.00),
    ('Sobrancelha', 15, 15.00);

-- ========== Horário ==========
CREATE TABLE Horario (
    idHorario INT AUTO_INCREMENT PRIMARY KEY,
    id_barbeiro INT NOT NULL,
    data DATE NOT NULL,
    hora TIME NOT NULL,
    disponivel BOOLEAN NOT NULL DEFAULT TRUE,
    FOREIGN KEY (id_barbeiro) REFERENCES Barbeiro(id_barbeiro),
    UNIQUE KEY uk_barbeiro_data_hora (id_barbeiro, data, hora)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== Agendamentos ==========
-- idHorario_ativo já criada com a regra FINAL (após as migrações
-- atualizacao_unique_horario.sql + atualizacao_liberar_horario_concluido.sql):
-- só "ocupa" o horário enquanto o agendamento está agendado/confirmado.
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

    -- Agendamento de Fidelidade: recorrência automática por semanas
    -- (ver includes/FidelidadeService.php).
    fidelidade ENUM('1','2','3','4') NULL,
    grupo_recorrencia CHAR(32) NULL,
    gerado_automaticamente TINYINT(1) NOT NULL DEFAULT 0,

    Status ENUM('agendado','confirmado','concluido','cancelado') NOT NULL DEFAULT 'agendado',

    FOREIGN KEY (idCliente) REFERENCES Cliente(idCliente),
    FOREIGN KEY (idServico) REFERENCES Servico(idServico),
    FOREIGN KEY (idHorario) REFERENCES Horario(idHorario),

    UNIQUE KEY uk_horario_ativo_unico (idHorario_ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_cliente_data_status ON Agendamentos (idCliente, Data, Status);
CREATE INDEX idx_grupo_recorrencia ON Agendamentos (grupo_recorrencia);

-- ========== Financeiro (Dashboard / Cadastrar Baixa / Extrato / Fiados) ==========
CREATE TABLE FinanceiroLancamentos (
    idLancamento    INT AUTO_INCREMENT PRIMARY KEY,
    id_barbeiro     INT NOT NULL,
    idAgendamento   INT NULL,
    idCliente       INT NULL,

    origem          ENUM('manual','agendamento') NOT NULL DEFAULT 'manual',
    tipo            ENUM('entrada','saida') NOT NULL DEFAULT 'entrada',

    titulo          VARCHAR(120) NOT NULL,
    descricao       VARCHAR(300) NULL,
    quantidade      INT NULL,
    valor           DECIMAL(10,2) NOT NULL,

    -- Quanto já foi pago deste lançamento (saldo em aberto = valor - valor_pago).
    valor_pago      DECIMAL(10,2) NOT NULL DEFAULT 0,

    forma_pagamento VARCHAR(120) NULL,
    status          ENUM('pago','pendente') NOT NULL DEFAULT 'pago',

    data            DATE NOT NULL,
    criado_em       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    pago_em         TIMESTAMP NULL,

    FOREIGN KEY (id_barbeiro)   REFERENCES Barbeiro(id_barbeiro),
    FOREIGN KEY (idAgendamento) REFERENCES Agendamentos(idAgendamento),
    FOREIGN KEY (idCliente)     REFERENCES Cliente(idCliente),

    UNIQUE KEY uk_lancamento_agendamento (idAgendamento),

    INDEX idx_financeiro_status (id_barbeiro, status, data),
    INDEX idx_financeiro_data (id_barbeiro, tipo, data)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== Histórico do Cliente ==========
CREATE TABLE ClienteHistorico (
    idHistorico INT AUTO_INCREMENT PRIMARY KEY,
    idCliente INT NOT NULL,
    id_barbeiro INT NULL,
    idLancamento INT NULL,
    tipo ENUM('fiado','observacao','atendimento','outro','pagamento') NOT NULL DEFAULT 'observacao',
    descricao VARCHAR(500) NOT NULL,
    valor DECIMAL(10,2) NULL,
    quitado TINYINT(1) NOT NULL DEFAULT 0,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (idCliente) REFERENCES Cliente(idCliente),
    FOREIGN KEY (id_barbeiro) REFERENCES Barbeiro(id_barbeiro),
    FOREIGN KEY (idLancamento) REFERENCES FinanceiroLancamentos(idLancamento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== Uploads / Versões ==========
CREATE TABLE UploadVersao (
    idUpload INT AUTO_INCREMENT PRIMARY KEY,
    versao VARCHAR(30) NOT NULL UNIQUE,
    descricao VARCHAR(500) NOT NULL,
    data_hora DATETIME NOT NULL,
    id_admin INT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_admin) REFERENCES Administrador(id_Admin)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== Login Tentativas (faltava no ScriptBancoMYSQL.sql original) ==========
-- Ver includes/LoginThrottle.php — obrigatória para QUALQUER tentativa de login.
CREATE TABLE LoginTentativas (
    id        BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    chave     VARCHAR(191) NOT NULL COMMENT 'IP + login normalizado que tentou logar',
    sucesso   TINYINT(1) NOT NULL DEFAULT 0,
    criado_em DATETIME NOT NULL,
    INDEX idx_chave_criado (chave, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== Financeiro > Relatórios (faltava no ScriptBancoMYSQL.sql original) ==========
-- Ver Financeiro/scripts/relatorio_gerar.php + includes/RelatorioPdfBuilder.php.
-- PDF gerado é salvo direto no banco (arquivo_pdf LONGBLOB) — sem depender
-- de permissão de escrita em disco no host.
CREATE TABLE FinanceiroRelatorios (
    idRelatorio     INT AUTO_INCREMENT PRIMARY KEY,
    id_barbeiro     INT NOT NULL,

    tipo            ENUM('diario','semanal','mensal','anual','periodo') NOT NULL,
    data_inicio     DATE NOT NULL,
    data_fim        DATE NOT NULL,

    titulo          VARCHAR(150) NOT NULL,

    total_entradas  DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_saidas    DECIMAL(10,2) NOT NULL DEFAULT 0,
    saldo           DECIMAL(10,2) NOT NULL DEFAULT 0,
    qtd_lancamentos INT NOT NULL DEFAULT 0,

    nome_arquivo    VARCHAR(180) NOT NULL,
    tamanho_bytes   INT NOT NULL DEFAULT 0,
    arquivo_pdf     LONGBLOB NOT NULL,

    gerado_em       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (id_barbeiro) REFERENCES Barbeiro(id_barbeiro),

    INDEX idx_relatorios_barbeiro (id_barbeiro, gerado_em),
    INDEX idx_relatorios_tipo (id_barbeiro, tipo, data_inicio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== Dados de demonstração (100 clientes fictícios) ==========
-- Só para ter algo pra ver/testar nas telas de Clientes/Agendamentos.
-- Apague com "DELETE FROM Cliente WHERE email LIKE '%@teste.com';" antes
-- de entregar uma instalação de verdade a um cliente.
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

-- =============================================================================
-- AVISO DE SEGURANÇA
-- A senha de banco hardcoded em config/config.php (fallback do getenv) já
-- circulou em texto puro no código-fonte deste projeto. Ao subir num VPS
-- novo, gere uma senha nova e use SEMPRE as variáveis de ambiente
-- DB_HOST / DB_NAME / DB_USER / DB_PASS (ver seção Docker) — nunca a senha
-- antiga. As credenciais de admin/barbeiro acima ("Kaio@123"/"Barbeiro@123")
-- também são só para teste: troque-as antes de entregar a um cliente real.
-- =============================================================================

-- =============================================================================
-- instalacao_completa.sql
-- Script único de instalação do zero do banco (base genérica para revenda).
--
-- ORIGEM: reconstruído a partir de um dump real, extraído por você direto
-- do sistema "Alex Barbearia" em produção (TesteBarbearia_1.sql, 24/09/2026,
-- MySQL 9.7.2), depois de todas as correções aplicadas nesta conversa
-- (LoginTentativas, FinanceiroRelatorios, FinanceiroRecebimentos, colunas
-- de agendamento duplicado/ausente em Agendamentos, ListaEspera,
-- DiaBloqueado). Ou seja: isso não é mais uma reconstrução minha por
-- engenharia reversa do código — é a estrutura real, comprovadamente
-- funcionando em produção, só que limpa para uma instalação nova (sem os
-- dados de agendamentos/clientes/financeiro reais do Alex).
--
-- O que mudou em relação à versão anterior deste arquivo (para bater
-- exatamente com o dump real):
--   - Agendamentos.eh_principal_agendamento: NOT NULL DEFAULT 1 (antes eu
--     tinha deixado NULL sem default).
--   - Agendamentos.cobranca_duplicado: ENUM('um','dois') (antes eu tinha
--     usado VARCHAR(10) livre).
--   - DiaBloqueado: tem idDiaBloqueado (PK própria, autoincrement) e um
--     campo `motivo` (VARCHAR, opcional) que eu não tinha incluído; o
--     UNIQUE fica em (id_barbeiro, data), não mais na PK sozinha.
--   - ListaEspera: sem UNIQUE(id_barbeiro, idCliente) — a checagem de
--     "cliente já está na lista" já é feita em PHP antes do INSERT
--     (Agendamentos/scripts/listaespera_adicionar.php); no dump real não
--     há essa constraint no banco.
--   - FinanceiroRecebimentos: só tem FK para FinanceiroLancamentos (ON
--     DELETE CASCADE); sem FK própria para Barbeiro/Cliente.
--   - Collation: padronizei TODAS as tabelas em utf8mb4_0900_ai_ci (padrão
--     do próprio MySQL 8, usado em 13 das 14 tabelas do dump real). No
--     banco de produção atual, FinanceiroRecebimentos ficou em
--     utf8mb4_unicode_ci (porque o script que criei antes não especificou
--     collation) — funciona normalmente (chaves entre tabelas são sempre
--     INT), mas é uma inconsistência que só existe por causa desse detalhe
--     meu; numa instalação NOVA fica tudo já no mesmo padrão.
--
-- DADOS: propositalmente só o login padrão de Administrador (admin/
-- Kaio@123) — igual ao dump real, que veio limpo (sem clientes, serviços,
-- horários ou barbeiros de exemplo). O fluxo pensado é: logar como admin e
-- cadastrar o(s) barbeiro(s) e os serviços reais do cliente pela própria
-- interface (Barbeiros > Cadastrar, Serviços > Cadastrar). Se preferir que
-- eu inclua serviços/barbeiro de exemplo para testar mais rápido, é só
-- pedir que eu gero uma segunda versão com esses dados.
--
-- COMO RODAR (banco já provisionado, ex. no Easypanel — script NÃO cria
-- nem apaga o banco em si, só as tabelas dentro dele):
--   mysql -h <host> -P 3306 -u <usuario> -p <nome_do_banco> < instalacao_completa.sql
-- ou pelo phpMyAdmin/Adminer: selecione o banco na barra lateral e cole
-- este script na aba SQL.
--
-- Seguro rodar mais de uma vez: os DROP TABLE abaixo limpam qualquer
-- tentativa anterior antes de recriar do zero. ATENÇÃO: isso APAGA
-- qualquer dado que já exista nessas tabelas — só rode numa instalação
-- NOVA (vazia) ou quando quiser mesmo resetar tudo, nunca num banco de
-- cliente já em uso com dados reais.
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS FinanceiroRelatorios, FinanceiroRecebimentos, LoginTentativas,
    ClienteHistorico, ListaEspera, DiaBloqueado, FinanceiroLancamentos, Agendamentos,
    UploadVersao, Horario, Servico, Cliente, Barbeiro, Administrador;
SET FOREIGN_KEY_CHECKS = 1;

-- ========== Administrador ==========
CREATE TABLE Administrador (
    id_Admin INT AUTO_INCREMENT PRIMARY KEY,
    nome     VARCHAR(100) NOT NULL,
    login    VARCHAR(50) NOT NULL UNIQUE,
    senha    VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Login de teste padrão — TROQUE a senha antes de usar em produção real
-- com um cliente (ver aviso de segurança no final deste arquivo).
INSERT INTO Administrador (nome, login, senha) VALUES ('Admin Admin', 'admin', 'Kaio@123');

-- ========== Barbeiro ==========
CREATE TABLE Barbeiro (
    id_barbeiro INT AUTO_INCREMENT PRIMARY KEY,
    nome        VARCHAR(100) NOT NULL,
    login       VARCHAR(50) NOT NULL UNIQUE,
    senha       VARCHAR(255) NOT NULL,
    telefone    VARCHAR(20) NOT NULL,
    foto        VARCHAR(255) NULL,
    criado_em   TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ========== Cliente ==========
CREATE TABLE Cliente (
    idCliente INT AUTO_INCREMENT PRIMARY KEY,
    nome      VARCHAR(100) NOT NULL,
    telefone  VARCHAR(20) NOT NULL,
    email     VARCHAR(120) NULL,
    ativo     TINYINT(1) NOT NULL DEFAULT 1,
    criado_em TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ========== Serviço ==========
CREATE TABLE Servico (
    idServico       INT AUTO_INCREMENT PRIMARY KEY,
    nome            VARCHAR(80) NOT NULL,
    duracao_minutos INT NOT NULL DEFAULT 40,
    valor           DECIMAL(10,2) NOT NULL,
    ativo           TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ========== Horário ==========
CREATE TABLE Horario (
    idHorario   INT AUTO_INCREMENT PRIMARY KEY,
    id_barbeiro INT NOT NULL,
    data        DATE NOT NULL,
    hora        TIME NOT NULL,
    disponivel  TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (id_barbeiro) REFERENCES Barbeiro(id_barbeiro),
    UNIQUE KEY uk_barbeiro_data_hora (id_barbeiro, data, hora)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ========== Agendamentos ==========
CREATE TABLE Agendamentos (
    idAgendamento   INT AUTO_INCREMENT PRIMARY KEY,
    idCliente       INT NOT NULL,
    idServico       INT NOT NULL,
    idHorario       INT NOT NULL,
    idHorario_ativo INT GENERATED ALWAYS AS (CASE WHEN Status IN ('agendado', 'confirmado') THEN idHorario ELSE NULL END) STORED,
    Data            DATE NOT NULL,
    Valor           DECIMAL(10,2) NOT NULL,
    IncluirBarba    TINYINT(1) NOT NULL DEFAULT 0,
    Observacao      VARCHAR(255) NULL,

    -- Agendamento de Fidelidade (recorrência automática por semanas —
    -- ver includes/FidelidadeService.php). Também é o mecanismo usado
    -- para clientes "quinzenais"/"21 dias": não existe campo separado de
    -- periodicidade em Cliente, tudo passa por aqui.
    fidelidade              ENUM('1','2','3','4') NULL,
    grupo_recorrencia       CHAR(32) NULL,
    gerado_automaticamente  TINYINT(1) NOT NULL DEFAULT 0,

    -- Agendamento "duplicado": dois horários vinculados a um único
    -- atendimento (ex.: pai + filho no mesmo corte). A linha PRINCIPAL
    -- (eh_principal_agendamento=1) carrega o valor cobrado; a SECUNDÁRIA
    -- (=0) nasce com Valor 0.00. Ver Agendamentos/scripts/agendamento_salvar.php.
    grupo_agendamento         CHAR(32) NULL,
    eh_principal_agendamento TINYINT(1) NOT NULL DEFAULT 1,
    cobranca_duplicado       ENUM('um','dois') NULL,

    -- 'ausente' = Cliente Ausente (FinanceiroService::marcarAgendamentoAusente):
    -- não gera lançamento financeiro nenhum.
    Status ENUM('agendado','confirmado','concluido','cancelado','ausente') NOT NULL DEFAULT 'agendado',

    FOREIGN KEY (idCliente) REFERENCES Cliente(idCliente),
    FOREIGN KEY (idServico) REFERENCES Servico(idServico),
    FOREIGN KEY (idHorario) REFERENCES Horario(idHorario),

    UNIQUE KEY uk_horario_ativo_unico (idHorario_ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE INDEX idx_cliente_data_status ON Agendamentos (idCliente, Data, Status);
CREATE INDEX idx_grupo_recorrencia ON Agendamentos (grupo_recorrencia);
CREATE INDEX idx_grupo_agendamento ON Agendamentos (grupo_agendamento);

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
    criado_em       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    pago_em         TIMESTAMP NULL,

    FOREIGN KEY (id_barbeiro)   REFERENCES Barbeiro(id_barbeiro),
    FOREIGN KEY (idAgendamento) REFERENCES Agendamentos(idAgendamento),
    FOREIGN KEY (idCliente)     REFERENCES Cliente(idCliente),

    UNIQUE KEY uk_lancamento_agendamento (idAgendamento),

    INDEX idx_financeiro_status (id_barbeiro, status, data),
    INDEX idx_financeiro_data (id_barbeiro, tipo, data)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ========== Financeiro > Recebimentos ==========
-- Um recebimento por linha — a unidade real que Dashboard/Extrato/
-- Relatórios somam. Ver includes/FinanceiroService.php::registrarRecebimento().
CREATE TABLE FinanceiroRecebimentos (
    idRecebimento   INT AUTO_INCREMENT PRIMARY KEY,
    idLancamento    INT NOT NULL,
    id_barbeiro     INT NOT NULL,
    idCliente       INT NULL,

    tipo            ENUM('entrada','saida') NOT NULL,
    valor           DECIMAL(10,2) NOT NULL,
    forma_pagamento VARCHAR(100) NULL,

    data            DATE NOT NULL,
    criado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_recebimento_lancamento FOREIGN KEY (idLancamento) REFERENCES FinanceiroLancamentos(idLancamento) ON DELETE CASCADE,

    INDEX idx_recebimento_barbeiro_data (id_barbeiro, data),
    INDEX idx_recebimento_lancamento (idLancamento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ========== Histórico do Cliente ==========
CREATE TABLE ClienteHistorico (
    idHistorico INT AUTO_INCREMENT PRIMARY KEY,
    idCliente   INT NOT NULL,
    id_barbeiro INT NULL,
    idLancamento INT NULL,
    tipo        ENUM('fiado','observacao','atendimento','outro','pagamento') NOT NULL DEFAULT 'observacao',
    descricao   VARCHAR(500) NOT NULL,
    valor       DECIMAL(10,2) NULL,
    quitado     TINYINT(1) NOT NULL DEFAULT 0,
    criado_em   TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (idCliente) REFERENCES Cliente(idCliente),
    FOREIGN KEY (id_barbeiro) REFERENCES Barbeiro(id_barbeiro),
    FOREIGN KEY (idLancamento) REFERENCES FinanceiroLancamentos(idLancamento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ========== Lista de Espera ==========
-- Popup "Lista de Espera" em Agendamentos/paginas/agendar.php.
CREATE TABLE ListaEspera (
    idEspera    INT AUTO_INCREMENT PRIMARY KEY,
    id_barbeiro INT NOT NULL,
    idCliente   INT NOT NULL,
    observacao  VARCHAR(255) NULL,
    criado_em   TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (id_barbeiro) REFERENCES Barbeiro(id_barbeiro),
    FOREIGN KEY (idCliente)   REFERENCES Cliente(idCliente),

    INDEX idCliente (idCliente),
    INDEX idx_lista_espera_barbeiro (id_barbeiro, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ========== Dia Bloqueado ==========
-- Bloqueio de um DIA INTEIRO do calendário do barbeiro. Ver
-- includes/HorarioService.php e Agendamentos/scripts/dia_bloqueio_status.php.
CREATE TABLE DiaBloqueado (
    idDiaBloqueado INT AUTO_INCREMENT PRIMARY KEY,
    id_barbeiro    INT NOT NULL,
    data           DATE NOT NULL,
    motivo         VARCHAR(255) NULL,
    criado_em      TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (id_barbeiro) REFERENCES Barbeiro(id_barbeiro),

    UNIQUE KEY uk_barbeiro_dia_bloqueado (id_barbeiro, data),
    INDEX idx_dia_bloqueado_barbeiro_data (id_barbeiro, data)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ========== Uploads / Versões ==========
CREATE TABLE UploadVersao (
    idUpload  INT AUTO_INCREMENT PRIMARY KEY,
    versao    VARCHAR(30) NOT NULL UNIQUE,
    descricao VARCHAR(500) NOT NULL,
    data_hora DATETIME NOT NULL,
    id_admin  INT NULL,
    criado_em TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_admin) REFERENCES Administrador(id_Admin)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ========== Login Tentativas ==========
-- Ver includes/LoginThrottle.php — obrigatória para QUALQUER tentativa de login.
CREATE TABLE LoginTentativas (
    id        BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    chave     VARCHAR(191) NOT NULL COMMENT 'IP + login normalizado que tentou logar',
    sucesso   TINYINT(1) NOT NULL DEFAULT 0,
    criado_em DATETIME NOT NULL,
    INDEX idx_chave_criado (chave, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ========== Financeiro > Relatórios ==========
-- Ver Financeiro/scripts/relatorio_gerar.php + includes/RelatorioPdfBuilder.php.
-- PDF gerado é salvo direto no banco (arquivo_pdf LONGBLOB).
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

    gerado_em       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (id_barbeiro) REFERENCES Barbeiro(id_barbeiro),

    INDEX idx_relatorios_barbeiro (id_barbeiro, gerado_em),
    INDEX idx_relatorios_tipo (id_barbeiro, tipo, data_inicio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =============================================================================
-- AVISO DE SEGURANÇA
-- A senha de banco hardcoded em config/config.php (fallback do getenv) já
-- circulou em texto puro no código-fonte deste projeto. Ao subir num VPS
-- novo, gere uma senha nova e use SEMPRE as variáveis de ambiente
-- DB_HOST / DB_NAME / DB_USER / DB_PASS — nunca a senha antiga. O login de
-- admin acima ("Kaio@123") também é só um padrão de partida: troque-o (ou
-- crie um novo admin e apague este) antes de entregar a um cliente real.
-- =============================================================================

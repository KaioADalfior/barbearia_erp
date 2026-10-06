-- Catálogo da barbearia (vitrine pública /c/agendar) — configurado em
-- Catálogo > Configurar. Só guarda apresentação (identidade visual, contato,
-- horário de atendimento, descrição/ordem dos serviços, cargo dos
-- profissionais); nenhuma regra de agendamento depende dessas tabelas.
--
-- O sistema cria estas tabelas sozinho na primeira vez que a tela é aberta;
-- rode este script manualmente apenas se o usuário do banco não tiver
-- permissão de CREATE TABLE. Pode ser executado mais de uma vez.

CREATE TABLE IF NOT EXISTS CatalogoConfig (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    nome_exibicao VARCHAR(120) NULL,
    slogan VARCHAR(160) NULL,
    sobre TEXT NULL,
    aviso VARCHAR(255) NULL,
    logo VARCHAR(255) NULL,
    capa VARCHAR(255) NULL,
    cor_destaque VARCHAR(7) NOT NULL DEFAULT '#2f6fed',
    tema VARCHAR(10) NOT NULL DEFAULT 'escuro',
    endereco VARCHAR(255) NULL,
    mapa_url VARCHAR(500) NULL,
    whatsapp VARCHAR(30) NULL,
    telefone VARCHAR(30) NULL,
    instagram VARCHAR(80) NULL,
    facebook VARCHAR(160) NULL,
    formas_pagamento TEXT NULL,
    comodidades TEXT NULL,
    horarios TEXT NULL,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS CatalogoServico (
    idServico INT NOT NULL PRIMARY KEY,
    descricao VARCHAR(255) NULL,
    categoria VARCHAR(60) NULL,
    visivel TINYINT(1) NOT NULL DEFAULT 1,
    ordem INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS CatalogoBarbeiro (
    id_barbeiro INT NOT NULL PRIMARY KEY,
    cargo VARCHAR(60) NULL,
    visivel TINYINT(1) NOT NULL DEFAULT 1,
    ordem INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

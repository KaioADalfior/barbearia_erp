-- add_listaespera_diabloqueado.sql
-- Cria as tabelas ListaEspera e DiaBloqueado, que também nunca tiveram
-- migração em scriptBD/ apesar de já estarem em uso pelo código (mesmo
-- padrão dos gaps anteriores: LoginTentativas, FinanceiroRelatorios,
-- FinanceiroRecebimentos, e as colunas novas de Agendamentos).
--
--   - ListaEspera: popup "Lista de Espera" em Agendamentos/paginas/agendar.php
--     (Agendamentos/scripts/listaespera_listar.php, listaespera_adicionar.php,
--     listaespera_remover.php).
--   - DiaBloqueado: bloqueio de dia inteiro no calendário (includes/
--     HorarioService.php::diaBloqueado()/diasBloqueadosNoIntervalo(),
--     Agendamentos/scripts/dia_bloqueio_status.php).
--
-- Script 100% aditivo — cria só o que falta, não mexe em nada existente.
-- Seguro rodar no banco de produção agora.

CREATE TABLE ListaEspera (
    idEspera    INT AUTO_INCREMENT PRIMARY KEY,
    id_barbeiro INT NOT NULL,
    idCliente   INT NOT NULL,
    observacao  VARCHAR(255) NULL,
    criado_em   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (id_barbeiro) REFERENCES Barbeiro(id_barbeiro),
    FOREIGN KEY (idCliente)   REFERENCES Cliente(idCliente),

    -- Mesma regra já aplicada em PHP (listaespera_adicionar.php): um
    -- cliente não pode entrar duas vezes na lista de espera do mesmo
    -- barbeiro. Fica como rede de segurança contra corrida entre requisições.
    UNIQUE KEY uk_espera_barbeiro_cliente (id_barbeiro, idCliente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE DiaBloqueado (
    id_barbeiro INT NOT NULL,
    data        DATE NOT NULL,
    criado_em   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id_barbeiro, data),
    FOREIGN KEY (id_barbeiro) REFERENCES Barbeiro(id_barbeiro)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

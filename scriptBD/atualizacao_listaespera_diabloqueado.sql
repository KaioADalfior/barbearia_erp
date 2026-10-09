-- atualizacao_listaespera_diabloqueado.sql
-- Cria as tabelas ListaEspera e DiaBloqueado, usadas pelo código mas que não
-- tinham migração em scriptBD/ (só existiam em arquivos soltos fora dele).
--
--   - ListaEspera: popup "Lista de Espera" em Agendamentos/paginas/agendar.php
--     (listaespera_listar.php, listaespera_adicionar.php, listaespera_remover.php).
--   - DiaBloqueado: bloqueio de dia inteiro (includes/HorarioService.php,
--     Agendamentos/scripts/dia_bloqueio_status.php).
--
-- 100% aditivo e idempotente (CREATE TABLE IF NOT EXISTS): em um banco que já
-- tem as tabelas, não altera nada. Seguro rodar mais de uma vez.

CREATE TABLE IF NOT EXISTS ListaEspera (
    idEspera    INT AUTO_INCREMENT PRIMARY KEY,
    id_barbeiro INT NOT NULL,
    idCliente   INT NOT NULL,
    observacao  VARCHAR(255) NULL,
    criado_em   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (id_barbeiro) REFERENCES Barbeiro(id_barbeiro),
    FOREIGN KEY (idCliente)   REFERENCES Cliente(idCliente),

    -- Um cliente não entra duas vezes na lista do mesmo barbeiro (a regra
    -- também é checada em PHP; aqui é a rede de segurança contra corrida).
    UNIQUE KEY uk_espera_barbeiro_cliente (id_barbeiro, idCliente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS DiaBloqueado (
    id_barbeiro INT NOT NULL,
    data        DATE NOT NULL,
    criado_em   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id_barbeiro, data),
    FOREIGN KEY (id_barbeiro) REFERENCES Barbeiro(id_barbeiro)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

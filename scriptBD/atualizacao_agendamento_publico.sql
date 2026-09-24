-- scriptBD/atualizacao_agendamento_publico.sql
-- Cria o suporte para agendamento público por link (sem login), usado por
-- Publico/paginas/agendar.php e Publico/scripts/*.
--
--   - Barbeiro.link_publico: slug+token único que identifica o barbeiro na
--     URL pública (ex.: /c/agendar/barbearia-do-renatinho-a1b2c3d4e5f6a7b8).
--     Gerado pelo próprio barbeiro em Configurações > "Seu link de
--     agendamento" (ver Configuracoes/scripts/link_publico_gerar.php).
--     Fica NULL até o barbeiro gerar o primeiro link.
--   - AgendamentoPublicoTentativas: limita quantas tentativas de
--     agendamento um mesmo IP pode fazer pelo link público numa janela de
--     tempo — esse formulário não tem login nem senha, então isso é a
--     principal defesa contra spam/abuso automatizado nele (ver
--     includes/AgendamentoPublicoThrottle.php).
--
-- Script aditivo — só cria/adiciona o que falta, não mexe em nada
-- existente. RODE UMA VEZ SÓ: rodar de novo dá erro de "duplicate column"
-- (inofensivo, sem perda de dado, mas nem precisa rodar de novo).

ALTER TABLE Barbeiro
    ADD COLUMN link_publico VARCHAR(160) NULL UNIQUE AFTER foto;

CREATE TABLE IF NOT EXISTS AgendamentoPublicoTentativas (
    id        BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip        VARCHAR(45) NOT NULL COMMENT 'IP de quem tentou agendar pelo link público',
    criado_em DATETIME NOT NULL,
    INDEX idx_ip_criado (ip, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

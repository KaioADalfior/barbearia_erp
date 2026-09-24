-- scriptBD/atualizacao_login_lockout.sql
-- Migração idempotente: cria a tabela usada para o bloqueio temporário de
-- login após várias tentativas incorretas (ver includes/LoginThrottle.php).
-- Rode este script uma vez no banco já existente. Não altera nenhuma
-- tabela atual, só adiciona uma nova.

CREATE TABLE IF NOT EXISTS LoginTentativas (
    id        BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    chave     VARCHAR(191) NOT NULL COMMENT 'IP + login normalizado que tentou logar',
    sucesso   TINYINT(1) NOT NULL DEFAULT 0,
    criado_em DATETIME NOT NULL,
    INDEX idx_chave_criado (chave, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

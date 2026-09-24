-- add_agendamentos_duplicado_ausente.sql
-- Adiciona à tabela Agendamentos as colunas e o valor de status que o
-- código já usa há tempo (agendamento "duplicado" — dois horários
-- vinculados para o mesmo atendimento — e status "Cliente Ausente"), mas
-- que nunca tiveram uma migração correspondente em scriptBD/. Sem isso,
-- QUALQUER leitura da grade de horários (Agendamentos/scripts/
-- horarios_buscar.php) quebra com "Unknown column", derrubando a tela de
-- Agendar por inteiro (é esse o erro "Erro de conexão. Não foi possível
-- falar com o servidor." — na verdade é o PHP devolvendo um erro em vez de
-- JSON, e o front não sabe interpretar isso).
--
-- Script 100% aditivo — não apaga nem altera nenhum dado já gravado nas
-- linhas existentes (os agendamentos já existentes simplesmente ganham
-- essas 3 colunas com NULL). Seguro rodar no banco de produção agora.
--
-- RODE UMA VEZ SÓ: sem "IF NOT EXISTS" de propósito (nem todo MySQL/
-- MariaDB suporta essa sintaxe em ADD COLUMN) — rodar duas vezes só dá um
-- erro inofensivo de "coluna duplicada", não apaga nada.

ALTER TABLE Agendamentos
    ADD COLUMN grupo_agendamento CHAR(32) NULL
        COMMENT 'Vincula as 2 linhas de um agendamento duplicado (2 horários, 1 atendimento). Ver Agendamentos/scripts/agendamento_salvar.php.'
        AFTER gerado_automaticamente,
    ADD COLUMN eh_principal_agendamento TINYINT(1) NULL
        COMMENT '1 = linha principal (cobra o valor cheio); 0 = linha secundária (Valor sempre 0.00).'
        AFTER grupo_agendamento,
    ADD COLUMN cobranca_duplicado VARCHAR(10) NULL
        COMMENT 'Só na linha principal de um duplicado: "um" ou "dois" cortes cobrados.'
        AFTER eh_principal_agendamento,
    ADD INDEX idx_grupo_agendamento (grupo_agendamento);

ALTER TABLE Agendamentos
    MODIFY COLUMN Status ENUM('agendado','confirmado','concluido','cancelado','ausente') NOT NULL DEFAULT 'agendado';

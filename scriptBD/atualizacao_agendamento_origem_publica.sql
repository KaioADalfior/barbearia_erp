-- scriptBD/atualizacao_agendamento_origem_publica.sql
-- Marca, em cada linha de Agendamentos, se ela foi criada pelo cliente
-- final direto no link público (ver Publico/scripts/publico_agendar_salvar.php)
-- ou pelo barbeiro no painel interno. Usado só pra exibir uma cor/rótulo
-- diferente na grade de horários de Agendamentos/paginas/agendar.php (ver
-- Agendamentos/scripts/horarios_buscar.php > origemPublica) — não muda
-- nenhuma regra de negócio.
--
-- Complementa scriptBD/atualizacao_agendamento_publico.sql (migração
-- separada de propósito: mesmo que aquela já tenha sido rodada, esta é
-- independente e também é aditiva).
--
-- Script aditivo. RODE UMA VEZ SÓ: rodar de novo dá erro de "duplicate
-- column" (inofensivo, sem perda de dado, mas nem precisa rodar de novo).

ALTER TABLE Agendamentos
    ADD COLUMN origem_publica TINYINT(1) NOT NULL DEFAULT 0 AFTER gerado_automaticamente;

-- atualizacao_servicos.sql
-- Cadastra serviços padrão, caso a tabela Servico ainda esteja vazia.
-- Necessário para o formulário de agendamento (pages/agendar.php), que
-- exige um serviço selecionado em cada agendamento.
-- Pode ser executado com segurança mais de uma vez (só insere se a
-- tabela estiver vazia).

USE alexbarber;

INSERT INTO Servico (nome, duracao_minutos, valor)
SELECT * FROM (
    SELECT 'Corte'          AS nome, 40 AS duracao_minutos, 35.00 AS valor UNION ALL
    SELECT 'Barba',              20,                          25.00        UNION ALL
    SELECT 'Corte + Barba',      60,                          55.00        UNION ALL
    SELECT 'Sobrancelha',        15,                          15.00
) AS padrao
WHERE NOT EXISTS (SELECT 1 FROM Servico);

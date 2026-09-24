-- add_financeiro_recebimentos.sql
-- Cria a tabela FinanceiroRecebimentos, que faltava no banco (não existia
-- em nenhum script de instalação/migração do projeto, apesar de ser usada
-- por includes/FinanceiroService.php e includes/RelatorioService.php).
--
-- Sem essa tabela: Dashboard Financeiro, Extrato (Cadastrar Baixa) e
-- Relatórios quebram ao LER; e pior, REGISTRAR uma baixa paga ou receber
-- um fiado quebra ao GRAVAR (a rotina roda dentro de uma transação — ao
-- falhar o INSERT em FinanceiroRecebimentos, a transação inteira é
-- desfeita, então nenhum dado ficou corrompido, mas nenhuma baixa/
-- recebimento paga está sendo salva desde que o sistema foi publicado).
--
-- Script 100% aditivo — não mexe em nenhuma tabela existente nem em
-- nenhum dado já gravado. Seguro para rodar no banco de produção agora.

CREATE TABLE IF NOT EXISTS FinanceiroRecebimentos (
    idRecebimento   INT AUTO_INCREMENT PRIMARY KEY,
    idLancamento    INT NOT NULL,
    id_barbeiro     INT NOT NULL,
    idCliente       INT NULL,

    tipo            ENUM('entrada','saida') NOT NULL DEFAULT 'entrada',
    valor           DECIMAL(10,2) NOT NULL,

    -- Mesma convenção de FinanceiroLancamentos.forma_pagamento: uma ou
    -- mais formas separadas por vírgula.
    forma_pagamento VARCHAR(120) NULL,

    -- Data em que o dinheiro efetivamente entrou (pode ser diferente da
    -- data do lançamento original, no caso de fiado pago depois).
    data            DATE NOT NULL,
    criado_em       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- ON DELETE CASCADE aqui é intencional: FinanceiroService::excluirLancamento()
    -- já apaga os recebimentos manualmente antes de apagar o lançamento
    -- (pra não depender da FK), mas a cascade fica como rede de segurança.
    FOREIGN KEY (idLancamento) REFERENCES FinanceiroLancamentos(idLancamento) ON DELETE CASCADE,
    FOREIGN KEY (id_barbeiro)  REFERENCES Barbeiro(id_barbeiro),
    FOREIGN KEY (idCliente)    REFERENCES Cliente(idCliente),

    INDEX idx_recebimentos_data (id_barbeiro, tipo, data),
    INDEX idx_recebimentos_lancamento (idLancamento)
);

-- atualizacao_relatorios.sql
-- Cria o backend do módulo Financeiro > Relatórios: cada relatório gerado
-- manualmente pelo barbeiro (diário/semanal/mensal/anual) é renderizado em
-- PDF (Financeiro/scripts/relatorio_gerar.php + includes/RelatorioPdfBuilder.php)
-- e o PDF final é salvo diretamente no banco de dados (coluna arquivo_pdf,
-- LONGBLOB), junto com os totais já calculados no momento da geração —
-- assim o relatório baixado depois reflete sempre o que existia na época em
-- que foi gerado, mesmo que lançamentos futuros mudem o extrato.
--
-- Guardar o PDF como BLOB (em vez de arquivo em disco) evita depender de
-- permissão de escrita em pasta no hospedeiro (compatível com InfinityFree).
--
-- Pode ser executado com segurança mais de uma vez (idempotente).

USE alexbarber;

CREATE TABLE IF NOT EXISTS FinanceiroRelatorios (
    idRelatorio     INT AUTO_INCREMENT PRIMARY KEY,
    id_barbeiro     INT NOT NULL,

    -- Tipo do relatório e período (intervalo) que ele cobre.
    tipo            ENUM('diario','semanal','mensal','anual') NOT NULL,
    data_inicio     DATE NOT NULL,
    data_fim        DATE NOT NULL,

    -- Título já formatado (ex: "Relatório Diário — 07/08/2026"), usado na
    -- tabela de listagem e como texto no cabeçalho do próprio PDF.
    titulo          VARCHAR(150) NOT NULL,

    -- Totais consolidados no momento da geração (considerando apenas
    -- lançamentos com status = 'pago', mesma regra do Dashboard).
    total_entradas  DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_saidas    DECIMAL(10,2) NOT NULL DEFAULT 0,
    saldo           DECIMAL(10,2) NOT NULL DEFAULT 0,
    qtd_lancamentos INT NOT NULL DEFAULT 0,

    -- Arquivo PDF gerado (nome sugerido de download + bytes do arquivo).
    nome_arquivo    VARCHAR(180) NOT NULL,
    tamanho_bytes   INT NOT NULL DEFAULT 0,
    arquivo_pdf     LONGBLOB NOT NULL,

    gerado_em       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (id_barbeiro) REFERENCES Barbeiro(id_barbeiro),

    INDEX idx_relatorios_barbeiro (id_barbeiro, gerado_em),
    INDEX idx_relatorios_tipo (id_barbeiro, tipo, data_inicio)
);

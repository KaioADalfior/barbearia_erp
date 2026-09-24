-- atualizacao_historico_cliente.sql
-- Adiciona o histórico do cliente (aba "Histórico" no modal de detalhes de
-- Clientes/paginas/cliente_listar.php), usado pelo barbeiro para registrar
-- anotações livres sobre o cliente — ex: FIADO (valor em aberto), observação
-- geral, atendimento feito fora da agenda, etc.
--
-- Pode ser executado com segurança mais de uma vez (CREATE TABLE IF NOT EXISTS).

USE alexbarber;

CREATE TABLE IF NOT EXISTS ClienteHistorico (
    idHistorico INT AUTO_INCREMENT PRIMARY KEY,
    idCliente INT NOT NULL,
    id_barbeiro INT NULL,
    tipo ENUM('fiado','observacao','atendimento','outro') NOT NULL DEFAULT 'observacao',
    descricao VARCHAR(500) NOT NULL,
    valor DECIMAL(10,2) NULL,
    quitado TINYINT(1) NOT NULL DEFAULT 0,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (idCliente) REFERENCES Cliente(idCliente),
    FOREIGN KEY (id_barbeiro) REFERENCES Barbeiro(id_barbeiro)
);

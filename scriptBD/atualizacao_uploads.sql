-- atualizacao_uploads.sql
-- Rode este script se o banco "alexbarber" já existir e você só quer
-- adicionar a funcionalidade de Uploads/Versões sem recriar tudo.

USE alexbarber;

CREATE TABLE IF NOT EXISTS UploadVersao (
    idUpload INT AUTO_INCREMENT PRIMARY KEY,
    versao VARCHAR(30) NOT NULL UNIQUE,
    descricao VARCHAR(500) NOT NULL,
    data_hora DATETIME NOT NULL,
    id_admin INT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_admin) REFERENCES Administrador(id_Admin)
);

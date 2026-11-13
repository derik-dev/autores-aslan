-- Estrutura (tabelas) do banco de dados MySQL.
-- Importar pelo phpMyAdmin (selecione o banco antes de importar).

CREATE TABLE IF NOT EXISTS usuarios (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nome        VARCHAR(120) NOT NULL,
    email       VARCHAR(190) NOT NULL UNIQUE,
    senha_hash  VARCHAR(255) NOT NULL,
    criado_em   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS textos (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    usuario_id     INT UNSIGNED NOT NULL,
    titulo         VARCHAR(200) NOT NULL,
    conteudo       MEDIUMTEXT NOT NULL,
    criado_em      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_textos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

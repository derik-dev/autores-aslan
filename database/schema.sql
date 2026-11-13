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

-- ---------- Texto em áudio (ElevenLabs) ----------

CREATE TABLE IF NOT EXISTS audios (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    usuario_id     INT UNSIGNED NOT NULL,
    titulo         VARCHAR(200) NOT NULL,
    voz_id         VARCHAR(64) NOT NULL,
    modelo         VARCHAR(20) NOT NULL,
    total_chars    INT UNSIGNED NOT NULL,
    status         ENUM('pendente','processando','concluido','erro') NOT NULL DEFAULT 'pendente',
    erro           VARCHAR(500) NULL,
    criado_em      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_audios_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- O texto é dividido em trechos; cada trecho vira um arquivo MP3.
CREATE TABLE IF NOT EXISTS audio_trechos (
    id        INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    audio_id  INT UNSIGNED NOT NULL,
    ordem     INT UNSIGNED NOT NULL,
    texto     MEDIUMTEXT NOT NULL,
    chars     INT UNSIGNED NOT NULL,
    feito     TINYINT(1) NOT NULL DEFAULT 0,
    bytes     INT UNSIGNED NOT NULL DEFAULT 0,
    UNIQUE KEY uq_audio_ordem (audio_id, ordem),
    CONSTRAINT fk_trechos_audio FOREIGN KEY (audio_id) REFERENCES audios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

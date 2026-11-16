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

-- ---------- Tradução de livros (Claude) ----------

CREATE TABLE IF NOT EXISTS traducoes (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    usuario_id     INT UNSIGNED NOT NULL,
    titulo         VARCHAR(200) NOT NULL,
    idioma         VARCHAR(5) NOT NULL,
    instrucoes     TEXT NULL,
    total_chars    INT UNSIGNED NOT NULL,
    status         ENUM('pendente','processando','concluido','erro') NOT NULL DEFAULT 'pendente',
    erro           VARCHAR(500) NULL,
    criado_em      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_traducoes_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- O livro é dividido em trechos; cada trecho guarda o original, a tradução e se foi aprovado na revisão.
CREATE TABLE IF NOT EXISTS traducao_trechos (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    traducao_id  INT UNSIGNED NOT NULL,
    ordem        INT UNSIGNED NOT NULL,
    original     MEDIUMTEXT NOT NULL,
    traduzido    MEDIUMTEXT NULL,
    chars        INT UNSIGNED NOT NULL,
    feito        TINYINT(1) NOT NULL DEFAULT 0,
    aprovado     TINYINT(1) NOT NULL DEFAULT 0,
    UNIQUE KEY uq_traducao_ordem (traducao_id, ordem),
    CONSTRAINT fk_trad_trechos FOREIGN KEY (traducao_id) REFERENCES traducoes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Se a tabela traducoes já existia antes desta versão, rode também (campo de ficha/glossário maior):
-- ALTER TABLE traducoes MODIFY instrucoes TEXT NULL;

-- ---------- Vendas ----------

CREATE TABLE IF NOT EXISTS importacoes_vendas (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    usuario_id  INT UNSIGNED NOT NULL,
    arquivo     VARCHAR(200) NOT NULL,
    linhas      INT UNSIGNED NOT NULL DEFAULT 0,
    ignoradas   INT UNSIGNED NOT NULL DEFAULT 0,
    criado_em   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vendas (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    importacao_id  INT UNSIGNED NOT NULL,
    autor_id       INT UNSIGNED NOT NULL,
    livro          VARCHAR(200) NOT NULL,
    canal          VARCHAR(80) NOT NULL,
    data_venda     DATE NOT NULL,
    quantidade     INT NOT NULL DEFAULT 1,
    valor_bruto    DECIMAL(12,2) NOT NULL DEFAULT 0,
    receita_autor  DECIMAL(12,2) NOT NULL DEFAULT 0,
    KEY idx_vendas_autor (autor_id, data_venda),
    CONSTRAINT fk_vendas_imp FOREIGN KEY (importacao_id) REFERENCES importacoes_vendas(id) ON DELETE CASCADE,
    CONSTRAINT fk_vendas_autor FOREIGN KEY (autor_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Análises e pesquisa de mercado (relatórios gerados por IA) ----------

CREATE TABLE IF NOT EXISTS relatorios (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    usuario_id  INT UNSIGNED NOT NULL,
    tipo        VARCHAR(20) NOT NULL,
    titulo      VARCHAR(200) NOT NULL,
    conteudo    MEDIUMTEXT NOT NULL,
    criado_em   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_relatorios_usuario (usuario_id, tipo),
    CONSTRAINT fk_relatorios_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Inspiração ----------

CREATE TABLE IF NOT EXISTS inspiracoes (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    usuario_id  INT UNSIGNED NOT NULL,
    tipo        VARCHAR(20) NOT NULL,
    titulo      VARCHAR(200) NOT NULL,
    conteudo    TEXT NULL,
    url         VARCHAR(500) NULL,
    imagem      VARCHAR(80) NULL,
    criado_em   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_insp_usuario (usuario_id, tipo),
    CONSTRAINT fk_insp_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Entrega digital ----------

CREATE TABLE IF NOT EXISTS entregas (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    usuario_id     INT UNSIGNED NOT NULL,
    titulo         VARCHAR(200) NOT NULL,
    arquivo        VARCHAR(80) NULL,
    nome_original  VARCHAR(200) NULL,
    audio_id       INT UNSIGNED NULL,
    tamanho        BIGINT UNSIGNED NOT NULL DEFAULT 0,
    criado_em      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_entregas_usuario (usuario_id),
    CONSTRAINT fk_entregas_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS entrega_links (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    entrega_id    INT UNSIGNED NOT NULL,
    token         CHAR(40) NOT NULL UNIQUE,
    destinatario  VARCHAR(190) NOT NULL,
    expira_em     DATETIME NULL,
    limite        INT UNSIGNED NOT NULL DEFAULT 3,
    baixados      INT UNSIGNED NOT NULL DEFAULT 0,
    ativo         TINYINT(1) NOT NULL DEFAULT 1,
    criado_em     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_links_entrega FOREIGN KEY (entrega_id) REFERENCES entregas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Exemplares antecipados ----------

CREATE TABLE IF NOT EXISTS arcs (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    usuario_id  INT UNSIGNED NOT NULL,
    titulo      VARCHAR(200) NOT NULL,
    descricao   TEXT NULL,
    entrega_id  INT UNSIGNED NULL,
    token       CHAR(24) NOT NULL UNIQUE,
    vagas       INT UNSIGNED NOT NULL DEFAULT 50,
    aberto      TINYINT(1) NOT NULL DEFAULT 1,
    criado_em   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_arcs_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS arc_leitores (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    arc_id      INT UNSIGNED NOT NULL,
    nome        VARCHAR(120) NOT NULL,
    email       VARCHAR(190) NOT NULL,
    onde        VARCHAR(300) NULL,
    status      ENUM('pendente','aprovado','recusado') NOT NULL DEFAULT 'pendente',
    link_token  CHAR(40) NULL,
    resenhou    TINYINT(1) NOT NULL DEFAULT 0,
    criado_em   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_arc_email (arc_id, email),
    CONSTRAINT fk_leitores_arc FOREIGN KEY (arc_id) REFERENCES arcs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Análises: conversa livre sobre as vendas ----------

CREATE TABLE IF NOT EXISTS analise_conversas (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    usuario_id     INT UNSIGNED NOT NULL,
    titulo         VARCHAR(120) NOT NULL DEFAULT 'Nova conversa',
    autor_filtro   INT UNSIGNED NOT NULL DEFAULT 0,
    relatorio_id   INT UNSIGNED NULL,
    criado_em      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_conversas_usuario (usuario_id, atualizado_em),
    CONSTRAINT fk_conversas_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS analise_mensagens (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    conversa_id  INT UNSIGNED NOT NULL,
    papel        ENUM('user','assistant') NOT NULL,
    conteudo     MEDIUMTEXT NOT NULL,
    criado_em    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_mensagens_conversa (conversa_id, id),
    CONSTRAINT fk_mensagens_conversa FOREIGN KEY (conversa_id) REFERENCES analise_conversas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

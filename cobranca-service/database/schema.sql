DROP TABLE IF EXISTS caixa_de_saida;
DROP TABLE IF EXISTS pagamentos;
DROP TABLE IF EXISTS faturas;

CREATE TABLE faturas (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    matricula_id  INT UNSIGNED  NOT NULL,
    aluno_id      INT UNSIGNED  NOT NULL,
    competencia   DATE          NOT NULL,
    valor         DECIMAL(10,2) NOT NULL,
    vencimento    DATE          NOT NULL,
    status        ENUM('aberta','paga','cancelada') NOT NULL DEFAULT 'aberta',
    pago_em       DATETIME      NULL,
    gateway_ref   VARCHAR(64)   NULL,
    criado_em     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_faturas_matricula_competencia (matricula_id, competencia),
    KEY idx_faturas_aluno_status (aluno_id, status),
    KEY idx_faturas_vencimento (vencimento),
    KEY idx_faturas_gateway_ref (gateway_ref)
) ENGINE=InnoDB;

CREATE TABLE pagamentos (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fatura_id       BIGINT UNSIGNED NOT NULL,
    valor           DECIMAL(10,2)   NOT NULL,
    metodo          VARCHAR(30)     NOT NULL,
    gateway_payload JSON            NULL,
    criado_em       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_pagamentos_fatura (fatura_id),
    CONSTRAINT fk_pagamentos_fatura FOREIGN KEY (fatura_id) REFERENCES faturas (id)
) ENGINE=InnoDB;

CREATE TABLE caixa_de_saida (
    id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    aluno_id       INT UNSIGNED      NOT NULL,
    dias_de_atraso SMALLINT UNSIGNED NOT NULL,
    criado_em      DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    entregue_em    DATETIME          NULL,
    tentativas     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    KEY idx_caixa_pendentes (entregue_em, id)
) ENGINE=InnoDB;

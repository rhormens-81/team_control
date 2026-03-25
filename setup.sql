-- ============================================================
--  Team Control — Setup do banco MariaDB
--  Execute uma vez: mysql -u root -proot < setup.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS team_control
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE team_control;

CREATE TABLE IF NOT EXISTS settings (
    `key`   VARCHAR(100) NOT NULL PRIMARY KEY,
    `value` VARCHAR(255)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS equipes (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    nome       VARCHAR(255) NOT NULL,
    lider      VARCHAR(255) NOT NULL,
    gerente    VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS funcionarios (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    nome              VARCHAR(255) NOT NULL,
    gui               VARCHAR(50),
    equipe_id         INT,
    lider             VARCHAR(255),
    gerente           VARCHAR(255),
    funcao_ey         VARCHAR(255),
    nivel_ey          VARCHAR(100),
    funcao_bb         VARCHAR(255),
    nivel_bb          VARCHAR(50),
    inicio_ey         DATE,
    inicio_bb         DATE,
    fim_bb            DATE,
    fim_ey            DATE,
    motivo            TEXT,
    status            VARCHAR(20)  DEFAULT 'Ativo',
    alocado        VARCHAR(10)  DEFAULT 'Sim',
    data_desalocacao  DATE,
    data_desligamento DATE,
    created_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (equipe_id) REFERENCES equipes(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO settings (`key`, `value`) VALUES ('meta_funcionarios', '150');
INSERT IGNORE INTO settings (`key`, `value`) VALUES ('meta_equipes', '15');

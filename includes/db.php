<?php
define('DB_HOST', '192.168.15.8');
define('DB_PORT', '3306');
define('DB_NAME', 'team_control');
define('DB_USER', 'casaos');
define('DB_PASS', 'casaos');

function getDB() {
    static $db = null;
    if ($db === null) {
        $db = new PDO(
            'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
            ]
        );
        initDB($db);
    }
    return $db;
}

function initDB($db) {
    $db->exec("
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
            id               INT AUTO_INCREMENT PRIMARY KEY,
            nome             VARCHAR(255) NOT NULL,
            gui              VARCHAR(50),
            equipe_id        INT,
            lider            VARCHAR(255),
            gerente          VARCHAR(255),
            funcao_ey        VARCHAR(255),
            nivel_ey         VARCHAR(100),
            funcao_bb        VARCHAR(255),
            nivel_bb         VARCHAR(50),
            inicio_ey        DATE,
            inicio_bb        DATE,
            fim_bb           DATE,
            fim_ey           DATE,
            motivo           TEXT,
            status           VARCHAR(20) DEFAULT 'Ativo',
            desalocado       VARCHAR(10) DEFAULT 'Não',
            data_desalocacao DATE,
            data_desligamento DATE,
            created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (equipe_id) REFERENCES equipes(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Default settings
    $db->exec("INSERT IGNORE INTO settings (`key`, `value`) VALUES ('meta_funcionarios', '150')");
    $db->exec("INSERT IGNORE INTO settings (`key`, `value`) VALUES ('meta_equipes', '15')");
}

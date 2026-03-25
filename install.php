<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/db.php';

$success = false;
$message = '';
$error = '';

if (isset($_POST['install'])) {
    try {
        // Tenta conectar sem o banco de dados primeiro para garantir que o banco exista
        $pdo = new PDO("mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=utf8mb4", DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Lê o arquivo SQL
        $sqlFile = 'setup.sql';
        if (!file_exists($sqlFile)) {
            throw new Exception("Arquivo setup.sql não encontrado!");
        }
        
        $sql = file_get_contents($sqlFile);
        
        // Executa o SQL (o setup.sql já contém CREATE DATABASE IF NOT EXISTS e USE)
        $pdo->exec($sql);
        
        $success = true;
        $message = "Banco de dados e tabelas configurados com sucesso!";
    } catch (PDOException $e) {
        $error = "Erro no banco de dados: " . $e->getMessage();
    } catch (Exception $e) {
        $error = "Erro: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalação | Team Control</title>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --accent: #ffe600;
            --bg: #ffffff;
            --text: #1a1a1a;
            --border: #e0e0e0;
        }
        body {
            font-family: 'DM Sans', sans-serif;
            background: #f8f8f8;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
        }
        .install-card {
            background: white;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            max-width: 450px;
            width: 90%;
            text-align: center;
            border: 1px solid var(--border);
        }
        .brand {
            font-family: 'Syne', sans-serif;
            font-weight: 800;
            font-size: 24px;
            margin-bottom: 30px;
        }
        .brand span {
            background: var(--accent);
            padding: 5px 10px;
            border-radius: 8px;
        }
        h2 { margin-bottom: 10px; font-size: 20px; }
        p { color: #666; margin-bottom: 30px; font-size: 14px; }
        .btn {
            background: var(--accent);
            color: black;
            border: none;
            padding: 14px 28px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            font-size: 15px;
            transition: transform 0.2s;
        }
        .btn:hover { transform: translateY(-2px); }
        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 13px;
            text-align: left;
        }
        .alert-success { background: #e6fffa; color: #2c7a7b; border: 1px solid #b2f5ea; }
        .alert-error { background: #fff5f5; color: #c53030; border: 1px solid #feb2b2; }
    </style>
</head>
<body>
    <div class="install-card">
        <div class="brand"><span>Team</span> Control</div>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <?= $message ?>
            </div>
            <a href="index.php" class="btn" style="text-decoration:none; display:inline-block;">Ir para o Dashboard</a>
        <?php else: ?>
            <h2>Configuração de Banco de Dados</h2>
            <p>Clique no botão abaixo para criar as tabelas necessárias no banco de dados EY Team Control.</p>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?= $error ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <button type="submit" name="install" class="btn">Instalar Banco de Dados</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>

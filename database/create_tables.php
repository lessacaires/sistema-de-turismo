<?php

// Carrega o arquivo de configuração do banco de dados
require_once __DIR__ . '/../config/database.php';

// Lê o arquivo SQL
$sql = file_get_contents(__DIR__ . '/schema.sql');

// Conecta ao banco de dados MySQL (sem selecionar um banco de dados específico)
try {
    $config = require __DIR__ . '/../config/database.php';
    
    $host = $config['host'];
    $user = $config['user'];
    $password = $config['password'];
    
    $pdo = new PDO("mysql:host=$host", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Conectado ao MySQL com sucesso!\n";
    
    // Divide o SQL em comandos individuais
    $commands = explode(';', $sql);
    
    // Executa cada comando
    foreach ($commands as $command) {
        $command = trim($command);
        if (!empty($command)) {
            try {
                $pdo->exec($command);
                echo "Comando executado com sucesso: " . substr($command, 0, 50) . "...\n";
            } catch (PDOException $e) {
                echo "Erro ao executar comando: " . $e->getMessage() . "\n";
                echo "Comando: " . $command . "\n";
            }
        }
    }
    
    echo "Banco de dados e tabelas criados com sucesso!\n";
    
} catch (PDOException $e) {
    die("Erro na conexão com o banco de dados: " . $e->getMessage());
}

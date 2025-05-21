<?php

// Carregar a configuração do banco de dados
require_once __DIR__ . '/../config/database.php';

// Função para executar um script SQL
function execute_sql_file($pdo, $file) {
    try {
        // Ler o conteúdo do arquivo
        $sql = file_get_contents($file);
        
        // Executar as consultas
        $pdo->exec($sql);
        
        echo "Script SQL executado com sucesso: " . $file . "\n";
        return true;
    } catch (PDOException $e) {
        echo "Erro ao executar o script SQL: " . $e->getMessage() . "\n";
        return false;
    }
}

// Conectar ao banco de dados
try {
    $pdo = connect_db();
    echo "Conexão com o banco de dados estabelecida com sucesso.\n";
    
    // Executar o script de criação das tabelas
    $result = execute_sql_file($pdo, __DIR__ . '/scripts/create_tables.sql');
    
    if ($result) {
        echo "Banco de dados configurado com sucesso!\n";
        
        // Verificar se já existem usuários no sistema
        $stmt = $pdo->query("SELECT COUNT(*) FROM users");
        $userCount = $stmt->fetchColumn();
        
        // Se não houver usuários, criar um usuário administrador padrão
        if ($userCount == 0) {
            $username = 'admin';
            $password = password_hash('admin123', PASSWORD_DEFAULT);
            $email = 'admin@example.com';
            
            $stmt = $pdo->prepare("INSERT INTO users (username, password, email, full_name, role, is_active, registration_date) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$username, $password, $email, 'Administrador', 'admin', true]);
            
            echo "Usuário administrador criado com sucesso!\n";
            echo "Username: admin\n";
            echo "Senha: admin123\n";
            echo "IMPORTANTE: Altere esta senha após o primeiro login!\n";
        }
    }
} catch (PDOException $e) {
    echo "Erro na conexão com o banco de dados: " . $e->getMessage() . "\n";
}

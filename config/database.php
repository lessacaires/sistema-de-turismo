<?php

return [
    'host' => 'localhost',
    'dbname' => 'saas',
    'user' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
];

function connect_db() {
    $config = require __FILE__; // Use __FILE__ para referenciar este arquivo

    $host = $config['host'];
    $dbname = $config['dbname'];
    $user = $config['user'];
    $password = $config['password'];
    $charset = $config['charset'];

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=$charset", $user, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        die("Erro na conexão com o banco de dados: " . $e->getMessage());
    }
}
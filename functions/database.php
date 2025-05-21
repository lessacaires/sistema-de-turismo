<?php

if (!function_exists('connect_db')) {
    function connect_db() {
        $config = require __DIR__ . '/../config/database.php';

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
}

function query_db(PDO $pdo, string $sql, array $params = []) {
    // ... (rest of the functions)
}

function fetch_one(PDOStatement $stmt) {
    // ...
}

function fetch_all(PDOStatement $stmt) {
    // ...
}
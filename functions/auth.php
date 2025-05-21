<?php

function register_user(PDO $pdo, string $username, string $password, string $email) {
    // Verificar se o nome de usuário ou e-mail já existem
    $stmt = query_db($pdo, "SELECT id FROM users WHERE username = :username OR email = :email", [
        ':username' => $username,
        ':email' => $email,
    ]);

    if (fetch_one($stmt)) {
        return false; // Nome de usuário ou e-mail já existe
    }

    // Hash da senha antes de salvar no banco de dados
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $stmt = query_db($pdo, "INSERT INTO users (username, password, email, registration_date) VALUES (:username, :password, :email, NOW())", [
        ':username' => $username,
        ':password' => $hashedPassword,
        ':email' => $email,
    ]);

    return $stmt->rowCount() > 0; // Retorna true se o registro for bem-sucedido
}

function login_user(PDO $pdo, string $usernameOrEmail, string $password) {
    // Buscar usuário por nome de usuário ou e-mail
    $stmt = query_db($pdo, "SELECT id, username, password FROM users WHERE username = :username OR email = :email", [
        ':username' => $usernameOrEmail,
        ':email' => $usernameOrEmail,
    ]);

    $user = fetch_one($stmt);

    if ($user && password_verify($password, $user['password'])) {
        // Senha verificada com sucesso, iniciar a sessão do usuário
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        return true;
    } else {
        return false; // Credenciais inválidas
    }
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function get_logged_in_user_id() {
    return $_SESSION['user_id'] ?? null;
}

function get_logged_in_username() {
    return $_SESSION['username'] ?? null;
}

function logout_user() {
    session_unset();
    session_destroy();
}

// Iniciar a sessão no início de cada requisição
session_start();
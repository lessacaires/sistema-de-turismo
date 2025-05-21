<?php

// Carrega o arquivo de configuração do banco de dados
require_once __DIR__ . '/../config/database.php';

// Carrega o arquivo de funções de banco de dados
require_once __DIR__ . '/../functions/database.php';

// Carrega o arquivo de funções de autenticação
require_once __DIR__ . '/../functions/auth.php';

// Carrega o arquivo de funções utilitárias
require_once __DIR__ . '/../functions/utils.php';

// Conecta ao banco de dados
$pdo = connect_db();

// Inicializa a variável de erro
$error = null;

// Processa o formulário de login
if (isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    // Tenta fazer login
    if (login_user($pdo, $username, $password)) {
        // Redireciona para a página inicial após o login bem-sucedido
        header('Location: index.php');
        exit;
    } else {
        // Define a mensagem de erro
        $error = 'Usuário ou senha inválidos. Por favor, tente novamente.';
    }
}

// Define as variáveis para o template
$pageTitle = 'Login - Sistema de Turismo';
$showNavbar = false;

// Carrega o template de login
ob_start();
include __DIR__ . '/../template/auth/login.php';
$content = ob_get_clean();

// Carrega o layout principal
include __DIR__ . '/../template/layouts/main.php';

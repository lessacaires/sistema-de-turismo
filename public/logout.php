<?php

// Carrega o arquivo de funções de autenticação
require_once __DIR__ . '/../functions/auth.php';

// Faz logout do usuário
logout_user();

// Redireciona para a página de login
header('Location: login.php');
exit;

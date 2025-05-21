<?php

/**
 * Verifica se o usuário atual tem a permissão especificada
 *
 * @param string $permission Nome da permissão a ser verificada
 * @return bool Retorna true se o usuário tem a permissão, false caso contrário
 */
function has_permission($permission) {
    // Se o usuário não estiver logado, não tem permissão
    if (!is_logged_in()) {
        return false;
    }

    // Obter o papel do usuário do banco de dados
    global $pdo;
    if (!isset($pdo)) {
        $pdo = connect_db();
    }

    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([get_logged_in_user_id()]);
    $role = $stmt->fetchColumn();

    // Definir as permissões para cada papel
    $permissions = [
        'admin' => ['admin', 'receptive', 'tours', 'restaurant', 'bar', 'pos', 'financial', 'stock', 'employees', 'purchases'],
        'manager' => ['receptive', 'tours', 'restaurant', 'bar', 'pos', 'financial', 'stock', 'employees', 'purchases'],
        'receptionist' => ['receptive', 'tours'],
        'waiter' => ['restaurant', 'bar'],
        'bartender' => ['bar'],
        'cashier' => ['pos', 'restaurant', 'bar']
    ];

    // Verificar se o papel do usuário tem a permissão solicitada
    return in_array($role, array_keys($permissions)) && in_array($permission, $permissions[$role]);
}

/**
 * Formata um valor monetário para exibição
 *
 * @param float $value Valor a ser formatado
 * @return string Valor formatado como moeda (R$ 0,00)
 */
function format_money($value) {
    return 'R$ ' . number_format($value, 2, ',', '.');
}

/**
 * Formata uma data para exibição
 *
 * @param string $date Data no formato Y-m-d ou Y-m-d H:i:s
 * @param bool $showTime Se deve mostrar o horário
 * @return string Data formatada (dd/mm/yyyy ou dd/mm/yyyy hh:mm)
 */
function format_date($date, $showTime = false) {
    if (empty($date)) {
        return '';
    }

    $format = $showTime ? 'd/m/Y H:i' : 'd/m/Y';
    $dateObj = new DateTime($date);
    return $dateObj->format($format);
}

/**
 * Gera um número de pedido/reserva único
 *
 * @param string $prefix Prefixo para o número (ex: 'RES', 'ORD')
 * @return string Número único no formato PREFIX-YYYYMMDD-XXXX
 */
function generate_reference_number($prefix = 'REF') {
    $date = date('Ymd');
    $random = mt_rand(1000, 9999);
    return $prefix . '-' . $date . '-' . $random;
}
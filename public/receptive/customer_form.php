<?php

// Carrega os arquivos necessários
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../functions/database.php';
require_once __DIR__ . '/../../functions/auth.php';
require_once __DIR__ . '/../../functions/utils.php';

// Conecta ao banco de dados
$pdo = connect_db();

// Verifica se o usuário está logado
if (!is_logged_in()) {
    header('Location: ../login.php');
    exit;
}

// Verifica se o usuário tem permissão para acessar esta página
if (!has_permission('receptive')) {
    header('Location: ../index.php');
    exit;
}

// Inicializa variáveis
$alertMessage = null;
$alertType = null;
$customer = [
    'id' => null,
    'full_name' => '',
    'cpf' => '',
    'email' => '',
    'phone' => '',
    'address' => '',
    'birth_date' => '',
    'notes' => ''
];

// Verifica se é uma edição
$isEdit = isset($_GET['id']) && is_numeric($_GET['id']);

if ($isEdit) {
    // Obtém os dados do cliente
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $customerData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($customerData) {
        $customer = $customerData;
    } else {
        $alertMessage = "Cliente não encontrado.";
        $alertType = "danger";
        $isEdit = false;
    }
}

// Processa o formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obtém os dados do formulário
    $customer['full_name'] = $_POST['full_name'] ?? '';
    $customer['cpf'] = $_POST['cpf'] ?? '';
    $customer['email'] = $_POST['email'] ?? '';
    $customer['phone'] = $_POST['phone'] ?? '';
    $customer['address'] = $_POST['address'] ?? '';
    $customer['birth_date'] = $_POST['birth_date'] ?? '';
    $customer['notes'] = $_POST['notes'] ?? '';
    
    // Validação básica
    $errors = [];
    
    if (empty($customer['full_name'])) {
        $errors[] = "O nome completo é obrigatório.";
    }
    
    if (!empty($customer['cpf'])) {
        // Verifica se o CPF já existe (exceto para o cliente atual em caso de edição)
        $sql = "SELECT id FROM customers WHERE cpf = ? AND id != ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$customer['cpf'], $isEdit ? $customer['id'] : 0]);
        
        if ($stmt->fetchColumn()) {
            $errors[] = "Este CPF já está cadastrado para outro cliente.";
        }
    }
    
    if (!empty($customer['email'])) {
        // Verifica se o e-mail já existe (exceto para o cliente atual em caso de edição)
        $sql = "SELECT id FROM customers WHERE email = ? AND id != ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$customer['email'], $isEdit ? $customer['id'] : 0]);
        
        if ($stmt->fetchColumn()) {
            $errors[] = "Este e-mail já está cadastrado para outro cliente.";
        }
    }
    
    // Se não houver erros, salva os dados
    if (empty($errors)) {
        if ($isEdit) {
            // Atualiza o cliente existente
            $sql = "UPDATE customers SET 
                full_name = ?, 
                cpf = ?, 
                email = ?, 
                phone = ?, 
                address = ?, 
                birth_date = ?, 
                notes = ? 
                WHERE id = ?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $customer['full_name'],
                $customer['cpf'],
                $customer['email'],
                $customer['phone'],
                $customer['address'],
                $customer['birth_date'],
                $customer['notes'],
                $customer['id']
            ]);
            
            $alertMessage = "Cliente atualizado com sucesso!";
            $alertType = "success";
        } else {
            // Insere um novo cliente
            $sql = "INSERT INTO customers (
                full_name, 
                cpf, 
                email, 
                phone, 
                address, 
                birth_date, 
                notes, 
                registration_date
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $customer['full_name'],
                $customer['cpf'],
                $customer['email'],
                $customer['phone'],
                $customer['address'],
                $customer['birth_date'],
                $customer['notes']
            ]);
            
            $customerId = $pdo->lastInsertId();
            
            $alertMessage = "Cliente cadastrado com sucesso!";
            $alertType = "success";
            
            // Limpa o formulário para um novo cadastro
            $customer = [
                'id' => null,
                'full_name' => '',
                'cpf' => '',
                'email' => '',
                'phone' => '',
                'address' => '',
                'birth_date' => '',
                'notes' => ''
            ];
        }
    } else {
        // Se houver erros, exibe-os
        $alertMessage = implode("<br>", $errors);
        $alertType = "danger";
    }
}

// Define as variáveis para o template
$pageTitle = ($isEdit ? 'Editar' : 'Novo') . ' Cliente - Sistema de Turismo';
$pageHeader = ($isEdit ? 'Editar' : 'Novo') . ' Cliente';
$showNavbar = true;

// Carrega o conteúdo da página
ob_start();
?>

<div class="row mb-4">
    <div class="col-md-12">
        <a href="customers.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Voltar para a lista
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0"><?= $isEdit ? 'Editar' : 'Novo' ?> Cliente</h5>
    </div>
    <div class="card-body">
        <form action="" method="post">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="full_name" class="form-label">Nome Completo *</label>
                    <input type="text" class="form-control" id="full_name" name="full_name" value="<?= htmlspecialchars($customer['full_name']) ?>" required>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="cpf" class="form-label">CPF</label>
                    <input type="text" class="form-control" id="cpf" name="cpf" value="<?= htmlspecialchars($customer['cpf']) ?>">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="email" class="form-label">E-mail</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($customer['email']) ?>">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="phone" class="form-label">Telefone</label>
                    <input type="text" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($customer['phone']) ?>">
                </div>
                
                <div class="col-md-12 mb-3">
                    <label for="address" class="form-label">Endereço</label>
                    <input type="text" class="form-control" id="address" name="address" value="<?= htmlspecialchars($customer['address']) ?>">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="birth_date" class="form-label">Data de Nascimento</label>
                    <input type="date" class="form-control" id="birth_date" name="birth_date" value="<?= htmlspecialchars($customer['birth_date']) ?>">
                </div>
                
                <div class="col-md-12 mb-3">
                    <label for="notes" class="form-label">Observações</label>
                    <textarea class="form-control" id="notes" name="notes" rows="3"><?= htmlspecialchars($customer['notes']) ?></textarea>
                </div>
            </div>
            
            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                <a href="customers.php" class="btn btn-secondary me-md-2">Cancelar</a>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();

// Carrega o layout principal
include __DIR__ . '/../../template/layouts/main.php';

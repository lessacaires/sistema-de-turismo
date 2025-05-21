<?php

// Carrega os arquivos necessários
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../functions/database.php';
require_once __DIR__ . '/../../../functions/auth.php';
require_once __DIR__ . '/../../../functions/utils.php';

// Conecta ao banco de dados
$pdo = connect_db();

// Verifica se o usuário está logado
if (!is_logged_in()) {
    header('Location: ../../login.php');
    exit;
}

// Verifica se o usuário tem permissão para acessar esta página
if (!has_permission('purchases')) {
    header('Location: ../../index.php');
    exit;
}

// Inicializa variáveis
$alertMessage = null;
$alertType = null;
$supplier = [
    'id' => null,
    'name' => '',
    'cnpj' => '',
    'contact_name' => '',
    'email' => '',
    'phone' => '',
    'address' => '',
    'notes' => '',
    'is_active' => 1
];

// Verifica se é uma edição
$isEdit = isset($_GET['id']) && is_numeric($_GET['id']);

if ($isEdit) {
    // Obtém os dados do fornecedor
    $stmt = $pdo->prepare("SELECT * FROM suppliers WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $supplierData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($supplierData) {
        $supplier = $supplierData;
    } else {
        $alertMessage = "Fornecedor não encontrado.";
        $alertType = "danger";
        $isEdit = false;
    }
}

// Processa o formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obtém os dados do formulário
    $supplier['name'] = $_POST['name'] ?? '';
    $supplier['cnpj'] = $_POST['cnpj'] ?? '';
    $supplier['contact_name'] = $_POST['contact_name'] ?? '';
    $supplier['email'] = $_POST['email'] ?? '';
    $supplier['phone'] = $_POST['phone'] ?? '';
    $supplier['address'] = $_POST['address'] ?? '';
    $supplier['notes'] = $_POST['notes'] ?? '';
    $supplier['is_active'] = isset($_POST['is_active']) ? 1 : 0;
    
    // Validação básica
    $errors = [];
    
    if (empty($supplier['name'])) {
        $errors[] = "O nome do fornecedor é obrigatório.";
    }
    
    if (!empty($supplier['cnpj'])) {
        // Verifica se o CNPJ já existe (exceto para o fornecedor atual em caso de edição)
        $sql = "SELECT id FROM suppliers WHERE cnpj = ? AND id != ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$supplier['cnpj'], $isEdit ? $supplier['id'] : 0]);
        
        if ($stmt->fetchColumn()) {
            $errors[] = "Este CNPJ já está cadastrado para outro fornecedor.";
        }
    }
    
    if (!empty($supplier['email'])) {
        // Verifica se o e-mail já existe (exceto para o fornecedor atual em caso de edição)
        $sql = "SELECT id FROM suppliers WHERE email = ? AND id != ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$supplier['email'], $isEdit ? $supplier['id'] : 0]);
        
        if ($stmt->fetchColumn()) {
            $errors[] = "Este e-mail já está cadastrado para outro fornecedor.";
        }
    }
    
    // Se não houver erros, salva os dados
    if (empty($errors)) {
        if ($isEdit) {
            // Atualiza o fornecedor existente
            $sql = "UPDATE suppliers SET 
                name = ?, 
                cnpj = ?, 
                contact_name = ?, 
                email = ?, 
                phone = ?, 
                address = ?, 
                notes = ?, 
                is_active = ?
                WHERE id = ?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $supplier['name'],
                $supplier['cnpj'],
                $supplier['contact_name'],
                $supplier['email'],
                $supplier['phone'],
                $supplier['address'],
                $supplier['notes'],
                $supplier['is_active'],
                $supplier['id']
            ]);
            
            $alertMessage = "Fornecedor atualizado com sucesso!";
            $alertType = "success";
        } else {
            // Insere um novo fornecedor
            $sql = "INSERT INTO suppliers (
                name, 
                cnpj, 
                contact_name, 
                email, 
                phone, 
                address, 
                notes, 
                is_active
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $supplier['name'],
                $supplier['cnpj'],
                $supplier['contact_name'],
                $supplier['email'],
                $supplier['phone'],
                $supplier['address'],
                $supplier['notes'],
                $supplier['is_active']
            ]);
            
            $supplierId = $pdo->lastInsertId();
            
            $alertMessage = "Fornecedor cadastrado com sucesso!";
            $alertType = "success";
            
            // Redireciona para a página de edição
            header("Location: supplier_form.php?id=$supplierId");
            exit;
        }
    } else {
        // Se houver erros, exibe-os
        $alertMessage = implode("<br>", $errors);
        $alertType = "danger";
    }
}

// Define as variáveis para o template
$pageTitle = ($isEdit ? 'Editar' : 'Novo') . ' Fornecedor - Sistema de Turismo';
$pageHeader = ($isEdit ? 'Editar' : 'Novo') . ' Fornecedor';
$showNavbar = true;

// Carrega o conteúdo da página
ob_start();
?>

<div class="row mb-4">
    <div class="col-md-12">
        <a href="suppliers.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Voltar para a lista
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0"><?= $isEdit ? 'Editar' : 'Novo' ?> Fornecedor</h5>
    </div>
    <div class="card-body">
        <form action="" method="post">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="name" class="form-label">Nome do Fornecedor *</label>
                    <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($supplier['name']) ?>" required>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="cnpj" class="form-label">CNPJ</label>
                    <input type="text" class="form-control" id="cnpj" name="cnpj" value="<?= htmlspecialchars($supplier['cnpj']) ?>">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="contact_name" class="form-label">Nome do Contato</label>
                    <input type="text" class="form-control" id="contact_name" name="contact_name" value="<?= htmlspecialchars($supplier['contact_name']) ?>">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="email" class="form-label">E-mail</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($supplier['email']) ?>">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="phone" class="form-label">Telefone</label>
                    <input type="text" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($supplier['phone']) ?>">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="address" class="form-label">Endereço</label>
                    <input type="text" class="form-control" id="address" name="address" value="<?= htmlspecialchars($supplier['address']) ?>">
                </div>
                
                <div class="col-md-12 mb-3">
                    <label for="notes" class="form-label">Observações</label>
                    <textarea class="form-control" id="notes" name="notes" rows="3"><?= htmlspecialchars($supplier['notes']) ?></textarea>
                </div>
                
                <div class="col-md-12 mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" <?= $supplier['is_active'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">
                            Fornecedor ativo
                        </label>
                    </div>
                </div>
            </div>
            
            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                <a href="suppliers.php" class="btn btn-secondary me-md-2">Cancelar</a>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>

<script>
// Formata o campo de CNPJ
document.getElementById('cnpj').addEventListener('blur', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length === 14) {
        value = value.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5');
        e.target.value = value;
    }
});
</script>

<?php
$content = ob_get_clean();

// Carrega o layout principal
include __DIR__ . '/../../../template/layouts/main.php';

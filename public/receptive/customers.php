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

// Processa a exclusão de cliente
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $customerId = $_GET['delete'];
    
    // Verifica se o cliente existe
    $stmt = $pdo->prepare("SELECT id FROM customers WHERE id = ?");
    $stmt->execute([$customerId]);
    
    if ($stmt->fetchColumn()) {
        // Verifica se o cliente possui reservas
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tour_bookings WHERE customer_id = ?");
        $stmt->execute([$customerId]);
        $bookingCount = $stmt->fetchColumn();
        
        if ($bookingCount > 0) {
            $alertMessage = "Não é possível excluir este cliente pois ele possui reservas associadas.";
            $alertType = "danger";
        } else {
            // Exclui o cliente
            $stmt = $pdo->prepare("DELETE FROM customers WHERE id = ?");
            $stmt->execute([$customerId]);
            
            $alertMessage = "Cliente excluído com sucesso!";
            $alertType = "success";
        }
    } else {
        $alertMessage = "Cliente não encontrado.";
        $alertType = "danger";
    }
}

// Processa a busca de clientes
$searchTerm = $_GET['search'] ?? '';
$searchParams = [];

if (!empty($searchTerm)) {
    $searchQuery = "WHERE full_name LIKE :search OR email LIKE :search OR phone LIKE :search OR cpf LIKE :search";
    $searchParams[':search'] = "%$searchTerm%";
} else {
    $searchQuery = "";
}

// Obtém a lista de clientes
$stmt = $pdo->prepare("
    SELECT * FROM customers 
    $searchQuery
    ORDER BY full_name ASC
");
$stmt->execute($searchParams);
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Define as variáveis para o template
$pageTitle = 'Clientes - Sistema de Turismo';
$pageHeader = 'Gerenciamento de Clientes';
$showNavbar = true;

// Carrega o conteúdo da página
ob_start();
?>

<div class="row mb-4">
    <div class="col-md-6">
        <form action="" method="get" class="d-flex">
            <input type="text" name="search" class="form-control me-2" placeholder="Buscar cliente..." value="<?= htmlspecialchars($searchTerm) ?>">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i>
            </button>
        </form>
    </div>
    <div class="col-md-6 text-end">
        <a href="customer_form.php" class="btn btn-success">
            <i class="fas fa-plus"></i> Novo Cliente
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Lista de Clientes</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>CPF</th>
                        <th>E-mail</th>
                        <th>Telefone</th>
                        <th>Data de Cadastro</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($customers) > 0): ?>
                        <?php foreach ($customers as $customer): ?>
                            <tr>
                                <td><?= htmlspecialchars($customer['full_name']) ?></td>
                                <td><?= htmlspecialchars($customer['cpf']) ?></td>
                                <td><?= htmlspecialchars($customer['email']) ?></td>
                                <td><?= htmlspecialchars($customer['phone']) ?></td>
                                <td><?= format_date($customer['registration_date'], true) ?></td>
                                <td>
                                    <a href="customer_view.php?id=<?= $customer['id'] ?>" class="btn btn-sm btn-info" title="Visualizar">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="customer_form.php?id=<?= $customer['id'] ?>" class="btn btn-sm btn-primary" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="?delete=<?= $customer['id'] ?>" class="btn btn-sm btn-danger" title="Excluir" onclick="return confirmDelete('Tem certeza que deseja excluir este cliente?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center">Nenhum cliente encontrado.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

// Carrega o layout principal
include __DIR__ . '/../../template/layouts/main.php';

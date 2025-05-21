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
if (!has_permission('restaurant')) {
    header('Location: ../index.php');
    exit;
}

// Inicializa variáveis
$alertMessage = null;
$alertType = null;

// Processa a exclusão de mesa
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $tableId = $_GET['delete'];
    
    // Verifica se a mesa existe
    $stmt = $pdo->prepare("SELECT id FROM tables WHERE id = ?");
    $stmt->execute([$tableId]);
    
    if ($stmt->fetchColumn()) {
        // Verifica se a mesa possui comandas abertas
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE table_id = ? AND status != 'closed' AND status != 'cancelled'");
        $stmt->execute([$tableId]);
        $orderCount = $stmt->fetchColumn();
        
        if ($orderCount > 0) {
            $alertMessage = "Não é possível excluir esta mesa pois ela possui comandas abertas.";
            $alertType = "danger";
        } else {
            // Exclui a mesa
            $stmt = $pdo->prepare("DELETE FROM tables WHERE id = ?");
            $stmt->execute([$tableId]);
            
            $alertMessage = "Mesa excluída com sucesso!";
            $alertType = "success";
        }
    } else {
        $alertMessage = "Mesa não encontrada.";
        $alertType = "danger";
    }
}

// Processa a adição de nova mesa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_table'])) {
    $number = $_POST['number'] ?? '';
    $capacity = $_POST['capacity'] ?? 0;
    $location = $_POST['location'] ?? 'restaurant';
    
    // Validação básica
    $errors = [];
    
    if (empty($number)) {
        $errors[] = "O número da mesa é obrigatório.";
    }
    
    if ($capacity <= 0) {
        $errors[] = "A capacidade deve ser maior que zero.";
    }
    
    // Verifica se já existe uma mesa com este número
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tables WHERE number = ?");
    $stmt->execute([$number]);
    
    if ($stmt->fetchColumn() > 0) {
        $errors[] = "Já existe uma mesa com este número.";
    }
    
    // Se não houver erros, salva a mesa
    if (empty($errors)) {
        $sql = "INSERT INTO tables (number, capacity, location, status) VALUES (?, ?, ?, 'available')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$number, $capacity, $location]);
        
        $alertMessage = "Mesa adicionada com sucesso!";
        $alertType = "success";
    } else {
        // Se houver erros, exibe-os
        $alertMessage = implode("<br>", $errors);
        $alertType = "danger";
    }
}

// Processa a atualização do status da mesa
if (isset($_GET['change_status']) && is_numeric($_GET['change_status']) && isset($_GET['status'])) {
    $tableId = $_GET['change_status'];
    $newStatus = $_GET['status'];
    
    // Verifica se o status é válido
    $validStatuses = ['available', 'occupied', 'reserved', 'maintenance'];
    
    if (in_array($newStatus, $validStatuses)) {
        // Verifica se a mesa existe
        $stmt = $pdo->prepare("SELECT id, status FROM tables WHERE id = ?");
        $stmt->execute([$tableId]);
        $table = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($table) {
            // Verifica se a mesa possui comandas abertas
            if ($newStatus === 'available' && $table['status'] === 'occupied') {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE table_id = ? AND status != 'closed' AND status != 'cancelled'");
                $stmt->execute([$tableId]);
                $orderCount = $stmt->fetchColumn();
                
                if ($orderCount > 0) {
                    $alertMessage = "Não é possível liberar esta mesa pois ela possui comandas abertas.";
                    $alertType = "danger";
                } else {
                    // Atualiza o status da mesa
                    $stmt = $pdo->prepare("UPDATE tables SET status = ? WHERE id = ?");
                    $stmt->execute([$newStatus, $tableId]);
                    
                    $alertMessage = "Status da mesa atualizado com sucesso!";
                    $alertType = "success";
                }
            } else {
                // Atualiza o status da mesa
                $stmt = $pdo->prepare("UPDATE tables SET status = ? WHERE id = ?");
                $stmt->execute([$newStatus, $tableId]);
                
                $alertMessage = "Status da mesa atualizado com sucesso!";
                $alertType = "success";
            }
        } else {
            $alertMessage = "Mesa não encontrada.";
            $alertType = "danger";
        }
    } else {
        $alertMessage = "Status inválido.";
        $alertType = "danger";
    }
}

// Filtra as mesas por localização
$location = $_GET['location'] ?? 'all';

if ($location !== 'all') {
    $stmt = $pdo->prepare("SELECT * FROM tables WHERE location = ? ORDER BY number ASC");
    $stmt->execute([$location]);
} else {
    $stmt = $pdo->prepare("SELECT * FROM tables ORDER BY location, number ASC");
    $stmt->execute();
}

$tables = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Define as variáveis para o template
$pageTitle = 'Mesas - Sistema de Turismo';
$pageHeader = 'Gerenciamento de Mesas';
$showNavbar = true;

// Carrega o conteúdo da página
ob_start();
?>

<div class="row mb-4">
    <div class="col-md-6">
        <div class="btn-group" role="group">
            <a href="?location=all" class="btn btn-outline-primary <?= $location === 'all' ? 'active' : '' ?>">Todas</a>
            <a href="?location=restaurant" class="btn btn-outline-primary <?= $location === 'restaurant' ? 'active' : '' ?>">Restaurante</a>
            <a href="?location=bar" class="btn btn-outline-primary <?= $location === 'bar' ? 'active' : '' ?>">Bar</a>
            <a href="?location=outdoor" class="btn btn-outline-primary <?= $location === 'outdoor' ? 'active' : '' ?>">Área Externa</a>
        </div>
    </div>
    <div class="col-md-6 text-end">
        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addTableModal">
            <i class="fas fa-plus"></i> Nova Mesa
        </button>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Mapa de Mesas</h5>
    </div>
    <div class="card-body">
        <div class="table-map">
            <?php if (count($tables) > 0): ?>
                <?php foreach ($tables as $table): ?>
                    <div class="table-item <?= $table['status'] ?>">
                        <h4><?= htmlspecialchars($table['number']) ?></h4>
                        <p>Capacidade: <?= $table['capacity'] ?></p>
                        <p>
                            <?php
                            $locationText = '';
                            switch ($table['location']) {
                                case 'restaurant':
                                    $locationText = 'Restaurante';
                                    break;
                                case 'bar':
                                    $locationText = 'Bar';
                                    break;
                                case 'outdoor':
                                    $locationText = 'Área Externa';
                                    break;
                            }
                            
                            $statusText = '';
                            $statusClass = '';
                            switch ($table['status']) {
                                case 'available':
                                    $statusText = 'Disponível';
                                    $statusClass = 'success';
                                    break;
                                case 'occupied':
                                    $statusText = 'Ocupada';
                                    $statusClass = 'danger';
                                    break;
                                case 'reserved':
                                    $statusText = 'Reservada';
                                    $statusClass = 'warning';
                                    break;
                                case 'maintenance':
                                    $statusText = 'Manutenção';
                                    $statusClass = 'secondary';
                                    break;
                            }
                            ?>
                            <?= $locationText ?><br>
                            <span class="badge bg-<?= $statusClass ?>"><?= $statusText ?></span>
                        </p>
                        <div class="btn-group mt-2" role="group">
                            <?php if ($table['status'] !== 'available'): ?>
                                <a href="?change_status=<?= $table['id'] ?>&status=available&location=<?= $location ?>" class="btn btn-sm btn-success" title="Liberar">
                                    <i class="fas fa-check"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($table['status'] !== 'occupied'): ?>
                                <a href="?change_status=<?= $table['id'] ?>&status=occupied&location=<?= $location ?>" class="btn btn-sm btn-danger" title="Ocupar">
                                    <i class="fas fa-utensils"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($table['status'] !== 'reserved'): ?>
                                <a href="?change_status=<?= $table['id'] ?>&status=reserved&location=<?= $location ?>" class="btn btn-sm btn-warning" title="Reservar">
                                    <i class="fas fa-calendar-check"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($table['status'] !== 'maintenance'): ?>
                                <a href="?change_status=<?= $table['id'] ?>&status=maintenance&location=<?= $location ?>" class="btn btn-sm btn-secondary" title="Manutenção">
                                    <i class="fas fa-tools"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($table['status'] === 'available'): ?>
                                <a href="?delete=<?= $table['id'] ?>&location=<?= $location ?>" class="btn btn-sm btn-outline-danger" title="Excluir" onclick="return confirmDelete('Tem certeza que deseja excluir esta mesa?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($table['status'] === 'occupied'): ?>
                                <a href="orders.php?table_id=<?= $table['id'] ?>" class="btn btn-sm btn-primary" title="Ver Comanda">
                                    <i class="fas fa-receipt"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="alert alert-info">
                    Nenhuma mesa cadastrada. Clique em "Nova Mesa" para adicionar.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal para adicionar nova mesa -->
<div class="modal fade" id="addTableModal" tabindex="-1" aria-labelledby="addTableModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="" method="post">
                <div class="modal-header">
                    <h5 class="modal-title" id="addTableModalLabel">Nova Mesa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="number" class="form-label">Número da Mesa *</label>
                        <input type="text" class="form-control" id="number" name="number" required>
                    </div>
                    <div class="mb-3">
                        <label for="capacity" class="form-label">Capacidade *</label>
                        <input type="number" class="form-control" id="capacity" name="capacity" required min="1" value="4">
                    </div>
                    <div class="mb-3">
                        <label for="location" class="form-label">Localização *</label>
                        <select class="form-select" id="location" name="location" required>
                            <option value="restaurant" <?= $location === 'restaurant' ? 'selected' : '' ?>>Restaurante</option>
                            <option value="bar" <?= $location === 'bar' ? 'selected' : '' ?>>Bar</option>
                            <option value="outdoor" <?= $location === 'outdoor' ? 'selected' : '' ?>>Área Externa</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="add_table" class="btn btn-primary">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

// Carrega o layout principal
include __DIR__ . '/../../template/layouts/main.php';

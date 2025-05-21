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

// Processa o cancelamento de compra
if (isset($_GET['cancel']) && is_numeric($_GET['cancel'])) {
    $purchaseId = $_GET['cancel'];
    
    // Verifica se a compra existe
    $stmt = $pdo->prepare("SELECT id, status FROM purchases WHERE id = ?");
    $stmt->execute([$purchaseId]);
    $purchase = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($purchase) {
        if ($purchase['status'] === 'cancelled') {
            $alertMessage = "Esta compra já está cancelada.";
            $alertType = "warning";
        } elseif ($purchase['status'] === 'delivered') {
            $alertMessage = "Não é possível cancelar uma compra já entregue.";
            $alertType = "danger";
        } else {
            // Inicia uma transação
            $pdo->beginTransaction();
            
            try {
                // Cancela a compra
                $stmt = $pdo->prepare("UPDATE purchases SET status = 'cancelled', payment_status = 'cancelled' WHERE id = ?");
                $stmt->execute([$purchaseId]);
                
                // Confirma a transação
                $pdo->commit();
                
                $alertMessage = "Compra cancelada com sucesso!";
                $alertType = "success";
            } catch (Exception $e) {
                // Reverte a transação em caso de erro
                $pdo->rollBack();
                
                $alertMessage = "Erro ao cancelar a compra: " . $e->getMessage();
                $alertType = "danger";
            }
        }
    } else {
        $alertMessage = "Compra não encontrada.";
        $alertType = "danger";
    }
}

// Filtra as compras por status e período
$status = $_GET['status'] ?? 'all';
$startDate = $_GET['start_date'] ?? date('Y-m-01'); // Primeiro dia do mês atual
$endDate = $_GET['end_date'] ?? date('Y-m-t'); // Último dia do mês atual
$supplierId = $_GET['supplier_id'] ?? '';

// Constrói a consulta SQL com base nos filtros
$sql = "
    SELECT p.*, s.name as supplier_name, e.full_name as employee_name
    FROM purchases p
    JOIN suppliers s ON p.supplier_id = s.id
    LEFT JOIN employees e ON p.employee_id = e.id
    WHERE p.purchase_date BETWEEN ? AND ?
";

$params = [$startDate . ' 00:00:00', $endDate . ' 23:59:59'];

if ($status !== 'all') {
    $sql .= " AND p.status = ?";
    $params[] = $status;
}

if (!empty($supplierId)) {
    $sql .= " AND p.supplier_id = ?";
    $params[] = $supplierId;
}

$sql .= " ORDER BY p.purchase_date DESC";

// Executa a consulta
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtém a lista de fornecedores para o filtro
$stmt = $pdo->prepare("
    SELECT id, name
    FROM suppliers
    WHERE is_active = 1
    ORDER BY name ASC
");
$stmt->execute();
$suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtém o resumo das compras
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_purchases,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN status = 'partial' THEN 1 ELSE 0 END) as partial_count,
        SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered_count,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count,
        SUM(total_amount) as total_amount
    FROM purchases
    WHERE purchase_date BETWEEN ? AND ?
");
$stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
$purchasesSummary = $stmt->fetch(PDO::FETCH_ASSOC);

// Define as variáveis para o template
$pageTitle = 'Controle de Compras - Sistema de Turismo';
$pageHeader = 'Controle de Compras';
$showNavbar = true;

// Carrega o conteúdo da página
ob_start();
?>

<div class="row mb-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Filtrar Compras</h5>
            </div>
            <div class="card-body">
                <form action="" method="get" class="row g-3">
                    <div class="col-md-3">
                        <label for="start_date" class="form-label">Data Inicial</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" value="<?= $startDate ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="end_date" class="form-label">Data Final</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" value="<?= $endDate ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Todos</option>
                            <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pendente</option>
                            <option value="partial" <?= $status === 'partial' ? 'selected' : '' ?>>Parcial</option>
                            <option value="delivered" <?= $status === 'delivered' ? 'selected' : '' ?>>Entregue</option>
                            <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelado</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="supplier_id" class="form-label">Fornecedor</label>
                        <select class="form-select" id="supplier_id" name="supplier_id">
                            <option value="">Todos</option>
                            <?php foreach ($suppliers as $supplier): ?>
                                <option value="<?= $supplier['id'] ?>" <?= $supplierId == $supplier['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($supplier['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-12 d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">Filtrar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-4 text-end">
        <a href="purchase_form.php" class="btn btn-success">
            <i class="fas fa-plus"></i> Nova Compra
        </a>
        <a href="../stock/index.php" class="btn btn-info ms-2">
            <i class="fas fa-boxes"></i> Estoque
        </a>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase">Total de Compras</h6>
                        <h2 class="mb-0"><?= $purchasesSummary['total_purchases'] ?></h2>
                    </div>
                    <i class="fas fa-shopping-cart fa-3x opacity-50"></i>
                </div>
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <a href="?status=all&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>" class="text-white text-decoration-none">Ver todas</a>
                <i class="fas fa-arrow-circle-right text-white"></i>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card bg-warning text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase">Pendentes</h6>
                        <h2 class="mb-0"><?= $purchasesSummary['pending_count'] ?></h2>
                    </div>
                    <i class="fas fa-clock fa-3x opacity-50"></i>
                </div>
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <a href="?status=pending&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>" class="text-white text-decoration-none">Ver pendentes</a>
                <i class="fas fa-arrow-circle-right text-white"></i>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card bg-success text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase">Entregues</h6>
                        <h2 class="mb-0"><?= $purchasesSummary['delivered_count'] ?></h2>
                    </div>
                    <i class="fas fa-check-circle fa-3x opacity-50"></i>
                </div>
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <a href="?status=delivered&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>" class="text-white text-decoration-none">Ver entregues</a>
                <i class="fas fa-arrow-circle-right text-white"></i>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card bg-info text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase">Valor Total</h6>
                        <h2 class="mb-0"><?= format_money($purchasesSummary['total_amount'] ?? 0) ?></h2>
                    </div>
                    <i class="fas fa-dollar-sign fa-3x opacity-50"></i>
                </div>
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <a href="../financial/index.php" class="text-white text-decoration-none">Ver financeiro</a>
                <i class="fas fa-arrow-circle-right text-white"></i>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Lista de Compras</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Data</th>
                        <th>Fornecedor</th>
                        <th>Responsável</th>
                        <th>Valor Total</th>
                        <th>Status</th>
                        <th>Pagamento</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($purchases) > 0): ?>
                        <?php foreach ($purchases as $purchase): ?>
                            <tr>
                                <td><?= $purchase['id'] ?></td>
                                <td><?= format_date($purchase['purchase_date'], true) ?></td>
                                <td><?= htmlspecialchars($purchase['supplier_name']) ?></td>
                                <td><?= htmlspecialchars($purchase['employee_name'] ?? 'N/A') ?></td>
                                <td><?= format_money($purchase['total_amount']) ?></td>
                                <td>
                                    <?php
                                    $statusClass = '';
                                    $statusText = '';
                                    
                                    switch ($purchase['status']) {
                                        case 'pending':
                                            $statusClass = 'warning';
                                            $statusText = 'Pendente';
                                            break;
                                        case 'partial':
                                            $statusClass = 'info';
                                            $statusText = 'Parcial';
                                            break;
                                        case 'delivered':
                                            $statusClass = 'success';
                                            $statusText = 'Entregue';
                                            break;
                                        case 'cancelled':
                                            $statusClass = 'danger';
                                            $statusText = 'Cancelado';
                                            break;
                                    }
                                    ?>
                                    <span class="badge bg-<?= $statusClass ?>"><?= $statusText ?></span>
                                </td>
                                <td>
                                    <?php
                                    $paymentStatusClass = '';
                                    $paymentStatusText = '';
                                    
                                    switch ($purchase['payment_status']) {
                                        case 'pending':
                                            $paymentStatusClass = 'warning';
                                            $paymentStatusText = 'Pendente';
                                            break;
                                        case 'paid':
                                            $paymentStatusClass = 'success';
                                            $paymentStatusText = 'Pago';
                                            break;
                                        case 'cancelled':
                                            $paymentStatusClass = 'danger';
                                            $paymentStatusText = 'Cancelado';
                                            break;
                                    }
                                    ?>
                                    <span class="badge bg-<?= $paymentStatusClass ?>"><?= $paymentStatusText ?></span>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="purchase_view.php?id=<?= $purchase['id'] ?>" class="btn btn-sm btn-info" title="Visualizar">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <?php if ($purchase['status'] !== 'delivered' && $purchase['status'] !== 'cancelled'): ?>
                                            <a href="purchase_form.php?id=<?= $purchase['id'] ?>" class="btn btn-sm btn-primary" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            
                                            <a href="receive.php?id=<?= $purchase['id'] ?>" class="btn btn-sm btn-success" title="Receber">
                                                <i class="fas fa-truck-loading"></i>
                                            </a>
                                            
                                            <a href="?cancel=<?= $purchase['id'] ?>&status=<?= $status ?>&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>" class="btn btn-sm btn-danger" title="Cancelar" onclick="return confirmDelete('Tem certeza que deseja cancelar esta compra?')">
                                                <i class="fas fa-times-circle"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center">Nenhuma compra encontrada com os filtros selecionados.</td>
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
include __DIR__ . '/../../../template/layouts/main.php';

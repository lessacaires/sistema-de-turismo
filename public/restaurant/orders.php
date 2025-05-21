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
$selectedTableId = $_GET['table_id'] ?? null;
$showNewOrderForm = isset($_GET['new']) && $_GET['new'] == 1;

// Processa a criação de nova comanda
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_order'])) {
    $tableId = $_POST['table_id'] ?? null;
    $customerId = $_POST['customer_id'] ?? null;
    $orderType = $_POST['order_type'] ?? 'table';
    $notes = $_POST['notes'] ?? '';
    
    // Validação básica
    $errors = [];
    
    if ($orderType === 'table' && !$tableId) {
        $errors[] = "Selecione uma mesa para a comanda.";
    }
    
    // Verifica se a mesa já possui uma comanda aberta
    if ($tableId) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM orders 
            WHERE table_id = ? AND status != 'closed' AND status != 'cancelled'
        ");
        $stmt->execute([$tableId]);
        
        if ($stmt->fetchColumn() > 0) {
            $errors[] = "Esta mesa já possui uma comanda aberta.";
        }
        
        // Verifica se a mesa está disponível
        $stmt = $pdo->prepare("SELECT status FROM tables WHERE id = ?");
        $stmt->execute([$tableId]);
        $tableStatus = $stmt->fetchColumn();
        
        if ($tableStatus !== 'available' && $tableStatus !== 'occupied') {
            $errors[] = "Esta mesa não está disponível para abrir uma comanda.";
        }
    }
    
    // Se não houver erros, cria a comanda
    if (empty($errors)) {
        // Inicia uma transação
        $pdo->beginTransaction();
        
        try {
            // Cria a comanda
            $sql = "INSERT INTO orders (
                table_id,
                customer_id,
                employee_id,
                order_type,
                status,
                created_at,
                notes
            ) VALUES (?, ?, ?, ?, 'open', NOW(), ?)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $tableId,
                $customerId,
                get_logged_in_user_id(),
                $orderType,
                $notes
            ]);
            
            $orderId = $pdo->lastInsertId();
            
            // Se for uma comanda de mesa, atualiza o status da mesa para ocupada
            if ($orderType === 'table' && $tableId) {
                $stmt = $pdo->prepare("UPDATE tables SET status = 'occupied' WHERE id = ?");
                $stmt->execute([$tableId]);
            }
            
            // Confirma a transação
            $pdo->commit();
            
            $alertMessage = "Comanda criada com sucesso!";
            $alertType = "success";
            
            // Redireciona para a página de edição da comanda
            header("Location: order_edit.php?id=$orderId");
            exit;
        } catch (Exception $e) {
            // Reverte a transação em caso de erro
            $pdo->rollBack();
            
            $alertMessage = "Erro ao criar a comanda: " . $e->getMessage();
            $alertType = "danger";
        }
    } else {
        // Se houver erros, exibe-os
        $alertMessage = implode("<br>", $errors);
        $alertType = "danger";
    }
}

// Processa o cancelamento de comanda
if (isset($_GET['cancel']) && is_numeric($_GET['cancel'])) {
    $orderId = $_GET['cancel'];
    
    // Verifica se a comanda existe
    $stmt = $pdo->prepare("SELECT id, table_id, status FROM orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($order) {
        if ($order['status'] === 'closed') {
            $alertMessage = "Esta comanda já está fechada.";
            $alertType = "warning";
        } elseif ($order['status'] === 'cancelled') {
            $alertMessage = "Esta comanda já está cancelada.";
            $alertType = "warning";
        } else {
            // Inicia uma transação
            $pdo->beginTransaction();
            
            try {
                // Cancela a comanda
                $stmt = $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?");
                $stmt->execute([$orderId]);
                
                // Se for uma comanda de mesa, libera a mesa
                if ($order['table_id']) {
                    $stmt = $pdo->prepare("UPDATE tables SET status = 'available' WHERE id = ?");
                    $stmt->execute([$order['table_id']]);
                }
                
                // Confirma a transação
                $pdo->commit();
                
                $alertMessage = "Comanda cancelada com sucesso!";
                $alertType = "success";
            } catch (Exception $e) {
                // Reverte a transação em caso de erro
                $pdo->rollBack();
                
                $alertMessage = "Erro ao cancelar a comanda: " . $e->getMessage();
                $alertType = "danger";
            }
        }
    } else {
        $alertMessage = "Comanda não encontrada.";
        $alertType = "danger";
    }
}

// Obtém a lista de mesas disponíveis
$stmt = $pdo->prepare("
    SELECT * FROM tables 
    WHERE status = 'available' OR status = 'occupied'
    ORDER BY location, number ASC
");
$stmt->execute();
$tables = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtém a lista de clientes para o campo de busca
$stmt = $pdo->prepare("SELECT id, full_name FROM customers ORDER BY full_name ASC LIMIT 100");
$stmt->execute();
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Filtra as comandas por status
$status = $_GET['status'] ?? 'open';

if ($status === 'all') {
    $statusFilter = "";
} else {
    $statusFilter = "WHERE o.status = :status";
}

// Se uma mesa específica foi selecionada, filtra por ela
if ($selectedTableId) {
    $statusFilter = $statusFilter ? $statusFilter . " AND o.table_id = :table_id" : "WHERE o.table_id = :table_id";
}

// Obtém a lista de comandas
$sql = "
    SELECT o.*, 
           t.number as table_number, 
           t.location as table_location,
           c.full_name as customer_name,
           e.full_name as employee_name,
           (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) as item_count
    FROM orders o
    LEFT JOIN tables t ON o.table_id = t.id
    LEFT JOIN customers c ON o.customer_id = c.id
    LEFT JOIN employees e ON o.employee_id = e.id
    $statusFilter
    ORDER BY o.created_at DESC
";

$stmt = $pdo->prepare($sql);

if ($status !== 'all') {
    $stmt->bindParam(':status', $status);
}

if ($selectedTableId) {
    $stmt->bindParam(':table_id', $selectedTableId);
}

$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Define as variáveis para o template
$pageTitle = 'Comandas - Sistema de Turismo';
$pageHeader = 'Gerenciamento de Comandas';
$showNavbar = true;

// Carrega o conteúdo da página
ob_start();
?>

<div class="row mb-4">
    <div class="col-md-6">
        <div class="btn-group" role="group">
            <a href="?status=open" class="btn btn-outline-primary <?= $status === 'open' ? 'active' : '' ?>">Abertas</a>
            <a href="?status=in_progress" class="btn btn-outline-primary <?= $status === 'in_progress' ? 'active' : '' ?>">Em Andamento</a>
            <a href="?status=ready" class="btn btn-outline-primary <?= $status === 'ready' ? 'active' : '' ?>">Prontas</a>
            <a href="?status=delivered" class="btn btn-outline-primary <?= $status === 'delivered' ? 'active' : '' ?>">Entregues</a>
            <a href="?status=closed" class="btn btn-outline-primary <?= $status === 'closed' ? 'active' : '' ?>">Fechadas</a>
            <a href="?status=all" class="btn btn-outline-primary <?= $status === 'all' ? 'active' : '' ?>">Todas</a>
        </div>
    </div>
    <div class="col-md-6 text-end">
        <a href="?new=1" class="btn btn-success">
            <i class="fas fa-plus"></i> Nova Comanda
        </a>
    </div>
</div>

<?php if ($showNewOrderForm): ?>
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Nova Comanda</h5>
    </div>
    <div class="card-body">
        <form action="" method="post">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="order_type" class="form-label">Tipo de Comanda *</label>
                    <select class="form-select" id="order_type" name="order_type" required onchange="toggleTableSelection()">
                        <option value="table">Mesa</option>
                        <option value="takeaway">Para Viagem</option>
                        <option value="delivery">Entrega</option>
                    </select>
                </div>
                
                <div class="col-md-6 mb-3" id="table_selection">
                    <label for="table_id" class="form-label">Mesa *</label>
                    <select class="form-select" id="table_id" name="table_id">
                        <option value="">Selecione uma mesa...</option>
                        <?php foreach ($tables as $table): ?>
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
                            ?>
                            <option value="<?= $table['id'] ?>" <?= $selectedTableId == $table['id'] ? 'selected' : '' ?>>
                                Mesa <?= htmlspecialchars($table['number']) ?> (<?= $locationText ?>)
                                <?= $table['status'] === 'occupied' ? ' - Ocupada' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="customer_id" class="form-label">Cliente (opcional)</label>
                    <select class="form-select" id="customer_id" name="customer_id">
                        <option value="">Selecione um cliente...</option>
                        <?php foreach ($customers as $customer): ?>
                            <option value="<?= $customer['id'] ?>">
                                <?= htmlspecialchars($customer['full_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-12 mb-3">
                    <label for="notes" class="form-label">Observações</label>
                    <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
                </div>
            </div>
            
            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                <a href="orders.php" class="btn btn-secondary me-md-2">Cancelar</a>
                <button type="submit" name="create_order" class="btn btn-primary">Criar Comanda</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleTableSelection() {
    const orderType = document.getElementById('order_type').value;
    const tableSelection = document.getElementById('table_selection');
    
    if (orderType === 'table') {
        tableSelection.style.display = 'block';
        document.getElementById('table_id').setAttribute('required', 'required');
    } else {
        tableSelection.style.display = 'none';
        document.getElementById('table_id').removeAttribute('required');
    }
}

// Inicializa o estado do formulário
document.addEventListener('DOMContentLoaded', function() {
    toggleTableSelection();
});
</script>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Lista de Comandas</h5>
    </div>
    <div class="card-body">
        <?php if (count($orders) > 0): ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Mesa</th>
                        <th>Cliente</th>
                        <th>Tipo</th>
                        <th>Itens</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Data/Hora</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><?= $order['id'] ?></td>
                            <td>
                                <?php if ($order['table_id']): ?>
                                    Mesa <?= htmlspecialchars($order['table_number']) ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><?= $order['customer_name'] ? htmlspecialchars($order['customer_name']) : 'Cliente não identificado' ?></td>
                            <td>
                                <?php
                                $orderTypeText = '';
                                switch ($order['order_type']) {
                                    case 'table':
                                        $orderTypeText = 'Mesa';
                                        break;
                                    case 'takeaway':
                                        $orderTypeText = 'Para Viagem';
                                        break;
                                    case 'delivery':
                                        $orderTypeText = 'Entrega';
                                        break;
                                    case 'tour':
                                        $orderTypeText = 'Passeio';
                                        break;
                                }
                                echo $orderTypeText;
                                ?>
                            </td>
                            <td><?= $order['item_count'] ?></td>
                            <td><?= format_money($order['total_amount']) ?></td>
                            <td>
                                <?php
                                $statusClass = '';
                                $statusText = '';
                                
                                switch ($order['status']) {
                                    case 'open':
                                        $statusClass = 'primary';
                                        $statusText = 'Aberta';
                                        break;
                                    case 'in_progress':
                                        $statusClass = 'info';
                                        $statusText = 'Em Andamento';
                                        break;
                                    case 'ready':
                                        $statusClass = 'warning';
                                        $statusText = 'Pronta';
                                        break;
                                    case 'delivered':
                                        $statusClass = 'success';
                                        $statusText = 'Entregue';
                                        break;
                                    case 'closed':
                                        $statusClass = 'secondary';
                                        $statusText = 'Fechada';
                                        break;
                                    case 'cancelled':
                                        $statusClass = 'danger';
                                        $statusText = 'Cancelada';
                                        break;
                                }
                                ?>
                                <span class="badge bg-<?= $statusClass ?>"><?= $statusText ?></span>
                            </td>
                            <td><?= format_date($order['created_at'], true) ?></td>
                            <td>
                                <div class="btn-group" role="group">
                                    <a href="order_view.php?id=<?= $order['id'] ?>" class="btn btn-sm btn-info" title="Visualizar">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    
                                    <?php if ($order['status'] !== 'closed' && $order['status'] !== 'cancelled'): ?>
                                        <a href="order_edit.php?id=<?= $order['id'] ?>" class="btn btn-sm btn-primary" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        
                                        <?php if ($order['status'] === 'delivered' || $order['status'] === 'ready'): ?>
                                            <a href="order_close.php?id=<?= $order['id'] ?>" class="btn btn-sm btn-success" title="Fechar Comanda">
                                                <i class="fas fa-check-circle"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <a href="?cancel=<?= $order['id'] ?>&status=<?= $status ?>" class="btn btn-sm btn-danger" title="Cancelar" onclick="return confirmDelete('Tem certeza que deseja cancelar esta comanda?')">
                                            <i class="fas fa-times-circle"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <p class="text-center">Nenhuma comanda encontrada.</p>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();

// Carrega o layout principal
include __DIR__ . '/../../template/layouts/main.php';

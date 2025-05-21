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

// Verifica se o ID da comanda foi fornecido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: orders.php');
    exit;
}

$orderId = $_GET['id'];

// Obtém os dados da comanda
$stmt = $pdo->prepare("
    SELECT o.*, 
           t.number as table_number, 
           t.location as table_location,
           c.full_name as customer_name
    FROM orders o
    LEFT JOIN tables t ON o.table_id = t.id
    LEFT JOIN customers c ON o.customer_id = c.id
    WHERE o.id = ?
");
$stmt->execute([$orderId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header('Location: orders.php');
    exit;
}

// Verifica se a comanda pode ser editada
if ($order['status'] === 'closed' || $order['status'] === 'cancelled') {
    header('Location: order_view.php?id=' . $orderId);
    exit;
}

// Inicializa variáveis
$alertMessage = null;
$alertType = null;

// Processa a adição de item à comanda
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_item'])) {
    $productId = $_POST['product_id'] ?? null;
    $quantity = $_POST['quantity'] ?? 1;
    $notes = $_POST['item_notes'] ?? '';
    
    // Validação básica
    $errors = [];
    
    if (!$productId) {
        $errors[] = "Selecione um produto.";
    }
    
    if ($quantity <= 0) {
        $errors[] = "A quantidade deve ser maior que zero.";
    }
    
    // Se não houver erros, adiciona o item
    if (empty($errors)) {
        // Obtém os dados do produto
        $stmt = $pdo->prepare("SELECT name, price FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($product) {
            // Calcula o preço total
            $totalPrice = $product['price'] * $quantity;
            
            // Adiciona o item à comanda
            $sql = "INSERT INTO order_items (
                order_id,
                product_id,
                quantity,
                unit_price,
                total_price,
                status,
                notes
            ) VALUES (?, ?, ?, ?, ?, 'pending', ?)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $orderId,
                $productId,
                $quantity,
                $product['price'],
                $totalPrice,
                $notes
            ]);
            
            // Atualiza o total da comanda
            updateOrderTotal($pdo, $orderId);
            
            // Atualiza o status da comanda para "em andamento" se estiver "aberta"
            if ($order['status'] === 'open') {
                $stmt = $pdo->prepare("UPDATE orders SET status = 'in_progress' WHERE id = ?");
                $stmt->execute([$orderId]);
            }
            
            $alertMessage = "Item adicionado com sucesso!";
            $alertType = "success";
            
            // Recarrega os dados da comanda
            $stmt = $pdo->prepare("
                SELECT o.*, 
                       t.number as table_number, 
                       t.location as table_location,
                       c.full_name as customer_name
                FROM orders o
                LEFT JOIN tables t ON o.table_id = t.id
                LEFT JOIN customers c ON o.customer_id = c.id
                WHERE o.id = ?
            ");
            $stmt->execute([$orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $alertMessage = "Produto não encontrado.";
            $alertType = "danger";
        }
    } else {
        // Se houver erros, exibe-os
        $alertMessage = implode("<br>", $errors);
        $alertType = "danger";
    }
}

// Processa a remoção de item da comanda
if (isset($_GET['remove_item']) && is_numeric($_GET['remove_item'])) {
    $itemId = $_GET['remove_item'];
    
    // Verifica se o item pertence a esta comanda
    $stmt = $pdo->prepare("SELECT id FROM order_items WHERE id = ? AND order_id = ?");
    $stmt->execute([$itemId, $orderId]);
    
    if ($stmt->fetchColumn()) {
        // Remove o item
        $stmt = $pdo->prepare("DELETE FROM order_items WHERE id = ?");
        $stmt->execute([$itemId]);
        
        // Atualiza o total da comanda
        updateOrderTotal($pdo, $orderId);
        
        $alertMessage = "Item removido com sucesso!";
        $alertType = "success";
        
        // Recarrega os dados da comanda
        $stmt = $pdo->prepare("
            SELECT o.*, 
                   t.number as table_number, 
                   t.location as table_location,
                   c.full_name as customer_name
            FROM orders o
            LEFT JOIN tables t ON o.table_id = t.id
            LEFT JOIN customers c ON o.customer_id = c.id
            WHERE o.id = ?
        ");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $alertMessage = "Item não encontrado.";
        $alertType = "danger";
    }
}

// Processa a atualização do status do item
if (isset($_GET['update_item_status']) && is_numeric($_GET['update_item_status']) && isset($_GET['status'])) {
    $itemId = $_GET['update_item_status'];
    $newStatus = $_GET['status'];
    
    // Verifica se o status é válido
    $validStatuses = ['pending', 'preparing', 'ready', 'delivered', 'cancelled'];
    
    if (in_array($newStatus, $validStatuses)) {
        // Verifica se o item pertence a esta comanda
        $stmt = $pdo->prepare("SELECT id FROM order_items WHERE id = ? AND order_id = ?");
        $stmt->execute([$itemId, $orderId]);
        
        if ($stmt->fetchColumn()) {
            // Atualiza o status do item
            $stmt = $pdo->prepare("UPDATE order_items SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, $itemId]);
            
            $alertMessage = "Status do item atualizado com sucesso!";
            $alertType = "success";
        } else {
            $alertMessage = "Item não encontrado.";
            $alertType = "danger";
        }
    } else {
        $alertMessage = "Status inválido.";
        $alertType = "danger";
    }
}

// Processa a atualização do status da comanda
if (isset($_GET['update_order_status']) && isset($_GET['status'])) {
    $newStatus = $_GET['status'];
    
    // Verifica se o status é válido
    $validStatuses = ['open', 'in_progress', 'ready', 'delivered'];
    
    if (in_array($newStatus, $validStatuses)) {
        // Atualiza o status da comanda
        $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $orderId]);
        
        $alertMessage = "Status da comanda atualizado com sucesso!";
        $alertType = "success";
        
        // Recarrega os dados da comanda
        $stmt = $pdo->prepare("
            SELECT o.*, 
                   t.number as table_number, 
                   t.location as table_location,
                   c.full_name as customer_name
            FROM orders o
            LEFT JOIN tables t ON o.table_id = t.id
            LEFT JOIN customers c ON o.customer_id = c.id
            WHERE o.id = ?
        ");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $alertMessage = "Status inválido.";
        $alertType = "danger";
    }
}

// Obtém os itens da comanda
$stmt = $pdo->prepare("
    SELECT oi.*, p.name as product_name
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
    ORDER BY oi.id ASC
");
$stmt->execute([$orderId]);
$orderItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtém as categorias de produtos para o menu
$stmt = $pdo->prepare("
    SELECT id, name
    FROM product_categories
    WHERE type IN ('food', 'beverage')
    ORDER BY name ASC
");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtém os produtos para cada categoria
$categoryProducts = [];
foreach ($categories as $category) {
    $stmt = $pdo->prepare("
        SELECT id, name, price, description
        FROM products
        WHERE category_id = ? AND is_active = 1
        ORDER BY name ASC
    ");
    $stmt->execute([$category['id']]);
    $categoryProducts[$category['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Função para atualizar o total da comanda
function updateOrderTotal($pdo, $orderId) {
    $stmt = $pdo->prepare("
        SELECT SUM(total_price) as total
        FROM order_items
        WHERE order_id = ? AND status != 'cancelled'
    ");
    $stmt->execute([$orderId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total = $result['total'] ?: 0;
    
    $stmt = $pdo->prepare("UPDATE orders SET total_amount = ? WHERE id = ?");
    $stmt->execute([$total, $orderId]);
    
    return $total;
}

// Define as variáveis para o template
$pageTitle = 'Editar Comanda - Sistema de Turismo';
$pageHeader = 'Editar Comanda #' . $orderId;
$showNavbar = true;

// Carrega o conteúdo da página
ob_start();
?>

<div class="row mb-4">
    <div class="col-md-6">
        <a href="orders.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Voltar para a lista
        </a>
    </div>
    <div class="col-md-6 text-end">
        <div class="btn-group" role="group">
            <?php if ($order['status'] !== 'open'): ?>
                <a href="?id=<?= $orderId ?>&update_order_status=open" class="btn btn-outline-primary">
                    <i class="fas fa-folder-open"></i> Aberta
                </a>
            <?php endif; ?>
            
            <?php if ($order['status'] !== 'in_progress'): ?>
                <a href="?id=<?= $orderId ?>&update_order_status=in_progress" class="btn btn-outline-info">
                    <i class="fas fa-spinner"></i> Em Andamento
                </a>
            <?php endif; ?>
            
            <?php if ($order['status'] !== 'ready'): ?>
                <a href="?id=<?= $orderId ?>&update_order_status=ready" class="btn btn-outline-warning">
                    <i class="fas fa-check"></i> Pronta
                </a>
            <?php endif; ?>
            
            <?php if ($order['status'] !== 'delivered'): ?>
                <a href="?id=<?= $orderId ?>&update_order_status=delivered" class="btn btn-outline-success">
                    <i class="fas fa-utensils"></i> Entregue
                </a>
            <?php endif; ?>
            
            <a href="order_close.php?id=<?= $orderId ?>" class="btn btn-success">
                <i class="fas fa-cash-register"></i> Fechar Comanda
            </a>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-4 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Detalhes da Comanda</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <strong>Comanda #:</strong> <?= $orderId ?>
                </div>
                
                <div class="mb-3">
                    <strong>Tipo:</strong>
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
                </div>
                
                <?php if ($order['table_id']): ?>
                <div class="mb-3">
                    <strong>Mesa:</strong> <?= htmlspecialchars($order['table_number']) ?>
                    <?php
                    $locationText = '';
                    switch ($order['table_location']) {
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
                    echo " ($locationText)";
                    ?>
                </div>
                <?php endif; ?>
                
                <?php if ($order['customer_id']): ?>
                <div class="mb-3">
                    <strong>Cliente:</strong> <?= htmlspecialchars($order['customer_name']) ?>
                </div>
                <?php endif; ?>
                
                <div class="mb-3">
                    <strong>Data/Hora:</strong> <?= format_date($order['created_at'], true) ?>
                </div>
                
                <div class="mb-3">
                    <strong>Status:</strong>
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
                </div>
                
                <div class="mb-3">
                    <strong>Total:</strong> <?= format_money($order['total_amount']) ?>
                </div>
                
                <?php if (!empty($order['notes'])): ?>
                <div class="mb-3">
                    <strong>Observações:</strong><br>
                    <?= nl2br(htmlspecialchars($order['notes'])) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-8 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Itens da Comanda</h5>
            </div>
            <div class="card-body">
                <?php if (count($orderItems) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Produto</th>
                                <th>Qtd</th>
                                <th>Preço Unit.</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orderItems as $item): ?>
                                <tr>
                                    <td>
                                        <?= htmlspecialchars($item['product_name']) ?>
                                        <?php if (!empty($item['notes'])): ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($item['notes']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $item['quantity'] ?></td>
                                    <td><?= format_money($item['unit_price']) ?></td>
                                    <td><?= format_money($item['total_price']) ?></td>
                                    <td>
                                        <?php
                                        $itemStatusClass = '';
                                        $itemStatusText = '';
                                        
                                        switch ($item['status']) {
                                            case 'pending':
                                                $itemStatusClass = 'primary';
                                                $itemStatusText = 'Pendente';
                                                break;
                                            case 'preparing':
                                                $itemStatusClass = 'info';
                                                $itemStatusText = 'Preparando';
                                                break;
                                            case 'ready':
                                                $itemStatusClass = 'warning';
                                                $itemStatusText = 'Pronto';
                                                break;
                                            case 'delivered':
                                                $itemStatusClass = 'success';
                                                $itemStatusText = 'Entregue';
                                                break;
                                            case 'cancelled':
                                                $itemStatusClass = 'danger';
                                                $itemStatusText = 'Cancelado';
                                                break;
                                        }
                                        ?>
                                        <span class="badge bg-<?= $itemStatusClass ?>"><?= $itemStatusText ?></span>
                                    </td>
                                    <td>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" id="dropdownMenuButton<?= $item['id'] ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                                Ações
                                            </button>
                                            <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton<?= $item['id'] ?>">
                                                <?php if ($item['status'] !== 'pending'): ?>
                                                    <li><a class="dropdown-item" href="?id=<?= $orderId ?>&update_item_status=<?= $item['id'] ?>&status=pending">Marcar como Pendente</a></li>
                                                <?php endif; ?>
                                                
                                                <?php if ($item['status'] !== 'preparing'): ?>
                                                    <li><a class="dropdown-item" href="?id=<?= $orderId ?>&update_item_status=<?= $item['id'] ?>&status=preparing">Marcar como Preparando</a></li>
                                                <?php endif; ?>
                                                
                                                <?php if ($item['status'] !== 'ready'): ?>
                                                    <li><a class="dropdown-item" href="?id=<?= $orderId ?>&update_item_status=<?= $item['id'] ?>&status=ready">Marcar como Pronto</a></li>
                                                <?php endif; ?>
                                                
                                                <?php if ($item['status'] !== 'delivered'): ?>
                                                    <li><a class="dropdown-item" href="?id=<?= $orderId ?>&update_item_status=<?= $item['id'] ?>&status=delivered">Marcar como Entregue</a></li>
                                                <?php endif; ?>
                                                
                                                <?php if ($item['status'] !== 'cancelled'): ?>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li><a class="dropdown-item text-danger" href="?id=<?= $orderId ?>&update_item_status=<?= $item['id'] ?>&status=cancelled">Cancelar Item</a></li>
                                                <?php endif; ?>
                                                
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a class="dropdown-item text-danger" href="?id=<?= $orderId ?>&remove_item=<?= $item['id'] ?>" onclick="return confirmDelete('Tem certeza que deseja remover este item?')">Remover Item</a></li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="3" class="text-end">Total:</th>
                                <th><?= format_money($order['total_amount']) ?></th>
                                <th colspan="2"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php else: ?>
                <p class="text-center">Nenhum item adicionado à comanda.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Adicionar Item</h5>
    </div>
    <div class="card-body">
        <ul class="nav nav-tabs" id="productTabs" role="tablist">
            <?php foreach ($categories as $index => $category): ?>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $index === 0 ? 'active' : '' ?>" id="category-<?= $category['id'] ?>-tab" data-bs-toggle="tab" data-bs-target="#category-<?= $category['id'] ?>" type="button" role="tab" aria-controls="category-<?= $category['id'] ?>" aria-selected="<?= $index === 0 ? 'true' : 'false' ?>">
                        <?= htmlspecialchars($category['name']) ?>
                    </button>
                </li>
            <?php endforeach; ?>
        </ul>
        
        <div class="tab-content p-3" id="productTabsContent">
            <?php foreach ($categories as $index => $category): ?>
                <div class="tab-pane fade <?= $index === 0 ? 'show active' : '' ?>" id="category-<?= $category['id'] ?>" role="tabpanel" aria-labelledby="category-<?= $category['id'] ?>-tab">
                    <div class="row">
                        <?php if (isset($categoryProducts[$category['id']]) && count($categoryProducts[$category['id']]) > 0): ?>
                            <?php foreach ($categoryProducts[$category['id']] as $product): ?>
                                <div class="col-md-3 mb-3">
                                    <div class="card h-100">
                                        <div class="card-body">
                                            <h6 class="card-title"><?= htmlspecialchars($product['name']) ?></h6>
                                            <p class="card-text"><?= format_money($product['price']) ?></p>
                                            <form action="" method="post" class="d-flex">
                                                <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                                <input type="number" name="quantity" value="1" min="1" class="form-control form-control-sm me-2" style="width: 60px;">
                                                <button type="submit" name="add_item" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="col-12">
                                <p class="text-center">Nenhum produto encontrado nesta categoria.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="mt-4">
            <form action="" method="post" class="row g-3">
                <div class="col-md-4">
                    <label for="product_id" class="form-label">Produto</label>
                    <select class="form-select" id="product_id" name="product_id" required>
                        <option value="">Selecione um produto...</option>
                        <?php foreach ($categories as $category): ?>
                            <?php if (isset($categoryProducts[$category['id']]) && count($categoryProducts[$category['id']]) > 0): ?>
                                <optgroup label="<?= htmlspecialchars($category['name']) ?>">
                                    <?php foreach ($categoryProducts[$category['id']] as $product): ?>
                                        <option value="<?= $product['id'] ?>">
                                            <?= htmlspecialchars($product['name']) ?> - <?= format_money($product['price']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label for="quantity" class="form-label">Quantidade</label>
                    <input type="number" class="form-control" id="quantity" name="quantity" value="1" min="1" required>
                </div>
                
                <div class="col-md-4">
                    <label for="item_notes" class="form-label">Observações</label>
                    <input type="text" class="form-control" id="item_notes" name="item_notes" placeholder="Ex: Sem cebola, bem passado, etc.">
                </div>
                
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" name="add_item" class="btn btn-primary w-100">Adicionar Item</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

// Carrega o layout principal
include __DIR__ . '/../../template/layouts/main.php';

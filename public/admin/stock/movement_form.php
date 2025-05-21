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
if (!has_permission('stock')) {
    header('Location: ../../index.php');
    exit;
}

// Inicializa variáveis
$alertMessage = null;
$alertType = null;
$selectedProductId = $_GET['product_id'] ?? null;

// Obtém a lista de produtos
$stmt = $pdo->prepare("
    SELECT p.id, p.name, p.stock_quantity, pc.name as category_name
    FROM products p
    JOIN product_categories pc ON p.category_id = pc.id
    WHERE p.is_active = 1
    ORDER BY p.name ASC
");
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Processa o formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productId = $_POST['product_id'] ?? null;
    $quantity = $_POST['quantity'] ?? 0;
    $movementType = $_POST['movement_type'] ?? '';
    $notes = $_POST['notes'] ?? '';
    
    // Validação básica
    $errors = [];
    
    if (!$productId) {
        $errors[] = "Selecione um produto.";
    }
    
    if ($quantity <= 0) {
        $errors[] = "A quantidade deve ser maior que zero.";
    }
    
    if (empty($movementType)) {
        $errors[] = "Selecione o tipo de movimentação.";
    }
    
    // Se não houver erros, registra a movimentação
    if (empty($errors)) {
        // Inicia uma transação
        $pdo->beginTransaction();
        
        try {
            // Obtém o estoque atual do produto
            $stmt = $pdo->prepare("SELECT stock_quantity FROM products WHERE id = ?");
            $stmt->execute([$productId]);
            $currentStock = $stmt->fetchColumn();
            
            // Se o produto não controla estoque, inicializa com zero
            if ($currentStock === null) {
                $currentStock = 0;
                
                // Atualiza o produto para começar a controlar estoque
                $stmt = $pdo->prepare("UPDATE products SET stock_quantity = 0 WHERE id = ?");
                $stmt->execute([$productId]);
            }
            
            // Calcula o novo estoque
            $finalQuantity = $currentStock;
            
            if ($movementType === 'purchase' || $movementType === 'adjustment' || $movementType === 'transfer') {
                // Entrada de estoque
                $finalQuantity = $currentStock + $quantity;
            } elseif ($movementType === 'sale' || $movementType === 'loss') {
                // Saída de estoque
                $finalQuantity = $currentStock - $quantity;
                
                // Verifica se há estoque suficiente
                if ($finalQuantity < 0 && $movementType === 'sale') {
                    throw new Exception("Estoque insuficiente para esta movimentação.");
                }
                
                // Se for perda, a quantidade é negativa
                $quantity = -$quantity;
            }
            
            // Atualiza o estoque do produto
            $stmt = $pdo->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?");
            $stmt->execute([$finalQuantity, $productId]);
            
            // Registra a movimentação
            $sql = "INSERT INTO stock_movements (
                product_id,
                quantity,
                movement_type,
                reference_id,
                reference_type,
                previous_quantity,
                new_quantity,
                movement_date,
                employee_id,
                notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $productId,
                $quantity,
                $movementType,
                null, // reference_id
                null, // reference_type
                $currentStock,
                $finalQuantity,
                get_logged_in_user_id(),
                $notes
            ]);
            
            // Confirma a transação
            $pdo->commit();
            
            $alertMessage = "Movimentação de estoque registrada com sucesso!";
            $alertType = "success";
            
            // Redireciona para a página de movimentações do produto
            header("Location: movements.php?product_id=$productId");
            exit;
        } catch (Exception $e) {
            // Reverte a transação em caso de erro
            $pdo->rollBack();
            
            $alertMessage = "Erro ao registrar a movimentação: " . $e->getMessage();
            $alertType = "danger";
        }
    } else {
        // Se houver erros, exibe-os
        $alertMessage = implode("<br>", $errors);
        $alertType = "danger";
    }
}

// Define as variáveis para o template
$pageTitle = 'Nova Movimentação de Estoque - Sistema de Turismo';
$pageHeader = 'Nova Movimentação de Estoque';
$showNavbar = true;

// Carrega o conteúdo da página
ob_start();
?>

<div class="row mb-4">
    <div class="col-md-12">
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Voltar para o Controle de Estoque
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Nova Movimentação de Estoque</h5>
    </div>
    <div class="card-body">
        <form action="" method="post">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="product_id" class="form-label">Produto *</label>
                    <select class="form-select" id="product_id" name="product_id" required onchange="updateProductInfo()">
                        <option value="">Selecione um produto...</option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?= $product['id'] ?>" 
                                    data-stock="<?= $product['stock_quantity'] !== null ? $product['stock_quantity'] : 'N/A' ?>"
                                    <?= $selectedProductId == $product['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($product['name']) ?> 
                                (<?= htmlspecialchars($product['category_name']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="current_stock" class="form-label">Estoque Atual</label>
                    <input type="text" class="form-control" id="current_stock" readonly>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="movement_type" class="form-label">Tipo de Movimentação *</label>
                    <select class="form-select" id="movement_type" name="movement_type" required>
                        <option value="">Selecione...</option>
                        <option value="purchase">Compra (Entrada)</option>
                        <option value="adjustment">Ajuste (Entrada)</option>
                        <option value="sale">Venda (Saída)</option>
                        <option value="loss">Perda/Quebra (Saída)</option>
                        <option value="transfer">Transferência</option>
                    </select>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="quantity" class="form-label">Quantidade *</label>
                    <input type="number" class="form-control" id="quantity" name="quantity" min="1" required>
                </div>
                
                <div class="col-md-12 mb-3">
                    <label for="notes" class="form-label">Observações</label>
                    <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                </div>
            </div>
            
            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                <a href="index.php" class="btn btn-secondary me-md-2">Cancelar</a>
                <button type="submit" class="btn btn-primary">Registrar Movimentação</button>
            </div>
        </form>
    </div>
</div>

<script>
function updateProductInfo() {
    const productSelect = document.getElementById('product_id');
    const currentStockInput = document.getElementById('current_stock');
    
    if (productSelect.selectedIndex > 0) {
        const selectedOption = productSelect.options[productSelect.selectedIndex];
        const currentStock = selectedOption.getAttribute('data-stock');
        
        currentStockInput.value = currentStock;
    } else {
        currentStockInput.value = '';
    }
}

// Inicializa o formulário
document.addEventListener('DOMContentLoaded', function() {
    updateProductInfo();
});
</script>

<?php
$content = ob_get_clean();

// Carrega o layout principal
include __DIR__ . '/../../../template/layouts/main.php';

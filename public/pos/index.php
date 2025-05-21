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
if (!has_permission('pos')) {
    header('Location: ../index.php');
    exit;
}

// Inicializa variáveis
$alertMessage = null;
$alertType = null;

// Processa a finalização da venda
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['finalize_sale'])) {
    $productIds = $_POST['product_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    $prices = $_POST['price'] ?? [];
    $customerId = $_POST['customer_id'] ?? null;
    $paymentMethod = $_POST['payment_method'] ?? '';
    $totalAmount = $_POST['total_amount'] ?? 0;
    $notes = $_POST['notes'] ?? '';
    
    // Validação básica
    $errors = [];
    
    if (empty($productIds)) {
        $errors[] = "Adicione pelo menos um produto à venda.";
    }
    
    if (empty($paymentMethod)) {
        $errors[] = "Selecione um método de pagamento.";
    }
    
    // Se não houver erros, finaliza a venda
    if (empty($errors)) {
        // Inicia uma transação
        $pdo->beginTransaction();
        
        try {
            // Cria a comanda/pedido
            $sql = "INSERT INTO orders (
                customer_id,
                employee_id,
                order_type,
                status,
                created_at,
                closed_at,
                total_amount,
                payment_method,
                payment_status,
                notes
            ) VALUES (?, ?, 'takeaway', 'closed', NOW(), NOW(), ?, ?, 'paid', ?)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $customerId,
                get_logged_in_user_id(),
                $totalAmount,
                $paymentMethod,
                $notes
            ]);
            
            $orderId = $pdo->lastInsertId();
            
            // Adiciona os itens à comanda
            for ($i = 0; $i < count($productIds); $i++) {
                $productId = $productIds[$i];
                $quantity = $quantities[$i];
                $price = $prices[$i];
                $totalPrice = $price * $quantity;
                
                $sql = "INSERT INTO order_items (
                    order_id,
                    product_id,
                    quantity,
                    unit_price,
                    total_price,
                    status
                ) VALUES (?, ?, ?, ?, ?, 'delivered')";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $orderId,
                    $productId,
                    $quantity,
                    $price,
                    $totalPrice
                ]);
                
                // Atualiza o estoque
                $stmt = $pdo->prepare("
                    UPDATE products 
                    SET stock_quantity = stock_quantity - ? 
                    WHERE id = ? AND stock_quantity >= ?
                ");
                $stmt->execute([$quantity, $productId, $quantity]);
                
                // Registra a movimentação de estoque
                $stmt = $pdo->prepare("
                    SELECT stock_quantity FROM products WHERE id = ?
                ");
                $stmt->execute([$productId]);
                $newQuantity = $stmt->fetchColumn();
                
                $sql = "INSERT INTO stock_movements (
                    product_id,
                    quantity,
                    movement_type,
                    reference_id,
                    reference_type,
                    previous_quantity,
                    new_quantity,
                    movement_date,
                    employee_id
                ) VALUES (?, ?, 'sale', ?, 'order', ?, ?, NOW(), ?)";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $productId,
                    -$quantity,
                    $orderId,
                    $newQuantity + $quantity,
                    $newQuantity,
                    get_logged_in_user_id()
                ]);
            }
            
            // Registra a transação financeira
            $sql = "INSERT INTO financial_transactions (
                transaction_date,
                amount,
                type,
                category,
                description,
                payment_method,
                reference_id,
                reference_type,
                employee_id
            ) VALUES (NOW(), ?, 'income', 'sale', ?, ?, ?, 'order', ?)";
            
            $description = "Venda PDV #$orderId";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $totalAmount,
                $description,
                $paymentMethod,
                $orderId,
                get_logged_in_user_id()
            ]);
            
            // Confirma a transação
            $pdo->commit();
            
            $alertMessage = "Venda finalizada com sucesso!";
            $alertType = "success";
            
            // Redireciona para a página de impressão do comprovante
            header("Location: receipt.php?id=$orderId");
            exit;
        } catch (Exception $e) {
            // Reverte a transação em caso de erro
            $pdo->rollBack();
            
            $alertMessage = "Erro ao finalizar a venda: " . $e->getMessage();
            $alertType = "danger";
        }
    } else {
        // Se houver erros, exibe-os
        $alertMessage = implode("<br>", $errors);
        $alertType = "danger";
    }
}

// Obtém as categorias de produtos
$stmt = $pdo->prepare("
    SELECT id, name
    FROM product_categories
    WHERE type IN ('food', 'beverage', 'merchandise')
    ORDER BY name ASC
");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtém os produtos para cada categoria
$categoryProducts = [];
foreach ($categories as $category) {
    $stmt = $pdo->prepare("
        SELECT id, name, price, stock_quantity, description
        FROM products
        WHERE category_id = ? AND is_active = 1
        ORDER BY name ASC
    ");
    $stmt->execute([$category['id']]);
    $categoryProducts[$category['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Obtém a lista de clientes para o campo de busca
$stmt = $pdo->prepare("SELECT id, full_name FROM customers ORDER BY full_name ASC LIMIT 100");
$stmt->execute();
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Define as variáveis para o template
$pageTitle = 'PDV - Sistema de Turismo';
$pageHeader = 'Ponto de Venda (PDV)';
$showNavbar = true;

// Adiciona CSS personalizado
$extraCss = '
<style>
    .pos-container {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 1rem;
    }
    
    .product-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 1rem;
    }
    
    .product-item {
        border: 1px solid #dee2e6;
        border-radius: 0.25rem;
        padding: 0.5rem;
        text-align: center;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .product-item:hover {
        background-color: #f8f9fa;
        transform: translateY(-2px);
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    }
    
    .product-item.out-of-stock {
        opacity: 0.5;
        cursor: not-allowed;
    }
    
    .order-summary {
        position: sticky;
        top: 1rem;
    }
</style>
';

// Adiciona JavaScript personalizado
$extraJs = '
<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Inicializa o carrinho vazio
        if (!window.cart) {
            window.cart = [];
        }
        
        // Atualiza a exibição do carrinho
        updateCartDisplay();
    });
    
    function addToCart(productId, productName, price) {
        // Verifica se o produto já está no carrinho
        const existingItem = window.cart.find(item => item.productId === productId);
        
        if (existingItem) {
            // Se o produto já existe, incrementa a quantidade
            existingItem.quantity += 1;
            existingItem.total = existingItem.quantity * existingItem.price;
        } else {
            // Se o produto não existe, adiciona ao carrinho
            window.cart.push({
                productId: productId,
                name: productName,
                price: price,
                quantity: 1,
                total: price
            });
        }
        
        // Atualiza a exibição do carrinho
        updateCartDisplay();
    }
    
    function removeFromCart(index) {
        // Remove o item do carrinho
        window.cart.splice(index, 1);
        
        // Atualiza a exibição do carrinho
        updateCartDisplay();
    }
    
    function updateQuantity(index, newQuantity) {
        // Atualiza a quantidade do item
        if (newQuantity > 0) {
            window.cart[index].quantity = newQuantity;
            window.cart[index].total = newQuantity * window.cart[index].price;
        } else {
            // Se a quantidade for zero ou negativa, remove o item
            removeFromCart(index);
        }
        
        // Atualiza a exibição do carrinho
        updateCartDisplay();
    }
    
    function updateCartDisplay() {
        const cartItemsElement = document.getElementById("cart-items");
        const totalElement = document.getElementById("cart-total");
        const totalAmountInput = document.getElementById("total_amount");
        const finalizeButton = document.getElementById("finalize-button");
        
        // Limpa o conteúdo atual
        cartItemsElement.innerHTML = "";
        
        // Calcula o total
        let total = 0;
        
        // Adiciona os itens ao carrinho
        if (window.cart.length > 0) {
            window.cart.forEach((item, index) => {
                const row = document.createElement("tr");
                
                row.innerHTML = `
                    <td>${item.name}</td>
                    <td>
                        <input type="number" class="form-control form-control-sm" value="${item.quantity}" min="1" style="width: 60px;" onchange="updateQuantity(${index}, parseInt(this.value))">
                        <input type="hidden" name="product_id[]" value="${item.productId}">
                        <input type="hidden" name="quantity[]" value="${item.quantity}">
                        <input type="hidden" name="price[]" value="${item.price}">
                    </td>
                    <td>R$ ${item.price.toFixed(2).replace(".", ",")}</td>
                    <td>R$ ${item.total.toFixed(2).replace(".", ",")}</td>
                    <td>
                        <button type="button" class="btn btn-sm btn-danger" onclick="removeFromCart(${index})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                `;
                
                cartItemsElement.appendChild(row);
                total += item.total;
            });
            
            // Habilita o botão de finalizar
            finalizeButton.disabled = false;
        } else {
            // Carrinho vazio
            const row = document.createElement("tr");
            row.innerHTML = `<td colspan="5" class="text-center">Carrinho vazio</td>`;
            cartItemsElement.appendChild(row);
            
            // Desabilita o botão de finalizar
            finalizeButton.disabled = true;
        }
        
        // Atualiza o total
        totalElement.textContent = `R$ ${total.toFixed(2).replace(".", ",")}`;
        totalAmountInput.value = total;
    }
    
    function clearCart() {
        if (confirm("Tem certeza que deseja limpar o carrinho?")) {
            window.cart = [];
            updateCartDisplay();
        }
    }
</script>
';

// Carrega o conteúdo da página
ob_start();
?>

<div class="pos-container">
    <div>
        <div class="card mb-4">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs" id="productTabs" role="tablist">
                    <?php foreach ($categories as $index => $category): ?>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?= $index === 0 ? 'active' : '' ?>" id="category-<?= $category['id'] ?>-tab" data-bs-toggle="tab" data-bs-target="#category-<?= $category['id'] ?>" type="button" role="tab" aria-controls="category-<?= $category['id'] ?>" aria-selected="<?= $index === 0 ? 'true' : 'false' ?>">
                                <?= htmlspecialchars($category['name']) ?>
                            </button>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content" id="productTabsContent">
                    <?php foreach ($categories as $index => $category): ?>
                        <div class="tab-pane fade <?= $index === 0 ? 'show active' : '' ?>" id="category-<?= $category['id'] ?>" role="tabpanel" aria-labelledby="category-<?= $category['id'] ?>-tab">
                            <div class="product-grid">
                                <?php if (isset($categoryProducts[$category['id']]) && count($categoryProducts[$category['id']]) > 0): ?>
                                    <?php foreach ($categoryProducts[$category['id']] as $product): ?>
                                        <?php 
                                        $outOfStock = false;
                                        if ($product['stock_quantity'] !== null && $product['stock_quantity'] <= 0) {
                                            $outOfStock = true;
                                        }
                                        ?>
                                        <div class="product-item <?= $outOfStock ? 'out-of-stock' : '' ?>" <?= $outOfStock ? '' : 'onclick="addToCart(' . $product['id'] . ', \'' . addslashes($product['name']) . '\', ' . $product['price'] . ')"' ?>>
                                            <h6><?= htmlspecialchars($product['name']) ?></h6>
                                            <p class="mb-0">R$ <?= number_format($product['price'], 2, ',', '.') ?></p>
                                            <?php if ($product['stock_quantity'] !== null): ?>
                                                <small class="text-muted">Estoque: <?= $product['stock_quantity'] ?></small>
                                            <?php endif; ?>
                                            <?php if ($outOfStock): ?>
                                                <span class="badge bg-danger">Sem estoque</span>
                                            <?php endif; ?>
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
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Busca de Produtos</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8 mb-3">
                        <input type="text" class="form-control" id="product-search" placeholder="Buscar produto...">
                    </div>
                    <div class="col-md-4 mb-3">
                        <button type="button" class="btn btn-primary w-100">Buscar</button>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Produto</th>
                                <th>Preço</th>
                                <th>Estoque</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="search-results">
                            <tr>
                                <td colspan="4" class="text-center">Use a busca para encontrar produtos</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div>
        <div class="card order-summary">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Carrinho</h5>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="clearCart()">
                    <i class="fas fa-trash"></i> Limpar
                </button>
            </div>
            <div class="card-body">
                <form action="" method="post">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Produto</th>
                                    <th>Qtd</th>
                                    <th>Preço</th>
                                    <th>Total</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="cart-items">
                                <tr>
                                    <td colspan="5" class="text-center">Carrinho vazio</td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="3" class="text-end">Total:</th>
                                    <th id="cart-total">R$ 0,00</th>
                                    <th></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    
                    <hr>
                    
                    <div class="mb-3">
                        <label for="customer_id" class="form-label">Cliente (opcional)</label>
                        <select class="form-select" id="customer_id" name="customer_id">
                            <option value="">Cliente não identificado</option>
                            <?php foreach ($customers as $customer): ?>
                                <option value="<?= $customer['id'] ?>">
                                    <?= htmlspecialchars($customer['full_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="payment_method" class="form-label">Forma de Pagamento</label>
                        <select class="form-select" id="payment_method" name="payment_method" required>
                            <option value="">Selecione...</option>
                            <option value="cash">Dinheiro</option>
                            <option value="credit_card">Cartão de Crédito</option>
                            <option value="debit_card">Cartão de Débito</option>
                            <option value="pix">PIX</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Observações</label>
                        <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
                    </div>
                    
                    <input type="hidden" name="total_amount" id="total_amount" value="0">
                    
                    <div class="d-grid">
                        <button type="submit" name="finalize_sale" id="finalize-button" class="btn btn-success" disabled>
                            <i class="fas fa-check-circle"></i> Finalizar Venda
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

// Carrega o layout principal
include __DIR__ . '/../../template/layouts/main.php';

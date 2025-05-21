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

// Define os filtros
$productId = $_GET['product_id'] ?? null;
$startDate = $_GET['start_date'] ?? date('Y-m-01'); // Primeiro dia do mês atual
$endDate = $_GET['end_date'] ?? date('Y-m-t'); // Último dia do mês atual
$movementType = $_GET['movement_type'] ?? 'all';

// Constrói a consulta SQL com base nos filtros
$sql = "
    SELECT sm.*, p.name as product_name, e.full_name as employee_name
    FROM stock_movements sm
    JOIN products p ON sm.product_id = p.id
    LEFT JOIN employees e ON sm.employee_id = e.id
    WHERE sm.movement_date BETWEEN ? AND ?
";

$params = [$startDate . ' 00:00:00', $endDate . ' 23:59:59'];

if ($productId) {
    $sql .= " AND sm.product_id = ?";
    $params[] = $productId;
}

if ($movementType !== 'all') {
    $sql .= " AND sm.movement_type = ?";
    $params[] = $movementType;
}

$sql .= " ORDER BY sm.movement_date DESC";

// Executa a consulta
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$movements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Se um produto específico foi selecionado, obtém seus detalhes
$product = null;
if ($productId) {
    $stmt = $pdo->prepare("
        SELECT p.*, pc.name as category_name
        FROM products p
        JOIN product_categories pc ON p.category_id = pc.id
        WHERE p.id = ?
    ");
    $stmt->execute([$productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Obtém a lista de produtos para o filtro
$stmt = $pdo->prepare("
    SELECT id, name
    FROM products
    WHERE is_active = 1
    ORDER BY name ASC
");
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Define as variáveis para o template
$pageTitle = 'Movimentações de Estoque - Sistema de Turismo';
$pageHeader = $product ? 'Movimentações de Estoque: ' . $product['name'] : 'Movimentações de Estoque';
$showNavbar = true;

// Carrega o conteúdo da página
ob_start();
?>

<div class="row mb-4">
    <div class="col-md-8">
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Voltar para o Controle de Estoque
        </a>
    </div>
    <div class="col-md-4 text-end">
        <a href="movement_form.php<?= $productId ? '?product_id=' . $productId : '' ?>" class="btn btn-success">
            <i class="fas fa-plus"></i> Nova Movimentação
        </a>
    </div>
</div>

<?php if ($product): ?>
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Detalhes do Produto</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <p><strong>Nome:</strong> <?= htmlspecialchars($product['name']) ?></p>
                        <p><strong>Categoria:</strong> <?= htmlspecialchars($product['category_name']) ?></p>
                    </div>
                    <div class="col-md-4">
                        <p><strong>Preço:</strong> <?= format_money($product['price']) ?></p>
                        <p><strong>Status:</strong> <?= $product['is_active'] ? '<span class="badge bg-success">Ativo</span>' : '<span class="badge bg-danger">Inativo</span>' ?></p>
                    </div>
                    <div class="col-md-4">
                        <p><strong>Estoque Atual:</strong> <?= $product['stock_quantity'] !== null ? $product['stock_quantity'] : 'N/A' ?></p>
                        <p><strong>Estoque Mínimo:</strong> <?= $product['min_stock_quantity'] !== null ? $product['min_stock_quantity'] : 'N/A' ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Filtros</h5>
    </div>
    <div class="card-body">
        <form action="" method="get" class="row g-3">
            <?php if ($productId): ?>
                <input type="hidden" name="product_id" value="<?= $productId ?>">
            <?php else: ?>
                <div class="col-md-3">
                    <label for="product_id" class="form-label">Produto</label>
                    <select class="form-select" id="product_id" name="product_id">
                        <option value="">Todos</option>
                        <?php foreach ($products as $prod): ?>
                            <option value="<?= $prod['id'] ?>" <?= $productId == $prod['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($prod['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            
            <div class="col-md-3">
                <label for="start_date" class="form-label">Data Inicial</label>
                <input type="date" class="form-control" id="start_date" name="start_date" value="<?= $startDate ?>">
            </div>
            
            <div class="col-md-3">
                <label for="end_date" class="form-label">Data Final</label>
                <input type="date" class="form-control" id="end_date" name="end_date" value="<?= $endDate ?>">
            </div>
            
            <div class="col-md-3">
                <label for="movement_type" class="form-label">Tipo de Movimentação</label>
                <select class="form-select" id="movement_type" name="movement_type">
                    <option value="all" <?= $movementType === 'all' ? 'selected' : '' ?>>Todos</option>
                    <option value="purchase" <?= $movementType === 'purchase' ? 'selected' : '' ?>>Compra (Entrada)</option>
                    <option value="sale" <?= $movementType === 'sale' ? 'selected' : '' ?>>Venda (Saída)</option>
                    <option value="adjustment" <?= $movementType === 'adjustment' ? 'selected' : '' ?>>Ajuste</option>
                    <option value="loss" <?= $movementType === 'loss' ? 'selected' : '' ?>>Perda/Quebra</option>
                    <option value="transfer" <?= $movementType === 'transfer' ? 'selected' : '' ?>>Transferência</option>
                </select>
            </div>
            
            <div class="col-md-12 d-flex justify-content-end">
                <button type="submit" class="btn btn-primary">Filtrar</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Histórico de Movimentações</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Data</th>
                        <?php if (!$productId): ?>
                            <th>Produto</th>
                        <?php endif; ?>
                        <th>Tipo</th>
                        <th>Quantidade</th>
                        <th>Estoque Anterior</th>
                        <th>Estoque Final</th>
                        <th>Responsável</th>
                        <th>Observações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($movements) > 0): ?>
                        <?php foreach ($movements as $movement): ?>
                            <tr>
                                <td><?= $movement['id'] ?></td>
                                <td><?= format_date($movement['movement_date'], true) ?></td>
                                <?php if (!$productId): ?>
                                    <td><?= htmlspecialchars($movement['product_name']) ?></td>
                                <?php endif; ?>
                                <td>
                                    <?php
                                    $typeText = '';
                                    $typeClass = '';
                                    
                                    switch ($movement['movement_type']) {
                                        case 'purchase':
                                            $typeText = 'Compra';
                                            $typeClass = 'success';
                                            break;
                                        case 'sale':
                                            $typeText = 'Venda';
                                            $typeClass = 'danger';
                                            break;
                                        case 'adjustment':
                                            $typeText = 'Ajuste';
                                            $typeClass = 'primary';
                                            break;
                                        case 'loss':
                                            $typeText = 'Perda/Quebra';
                                            $typeClass = 'warning';
                                            break;
                                        case 'transfer':
                                            $typeText = 'Transferência';
                                            $typeClass = 'info';
                                            break;
                                    }
                                    ?>
                                    <span class="badge bg-<?= $typeClass ?>"><?= $typeText ?></span>
                                </td>
                                <td class="<?= $movement['quantity'] > 0 ? 'text-success' : 'text-danger' ?>">
                                    <?= $movement['quantity'] > 0 ? '+' : '' ?><?= $movement['quantity'] ?>
                                </td>
                                <td><?= $movement['previous_quantity'] ?></td>
                                <td><?= $movement['new_quantity'] ?></td>
                                <td><?= htmlspecialchars($movement['employee_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($movement['notes'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?= $productId ? '8' : '9' ?>" class="text-center">Nenhuma movimentação encontrada com os filtros selecionados.</td>
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

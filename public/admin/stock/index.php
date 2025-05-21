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

// Filtra os produtos por categoria e status
$categoryId = $_GET['category_id'] ?? 'all';
$status = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

// Constrói a consulta SQL com base nos filtros
$sql = "
    SELECT p.*, pc.name as category_name
    FROM products p
    JOIN product_categories pc ON p.category_id = pc.id
    WHERE 1=1
";

$params = [];

if ($categoryId !== 'all') {
    $sql .= " AND p.category_id = ?";
    $params[] = $categoryId;
}

if ($status === 'active') {
    $sql .= " AND p.is_active = 1";
} elseif ($status === 'inactive') {
    $sql .= " AND p.is_active = 0";
}

if (!empty($search)) {
    $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY p.name ASC";

// Executa a consulta
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtém as categorias de produtos para o filtro
$stmt = $pdo->prepare("
    SELECT id, name
    FROM product_categories
    ORDER BY name ASC
");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtém o resumo do estoque
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_products,
        SUM(CASE WHEN stock_quantity <= min_stock_quantity AND stock_quantity IS NOT NULL THEN 1 ELSE 0 END) as low_stock_count,
        SUM(CASE WHEN stock_quantity = 0 AND stock_quantity IS NOT NULL THEN 1 ELSE 0 END) as out_of_stock_count,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_products
    FROM products
");
$stmt->execute();
$stockSummary = $stmt->fetch(PDO::FETCH_ASSOC);

// Define as variáveis para o template
$pageTitle = 'Controle de Estoque - Sistema de Turismo';
$pageHeader = 'Controle de Estoque';
$showNavbar = true;

// Carrega o conteúdo da página
ob_start();
?>

<div class="row mb-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Filtrar Produtos</h5>
            </div>
            <div class="card-body">
                <form action="" method="get" class="row g-3">
                    <div class="col-md-4">
                        <label for="category_id" class="form-label">Categoria</label>
                        <select class="form-select" id="category_id" name="category_id">
                            <option value="all">Todas</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= $category['id'] ?>" <?= $categoryId == $category['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($category['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Todos</option>
                            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Ativos</option>
                            <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inativos</option>
                            <option value="low_stock" <?= $status === 'low_stock' ? 'selected' : '' ?>>Estoque Baixo</option>
                            <option value="out_of_stock" <?= $status === 'out_of_stock' ? 'selected' : '' ?>>Sem Estoque</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="search" class="form-label">Buscar</label>
                        <input type="text" class="form-control" id="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Nome ou descrição...">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-4 text-end">
        <a href="product_form.php" class="btn btn-success">
            <i class="fas fa-plus"></i> Novo Produto
        </a>
        <a href="movement_form.php" class="btn btn-primary ms-2">
            <i class="fas fa-exchange-alt"></i> Movimentação
        </a>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase">Total de Produtos</h6>
                        <h2 class="mb-0"><?= $stockSummary['total_products'] ?></h2>
                    </div>
                    <i class="fas fa-box fa-3x opacity-50"></i>
                </div>
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <a href="?status=all" class="text-white text-decoration-none">Ver todos</a>
                <i class="fas fa-arrow-circle-right text-white"></i>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card bg-success text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase">Produtos Ativos</h6>
                        <h2 class="mb-0"><?= $stockSummary['active_products'] ?></h2>
                    </div>
                    <i class="fas fa-check-circle fa-3x opacity-50"></i>
                </div>
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <a href="?status=active" class="text-white text-decoration-none">Ver ativos</a>
                <i class="fas fa-arrow-circle-right text-white"></i>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card bg-warning text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase">Estoque Baixo</h6>
                        <h2 class="mb-0"><?= $stockSummary['low_stock_count'] ?></h2>
                    </div>
                    <i class="fas fa-exclamation-triangle fa-3x opacity-50"></i>
                </div>
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <a href="?status=low_stock" class="text-white text-decoration-none">Ver produtos</a>
                <i class="fas fa-arrow-circle-right text-white"></i>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card bg-danger text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase">Sem Estoque</h6>
                        <h2 class="mb-0"><?= $stockSummary['out_of_stock_count'] ?></h2>
                    </div>
                    <i class="fas fa-times-circle fa-3x opacity-50"></i>
                </div>
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <a href="?status=out_of_stock" class="text-white text-decoration-none">Ver produtos</a>
                <i class="fas fa-arrow-circle-right text-white"></i>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Lista de Produtos</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>Categoria</th>
                        <th>Preço</th>
                        <th>Estoque Atual</th>
                        <th>Estoque Mínimo</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($products) > 0): ?>
                        <?php foreach ($products as $product): ?>
                            <?php
                            $stockStatus = '';
                            $stockClass = '';
                            
                            if ($product['stock_quantity'] === null) {
                                $stockStatus = 'N/A';
                                $stockClass = '';
                            } elseif ($product['stock_quantity'] <= 0) {
                                $stockStatus = 'Sem Estoque';
                                $stockClass = 'text-danger';
                            } elseif ($product['stock_quantity'] <= $product['min_stock_quantity']) {
                                $stockStatus = 'Estoque Baixo';
                                $stockClass = 'text-warning';
                            } else {
                                $stockStatus = 'OK';
                                $stockClass = 'text-success';
                            }
                            ?>
                            <tr>
                                <td><?= $product['id'] ?></td>
                                <td><?= htmlspecialchars($product['name']) ?></td>
                                <td><?= htmlspecialchars($product['category_name']) ?></td>
                                <td><?= format_money($product['price']) ?></td>
                                <td class="<?= $stockClass ?>"><?= $product['stock_quantity'] !== null ? $product['stock_quantity'] : 'N/A' ?></td>
                                <td><?= $product['min_stock_quantity'] !== null ? $product['min_stock_quantity'] : 'N/A' ?></td>
                                <td>
                                    <?php if ($product['is_active']): ?>
                                        <span class="badge bg-success">Ativo</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inativo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="product_view.php?id=<?= $product['id'] ?>" class="btn btn-sm btn-info" title="Visualizar">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="product_form.php?id=<?= $product['id'] ?>" class="btn btn-sm btn-primary" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="movements.php?product_id=<?= $product['id'] ?>" class="btn btn-sm btn-secondary" title="Movimentações">
                                        <i class="fas fa-history"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center">Nenhum produto encontrado com os filtros selecionados.</td>
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

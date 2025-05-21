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
if (!has_permission('financial')) {
    header('Location: ../../index.php');
    exit;
}

// Inicializa variáveis
$alertMessage = null;
$alertType = null;

// Define o período de análise
$startDate = $_GET['start_date'] ?? date('Y-m-01'); // Primeiro dia do mês atual
$endDate = $_GET['end_date'] ?? date('Y-m-t'); // Último dia do mês atual
$type = $_GET['type'] ?? 'all'; // Tipo de transação (income, expense, all)
$category = $_GET['category'] ?? ''; // Categoria específica
$paymentMethod = $_GET['payment_method'] ?? ''; // Método de pagamento específico

// Constrói a consulta SQL com base nos filtros
$sql = "
    SELECT ft.*, e.full_name as employee_name
    FROM financial_transactions ft
    LEFT JOIN employees e ON ft.employee_id = e.id
    WHERE ft.transaction_date BETWEEN ? AND ?
";

$params = [$startDate . ' 00:00:00', $endDate . ' 23:59:59'];

if ($type !== 'all') {
    $sql .= " AND ft.type = ?";
    $params[] = $type;
}

if (!empty($category)) {
    $sql .= " AND ft.category = ?";
    $params[] = $category;
}

if (!empty($paymentMethod)) {
    $sql .= " AND ft.payment_method = ?";
    $params[] = $paymentMethod;
}

$sql .= " ORDER BY ft.transaction_date DESC";

// Executa a consulta
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcula os totais
$totalIncome = 0;
$totalExpense = 0;

foreach ($transactions as $transaction) {
    if ($transaction['type'] === 'income') {
        $totalIncome += $transaction['amount'];
    } else {
        $totalExpense += $transaction['amount'];
    }
}

$balance = $totalIncome - $totalExpense;

// Obtém as categorias de transações para o filtro
$stmt = $pdo->prepare("
    SELECT DISTINCT category, type
    FROM financial_transactions
    ORDER BY type, category
");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Agrupa as categorias por tipo
$incomeCategories = [];
$expenseCategories = [];

foreach ($categories as $cat) {
    if ($cat['type'] === 'income') {
        $incomeCategories[] = $cat['category'];
    } else {
        $expenseCategories[] = $cat['category'];
    }
}

// Obtém os métodos de pagamento para o filtro
$stmt = $pdo->prepare("
    SELECT DISTINCT payment_method
    FROM financial_transactions
    ORDER BY payment_method
");
$stmt->execute();
$paymentMethods = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Define as variáveis para o template
$pageTitle = 'Transações Financeiras - Sistema de Turismo';
$pageHeader = 'Transações Financeiras';
$showNavbar = true;

// Carrega o conteúdo da página
ob_start();
?>

<div class="row mb-4">
    <div class="col-md-8">
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Voltar para o Controle Financeiro
        </a>
    </div>
    <div class="col-md-4 text-end">
        <a href="transaction_form.php" class="btn btn-success">
            <i class="fas fa-plus"></i> Nova Transação
        </a>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Filtros</h5>
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
            <div class="col-md-2">
                <label for="type" class="form-label">Tipo</label>
                <select class="form-select" id="type" name="type">
                    <option value="all" <?= $type === 'all' ? 'selected' : '' ?>>Todos</option>
                    <option value="income" <?= $type === 'income' ? 'selected' : '' ?>>Receitas</option>
                    <option value="expense" <?= $type === 'expense' ? 'selected' : '' ?>>Despesas</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="category" class="form-label">Categoria</label>
                <select class="form-select" id="category" name="category">
                    <option value="">Todas</option>
                    <optgroup label="Receitas">
                        <?php foreach ($incomeCategories as $cat): ?>
                            <option value="<?= $cat ?>" <?= $category === $cat ? 'selected' : '' ?>>
                                <?= ucfirst(str_replace('_', ' ', $cat)) ?>
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                    <optgroup label="Despesas">
                        <?php foreach ($expenseCategories as $cat): ?>
                            <option value="<?= $cat ?>" <?= $category === $cat ? 'selected' : '' ?>>
                                <?= ucfirst(str_replace('_', ' ', $cat)) ?>
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                </select>
            </div>
            <div class="col-md-2">
                <label for="payment_method" class="form-label">Método</label>
                <select class="form-select" id="payment_method" name="payment_method">
                    <option value="">Todos</option>
                    <?php foreach ($paymentMethods as $method): ?>
                        <?php
                        $methodText = '';
                        switch ($method) {
                            case 'cash':
                                $methodText = 'Dinheiro';
                                break;
                            case 'credit_card':
                                $methodText = 'Cartão de Crédito';
                                break;
                            case 'debit_card':
                                $methodText = 'Cartão de Débito';
                                break;
                            case 'pix':
                                $methodText = 'PIX';
                                break;
                            case 'bank_transfer':
                                $methodText = 'Transferência Bancária';
                                break;
                            case 'check':
                                $methodText = 'Cheque';
                                break;
                            default:
                                $methodText = $method;
                        }
                        ?>
                        <option value="<?= $method ?>" <?= $paymentMethod === $method ? 'selected' : '' ?>>
                            <?= $methodText ?>
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

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card bg-success text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase">Receitas</h6>
                        <h2 class="mb-0"><?= format_money($totalIncome) ?></h2>
                    </div>
                    <i class="fas fa-arrow-up fa-3x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card bg-danger text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase">Despesas</h6>
                        <h2 class="mb-0"><?= format_money($totalExpense) ?></h2>
                    </div>
                    <i class="fas fa-arrow-down fa-3x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card <?= $balance >= 0 ? 'bg-primary' : 'bg-warning' ?> text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase">Saldo</h6>
                        <h2 class="mb-0"><?= format_money($balance) ?></h2>
                    </div>
                    <i class="fas fa-balance-scale fa-3x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Lista de Transações</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Data</th>
                        <th>Descrição</th>
                        <th>Categoria</th>
                        <th>Método</th>
                        <th>Responsável</th>
                        <th class="text-end">Valor</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($transactions) > 0): ?>
                        <?php foreach ($transactions as $transaction): ?>
                            <tr>
                                <td><?= $transaction['id'] ?></td>
                                <td><?= format_date($transaction['transaction_date'], true) ?></td>
                                <td><?= htmlspecialchars($transaction['description']) ?></td>
                                <td><?= ucfirst(str_replace('_', ' ', $transaction['category'])) ?></td>
                                <td>
                                    <?php
                                    $methodText = '';
                                    switch ($transaction['payment_method']) {
                                        case 'cash':
                                            $methodText = 'Dinheiro';
                                            break;
                                        case 'credit_card':
                                            $methodText = 'Cartão de Crédito';
                                            break;
                                        case 'debit_card':
                                            $methodText = 'Cartão de Débito';
                                            break;
                                        case 'pix':
                                            $methodText = 'PIX';
                                            break;
                                        case 'bank_transfer':
                                            $methodText = 'Transferência Bancária';
                                            break;
                                        case 'check':
                                            $methodText = 'Cheque';
                                            break;
                                        default:
                                            $methodText = $transaction['payment_method'];
                                    }
                                    echo $methodText;
                                    ?>
                                </td>
                                <td><?= htmlspecialchars($transaction['employee_name'] ?? 'N/A') ?></td>
                                <td class="text-end <?= $transaction['type'] === 'income' ? 'text-success' : 'text-danger' ?>">
                                    <?= $transaction['type'] === 'income' ? '+' : '-' ?><?= format_money($transaction['amount']) ?>
                                </td>
                                <td>
                                    <a href="transaction_view.php?id=<?= $transaction['id'] ?>" class="btn btn-sm btn-info" title="Visualizar">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="transaction_form.php?id=<?= $transaction['id'] ?>" class="btn btn-sm btn-primary" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center">Nenhuma transação encontrada com os filtros selecionados.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="6" class="text-end">Total:</th>
                        <th class="text-end"><?= format_money($balance) ?></th>
                        <th></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

// Carrega o layout principal
include __DIR__ . '/../../../template/layouts/main.php';

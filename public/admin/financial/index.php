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

// Obtém o resumo financeiro do período
$stmt = $pdo->prepare("
    SELECT 
        SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as total_income,
        SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as total_expense,
        SUM(CASE WHEN type = 'income' THEN amount ELSE -amount END) as balance
    FROM financial_transactions
    WHERE transaction_date BETWEEN ? AND ?
");
$stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
$financialSummary = $stmt->fetch(PDO::FETCH_ASSOC);

// Obtém as transações do período
$stmt = $pdo->prepare("
    SELECT ft.*, e.full_name as employee_name
    FROM financial_transactions ft
    LEFT JOIN employees e ON ft.employee_id = e.id
    WHERE ft.transaction_date BETWEEN ? AND ?
    ORDER BY ft.transaction_date DESC
");
$stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtém as categorias de transações
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

foreach ($categories as $category) {
    if ($category['type'] === 'income') {
        $incomeCategories[] = $category['category'];
    } else {
        $expenseCategories[] = $category['category'];
    }
}

// Obtém os totais por categoria
$stmt = $pdo->prepare("
    SELECT category, type, SUM(amount) as total
    FROM financial_transactions
    WHERE transaction_date BETWEEN ? AND ?
    GROUP BY category, type
    ORDER BY type, total DESC
");
$stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
$categoryTotals = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Define as variáveis para o template
$pageTitle = 'Controle Financeiro - Sistema de Turismo';
$pageHeader = 'Controle Financeiro';
$showNavbar = true;

// Carrega o conteúdo da página
ob_start();
?>

<div class="row mb-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Filtrar Período</h5>
            </div>
            <div class="card-body">
                <form action="" method="get" class="row g-3">
                    <div class="col-md-4">
                        <label for="start_date" class="form-label">Data Inicial</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" value="<?= $startDate ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="end_date" class="form-label">Data Final</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" value="<?= $endDate ?>">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-4 text-end">
        <a href="transaction_form.php" class="btn btn-success">
            <i class="fas fa-plus"></i> Nova Transação
        </a>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card bg-success text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase">Receitas</h6>
                        <h2 class="mb-0"><?= format_money($financialSummary['total_income'] ?? 0) ?></h2>
                    </div>
                    <i class="fas fa-arrow-up fa-3x opacity-50"></i>
                </div>
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <a href="transactions.php?type=income&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>" class="text-white text-decoration-none">Ver detalhes</a>
                <i class="fas fa-arrow-circle-right text-white"></i>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card bg-danger text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase">Despesas</h6>
                        <h2 class="mb-0"><?= format_money($financialSummary['total_expense'] ?? 0) ?></h2>
                    </div>
                    <i class="fas fa-arrow-down fa-3x opacity-50"></i>
                </div>
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <a href="transactions.php?type=expense&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>" class="text-white text-decoration-none">Ver detalhes</a>
                <i class="fas fa-arrow-circle-right text-white"></i>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card <?= ($financialSummary['balance'] ?? 0) >= 0 ? 'bg-primary' : 'bg-warning' ?> text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase">Saldo</h6>
                        <h2 class="mb-0"><?= format_money($financialSummary['balance'] ?? 0) ?></h2>
                    </div>
                    <i class="fas fa-balance-scale fa-3x opacity-50"></i>
                </div>
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <a href="reports.php" class="text-white text-decoration-none">Ver relatórios</a>
                <i class="fas fa-arrow-circle-right text-white"></i>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Receitas por Categoria</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Categoria</th>
                                <th class="text-end">Valor</th>
                                <th class="text-end">%</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $totalIncome = $financialSummary['total_income'] ?? 0;
                            $incomeCategoriesFound = false;
                            
                            foreach ($categoryTotals as $category): 
                                if ($category['type'] === 'income'):
                                    $incomeCategoriesFound = true;
                                    $percentage = $totalIncome > 0 ? ($category['total'] / $totalIncome) * 100 : 0;
                            ?>
                                <tr>
                                    <td><?= ucfirst(str_replace('_', ' ', $category['category'])) ?></td>
                                    <td class="text-end"><?= format_money($category['total']) ?></td>
                                    <td class="text-end"><?= number_format($percentage, 1) ?>%</td>
                                </tr>
                            <?php 
                                endif;
                            endforeach; 
                            
                            if (!$incomeCategoriesFound):
                            ?>
                                <tr>
                                    <td colspan="3" class="text-center">Nenhuma receita no período selecionado.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th>Total</th>
                                <th class="text-end"><?= format_money($totalIncome) ?></th>
                                <th class="text-end">100%</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Despesas por Categoria</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Categoria</th>
                                <th class="text-end">Valor</th>
                                <th class="text-end">%</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $totalExpense = $financialSummary['total_expense'] ?? 0;
                            $expenseCategoriesFound = false;
                            
                            foreach ($categoryTotals as $category): 
                                if ($category['type'] === 'expense'):
                                    $expenseCategoriesFound = true;
                                    $percentage = $totalExpense > 0 ? ($category['total'] / $totalExpense) * 100 : 0;
                            ?>
                                <tr>
                                    <td><?= ucfirst(str_replace('_', ' ', $category['category'])) ?></td>
                                    <td class="text-end"><?= format_money($category['total']) ?></td>
                                    <td class="text-end"><?= number_format($percentage, 1) ?>%</td>
                                </tr>
                            <?php 
                                endif;
                            endforeach; 
                            
                            if (!$expenseCategoriesFound):
                            ?>
                                <tr>
                                    <td colspan="3" class="text-center">Nenhuma despesa no período selecionado.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th>Total</th>
                                <th class="text-end"><?= format_money($totalExpense) ?></th>
                                <th class="text-end">100%</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">Últimas Transações</h5>
        <a href="transactions.php?start_date=<?= $startDate ?>&end_date=<?= $endDate ?>" class="btn btn-sm btn-primary">Ver Todas</a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
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
                        <?php foreach (array_slice($transactions, 0, 10) as $transaction): ?>
                            <tr>
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
                            <td colspan="7" class="text-center">Nenhuma transação encontrada no período selecionado.</td>
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

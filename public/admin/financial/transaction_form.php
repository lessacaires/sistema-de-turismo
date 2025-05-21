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
$transaction = [
    'id' => null,
    'transaction_date' => date('Y-m-d H:i:s'),
    'amount' => '',
    'type' => 'expense',
    'category' => '',
    'description' => '',
    'payment_method' => '',
    'reference_id' => null,
    'reference_type' => null,
    'employee_id' => get_logged_in_user_id()
];

// Verifica se é uma edição
$isEdit = isset($_GET['id']) && is_numeric($_GET['id']);

if ($isEdit) {
    // Obtém os dados da transação
    $stmt = $pdo->prepare("SELECT * FROM financial_transactions WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $transactionData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($transactionData) {
        $transaction = $transactionData;
    } else {
        $alertMessage = "Transação não encontrada.";
        $alertType = "danger";
        $isEdit = false;
    }
}

// Processa o formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obtém os dados do formulário
    $transaction['transaction_date'] = $_POST['transaction_date'] ?? date('Y-m-d H:i:s');
    $transaction['amount'] = $_POST['amount'] ? str_replace(',', '.', $_POST['amount']) : 0;
    $transaction['type'] = $_POST['type'] ?? 'expense';
    $transaction['category'] = $_POST['category'] ?? '';
    $transaction['description'] = $_POST['description'] ?? '';
    $transaction['payment_method'] = $_POST['payment_method'] ?? '';
    $transaction['reference_id'] = !empty($_POST['reference_id']) ? $_POST['reference_id'] : null;
    $transaction['reference_type'] = !empty($_POST['reference_type']) ? $_POST['reference_type'] : null;
    
    // Validação básica
    $errors = [];
    
    if (empty($transaction['transaction_date'])) {
        $errors[] = "A data da transação é obrigatória.";
    }
    
    if ($transaction['amount'] <= 0) {
        $errors[] = "O valor deve ser maior que zero.";
    }
    
    if (empty($transaction['category'])) {
        $errors[] = "A categoria é obrigatória.";
    }
    
    if (empty($transaction['description'])) {
        $errors[] = "A descrição é obrigatória.";
    }
    
    if (empty($transaction['payment_method'])) {
        $errors[] = "O método de pagamento é obrigatório.";
    }
    
    // Se não houver erros, salva os dados
    if (empty($errors)) {
        if ($isEdit) {
            // Atualiza a transação existente
            $sql = "UPDATE financial_transactions SET 
                transaction_date = ?, 
                amount = ?, 
                type = ?, 
                category = ?, 
                description = ?, 
                payment_method = ?, 
                reference_id = ?, 
                reference_type = ?, 
                employee_id = ?
                WHERE id = ?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $transaction['transaction_date'],
                $transaction['amount'],
                $transaction['type'],
                $transaction['category'],
                $transaction['description'],
                $transaction['payment_method'],
                $transaction['reference_id'],
                $transaction['reference_type'],
                get_logged_in_user_id(),
                $transaction['id']
            ]);
            
            $alertMessage = "Transação atualizada com sucesso!";
            $alertType = "success";
        } else {
            // Insere uma nova transação
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
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $transaction['transaction_date'],
                $transaction['amount'],
                $transaction['type'],
                $transaction['category'],
                $transaction['description'],
                $transaction['payment_method'],
                $transaction['reference_id'],
                $transaction['reference_type'],
                get_logged_in_user_id()
            ]);
            
            $transactionId = $pdo->lastInsertId();
            
            $alertMessage = "Transação cadastrada com sucesso!";
            $alertType = "success";
            
            // Limpa o formulário para um novo cadastro
            $transaction = [
                'id' => null,
                'transaction_date' => date('Y-m-d H:i:s'),
                'amount' => '',
                'type' => 'expense',
                'category' => '',
                'description' => '',
                'payment_method' => '',
                'reference_id' => null,
                'reference_type' => null,
                'employee_id' => get_logged_in_user_id()
            ];
        }
    } else {
        // Se houver erros, exibe-os
        $alertMessage = implode("<br>", $errors);
        $alertType = "danger";
    }
}

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

// Adiciona categorias padrão se não houver categorias cadastradas
if (empty($incomeCategories)) {
    $incomeCategories = ['sale', 'tour_sale', 'service', 'other_income'];
}

if (empty($expenseCategories)) {
    $expenseCategories = ['purchase', 'salary', 'rent', 'utilities', 'maintenance', 'supplies', 'marketing', 'other_expense'];
}

// Define as variáveis para o template
$pageTitle = ($isEdit ? 'Editar' : 'Nova') . ' Transação - Sistema de Turismo';
$pageHeader = ($isEdit ? 'Editar' : 'Nova') . ' Transação';
$showNavbar = true;

// Carrega o conteúdo da página
ob_start();
?>

<div class="row mb-4">
    <div class="col-md-12">
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Voltar para o Controle Financeiro
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0"><?= $isEdit ? 'Editar' : 'Nova' ?> Transação</h5>
    </div>
    <div class="card-body">
        <form action="" method="post">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="type" class="form-label">Tipo de Transação *</label>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="type" id="type_expense" value="expense" <?= $transaction['type'] === 'expense' ? 'checked' : '' ?> onchange="updateCategoryOptions()">
                        <label class="form-check-label" for="type_expense">
                            Despesa
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="type" id="type_income" value="income" <?= $transaction['type'] === 'income' ? 'checked' : '' ?> onchange="updateCategoryOptions()">
                        <label class="form-check-label" for="type_income">
                            Receita
                        </label>
                    </div>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="transaction_date" class="form-label">Data da Transação *</label>
                    <input type="datetime-local" class="form-control" id="transaction_date" name="transaction_date" value="<?= date('Y-m-d\TH:i', strtotime($transaction['transaction_date'])) ?>" required>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="amount" class="form-label">Valor (R$) *</label>
                    <input type="text" class="form-control" id="amount" name="amount" value="<?= number_format($transaction['amount'], 2, ',', '.') ?>" required>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="payment_method" class="form-label">Método de Pagamento *</label>
                    <select class="form-select" id="payment_method" name="payment_method" required>
                        <option value="">Selecione...</option>
                        <option value="cash" <?= $transaction['payment_method'] === 'cash' ? 'selected' : '' ?>>Dinheiro</option>
                        <option value="credit_card" <?= $transaction['payment_method'] === 'credit_card' ? 'selected' : '' ?>>Cartão de Crédito</option>
                        <option value="debit_card" <?= $transaction['payment_method'] === 'debit_card' ? 'selected' : '' ?>>Cartão de Débito</option>
                        <option value="pix" <?= $transaction['payment_method'] === 'pix' ? 'selected' : '' ?>>PIX</option>
                        <option value="bank_transfer" <?= $transaction['payment_method'] === 'bank_transfer' ? 'selected' : '' ?>>Transferência Bancária</option>
                        <option value="check" <?= $transaction['payment_method'] === 'check' ? 'selected' : '' ?>>Cheque</option>
                    </select>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="category" class="form-label">Categoria *</label>
                    <select class="form-select" id="category" name="category" required>
                        <option value="">Selecione...</option>
                        <optgroup id="income_categories" label="Receitas" <?= $transaction['type'] === 'expense' ? 'style="display:none"' : '' ?>>
                            <?php foreach ($incomeCategories as $category): ?>
                                <option value="<?= $category ?>" <?= $transaction['category'] === $category && $transaction['type'] === 'income' ? 'selected' : '' ?>>
                                    <?= ucfirst(str_replace('_', ' ', $category)) ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="other_income" <?= $transaction['category'] === 'other_income' && $transaction['type'] === 'income' ? 'selected' : '' ?>>Outras Receitas</option>
                        </optgroup>
                        <optgroup id="expense_categories" label="Despesas" <?= $transaction['type'] === 'income' ? 'style="display:none"' : '' ?>>
                            <?php foreach ($expenseCategories as $category): ?>
                                <option value="<?= $category ?>" <?= $transaction['category'] === $category && $transaction['type'] === 'expense' ? 'selected' : '' ?>>
                                    <?= ucfirst(str_replace('_', ' ', $category)) ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="other_expense" <?= $transaction['category'] === 'other_expense' && $transaction['type'] === 'expense' ? 'selected' : '' ?>>Outras Despesas</option>
                        </optgroup>
                    </select>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="description" class="form-label">Descrição *</label>
                    <input type="text" class="form-control" id="description" name="description" value="<?= htmlspecialchars($transaction['description']) ?>" required>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="reference_id" class="form-label">ID de Referência (opcional)</label>
                    <input type="text" class="form-control" id="reference_id" name="reference_id" value="<?= $transaction['reference_id'] ?>">
                    <small class="text-muted">ID de uma venda, compra ou outro registro relacionado</small>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="reference_type" class="form-label">Tipo de Referência (opcional)</label>
                    <input type="text" class="form-control" id="reference_type" name="reference_type" value="<?= $transaction['reference_type'] ?>">
                    <small class="text-muted">Ex: order, purchase, tour_booking, etc.</small>
                </div>
            </div>
            
            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                <a href="index.php" class="btn btn-secondary me-md-2">Cancelar</a>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>

<script>
function updateCategoryOptions() {
    const typeExpense = document.getElementById('type_expense').checked;
    const typeIncome = document.getElementById('type_income').checked;
    const incomeCategories = document.getElementById('income_categories');
    const expenseCategories = document.getElementById('expense_categories');
    
    if (typeExpense) {
        incomeCategories.style.display = 'none';
        expenseCategories.style.display = 'block';
    } else if (typeIncome) {
        incomeCategories.style.display = 'block';
        expenseCategories.style.display = 'none';
    }
}

// Formata o campo de valor para moeda
document.getElementById('amount').addEventListener('blur', function(e) {
    const value = e.target.value.replace(/\D/g, '');
    const formattedValue = (parseFloat(value) / 100).toFixed(2).replace('.', ',');
    e.target.value = formattedValue;
});

document.getElementById('amount').addEventListener('focus', function(e) {
    const value = e.target.value.replace(/\D/g, '');
    e.target.value = (parseFloat(value) / 100).toFixed(2).replace('.', ',');
});
</script>

<?php
$content = ob_get_clean();

// Carrega o layout principal
include __DIR__ . '/../../../template/layouts/main.php';

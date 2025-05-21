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
if (!has_permission('employees')) {
    header('Location: ../../index.php');
    exit;
}

// Inicializa variáveis
$alertMessage = null;
$alertType = null;

// Processa a exclusão de funcionário
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $employeeId = $_GET['delete'];
    
    // Verifica se o funcionário existe
    $stmt = $pdo->prepare("SELECT id, user_id FROM employees WHERE id = ?");
    $stmt->execute([$employeeId]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($employee) {
        // Verifica se o funcionário está associado a alguma transação
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM (
                SELECT employee_id FROM orders WHERE employee_id = ?
                UNION ALL
                SELECT employee_id FROM tour_bookings WHERE employee_id = ?
                UNION ALL
                SELECT employee_id FROM financial_transactions WHERE employee_id = ?
                UNION ALL
                SELECT employee_id FROM stock_movements WHERE employee_id = ?
                UNION ALL
                SELECT employee_id FROM purchases WHERE employee_id = ?
            ) as transactions
        ");
        $stmt->execute([$employeeId, $employeeId, $employeeId, $employeeId, $employeeId]);
        $transactionCount = $stmt->fetchColumn();
        
        if ($transactionCount > 0) {
            $alertMessage = "Não é possível excluir este funcionário pois ele está associado a transações no sistema.";
            $alertType = "danger";
        } else {
            // Inicia uma transação
            $pdo->beginTransaction();
            
            try {
                // Se o funcionário tiver um usuário associado, desativa o usuário
                if ($employee['user_id']) {
                    $stmt = $pdo->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
                    $stmt->execute([$employee['user_id']]);
                }
                
                // Exclui o funcionário
                $stmt = $pdo->prepare("DELETE FROM employees WHERE id = ?");
                $stmt->execute([$employeeId]);
                
                // Confirma a transação
                $pdo->commit();
                
                $alertMessage = "Funcionário excluído com sucesso!";
                $alertType = "success";
            } catch (Exception $e) {
                // Reverte a transação em caso de erro
                $pdo->rollBack();
                
                $alertMessage = "Erro ao excluir o funcionário: " . $e->getMessage();
                $alertType = "danger";
            }
        }
    } else {
        $alertMessage = "Funcionário não encontrado.";
        $alertType = "danger";
    }
}

// Filtra os funcionários por status e busca
$status = $_GET['status'] ?? 'active';
$search = $_GET['search'] ?? '';

// Constrói a consulta SQL com base nos filtros
$sql = "
    SELECT e.*, u.username, u.email as user_email
    FROM employees e
    LEFT JOIN users u ON e.user_id = u.id
    WHERE 1=1
";

$params = [];

if ($status === 'active') {
    $sql .= " AND e.termination_date IS NULL";
} elseif ($status === 'inactive') {
    $sql .= " AND e.termination_date IS NOT NULL";
}

if (!empty($search)) {
    $sql .= " AND (e.full_name LIKE ? OR e.cpf LIKE ? OR e.email LIKE ? OR e.position LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY e.full_name ASC";

// Executa a consulta
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtém o resumo dos funcionários
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_employees,
        SUM(CASE WHEN termination_date IS NULL THEN 1 ELSE 0 END) as active_employees,
        SUM(CASE WHEN termination_date IS NOT NULL THEN 1 ELSE 0 END) as inactive_employees,
        COUNT(DISTINCT position) as positions_count
    FROM employees
");
$stmt->execute();
$employeesSummary = $stmt->fetch(PDO::FETCH_ASSOC);

// Define as variáveis para o template
$pageTitle = 'Funcionários - Sistema de Turismo';
$pageHeader = 'Gerenciamento de Funcionários';
$showNavbar = true;

// Carrega o conteúdo da página
ob_start();
?>

<div class="row mb-4">
    <div class="col-md-8">
        <form action="" method="get" class="row g-3">
            <div class="col-md-4">
                <select class="form-select" name="status" onchange="this.form.submit()">
                    <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Todos os Funcionários</option>
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Funcionários Ativos</option>
                    <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Funcionários Inativos</option>
                </select>
            </div>
            <div class="col-md-8">
                <div class="input-group">
                    <input type="text" class="form-control" name="search" placeholder="Buscar por nome, CPF, e-mail ou cargo..." value="<?= htmlspecialchars($search) ?>">
                    <button class="btn btn-primary" type="submit">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
        </form>
    </div>
    <div class="col-md-4 text-end">
        <a href="employee_form.php" class="btn btn-success">
            <i class="fas fa-plus"></i> Novo Funcionário
        </a>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase">Total de Funcionários</h6>
                        <h2 class="mb-0"><?= $employeesSummary['total_employees'] ?></h2>
                    </div>
                    <i class="fas fa-users fa-3x opacity-50"></i>
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
                        <h6 class="text-uppercase">Funcionários Ativos</h6>
                        <h2 class="mb-0"><?= $employeesSummary['active_employees'] ?></h2>
                    </div>
                    <i class="fas fa-user-check fa-3x opacity-50"></i>
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
                        <h6 class="text-uppercase">Funcionários Inativos</h6>
                        <h2 class="mb-0"><?= $employeesSummary['inactive_employees'] ?></h2>
                    </div>
                    <i class="fas fa-user-times fa-3x opacity-50"></i>
                </div>
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <a href="?status=inactive" class="text-white text-decoration-none">Ver inativos</a>
                <i class="fas fa-arrow-circle-right text-white"></i>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card bg-info text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase">Cargos</h6>
                        <h2 class="mb-0"><?= $employeesSummary['positions_count'] ?></h2>
                    </div>
                    <i class="fas fa-id-badge fa-3x opacity-50"></i>
                </div>
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <a href="#" class="text-white text-decoration-none">Ver detalhes</a>
                <i class="fas fa-arrow-circle-right text-white"></i>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Lista de Funcionários</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>CPF</th>
                        <th>Cargo</th>
                        <th>Contato</th>
                        <th>Data de Contratação</th>
                        <th>Status</th>
                        <th>Acesso ao Sistema</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($employees) > 0): ?>
                        <?php foreach ($employees as $employee): ?>
                            <tr>
                                <td><?= htmlspecialchars($employee['full_name']) ?></td>
                                <td><?= htmlspecialchars($employee['cpf']) ?></td>
                                <td><?= htmlspecialchars($employee['position']) ?></td>
                                <td>
                                    <?php if (!empty($employee['email'])): ?>
                                        <i class="fas fa-envelope"></i> <?= htmlspecialchars($employee['email']) ?><br>
                                    <?php endif; ?>
                                    <?php if (!empty($employee['phone'])): ?>
                                        <i class="fas fa-phone"></i> <?= htmlspecialchars($employee['phone']) ?>
                                    <?php endif; ?>
                                </td>
                                <td><?= format_date($employee['hire_date']) ?></td>
                                <td>
                                    <?php if ($employee['termination_date']): ?>
                                        <span class="badge bg-danger">Inativo</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Ativo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($employee['user_id']): ?>
                                        <span class="badge bg-primary">
                                            <i class="fas fa-user"></i> <?= htmlspecialchars($employee['username']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Sem acesso</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="employee_view.php?id=<?= $employee['id'] ?>" class="btn btn-sm btn-info" title="Visualizar">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="employee_form.php?id=<?= $employee['id'] ?>" class="btn btn-sm btn-primary" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if (!$employee['termination_date']): ?>
                                            <a href="employee_terminate.php?id=<?= $employee['id'] ?>" class="btn btn-sm btn-warning" title="Desligar">
                                                <i class="fas fa-user-slash"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (!$employee['user_id']): ?>
                                            <a href="user_form.php?employee_id=<?= $employee['id'] ?>" class="btn btn-sm btn-success" title="Criar Acesso">
                                                <i class="fas fa-key"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="?delete=<?= $employee['id'] ?>" class="btn btn-sm btn-danger" title="Excluir" onclick="return confirmDelete('Tem certeza que deseja excluir este funcionário?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center">Nenhum funcionário encontrado com os filtros selecionados.</td>
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

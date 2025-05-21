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
$employee = [
    'id' => null,
    'user_id' => null,
    'full_name' => '',
    'cpf' => '',
    'birth_date' => '',
    'address' => '',
    'phone' => '',
    'email' => '',
    'position' => '',
    'salary' => '',
    'hire_date' => date('Y-m-d'),
    'termination_date' => null
];

// Verifica se é uma edição
$isEdit = isset($_GET['id']) && is_numeric($_GET['id']);

if ($isEdit) {
    // Obtém os dados do funcionário
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $employeeData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($employeeData) {
        $employee = $employeeData;
    } else {
        $alertMessage = "Funcionário não encontrado.";
        $alertType = "danger";
        $isEdit = false;
    }
}

// Processa o formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obtém os dados do formulário
    $employee['full_name'] = $_POST['full_name'] ?? '';
    $employee['cpf'] = $_POST['cpf'] ?? '';
    $employee['birth_date'] = $_POST['birth_date'] ?? null;
    $employee['address'] = $_POST['address'] ?? '';
    $employee['phone'] = $_POST['phone'] ?? '';
    $employee['email'] = $_POST['email'] ?? '';
    $employee['position'] = $_POST['position'] ?? '';
    $employee['salary'] = $_POST['salary'] ? str_replace(',', '.', $_POST['salary']) : null;
    $employee['hire_date'] = $_POST['hire_date'] ?? date('Y-m-d');
    
    // Validação básica
    $errors = [];
    
    if (empty($employee['full_name'])) {
        $errors[] = "O nome completo é obrigatório.";
    }
    
    if (empty($employee['position'])) {
        $errors[] = "O cargo é obrigatório.";
    }
    
    if (empty($employee['hire_date'])) {
        $errors[] = "A data de contratação é obrigatória.";
    }
    
    if (!empty($employee['cpf'])) {
        // Verifica se o CPF já existe (exceto para o funcionário atual em caso de edição)
        $sql = "SELECT id FROM employees WHERE cpf = ? AND id != ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$employee['cpf'], $isEdit ? $employee['id'] : 0]);
        
        if ($stmt->fetchColumn()) {
            $errors[] = "Este CPF já está cadastrado para outro funcionário.";
        }
    }
    
    if (!empty($employee['email'])) {
        // Verifica se o e-mail já existe (exceto para o funcionário atual em caso de edição)
        $sql = "SELECT id FROM employees WHERE email = ? AND id != ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$employee['email'], $isEdit ? $employee['id'] : 0]);
        
        if ($stmt->fetchColumn()) {
            $errors[] = "Este e-mail já está cadastrado para outro funcionário.";
        }
    }
    
    // Se não houver erros, salva os dados
    if (empty($errors)) {
        if ($isEdit) {
            // Atualiza o funcionário existente
            $sql = "UPDATE employees SET 
                full_name = ?, 
                cpf = ?, 
                birth_date = ?, 
                address = ?, 
                phone = ?, 
                email = ?, 
                position = ?, 
                salary = ?, 
                hire_date = ?
                WHERE id = ?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $employee['full_name'],
                $employee['cpf'],
                $employee['birth_date'],
                $employee['address'],
                $employee['phone'],
                $employee['email'],
                $employee['position'],
                $employee['salary'],
                $employee['hire_date'],
                $employee['id']
            ]);
            
            $alertMessage = "Funcionário atualizado com sucesso!";
            $alertType = "success";
        } else {
            // Insere um novo funcionário
            $sql = "INSERT INTO employees (
                full_name, 
                cpf, 
                birth_date, 
                address, 
                phone, 
                email, 
                position, 
                salary, 
                hire_date
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $employee['full_name'],
                $employee['cpf'],
                $employee['birth_date'],
                $employee['address'],
                $employee['phone'],
                $employee['email'],
                $employee['position'],
                $employee['salary'],
                $employee['hire_date']
            ]);
            
            $employeeId = $pdo->lastInsertId();
            
            $alertMessage = "Funcionário cadastrado com sucesso!";
            $alertType = "success";
            
            // Redireciona para a página de edição
            header("Location: employee_form.php?id=$employeeId");
            exit;
        }
    } else {
        // Se houver erros, exibe-os
        $alertMessage = implode("<br>", $errors);
        $alertType = "danger";
    }
}

// Lista de cargos comuns para sugestão
$commonPositions = [
    'Gerente',
    'Recepcionista',
    'Atendente',
    'Garçom',
    'Bartender',
    'Cozinheiro',
    'Auxiliar de Cozinha',
    'Guia Turístico',
    'Motorista',
    'Camareira',
    'Segurança',
    'Manutenção',
    'Administrativo',
    'Financeiro',
    'Recursos Humanos',
    'Marketing',
    'Vendas'
];

// Define as variáveis para o template
$pageTitle = ($isEdit ? 'Editar' : 'Novo') . ' Funcionário - Sistema de Turismo';
$pageHeader = ($isEdit ? 'Editar' : 'Novo') . ' Funcionário';
$showNavbar = true;

// Carrega o conteúdo da página
ob_start();
?>

<div class="row mb-4">
    <div class="col-md-12">
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Voltar para a lista
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0"><?= $isEdit ? 'Editar' : 'Novo' ?> Funcionário</h5>
    </div>
    <div class="card-body">
        <form action="" method="post">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="full_name" class="form-label">Nome Completo *</label>
                    <input type="text" class="form-control" id="full_name" name="full_name" value="<?= htmlspecialchars($employee['full_name']) ?>" required>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="cpf" class="form-label">CPF</label>
                    <input type="text" class="form-control" id="cpf" name="cpf" value="<?= htmlspecialchars($employee['cpf']) ?>">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="birth_date" class="form-label">Data de Nascimento</label>
                    <input type="date" class="form-control" id="birth_date" name="birth_date" value="<?= $employee['birth_date'] ?>">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="hire_date" class="form-label">Data de Contratação *</label>
                    <input type="date" class="form-control" id="hire_date" name="hire_date" value="<?= $employee['hire_date'] ?>" required>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="position" class="form-label">Cargo *</label>
                    <input type="text" class="form-control" id="position" name="position" value="<?= htmlspecialchars($employee['position']) ?>" list="positions" required>
                    <datalist id="positions">
                        <?php foreach ($commonPositions as $position): ?>
                            <option value="<?= htmlspecialchars($position) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="salary" class="form-label">Salário (R$)</label>
                    <input type="text" class="form-control" id="salary" name="salary" value="<?= $employee['salary'] ? number_format($employee['salary'], 2, ',', '.') : '' ?>">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="email" class="form-label">E-mail</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($employee['email']) ?>">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="phone" class="form-label">Telefone</label>
                    <input type="text" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($employee['phone']) ?>">
                </div>
                
                <div class="col-md-12 mb-3">
                    <label for="address" class="form-label">Endereço</label>
                    <input type="text" class="form-control" id="address" name="address" value="<?= htmlspecialchars($employee['address']) ?>">
                </div>
                
                <?php if ($isEdit && $employee['termination_date']): ?>
                <div class="col-md-12 mb-3">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> Este funcionário foi desligado em <?= format_date($employee['termination_date']) ?>.
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($isEdit && $employee['user_id']): ?>
                <div class="col-md-12 mb-3">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Este funcionário possui acesso ao sistema. Para gerenciar o acesso, vá para a <a href="../users/user_form.php?id=<?= $employee['user_id'] ?>">página de usuários</a>.
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                <a href="index.php" class="btn btn-secondary me-md-2">Cancelar</a>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>

<script>
// Formata o campo de salário para moeda
document.getElementById('salary').addEventListener('blur', function(e) {
    const value = e.target.value.replace(/\D/g, '');
    if (value) {
        const formattedValue = (parseFloat(value) / 100).toFixed(2).replace('.', ',');
        e.target.value = formattedValue;
    }
});

document.getElementById('salary').addEventListener('focus', function(e) {
    const value = e.target.value.replace(/\D/g, '');
    if (value) {
        e.target.value = (parseFloat(value) / 100).toFixed(2).replace('.', ',');
    }
});

// Formata o campo de CPF
document.getElementById('cpf').addEventListener('blur', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length === 11) {
        value = value.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
        e.target.value = value;
    }
});
</script>

<?php
$content = ob_get_clean();

// Carrega o layout principal
include __DIR__ . '/../../../template/layouts/main.php';

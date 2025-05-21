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
if (!has_permission('tours')) {
    header('Location: ../index.php');
    exit;
}

// Inicializa variáveis
$alertMessage = null;
$alertType = null;
$tour = [
    'id' => null,
    'product_id' => null,
    'name' => '',
    'description' => '',
    'price' => '',
    'duration' => '',
    'departure_time' => '',
    'departure_location' => '',
    'max_participants' => '',
    'guide_info' => '',
    'included_items' => '',
    'requirements' => '',
    'is_active' => 1
];

// Verifica se é uma edição
$isEdit = isset($_GET['id']) && is_numeric($_GET['id']);

if ($isEdit) {
    // Obtém os dados do passeio
    $stmt = $pdo->prepare("
        SELECT t.*, p.name, p.description, p.price, p.is_active
        FROM tours t
        JOIN products p ON t.product_id = p.id
        WHERE t.id = ?
    ");
    $stmt->execute([$_GET['id']]);
    $tourData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($tourData) {
        $tour = array_merge($tour, $tourData);
    } else {
        $alertMessage = "Passeio não encontrado.";
        $alertType = "danger";
        $isEdit = false;
    }
}

// Obtém as categorias de passeios
$stmt = $pdo->prepare("SELECT id, name FROM product_categories WHERE type = 'tour'");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Se não houver categorias, cria uma categoria padrão
if (count($categories) === 0) {
    $stmt = $pdo->prepare("INSERT INTO product_categories (name, description, type) VALUES ('Passeios', 'Categoria padrão para passeios turísticos', 'tour')");
    $stmt->execute();
    
    $categoryId = $pdo->lastInsertId();
    $categories = [[
        'id' => $categoryId,
        'name' => 'Passeios'
    ]];
}

// Processa o formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obtém os dados do formulário
    $tour['name'] = $_POST['name'] ?? '';
    $tour['description'] = $_POST['description'] ?? '';
    $tour['price'] = $_POST['price'] ? str_replace(',', '.', $_POST['price']) : 0;
    $tour['duration'] = $_POST['duration'] ?? '';
    $tour['departure_time'] = $_POST['departure_time'] ?? null;
    $tour['departure_location'] = $_POST['departure_location'] ?? '';
    $tour['max_participants'] = $_POST['max_participants'] ? intval($_POST['max_participants']) : null;
    $tour['guide_info'] = $_POST['guide_info'] ?? '';
    $tour['included_items'] = $_POST['included_items'] ?? '';
    $tour['requirements'] = $_POST['requirements'] ?? '';
    $tour['is_active'] = isset($_POST['is_active']) ? 1 : 0;
    $categoryId = $_POST['category_id'] ?? $categories[0]['id'];
    
    // Validação básica
    $errors = [];
    
    if (empty($tour['name'])) {
        $errors[] = "O nome do passeio é obrigatório.";
    }
    
    if (empty($tour['duration'])) {
        $errors[] = "A duração do passeio é obrigatória.";
    }
    
    if ($tour['price'] <= 0) {
        $errors[] = "O preço do passeio deve ser maior que zero.";
    }
    
    // Se não houver erros, salva os dados
    if (empty($errors)) {
        // Inicia uma transação
        $pdo->beginTransaction();
        
        try {
            if ($isEdit) {
                // Atualiza o produto existente
                $sql = "UPDATE products SET 
                    name = ?, 
                    description = ?, 
                    price = ?, 
                    is_active = ?,
                    updated_at = NOW()
                    WHERE id = ?";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $tour['name'],
                    $tour['description'],
                    $tour['price'],
                    $tour['is_active'],
                    $tour['product_id']
                ]);
                
                // Atualiza o passeio existente
                $sql = "UPDATE tours SET 
                    duration = ?, 
                    departure_time = ?, 
                    departure_location = ?, 
                    max_participants = ?, 
                    guide_info = ?, 
                    included_items = ?, 
                    requirements = ? 
                    WHERE id = ?";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $tour['duration'],
                    $tour['departure_time'],
                    $tour['departure_location'],
                    $tour['max_participants'],
                    $tour['guide_info'],
                    $tour['included_items'],
                    $tour['requirements'],
                    $tour['id']
                ]);
                
                $alertMessage = "Passeio atualizado com sucesso!";
                $alertType = "success";
            } else {
                // Insere um novo produto
                $sql = "INSERT INTO products (
                    category_id,
                    name, 
                    description, 
                    price, 
                    is_active,
                    created_at,
                    updated_at
                ) VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $categoryId,
                    $tour['name'],
                    $tour['description'],
                    $tour['price'],
                    $tour['is_active']
                ]);
                
                $productId = $pdo->lastInsertId();
                
                // Insere um novo passeio
                $sql = "INSERT INTO tours (
                    product_id,
                    duration, 
                    departure_time, 
                    departure_location, 
                    max_participants, 
                    guide_info, 
                    included_items, 
                    requirements
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $productId,
                    $tour['duration'],
                    $tour['departure_time'],
                    $tour['departure_location'],
                    $tour['max_participants'],
                    $tour['guide_info'],
                    $tour['included_items'],
                    $tour['requirements']
                ]);
                
                $tourId = $pdo->lastInsertId();
                
                $alertMessage = "Passeio cadastrado com sucesso!";
                $alertType = "success";
                
                // Redireciona para a página de edição
                header("Location: tour_form.php?id=$tourId");
                exit;
            }
            
            // Confirma a transação
            $pdo->commit();
        } catch (Exception $e) {
            // Reverte a transação em caso de erro
            $pdo->rollBack();
            
            $alertMessage = "Erro ao salvar o passeio: " . $e->getMessage();
            $alertType = "danger";
        }
    } else {
        // Se houver erros, exibe-os
        $alertMessage = implode("<br>", $errors);
        $alertType = "danger";
    }
}

// Define as variáveis para o template
$pageTitle = ($isEdit ? 'Editar' : 'Novo') . ' Passeio - Sistema de Turismo';
$pageHeader = ($isEdit ? 'Editar' : 'Novo') . ' Passeio';
$showNavbar = true;

// Carrega o conteúdo da página
ob_start();
?>

<div class="row mb-4">
    <div class="col-md-12">
        <a href="list.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Voltar para a lista
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0"><?= $isEdit ? 'Editar' : 'Novo' ?> Passeio</h5>
    </div>
    <div class="card-body">
        <form action="" method="post">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="name" class="form-label">Nome do Passeio *</label>
                    <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($tour['name']) ?>" required>
                </div>
                
                <div class="col-md-3 mb-3">
                    <label for="price" class="form-label">Preço (R$) *</label>
                    <input type="text" class="form-control" id="price" name="price" value="<?= htmlspecialchars(number_format($tour['price'], 2, ',', '.')) ?>" required>
                </div>
                
                <div class="col-md-3 mb-3">
                    <label for="duration" class="form-label">Duração *</label>
                    <input type="text" class="form-control" id="duration" name="duration" value="<?= htmlspecialchars($tour['duration']) ?>" required placeholder="Ex: 2 horas, Meio dia, etc.">
                </div>
                
                <div class="col-md-12 mb-3">
                    <label for="description" class="form-label">Descrição</label>
                    <textarea class="form-control" id="description" name="description" rows="3"><?= htmlspecialchars($tour['description']) ?></textarea>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="departure_time" class="form-label">Horário de Saída</label>
                    <input type="time" class="form-control" id="departure_time" name="departure_time" value="<?= $tour['departure_time'] ? date('H:i', strtotime($tour['departure_time'])) : '' ?>">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="departure_location" class="form-label">Local de Saída</label>
                    <input type="text" class="form-control" id="departure_location" name="departure_location" value="<?= htmlspecialchars($tour['departure_location']) ?>">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="max_participants" class="form-label">Número Máximo de Participantes</label>
                    <input type="number" class="form-control" id="max_participants" name="max_participants" value="<?= htmlspecialchars($tour['max_participants']) ?>" min="1">
                    <small class="text-muted">Deixe em branco para ilimitado</small>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="category_id" class="form-label">Categoria</label>
                    <select class="form-select" id="category_id" name="category_id" <?= $isEdit ? 'disabled' : '' ?>>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-12 mb-3">
                    <label for="guide_info" class="form-label">Informações do Guia</label>
                    <textarea class="form-control" id="guide_info" name="guide_info" rows="2"><?= htmlspecialchars($tour['guide_info']) ?></textarea>
                </div>
                
                <div class="col-md-12 mb-3">
                    <label for="included_items" class="form-label">Itens Inclusos</label>
                    <textarea class="form-control" id="included_items" name="included_items" rows="2"><?= htmlspecialchars($tour['included_items']) ?></textarea>
                </div>
                
                <div class="col-md-12 mb-3">
                    <label for="requirements" class="form-label">Requisitos</label>
                    <textarea class="form-control" id="requirements" name="requirements" rows="2"><?= htmlspecialchars($tour['requirements']) ?></textarea>
                </div>
                
                <div class="col-md-12 mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" <?= $tour['is_active'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">
                            Passeio ativo
                        </label>
                    </div>
                </div>
            </div>
            
            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                <a href="list.php" class="btn btn-secondary me-md-2">Cancelar</a>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();

// Carrega o layout principal
include __DIR__ . '/../../template/layouts/main.php';

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

// Processa a exclusão de passeio
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $tourId = $_GET['delete'];
    
    // Verifica se o passeio existe
    $stmt = $pdo->prepare("SELECT t.id FROM tours t JOIN products p ON t.product_id = p.id WHERE t.id = ?");
    $stmt->execute([$tourId]);
    
    if ($stmt->fetchColumn()) {
        // Verifica se o passeio possui agendamentos
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tour_schedules WHERE tour_id = ?");
        $stmt->execute([$tourId]);
        $scheduleCount = $stmt->fetchColumn();
        
        if ($scheduleCount > 0) {
            $alertMessage = "Não é possível excluir este passeio pois ele possui agendamentos associados.";
            $alertType = "danger";
        } else {
            // Obtém o ID do produto associado ao passeio
            $stmt = $pdo->prepare("SELECT product_id FROM tours WHERE id = ?");
            $stmt->execute([$tourId]);
            $productId = $stmt->fetchColumn();
            
            // Inicia uma transação
            $pdo->beginTransaction();
            
            try {
                // Exclui o passeio
                $stmt = $pdo->prepare("DELETE FROM tours WHERE id = ?");
                $stmt->execute([$tourId]);
                
                // Exclui o produto associado
                $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
                $stmt->execute([$productId]);
                
                // Confirma a transação
                $pdo->commit();
                
                $alertMessage = "Passeio excluído com sucesso!";
                $alertType = "success";
            } catch (Exception $e) {
                // Reverte a transação em caso de erro
                $pdo->rollBack();
                
                $alertMessage = "Erro ao excluir o passeio: " . $e->getMessage();
                $alertType = "danger";
            }
        }
    } else {
        $alertMessage = "Passeio não encontrado.";
        $alertType = "danger";
    }
}

// Processa a busca de passeios
$searchTerm = $_GET['search'] ?? '';
$searchParams = [];

if (!empty($searchTerm)) {
    $searchQuery = "AND p.name LIKE :search";
    $searchParams[':search'] = "%$searchTerm%";
} else {
    $searchQuery = "";
}

// Obtém a lista de passeios
$stmt = $pdo->prepare("
    SELECT t.*, p.name, p.price, p.description, p.is_active
    FROM tours t
    JOIN products p ON t.product_id = p.id
    WHERE p.category_id IN (SELECT id FROM product_categories WHERE type = 'tour')
    $searchQuery
    ORDER BY p.name ASC
");
$stmt->execute($searchParams);
$tours = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Define as variáveis para o template
$pageTitle = 'Passeios - Sistema de Turismo';
$pageHeader = 'Gerenciamento de Passeios';
$showNavbar = true;

// Carrega o conteúdo da página
ob_start();
?>

<div class="row mb-4">
    <div class="col-md-6">
        <form action="" method="get" class="d-flex">
            <input type="text" name="search" class="form-control me-2" placeholder="Buscar passeio..." value="<?= htmlspecialchars($searchTerm) ?>">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i>
            </button>
        </form>
    </div>
    <div class="col-md-6 text-end">
        <a href="tour_form.php" class="btn btn-success">
            <i class="fas fa-plus"></i> Novo Passeio
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Lista de Passeios</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Duração</th>
                        <th>Horário</th>
                        <th>Preço</th>
                        <th>Vagas</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($tours) > 0): ?>
                        <?php foreach ($tours as $tour): ?>
                            <tr>
                                <td><?= htmlspecialchars($tour['name']) ?></td>
                                <td><?= htmlspecialchars($tour['duration']) ?></td>
                                <td><?= $tour['departure_time'] ? date('H:i', strtotime($tour['departure_time'])) : 'Variável' ?></td>
                                <td><?= format_money($tour['price']) ?></td>
                                <td><?= $tour['max_participants'] ?: 'Ilimitado' ?></td>
                                <td>
                                    <?php if ($tour['is_active']): ?>
                                        <span class="badge bg-success">Ativo</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inativo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="tour_view.php?id=<?= $tour['id'] ?>" class="btn btn-sm btn-info" title="Visualizar">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="tour_form.php?id=<?= $tour['id'] ?>" class="btn btn-sm btn-primary" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="schedule.php?tour_id=<?= $tour['id'] ?>" class="btn btn-sm btn-warning" title="Agendamentos">
                                        <i class="fas fa-calendar-alt"></i>
                                    </a>
                                    <a href="?delete=<?= $tour['id'] ?>" class="btn btn-sm btn-danger" title="Excluir" onclick="return confirmDelete('Tem certeza que deseja excluir este passeio?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center">Nenhum passeio encontrado.</td>
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
include __DIR__ . '/../../template/layouts/main.php';

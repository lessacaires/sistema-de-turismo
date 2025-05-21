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
$selectedTourId = $_GET['tour_id'] ?? null;
$selectedDate = $_GET['date'] ?? date('Y-m');

// Obtém a lista de passeios
$stmt = $pdo->prepare("
    SELECT t.id, p.name
    FROM tours t
    JOIN products p ON t.product_id = p.id
    WHERE p.is_active = 1
    ORDER BY p.name ASC
");
$stmt->execute();
$tours = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Se não houver passeio selecionado e houver passeios disponíveis, seleciona o primeiro
if (!$selectedTourId && count($tours) > 0) {
    $selectedTourId = $tours[0]['id'];
}

// Processa a exclusão de agendamento
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $scheduleId = $_GET['delete'];
    
    // Verifica se o agendamento existe
    $stmt = $pdo->prepare("SELECT id FROM tour_schedules WHERE id = ?");
    $stmt->execute([$scheduleId]);
    
    if ($stmt->fetchColumn()) {
        // Verifica se o agendamento possui reservas
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tour_bookings WHERE schedule_id = ?");
        $stmt->execute([$scheduleId]);
        $bookingCount = $stmt->fetchColumn();
        
        if ($bookingCount > 0) {
            $alertMessage = "Não é possível excluir este agendamento pois ele possui reservas associadas.";
            $alertType = "danger";
        } else {
            // Exclui o agendamento
            $stmt = $pdo->prepare("DELETE FROM tour_schedules WHERE id = ?");
            $stmt->execute([$scheduleId]);
            
            $alertMessage = "Agendamento excluído com sucesso!";
            $alertType = "success";
        }
    } else {
        $alertMessage = "Agendamento não encontrado.";
        $alertType = "danger";
    }
}

// Processa o formulário de novo agendamento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_schedule'])) {
    $tourId = $_POST['tour_id'] ?? null;
    $date = $_POST['date'] ?? null;
    $availableSpots = $_POST['available_spots'] ?? null;
    $notes = $_POST['notes'] ?? '';
    
    // Validação básica
    $errors = [];
    
    if (!$tourId) {
        $errors[] = "Selecione um passeio.";
    }
    
    if (!$date) {
        $errors[] = "A data é obrigatória.";
    } elseif (strtotime($date) < strtotime(date('Y-m-d'))) {
        $errors[] = "A data não pode ser anterior à data atual.";
    }
    
    if (!$availableSpots || $availableSpots <= 0) {
        $errors[] = "O número de vagas deve ser maior que zero.";
    }
    
    // Verifica se já existe um agendamento para este passeio nesta data
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tour_schedules WHERE tour_id = ? AND date = ?");
    $stmt->execute([$tourId, $date]);
    
    if ($stmt->fetchColumn() > 0) {
        $errors[] = "Já existe um agendamento para este passeio nesta data.";
    }
    
    // Se não houver erros, salva o agendamento
    if (empty($errors)) {
        $sql = "INSERT INTO tour_schedules (tour_id, date, available_spots, status, notes) VALUES (?, ?, ?, 'scheduled', ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$tourId, $date, $availableSpots, $notes]);
        
        $alertMessage = "Agendamento criado com sucesso!";
        $alertType = "success";
        
        // Atualiza a data selecionada para o mês do novo agendamento
        $selectedDate = date('Y-m', strtotime($date));
        $selectedTourId = $tourId;
    } else {
        // Se houver erros, exibe-os
        $alertMessage = implode("<br>", $errors);
        $alertType = "danger";
    }
}

// Obtém os detalhes do passeio selecionado
$tourDetails = null;
if ($selectedTourId) {
    $stmt = $pdo->prepare("
        SELECT t.*, p.name, p.price, p.description
        FROM tours t
        JOIN products p ON t.product_id = p.id
        WHERE t.id = ?
    ");
    $stmt->execute([$selectedTourId]);
    $tourDetails = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Obtém os agendamentos do passeio selecionado para o mês selecionado
$schedules = [];
if ($selectedTourId) {
    $startDate = date('Y-m-01', strtotime($selectedDate));
    $endDate = date('Y-m-t', strtotime($selectedDate));
    
    $stmt = $pdo->prepare("
        SELECT ts.*, 
               (SELECT COUNT(*) FROM tour_bookings tb WHERE tb.schedule_id = ts.id) as bookings,
               (SELECT SUM(num_participants) FROM tour_bookings tb WHERE tb.schedule_id = ts.id) as participants
        FROM tour_schedules ts
        WHERE ts.tour_id = ? AND ts.date BETWEEN ? AND ?
        ORDER BY ts.date ASC
    ");
    $stmt->execute([$selectedTourId, $startDate, $endDate]);
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Define as variáveis para o template
$pageTitle = 'Agendamentos de Passeios - Sistema de Turismo';
$pageHeader = 'Agendamentos de Passeios';
$showNavbar = true;

// Carrega o conteúdo da página
ob_start();
?>

<div class="row mb-4">
    <div class="col-md-6">
        <a href="list.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Voltar para a lista de passeios
        </a>
    </div>
    <div class="col-md-6 text-end">
        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addScheduleModal">
            <i class="fas fa-plus"></i> Novo Agendamento
        </button>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Selecionar Passeio</h5>
            </div>
            <div class="card-body">
                <form action="" method="get" class="row g-3">
                    <div class="col-md-6">
                        <label for="tour_id" class="form-label">Passeio</label>
                        <select class="form-select" id="tour_id" name="tour_id" onchange="this.form.submit()">
                            <?php foreach ($tours as $tour): ?>
                                <option value="<?= $tour['id'] ?>" <?= $selectedTourId == $tour['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($tour['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="date" class="form-label">Mês</label>
                        <input type="month" class="form-control" id="date" name="date" value="<?= $selectedDate ?>" onchange="this.form.submit()">
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <?php if ($tourDetails): ?>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Detalhes do Passeio</h5>
            </div>
            <div class="card-body">
                <h5><?= htmlspecialchars($tourDetails['name']) ?></h5>
                <p><strong>Preço:</strong> <?= format_money($tourDetails['price']) ?></p>
                <p><strong>Duração:</strong> <?= htmlspecialchars($tourDetails['duration']) ?></p>
                <?php if ($tourDetails['departure_time']): ?>
                <p><strong>Horário de Saída:</strong> <?= date('H:i', strtotime($tourDetails['departure_time'])) ?></p>
                <?php endif; ?>
                <?php if ($tourDetails['departure_location']): ?>
                <p><strong>Local de Saída:</strong> <?= htmlspecialchars($tourDetails['departure_location']) ?></p>
                <?php endif; ?>
                <?php if ($tourDetails['max_participants']): ?>
                <p><strong>Vagas por Passeio:</strong> <?= $tourDetails['max_participants'] ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if ($selectedTourId): ?>
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Agendamentos para <?= date('F Y', strtotime($selectedDate)) ?></h5>
    </div>
    <div class="card-body">
        <?php if (count($schedules) > 0): ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Vagas Totais</th>
                        <th>Reservas</th>
                        <th>Vagas Disponíveis</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($schedules as $schedule): ?>
                        <?php 
                        $participants = $schedule['participants'] ?: 0;
                        $availableSpots = $schedule['available_spots'] - $participants;
                        ?>
                        <tr>
                            <td><?= format_date($schedule['date']) ?></td>
                            <td><?= $schedule['available_spots'] ?></td>
                            <td><?= $schedule['bookings'] ?> (<?= $participants ?> participantes)</td>
                            <td><?= $availableSpots ?></td>
                            <td>
                                <?php
                                $statusClass = '';
                                $statusText = '';
                                
                                switch ($schedule['status']) {
                                    case 'scheduled':
                                        $statusClass = 'primary';
                                        $statusText = 'Agendado';
                                        break;
                                    case 'completed':
                                        $statusClass = 'success';
                                        $statusText = 'Concluído';
                                        break;
                                    case 'cancelled':
                                        $statusClass = 'danger';
                                        $statusText = 'Cancelado';
                                        break;
                                }
                                ?>
                                <span class="badge bg-<?= $statusClass ?>"><?= $statusText ?></span>
                            </td>
                            <td>
                                <a href="schedule_view.php?id=<?= $schedule['id'] ?>" class="btn btn-sm btn-info" title="Visualizar">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="schedule_form.php?id=<?= $schedule['id'] ?>" class="btn btn-sm btn-primary" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="?delete=<?= $schedule['id'] ?>&tour_id=<?= $selectedTourId ?>&date=<?= $selectedDate ?>" class="btn btn-sm btn-danger" title="Excluir" onclick="return confirmDelete('Tem certeza que deseja excluir este agendamento?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <p class="text-center">Nenhum agendamento encontrado para este mês.</p>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Modal para adicionar novo agendamento -->
<div class="modal fade" id="addScheduleModal" tabindex="-1" aria-labelledby="addScheduleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="" method="post">
                <div class="modal-header">
                    <h5 class="modal-title" id="addScheduleModalLabel">Novo Agendamento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="modal_tour_id" class="form-label">Passeio</label>
                        <select class="form-select" id="modal_tour_id" name="tour_id" required>
                            <?php foreach ($tours as $tour): ?>
                                <option value="<?= $tour['id'] ?>" <?= $selectedTourId == $tour['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($tour['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="modal_date" class="form-label">Data</label>
                        <input type="date" class="form-control" id="modal_date" name="date" required min="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="mb-3">
                        <label for="modal_available_spots" class="form-label">Vagas Disponíveis</label>
                        <input type="number" class="form-control" id="modal_available_spots" name="available_spots" required min="1">
                    </div>
                    <div class="mb-3">
                        <label for="modal_notes" class="form-label">Observações</label>
                        <textarea class="form-control" id="modal_notes" name="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="add_schedule" class="btn btn-primary">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

// Carrega o layout principal
include __DIR__ . '/../../template/layouts/main.php';

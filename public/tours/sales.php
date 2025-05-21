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
$selectedCustomerId = $_GET['customer_id'] ?? null;
$selectedScheduleId = $_GET['schedule_id'] ?? null;

// Obtém os dados do cliente selecionado
$customer = null;
if ($selectedCustomerId) {
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute([$selectedCustomerId]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Obtém os dados do agendamento selecionado
$schedule = null;
if ($selectedScheduleId) {
    $stmt = $pdo->prepare("
        SELECT ts.*, t.*, p.name as tour_name, p.price
        FROM tour_schedules ts
        JOIN tours t ON ts.tour_id = t.id
        JOIN products p ON t.product_id = p.id
        WHERE ts.id = ?
    ");
    $stmt->execute([$selectedScheduleId]);
    $schedule = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Calcula o número de participantes já confirmados
    if ($schedule) {
        $stmt = $pdo->prepare("
            SELECT SUM(num_participants) as total_participants
            FROM tour_bookings
            WHERE schedule_id = ? AND payment_status != 'cancelled'
        ");
        $stmt->execute([$selectedScheduleId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $schedule['total_participants'] = $result['total_participants'] ?: 0;
        $schedule['available_spots_left'] = $schedule['available_spots'] - $schedule['total_participants'];
    }
}

// Processa o formulário de venda
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_booking'])) {
    $customerId = $_POST['customer_id'] ?? null;
    $scheduleId = $_POST['schedule_id'] ?? null;
    $numParticipants = $_POST['num_participants'] ?? 1;
    $totalPrice = $_POST['total_price'] ?? 0;
    $paymentMethod = $_POST['payment_method'] ?? '';
    $paymentStatus = $_POST['payment_status'] ?? 'pending';
    $notes = $_POST['notes'] ?? '';
    
    // Validação básica
    $errors = [];
    
    if (!$customerId) {
        $errors[] = "Selecione um cliente.";
    }
    
    if (!$scheduleId) {
        $errors[] = "Selecione um agendamento.";
    }
    
    if ($numParticipants <= 0) {
        $errors[] = "O número de participantes deve ser maior que zero.";
    }
    
    if ($totalPrice <= 0) {
        $errors[] = "O valor total deve ser maior que zero.";
    }
    
    // Verifica se há vagas disponíveis
    if ($schedule && $numParticipants > $schedule['available_spots_left']) {
        $errors[] = "Não há vagas suficientes disponíveis. Vagas restantes: " . $schedule['available_spots_left'];
    }
    
    // Se não houver erros, salva a reserva
    if (empty($errors)) {
        // Inicia uma transação
        $pdo->beginTransaction();
        
        try {
            // Insere a reserva
            $sql = "INSERT INTO tour_bookings (
                schedule_id,
                customer_id,
                employee_id,
                booking_date,
                num_participants,
                total_price,
                payment_status,
                payment_method,
                notes
            ) VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $scheduleId,
                $customerId,
                get_logged_in_user_id(),
                $numParticipants,
                $totalPrice,
                $paymentStatus,
                $paymentMethod,
                $notes
            ]);
            
            $bookingId = $pdo->lastInsertId();
            
            // Se o pagamento foi confirmado, registra a transação financeira
            if ($paymentStatus === 'paid') {
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
                ) VALUES (NOW(), ?, 'income', 'tour_sale', ?, ?, ?, 'tour_booking', ?)";
                
                $description = "Venda de passeio: " . $schedule['tour_name'] . " - " . format_date($schedule['date']);
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $totalPrice,
                    $description,
                    $paymentMethod,
                    $bookingId,
                    get_logged_in_user_id()
                ]);
            }
            
            // Confirma a transação
            $pdo->commit();
            
            $alertMessage = "Reserva realizada com sucesso!";
            $alertType = "success";
            
            // Redireciona para a página de visualização da reserva
            header("Location: booking_view.php?id=$bookingId");
            exit;
        } catch (Exception $e) {
            // Reverte a transação em caso de erro
            $pdo->rollBack();
            
            $alertMessage = "Erro ao salvar a reserva: " . $e->getMessage();
            $alertType = "danger";
        }
    } else {
        // Se houver erros, exibe-os
        $alertMessage = implode("<br>", $errors);
        $alertType = "danger";
    }
}

// Obtém a lista de clientes para o campo de busca
$stmt = $pdo->prepare("SELECT id, full_name, email, phone FROM customers ORDER BY full_name ASC LIMIT 100");
$stmt->execute();
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtém a lista de agendamentos futuros
$stmt = $pdo->prepare("
    SELECT ts.id, ts.date, p.name as tour_name, ts.available_spots,
           (SELECT SUM(num_participants) FROM tour_bookings tb WHERE tb.schedule_id = ts.id AND tb.payment_status != 'cancelled') as booked_spots
    FROM tour_schedules ts
    JOIN tours t ON ts.tour_id = t.id
    JOIN products p ON t.product_id = p.id
    WHERE ts.date >= CURDATE() AND ts.status = 'scheduled'
    ORDER BY ts.date ASC
    LIMIT 100
");
$stmt->execute();
$schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcula as vagas disponíveis para cada agendamento
foreach ($schedules as &$s) {
    $s['booked_spots'] = $s['booked_spots'] ?: 0;
    $s['available_spots_left'] = $s['available_spots'] - $s['booked_spots'];
}

// Define as variáveis para o template
$pageTitle = 'Venda de Passeios - Sistema de Turismo';
$pageHeader = 'Venda de Passeios';
$showNavbar = true;

// Carrega o conteúdo da página
ob_start();
?>

<div class="row mb-4">
    <div class="col-md-6">
        <a href="list.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Voltar para passeios
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Nova Reserva de Passeio</h5>
    </div>
    <div class="card-body">
        <form action="" method="post">
            <div class="row">
                <!-- Seleção de Cliente -->
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">Cliente</h6>
                            <a href="../receptive/customer_form.php" class="btn btn-sm btn-success" target="_blank">
                                <i class="fas fa-plus"></i> Novo Cliente
                            </a>
                        </div>
                        <div class="card-body">
                            <?php if ($customer): ?>
                                <input type="hidden" name="customer_id" value="<?= $customer['id'] ?>">
                                <div class="mb-3">
                                    <strong>Nome:</strong> <?= htmlspecialchars($customer['full_name']) ?>
                                </div>
                                <?php if (!empty($customer['email'])): ?>
                                <div class="mb-3">
                                    <strong>E-mail:</strong> <?= htmlspecialchars($customer['email']) ?>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($customer['phone'])): ?>
                                <div class="mb-3">
                                    <strong>Telefone:</strong> <?= htmlspecialchars($customer['phone']) ?>
                                </div>
                                <?php endif; ?>
                                <a href="?<?= $selectedScheduleId ? 'schedule_id=' . $selectedScheduleId : '' ?>" class="btn btn-outline-secondary btn-sm">
                                    <i class="fas fa-user-edit"></i> Trocar Cliente
                                </a>
                            <?php else: ?>
                                <div class="mb-3">
                                    <label for="customer_id" class="form-label">Selecione um Cliente</label>
                                    <select class="form-select" id="customer_id" name="customer_id" required>
                                        <option value="">Selecione...</option>
                                        <?php foreach ($customers as $c): ?>
                                            <option value="<?= $c['id'] ?>">
                                                <?= htmlspecialchars($c['full_name']) ?> 
                                                <?= !empty($c['phone']) ? '- ' . htmlspecialchars($c['phone']) : '' ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Seleção de Passeio -->
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h6 class="mb-0">Passeio</h6>
                        </div>
                        <div class="card-body">
                            <?php if ($schedule): ?>
                                <input type="hidden" name="schedule_id" value="<?= $schedule['id'] ?>">
                                <div class="mb-3">
                                    <strong>Passeio:</strong> <?= htmlspecialchars($schedule['tour_name']) ?>
                                </div>
                                <div class="mb-3">
                                    <strong>Data:</strong> <?= format_date($schedule['date']) ?>
                                </div>
                                <div class="mb-3">
                                    <strong>Preço por pessoa:</strong> <?= format_money($schedule['price']) ?>
                                </div>
                                <div class="mb-3">
                                    <strong>Vagas disponíveis:</strong> <?= $schedule['available_spots_left'] ?>
                                </div>
                                <a href="?<?= $selectedCustomerId ? 'customer_id=' . $selectedCustomerId : '' ?>" class="btn btn-outline-secondary btn-sm">
                                    <i class="fas fa-exchange-alt"></i> Trocar Passeio
                                </a>
                            <?php else: ?>
                                <div class="mb-3">
                                    <label for="schedule_id" class="form-label">Selecione um Passeio</label>
                                    <select class="form-select" id="schedule_id" name="schedule_id" required>
                                        <option value="">Selecione...</option>
                                        <?php foreach ($schedules as $s): ?>
                                            <?php if ($s['available_spots_left'] > 0): ?>
                                                <option value="<?= $s['id'] ?>">
                                                    <?= htmlspecialchars($s['tour_name']) ?> - 
                                                    <?= format_date($s['date']) ?> 
                                                    (<?= $s['available_spots_left'] ?> vagas)
                                                </option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <?php if ($customer && $schedule): ?>
                <!-- Detalhes da Reserva -->
                <div class="col-md-12 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">Detalhes da Reserva</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="num_participants" class="form-label">Número de Participantes</label>
                                    <input type="number" class="form-control" id="num_participants" name="num_participants" value="1" min="1" max="<?= $schedule['available_spots_left'] ?>" required onchange="updateTotalPrice()">
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="unit_price" class="form-label">Preço por Pessoa</label>
                                    <input type="text" class="form-control" id="unit_price" value="<?= number_format($schedule['price'], 2, ',', '.') ?>" readonly>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="total_price" class="form-label">Valor Total</label>
                                    <input type="text" class="form-control" id="total_price_display" value="<?= number_format($schedule['price'], 2, ',', '.') ?>" readonly>
                                    <input type="hidden" name="total_price" id="total_price" value="<?= $schedule['price'] ?>">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="payment_method" class="form-label">Forma de Pagamento</label>
                                    <select class="form-select" id="payment_method" name="payment_method" required>
                                        <option value="">Selecione...</option>
                                        <option value="cash">Dinheiro</option>
                                        <option value="credit_card">Cartão de Crédito</option>
                                        <option value="debit_card">Cartão de Débito</option>
                                        <option value="pix">PIX</option>
                                        <option value="bank_transfer">Transferência Bancária</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="payment_status" class="form-label">Status do Pagamento</label>
                                    <select class="form-select" id="payment_status" name="payment_status" required>
                                        <option value="pending">Pendente</option>
                                        <option value="paid">Pago</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-12 mb-3">
                                    <label for="notes" class="form-label">Observações</label>
                                    <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-12">
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="list.php" class="btn btn-secondary me-md-2">Cancelar</a>
                        <button type="submit" name="save_booking" class="btn btn-primary">Confirmar Reserva</button>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<script>
function updateTotalPrice() {
    const numParticipants = parseInt(document.getElementById('num_participants').value) || 1;
    const unitPrice = parseFloat(<?= $schedule['price'] ?? 0 ?>);
    const totalPrice = numParticipants * unitPrice;
    
    document.getElementById('total_price').value = totalPrice;
    document.getElementById('total_price_display').value = totalPrice.toFixed(2).replace('.', ',');
}
</script>

<?php
$content = ob_get_clean();

// Carrega o layout principal
include __DIR__ . '/../../template/layouts/main.php';

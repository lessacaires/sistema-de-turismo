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
if (!has_permission('receptive')) {
    header('Location: ../index.php');
    exit;
}

// Verifica se o ID do cliente foi fornecido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: customers.php');
    exit;
}

$customerId = $_GET['id'];

// Obtém os dados do cliente
$stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$customerId]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    header('Location: customers.php');
    exit;
}

// Obtém as reservas de passeios do cliente
$stmt = $pdo->prepare("
    SELECT tb.*, ts.date, p.name as tour_name
    FROM tour_bookings tb
    JOIN tour_schedules ts ON tb.schedule_id = ts.id
    JOIN tours t ON ts.tour_id = t.id
    JOIN products p ON t.product_id = p.id
    WHERE tb.customer_id = ?
    ORDER BY ts.date DESC
");
$stmt->execute([$customerId]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Define as variáveis para o template
$pageTitle = 'Detalhes do Cliente - Sistema de Turismo';
$pageHeader = 'Detalhes do Cliente';
$showNavbar = true;

// Carrega o conteúdo da página
ob_start();
?>

<div class="row mb-4">
    <div class="col-md-12">
        <a href="customers.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Voltar para a lista
        </a>
        <a href="customer_form.php?id=<?= $customer['id'] ?>" class="btn btn-primary ms-2">
            <i class="fas fa-edit"></i> Editar Cliente
        </a>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Informações do Cliente</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <strong>Nome:</strong> <?= htmlspecialchars($customer['full_name']) ?>
                </div>
                
                <?php if (!empty($customer['cpf'])): ?>
                <div class="mb-3">
                    <strong>CPF:</strong> <?= htmlspecialchars($customer['cpf']) ?>
                </div>
                <?php endif; ?>
                
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
                
                <?php if (!empty($customer['address'])): ?>
                <div class="mb-3">
                    <strong>Endereço:</strong> <?= htmlspecialchars($customer['address']) ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($customer['birth_date'])): ?>
                <div class="mb-3">
                    <strong>Data de Nascimento:</strong> <?= format_date($customer['birth_date']) ?>
                </div>
                <?php endif; ?>
                
                <div class="mb-3">
                    <strong>Data de Cadastro:</strong> <?= format_date($customer['registration_date'], true) ?>
                </div>
            </div>
        </div>
        
        <?php if (!empty($customer['notes'])): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Observações</h5>
            </div>
            <div class="card-body">
                <p><?= nl2br(htmlspecialchars($customer['notes'])) ?></p>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Histórico de Passeios</h5>
                <a href="../tours/sales.php?customer_id=<?= $customer['id'] ?>" class="btn btn-sm btn-success">
                    <i class="fas fa-plus"></i> Novo Passeio
                </a>
            </div>
            <div class="card-body">
                <?php if (count($bookings) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Passeio</th>
                                <th>Participantes</th>
                                <th>Valor</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <td><?= format_date($booking['date']) ?></td>
                                <td><?= htmlspecialchars($booking['tour_name']) ?></td>
                                <td><?= $booking['num_participants'] ?></td>
                                <td><?= format_money($booking['total_price']) ?></td>
                                <td>
                                    <?php
                                    $statusClass = '';
                                    $statusText = '';
                                    
                                    switch ($booking['payment_status']) {
                                        case 'paid':
                                            $statusClass = 'success';
                                            $statusText = 'Pago';
                                            break;
                                        case 'pending':
                                            $statusClass = 'warning';
                                            $statusText = 'Pendente';
                                            break;
                                        case 'cancelled':
                                            $statusClass = 'danger';
                                            $statusText = 'Cancelado';
                                            break;
                                        case 'refunded':
                                            $statusClass = 'info';
                                            $statusText = 'Reembolsado';
                                            break;
                                    }
                                    ?>
                                    <span class="badge bg-<?= $statusClass ?>"><?= $statusText ?></span>
                                </td>
                                <td>
                                    <a href="../tours/booking_view.php?id=<?= $booking['id'] ?>" class="btn btn-sm btn-info" title="Visualizar">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p class="text-center">Este cliente ainda não realizou nenhum passeio.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

// Carrega o layout principal
include __DIR__ . '/../../template/layouts/main.php';

<?php

// Carrega o arquivo de configuração do banco de dados
require_once __DIR__ . '/../config/database.php';

// Carrega o arquivo de funções de banco de dados
require_once __DIR__ . '/../functions/database.php';

// Carrega o arquivo de funções de autenticação
require_once __DIR__ . '/../functions/auth.php';

// Carrega o arquivo de funções utilitárias
require_once __DIR__ . '/../functions/utils.php';

// Conecta ao banco de dados
$pdo = connect_db();

// Verifica se o usuário está logado
if (!is_logged_in()) {
    // Redireciona para a página de login
    header('Location: login.php');
    exit;
}

// Define as variáveis para o template
$pageTitle = 'Início - Sistema de Turismo';
$pageHeader = 'Dashboard';
$showNavbar = true;

// Carrega o conteúdo da página
ob_start();
?>

<div class="row">
    <div class="col-md-3 mb-4">
        <div class="card bg-primary text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase">Passeios Hoje</h6>
                        <h2 class="mb-0">0</h2>
                    </div>
                    <i class="fas fa-map-marked-alt fa-3x opacity-50"></i>
                </div>
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <a href="tours/schedule.php" class="text-white text-decoration-none">Ver detalhes</a>
                <i class="fas fa-arrow-circle-right text-white"></i>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-4">
        <div class="card bg-success text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase">Vendas Hoje</h6>
                        <h2 class="mb-0">R$ 0,00</h2>
                    </div>
                    <i class="fas fa-cash-register fa-3x opacity-50"></i>
                </div>
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <a href="pos/sales_report.php" class="text-white text-decoration-none">Ver detalhes</a>
                <i class="fas fa-arrow-circle-right text-white"></i>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-4">
        <div class="card bg-warning text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase">Comandas Abertas</h6>
                        <h2 class="mb-0">0</h2>
                    </div>
                    <i class="fas fa-utensils fa-3x opacity-50"></i>
                </div>
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <a href="restaurant/orders.php" class="text-white text-decoration-none">Ver detalhes</a>
                <i class="fas fa-arrow-circle-right text-white"></i>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-4">
        <div class="card bg-danger text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase">Estoque Baixo</h6>
                        <h2 class="mb-0">0</h2>
                    </div>
                    <i class="fas fa-exclamation-triangle fa-3x opacity-50"></i>
                </div>
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <a href="admin/stock.php" class="text-white text-decoration-none">Ver detalhes</a>
                <i class="fas fa-arrow-circle-right text-white"></i>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-8 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Próximos Passeios</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Passeio</th>
                                <th>Vagas</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="5" class="text-center">Nenhum passeio agendado.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Acesso Rápido</h5>
            </div>
            <div class="card-body">
                <div class="list-group">
                    <a href="pos/index.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-cash-register me-2"></i> Abrir PDV
                    </a>
                    <a href="restaurant/orders.php?new=1" class="list-group-item list-group-item-action">
                        <i class="fas fa-utensils me-2"></i> Nova Comanda
                    </a>
                    <a href="tours/sales.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-ticket-alt me-2"></i> Vender Passeio
                    </a>
                    <a href="receptive/customers.php?new=1" class="list-group-item list-group-item-action">
                        <i class="fas fa-user-plus me-2"></i> Cadastrar Cliente
                    </a>
                    <a href="admin/reports.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-chart-bar me-2"></i> Relatórios
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

// Carrega o layout principal
include __DIR__ . '/../template/layouts/main.php';
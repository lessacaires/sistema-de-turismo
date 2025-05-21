<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Sistema de Turismo' ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/assets/css/style.css">
    
    <?php if (isset($extraCss)): ?>
        <?= $extraCss ?>
    <?php endif; ?>
</head>
<body>
    <?php if (isset($showNavbar) && $showNavbar): ?>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="/">Sistema de Turismo</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                    </li>
                    
                    <?php if (has_permission('receptive')): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="receptiveDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-concierge-bell"></i> Receptivo
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="receptiveDropdown">
                            <li><a class="dropdown-item" href="/receptive/customers.php">Clientes</a></li>
                            <li><a class="dropdown-item" href="/receptive/bookings.php">Reservas</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>
                    
                    <?php if (has_permission('tours')): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="toursDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-map-marked-alt"></i> Passeios
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="toursDropdown">
                            <li><a class="dropdown-item" href="/tours/list.php">Listar Passeios</a></li>
                            <li><a class="dropdown-item" href="/tours/schedule.php">Agendamentos</a></li>
                            <li><a class="dropdown-item" href="/tours/sales.php">Vendas</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>
                    
                    <?php if (has_permission('restaurant')): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="restaurantDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-utensils"></i> Restaurante
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="restaurantDropdown">
                            <li><a class="dropdown-item" href="/restaurant/tables.php">Mesas</a></li>
                            <li><a class="dropdown-item" href="/restaurant/orders.php">Comandas</a></li>
                            <li><a class="dropdown-item" href="/restaurant/menu.php">Cardápio</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>
                    
                    <?php if (has_permission('bar')): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="barDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-cocktail"></i> Bar
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="barDropdown">
                            <li><a class="dropdown-item" href="/bar/tables.php">Mesas</a></li>
                            <li><a class="dropdown-item" href="/bar/orders.php">Comandas</a></li>
                            <li><a class="dropdown-item" href="/bar/menu.php">Cardápio</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>
                    
                    <?php if (has_permission('pos')): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="/pos/index.php"><i class="fas fa-cash-register"></i> PDV</a>
                    </li>
                    <?php endif; ?>
                    
                    <?php if (has_permission('admin')): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-cogs"></i> Administração
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="adminDropdown">
                            <li><a class="dropdown-item" href="/admin/users.php">Usuários</a></li>
                            <li><a class="dropdown-item" href="/admin/employees.php">Funcionários</a></li>
                            <li><a class="dropdown-item" href="/admin/products.php">Produtos</a></li>
                            <li><a class="dropdown-item" href="/admin/stock.php">Estoque</a></li>
                            <li><a class="dropdown-item" href="/admin/suppliers.php">Fornecedores</a></li>
                            <li><a class="dropdown-item" href="/admin/purchases.php">Compras</a></li>
                            <li><a class="dropdown-item" href="/admin/financial.php">Financeiro</a></li>
                            <li><a class="dropdown-item" href="/admin/reports.php">Relatórios</a></li>
                            <li><a class="dropdown-item" href="/admin/settings.php">Configurações</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user"></i> <?= get_logged_in_username() ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="/profile.php">Meu Perfil</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="/logout.php">Sair</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <?php endif; ?>
    
    <!-- Main Content -->
    <main class="container-fluid py-4">
        <?php if (isset($pageHeader)): ?>
        <div class="row mb-4">
            <div class="col">
                <h1 class="h3"><?= $pageHeader ?></h1>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (isset($alertMessage) && isset($alertType)): ?>
        <div class="alert alert-<?= $alertType ?> alert-dismissible fade show" role="alert">
            <?= $alertMessage ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
        
        <?= $content ?? '' ?>
    </main>
    
    <!-- Footer -->
    <footer class="bg-light py-3 mt-auto">
        <div class="container text-center">
            <p class="mb-0">&copy; <?= date('Y') ?> Sistema de Turismo. Todos os direitos reservados.</p>
        </div>
    </footer>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Custom JS -->
    <script src="/assets/js/main.js"></script>
    
    <?php if (isset($extraJs)): ?>
        <?= $extraJs ?>
    <?php endif; ?>
</body>
</html>

<?php
/**
 * Página de Inicio - Portal de Clientes
 * Sistema de Facturación RETEC
 */

require_once 'auth.php';
require_once 'config.php';

// Procesar logout
if (isset($_GET['logout'])) {
    logout();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio - Portal de Clientes</title>
    <link rel="icon" type="image/png" href="logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --sidebar-width: 260px;
            --header-height: 60px;
            --sidebar-bg: #1a1a2e;
            --sidebar-hover: #16213e;
            --header-bg: #28a745;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f6f9;
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: var(--sidebar-bg);
            z-index: 1000;
        }

        .sidebar-header {
            padding: 20px;
            background: rgba(0,0,0,0.2);
            text-align: center;
        }

        .sidebar-header h3 {
            color: #fff;
            margin: 0;
            font-size: 1.3rem;
        }

        .sidebar-header small {
            color: rgba(255,255,255,0.6);
        }

        .sidebar-menu {
            padding: 20px 0;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: var(--sidebar-hover);
            color: #fff;
            border-left-color: var(--header-bg);
        }

        .sidebar-menu a i {
            margin-right: 12px;
            font-size: 1.2rem;
            width: 24px;
            text-align: center;
        }

        .sidebar-menu .menu-divider {
            height: 1px;
            background: rgba(255,255,255,0.1);
            margin: 15px 20px;
        }

        .cliente-info {
            padding: 15px 20px;
            background: rgba(40, 167, 69, 0.2);
            border-left: 3px solid #28a745;
            margin: 10px 15px;
            border-radius: 0 8px 8px 0;
        }

        .cliente-info .nombre {
            color: #fff;
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 3px;
        }

        .cliente-info .cuit {
            color: rgba(255,255,255,0.7);
            font-size: 0.8rem;
        }

        /* Header */
        .main-header {
            position: fixed;
            top: 0;
            left: var(--sidebar-width);
            right: 0;
            height: var(--header-height);
            background: var(--header-bg);
            z-index: 999;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .main-header h4 {
            color: #fff;
            margin: 0;
            font-weight: 600;
        }

        .user-dropdown .btn {
            background: rgba(255,255,255,0.2);
            border: none;
            color: #fff;
            padding: 8px 15px;
            border-radius: 8px;
        }

        .user-dropdown .btn:hover {
            background: rgba(255,255,255,0.3);
        }

        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            margin-top: var(--header-height);
            padding: 25px;
            min-height: calc(100vh - var(--header-height));
        }

        .welcome-card {
            background: #fff;
            border-radius: 15px;
            padding: 50px;
            text-align: center;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
        }

        .welcome-card i {
            font-size: 5rem;
            color: #28a745;
            margin-bottom: 20px;
        }

        .welcome-card h2 {
            color: #333;
            margin-bottom: 15px;
        }

        .welcome-card p {
            color: #666;
            font-size: 1.1rem;
        }

        @media (max-width: 768px) {
            .sidebar {
                margin-left: calc(var(--sidebar-width) * -1);
            }
            .main-header, .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="sidebar-header">
            <i class="bi bi-building" style="font-size: 2.5rem; color: #28a745;"></i>
            <h3>RETEC</h3>
            <small>Portal de Clientes</small>
        </div>

        <div class="cliente-info">
            <div class="nombre"><?= htmlspecialchars(getClienteNombre()) ?></div>
            <div class="cuit">CUIT: <?= htmlspecialchars(getClienteCuit()) ?></div>
        </div>

        <div class="sidebar-menu">
            <a href="inicio.php" class="active">
                <i class="bi bi-house-door"></i>
                <span>Inicio</span>
            </a>
            <a href="dashboard.php">
                <i class="bi bi-receipt"></i>
                <span>Mis Facturas</span>
            </a>
            <a href="remitos.php">
                <i class="bi bi-truck"></i>
                <span>Mis Remitos</span>
            </a>
            <div class="menu-divider"></div>
            <a href="?logout=1">
                <i class="bi bi-box-arrow-left"></i>
                <span>Cerrar Sesion</span>
            </a>
        </div>
    </nav>

    <!-- Header -->
    <header class="main-header">
        <h4><i class="bi bi-house-door me-2"></i>Inicio</h4>
        <div class="user-dropdown">
            <a href="?logout=1" class="btn">
                <i class="bi bi-box-arrow-left me-2"></i>Cerrar Sesion
            </a>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <div class="welcome-card">
            <i class="bi bi-person-check"></i>
            <h2>Bienvenido, <?= htmlspecialchars(getClienteNombre()) ?></h2>
            <p>Utilice el menu de la izquierda para navegar por el portal.</p>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

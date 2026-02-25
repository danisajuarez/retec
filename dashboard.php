<?php
/**
 * Dashboard Principal - Filtrado por CLIENTE
 * Sistema de Facturación RETEC
 */

require_once 'auth.php';
require_once 'config.php';

// Procesar logout
if (isset($_GET['logout'])) {
    logout();
}

// Limpiar cache si se solicita
if (isset($_GET['refresh'])) {
    unset($_SESSION['tipos_comp_cache']);
    foreach ($_SESSION as $key => $val) {
        if (strpos($key, 'facturas_') === 0) {
            unset($_SESSION[$key]);
        }
    }
    header('Location: dashboard.php');
    exit;
}

$clienteId = getClienteId();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Facturas - Portal de Clientes</title>
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
            transition: all 0.3s;
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

        /* Card Styles */
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
        }

        .card-header {
            background: #fff;
            border-bottom: 1px solid #eee;
            padding: 20px 25px;
            border-radius: 12px 12px 0 0 !important;
        }

        .card-header h5 {
            margin: 0;
            font-weight: 600;
            color: #333;
        }

        .card-body {
            padding: 25px;
        }

        /* Table Styles */
        .table thead th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #dee2e6;
        }

        .table tbody tr:hover {
            background: #f8fff8 !important;
        }

        .badge-factura {
            padding: 5px 10px;
            border-radius: 6px;
            font-weight: 500;
        }

        .badge-fa { background: #28a745; color: white; }
        .badge-fb { background: #17a2b8; color: white; }
        .badge-fc { background: #6c757d; color: white; }
        .badge-nda, .badge-ndb, .badge-ndc { background: #dc3545; color: white; }
        .badge-nca, .badge-ncb, .badge-ncc { background: #ffc107; color: #333; }

        .estado-pendiente { color: #dc3545; font-weight: 600; }
        .estado-pagado { color: #28a745; font-weight: 600; }
        .estado-parcial { color: #ffc107; font-weight: 600; }

        /* Stats Cards */
        .stat-card {
            border-radius: 12px;
            padding: 20px;
            color: #fff;
            margin-bottom: 20px;
        }

        .stat-card.bg-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important; }
        .stat-card.bg-success { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%) !important; }
        .stat-card.bg-warning { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%) !important; }
        .stat-card.bg-info { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%) !important; }

        .stat-card h3 {
            font-size: 2rem;
            font-weight: 700;
            margin: 0;
        }

        .stat-card p {
            margin: 5px 0 0;
            opacity: 0.9;
        }

        .stat-card i {
            font-size: 3rem;
            opacity: 0.3;
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                margin-left: calc(var(--sidebar-width) * -1);
            }
            .sidebar.active {
                margin-left: 0;
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
            <a href="inicio.php">
                <i class="bi bi-house-door"></i>
                <span>Inicio</span>
            </a>
            <a href="dashboard.php" class="active">
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
        <h4><i class="bi bi-receipt me-2"></i>Mis Facturas</h4>
        <div class="user-dropdown">
            <a href="?logout=1" class="btn">
                <i class="bi bi-box-arrow-left me-2"></i>Cerrar Sesión
            </a>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Facturas Table -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5><i class="bi bi-table me-2"></i>Mis Comprobantes</h5>
                <div class="d-flex align-items-center gap-2">
                    <select id="pageSize" class="form-select form-select-sm" style="width: 70px;">
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                        <option value="200">200</option>
                    </select>
                    <label class="form-label mb-0 small text-muted">Desde:</label>
                    <input type="date" id="fechaDesde" class="form-control form-control-sm" style="width: 130px;">
                    <label class="form-label mb-0 small text-muted">Hasta:</label>
                    <input type="date" id="fechaHasta" class="form-control form-control-sm" style="width: 130px;">
                    <input type="text" id="buscarProveedor" class="form-control form-control-sm" style="width: 150px;" placeholder="Proveedor...">
                    <button type="button" id="btnBuscar" class="btn btn-sm btn-success">
                        <i class="bi bi-search"></i>
                    </button>
                    <button type="button" id="btnLimpiar" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-x-circle"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="tablaFacturas" class="table table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Comprobante</th>
                                <th>Clase</th>
                                <th>Puesto</th>
                                <th>Numero</th>
                                <th class="text-end">Total</th>
                                <th>Proveedor</th>
                                <th class="text-center">PDF</th>
                            </tr>
                        </thead>
                        <tbody id="tablaBody">
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-end align-items-center mt-3">
                    <div class="d-flex align-items-center gap-3">
                        <span id="pageInfo" class="text-muted">Página 1</span>
                        <div class="btn-group">
                            <button type="button" id="btnAnterior" class="btn btn-outline-success" disabled>
                                <i class="bi bi-chevron-left"></i> Anterior
                            </button>
                            <button type="button" id="btnSiguiente" class="btn btn-outline-success">
                                Siguiente <i class="bi bi-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div id="loading" class="text-center py-4" style="display:none;">
                    <div class="spinner-border text-success" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentPage = 0;
        let pageSize = 25;
        let hasMore = false;

        document.addEventListener('DOMContentLoaded', function() {
            cargarFacturas();

            // Filtros
            document.getElementById('btnBuscar').addEventListener('click', function() {
                currentPage = 0;
                cargarFacturas();
            });

            document.getElementById('buscarProveedor').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    currentPage = 0;
                    cargarFacturas();
                }
            });

            document.getElementById('btnLimpiar').addEventListener('click', function() {
                document.getElementById('buscarProveedor').value = '';
                document.getElementById('fechaDesde').value = '';
                document.getElementById('fechaHasta').value = '';
                currentPage = 0;
                cargarFacturas();
            });

            document.getElementById('fechaDesde').addEventListener('change', function() {
                currentPage = 0;
                cargarFacturas();
            });

            document.getElementById('fechaHasta').addEventListener('change', function() {
                currentPage = 0;
                cargarFacturas();
            });

            document.getElementById('pageSize').addEventListener('change', function() {
                pageSize = parseInt(this.value);
                currentPage = 0;
                cargarFacturas();
            });

            document.getElementById('btnAnterior').addEventListener('click', function() {
                if (currentPage > 0) {
                    currentPage--;
                    cargarFacturas();
                }
            });

            document.getElementById('btnSiguiente').addEventListener('click', function() {
                if (hasMore) {
                    currentPage++;
                    cargarFacturas();
                }
            });
        });

        function cargarFacturas() {
            const tbody = document.getElementById('tablaBody');
            const loading = document.getElementById('loading');

            tbody.innerHTML = '';
            loading.style.display = 'block';

            const params = new URLSearchParams({
                draw: 1,
                start: currentPage * pageSize,
                length: pageSize,
                proveedor: document.getElementById('buscarProveedor').value,
                fecha_desde: document.getElementById('fechaDesde').value,
                fecha_hasta: document.getElementById('fechaHasta').value
            });

            fetch('api/facturas.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params
            })
            .then(response => response.json())
            .then(data => {
                loading.style.display = 'none';
                hasMore = data.hasMore;

                if (data.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No se encontraron facturas</td></tr>';
                } else {
                    data.data.forEach(row => {
                        tbody.innerHTML += crearFila(row);
                    });
                }

                // Actualizar controles de paginación
                document.getElementById('pageInfo').textContent = 'Página ' + (currentPage + 1);
                document.getElementById('btnAnterior').disabled = currentPage === 0;
                document.getElementById('btnSiguiente').disabled = !hasMore;
            })
            .catch(error => {
                loading.style.display = 'none';
                tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger py-4">Error al cargar datos</td></tr>';
                console.error('Error:', error);
            });
        }

        function crearFila(row) {
            let badgeClass = 'bg-secondary';
            const nombre = row.comprobante.toLowerCase();
            if (nombre.includes('nota de cr') || nombre.includes('crédito') || nombre.includes('credito')) {
                badgeClass = 'badge-nca';
            } else if (nombre.includes('nota de d') || nombre.includes('débito') || nombre.includes('debito')) {
                badgeClass = 'badge-nda';
            } else if (nombre.includes('factura')) {
                badgeClass = 'badge-fa';
            }

            const total = '$' + parseFloat(row.total).toLocaleString('es-AR', {minimumFractionDigits: 2});

            return `<tr>
                <td>${row.fecha}</td>
                <td><span class="badge badge-factura ${badgeClass}">${row.comprobante}</span></td>
                <td>${row.clase}</td>
                <td>${row.puesto}</td>
                <td>${row.numero}</td>
                <td class="text-end">${total}</td>
                <td>${row.proveedor || ''}</td>
                <td class="text-center">
                    ${row.link_descarga ? `<a href="${row.link_descarga}" target="_blank" class="btn btn-sm btn-outline-danger" title="Ver PDF"><i class="bi bi-file-pdf"></i></a>` : '-'}
                </td>
            </tr>`;
        }

        function mostrarToast(mensaje) {
            let toast = document.getElementById('toast-notif');
            if (!toast) {
                toast = document.createElement('div');
                toast.id = 'toast-notif';
                toast.style.cssText = 'position:fixed;bottom:20px;left:50%;transform:translateX(-50%);background:#28a745;color:white;padding:12px 24px;border-radius:8px;opacity:0;transition:opacity 0.3s;z-index:9999;';
                document.body.appendChild(toast);
            }
            toast.textContent = mensaje;
            toast.style.opacity = '1';
            setTimeout(function() {
                toast.style.opacity = '0';
            }, 2000);
        }
    </script>
</body>
</html>

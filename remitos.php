<?php
/**
 * Mis Remitos - Filtrado por CLIENTE
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
    foreach ($_SESSION as $key => $val) {
        if (strpos($key, 'remitos_') === 0) {
            unset($_SESSION[$key]);
        }
    }
    header('Location: remitos.php');
    exit;
}

$clienteId = getClienteId();

function toUtf8($str) {
    return mb_convert_encoding($str ?? '', 'UTF-8', 'ISO-8859-1');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Remitos - Portal de Clientes</title>
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

        .badge-remito {
            padding: 5px 10px;
            border-radius: 6px;
            font-weight: 500;
            background: #17a2b8;
            color: white;
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
            <a href="dashboard.php">
                <i class="bi bi-receipt"></i>
                <span>Mis Facturas</span>
            </a>
            <a href="remitos.php" class="active">
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
        <h4><i class="bi bi-truck me-2"></i>Mis Remitos</h4>
        <div class="user-dropdown">
            <a href="?logout=1" class="btn">
                <i class="bi bi-box-arrow-left me-2"></i>Cerrar Sesion
            </a>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Remitos Table -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5><i class="bi bi-table me-2"></i>Mis Remitos</h5>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <select id="pageSize" class="form-select form-select-sm" style="width: auto;">
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                        <option value="200">200</option>
                    </select>
                    <div class="d-flex align-items-center gap-1">
                        <label class="form-label mb-0 small text-muted">Desde:</label>
                        <input type="date" id="fechaDesde" class="form-control form-control-sm" style="width: 140px;">
                    </div>
                    <div class="d-flex align-items-center gap-1">
                        <label class="form-label mb-0 small text-muted">Hasta:</label>
                        <input type="date" id="fechaHasta" class="form-control form-control-sm" style="width: 140px;">
                    </div>
                    <button type="button" id="btnLimpiar" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-x-circle"></i> Limpiar
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="tablaRemitos" class="table table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Comprobante</th>
                                <th>Clase</th>
                                <th>Puesto</th>
                                <th>Numero</th>
                                <th class="text-end">Total</th>
                                <th>Factura Asociada</th>
                                <th class="text-center">PDF</th>
                            </tr>
                        </thead>
                        <tbody id="tablaBody">
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-end align-items-center mt-3">
                    <div class="d-flex align-items-center gap-3">
                        <span id="pageInfo" class="text-muted">Pagina 1</span>
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
            cargarRemitos();

            // Filtros
            document.getElementById('btnLimpiar').addEventListener('click', function() {
                document.getElementById('fechaDesde').value = '';
                document.getElementById('fechaHasta').value = '';
                currentPage = 0;
                cargarRemitos();
            });

            document.getElementById('fechaDesde').addEventListener('change', function() {
                currentPage = 0;
                cargarRemitos();
            });

            document.getElementById('fechaHasta').addEventListener('change', function() {
                currentPage = 0;
                cargarRemitos();
            });

            document.getElementById('pageSize').addEventListener('change', function() {
                pageSize = parseInt(this.value);
                currentPage = 0;
                cargarRemitos();
            });

            document.getElementById('btnAnterior').addEventListener('click', function() {
                if (currentPage > 0) {
                    currentPage--;
                    cargarRemitos();
                }
            });

            document.getElementById('btnSiguiente').addEventListener('click', function() {
                if (hasMore) {
                    currentPage++;
                    cargarRemitos();
                }
            });
        });

        function cargarRemitos() {
            const tbody = document.getElementById('tablaBody');
            const loading = document.getElementById('loading');

            tbody.innerHTML = '';
            loading.style.display = 'block';

            const params = new URLSearchParams({
                draw: 1,
                start: currentPage * pageSize,
                length: pageSize,
                fecha_desde: document.getElementById('fechaDesde').value,
                fecha_hasta: document.getElementById('fechaHasta').value
            });

            fetch('api/remitos.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params
            })
            .then(response => response.json())
            .then(data => {
                loading.style.display = 'none';
                hasMore = data.hasMore;

                if (data.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No se encontraron remitos</td></tr>';
                } else {
                    data.data.forEach(row => {
                        tbody.innerHTML += crearFila(row);
                    });
                }

                // Actualizar controles de paginacion
                document.getElementById('pageInfo').textContent = 'Pagina ' + (currentPage + 1);
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
            const total = '$' + parseFloat(row.total).toLocaleString('es-AR', {minimumFractionDigits: 2});
            const facturaAsociada = row.factura_asociada || '-';

            return `<tr>
                <td>${row.fecha}</td>
                <td><span class="badge badge-remito">${row.comprobante}</span></td>
                <td>${row.clase}</td>
                <td>${row.puesto}</td>
                <td>${row.numero}</td>
                <td class="text-end">${total}</td>
                <td>${facturaAsociada}</td>
                <td class="text-center">
                    ${row.link_ver ? `<a href="${row.link_ver}" target="_blank" class="btn btn-sm btn-outline-danger" title="Ver PDF"><i class="bi bi-file-pdf"></i></a>` : '-'}
                </td>
            </tr>`;
        }
    </script>
</body>
</html>

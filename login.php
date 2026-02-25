<?php
/**
 * Página de Login - Por CLIENTE
 * Sistema de Facturación RETEC
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Si ya está logueado, redirigir al inicio
if (isset($_SESSION['cliente_id'])) {
    header('Location: inicio.php');
    exit;
}

require_once 'config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['cuit'] ?? '');
    $password = $_POST['password'] ?? '';

    // Login temporal: últimos 4 dígitos del CUIL como usuario y contraseña
    $usuario = preg_replace('/[^0-9]/', '', $usuario); // Solo números

    if (empty($usuario) || empty($password)) {
        $error = 'Por favor complete todos los campos.';
    } else {
        try {
            $pdo = getConnection();

            // Buscar cliente por ID (TER_IDTercero)
            $stmt = $pdo->prepare("
                SELECT TER_IDTercero, TER_RazonSocialTer, TER_CUITTer, ter_bloqueado
                FROM sige_ter_tercero
                WHERE TER_IDTercero = :id
                LIMIT 1
            ");
            $stmt->execute(['id' => $usuario]);
            $cliente = $stmt->fetch();

            if ($cliente) {
                // Verificar si el cliente está bloqueado
                if (strtolower($cliente['ter_bloqueado'] ?? '') === 's') {
                    $error = 'Cliente bloqueado. Contacte al administrador.';
                } else {
                    // Login: la contraseña es el mismo ID
                    if ($password === $usuario) {
                        // Login exitoso
                        $_SESSION['cliente_id'] = $cliente['TER_IDTercero'];
                        $_SESSION['cliente_nombre'] = $cliente['TER_RazonSocialTer'];
                        $_SESSION['cliente_cuit'] = $cliente['TER_CUITTer'];

                        header('Location: inicio.php');
                        exit;
                    } else {
                        $error = 'Usuario o contraseña incorrectos.';
                    }
                }
            } else {
                $error = 'Usuario o contraseña incorrectos.';
            }
        } catch (PDOException $e) {
            error_log("Error de login: " . $e->getMessage());
            $error = 'Error del sistema. Intente más tarde.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Portal de Clientes</title>
    <link rel="icon" type="image/png" href="logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: #fff;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            overflow: hidden;
            max-width: 420px;
            width: 100%;
        }
        .login-header {
            background: #28a745;
            color: white;
            padding: 30px;
            text-align: center;
        }
        .login-header h1 {
            margin: 0;
            font-size: 1.8rem;
            font-weight: 600;
        }
        .login-header p {
            margin: 10px 0 0;
            opacity: 0.9;
        }
        .login-body {
            padding: 30px;
        }
        .form-control {
            border-radius: 8px;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
        }
        .form-control:focus {
            border-color: #28a745;
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.15);
        }
        .btn-login {
            background: #28a745;
            border: none;
            border-radius: 8px;
            padding: 12px;
            font-weight: 600;
            width: 100%;
        }
        .btn-login:hover {
            background: #218838;
        }
        .input-group-text {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-right: none;
            border-radius: 8px 0 0 8px;
        }
        .input-group .form-control {
            border-left: none;
            border-radius: 0 8px 8px 0;
        }
        .help-text {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <i class="bi bi-building" style="font-size: 3rem;"></i>
            <h1>Portal de Clientes</h1>
            <p>Sistema de Facturación RETEC</p>
        </div>
        <div class="login-body">
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="mb-3">
                    <label for="cuit" class="form-label">Usuario</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person-vcard"></i></span>
                        <input type="text" class="form-control" id="cuit" name="cuit"
                               value="<?= htmlspecialchars($_POST['cuit'] ?? '') ?>"
                               placeholder="ID de cliente" required autofocus>
                    </div>
                    <div class="help-text">Ingrese su ID de cliente</div>
                </div>
                <div class="mb-4">
                    <label for="password" class="form-label">Contraseña</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                        <input type="password" class="form-control" id="password" name="password"
                               placeholder="Contraseña" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-success btn-login">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Ingresar
                </button>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

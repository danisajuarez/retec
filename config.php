<?php
/**
 * Configuración de conexión a la base de datos
 * Sistema de Facturación RETEC
 */

define('DB_HOST', 'remoto.retec.com.ar');
define('DB_PORT', '3307');
define('DB_NAME', 'retec');
define('DB_USER', 'danisa');
define('DB_PASS', 'danisa2025');
define('DB_CHARSET', 'latin1');

function getConnection(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            "mysql:host=%s;port=%s;dbname=%s;charset=%s",
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_PERSISTENT         => true,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Error de conexión: " . $e->getMessage());
            throw new Exception("Error de conexión a la base de datos");
        }
    }

    return $pdo;
}

// ============================================
// SISTEMA DE LINKS SEGUROS PARA FACTURAS
// Formato: Base64(ID + CAE)
// ============================================

/**
 * Genera un código seguro para acceder a una factura
 * @param int $facturaId ID de la factura
 * @param string $cae CAE de la factura (14 dígitos)
 * @return string Código en Base64
 */
function generarCodigoFactura(int $facturaId, string $cae): string
{
    // Concatenar ID + CAE y codificar en Base64
    $data = $facturaId . $cae;
    return base64_encode($data);
}

/**
 * Valida un código y retorna el ID de factura si es válido
 * @param string $codigo Código a validar
 * @return array|null ['id' => int, 'cae' => string] o null si es inválido
 */
function decodificarCodigoFactura(string $codigo): ?array
{
    // Agregar padding si falta (el = se puede perder en URLs)
    $codigo = str_replace(['-', '_'], ['+', '/'], $codigo);
    $padding = strlen($codigo) % 4;
    if ($padding) {
        $codigo .= str_repeat('=', 4 - $padding);
    }

    // Decodificar Base64
    $decoded = base64_decode($codigo, true);
    if (!$decoded || strlen($decoded) < 15) {
        return null;
    }

    // Los últimos 14 caracteres son el CAE
    $cae = substr($decoded, -14);
    $id = substr($decoded, 0, -14);

    // Validar que sean numéricos
    if (!is_numeric($id) || !is_numeric($cae)) {
        return null;
    }

    return [
        'id' => (int)$id,
        'cae' => $cae
    ];
}

/**
 * Valida que el código corresponda a una factura real
 * @param string $codigo Código a validar
 * @param PDO $pdo Conexión a BD
 * @return int|null ID de factura si es válido, null si no
 */
function validarCodigoFactura(string $codigo, PDO $pdo): ?int
{
    $data = decodificarCodigoFactura($codigo);
    if (!$data) {
        return null;
    }

    // Verificar en la BD que el ID y CAE coincidan
    $stmt = $pdo->prepare("SELECT EFC_IdEfc FROM sige_efc_encfac WHERE EFC_IdEfc = ? AND EFC_CAE = ?");
    $stmt->execute([$data['id'], $data['cae']]);
    $result = $stmt->fetch();

    return $result ? (int)$result['EFC_IdEfc'] : null;
}

/**
 * Genera el link completo para ver/descargar una factura
 * @param int $facturaId ID de la factura
 * @param string $cae CAE de la factura
 * @return string URL completa
 */
function getLinkFactura(int $facturaId, string $cae): string
{
    $codigo = generarCodigoFactura($facturaId, $cae);
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    $scriptPath = $_SERVER['SCRIPT_NAME'] ?? '';
    if (strpos($scriptPath, '/api/') !== false) {
        $basePath = dirname(dirname($scriptPath));
    } else {
        $basePath = dirname($scriptPath);
    }
    $basePath = $basePath === '/' || $basePath === '\\' ? '' : $basePath;

    return $protocol . '://' . $host . $basePath . '/ver_factura.php?id=' . $codigo;
}

// Mantener compatibilidad con código anterior
function getLinkDescarga(int $facturaId): string
{
    // Esta función ahora necesita el CAE, pero para compatibilidad
    // se usa desde lugares donde ya tenemos el CAE
    // Por ahora retorna el link antiguo, se actualizará donde se use
    return "ver_factura.php?id=" . $facturaId;
}

// ============================================
// SISTEMA DE LINKS SEGUROS PARA REMITOS
// Formato: Base64(ID + Fecha en formato YYYYMMDD)
// ============================================

/**
 * Genera un código seguro para acceder a un remito
 * @param int $remitoId ID del remito
 * @param string $fecha Fecha del remito
 * @return string Código en Base64
 */
function generarCodigoRemito(int $remitoId, string $fecha): string
{
    $fechaFormato = date('Ymd', strtotime($fecha));
    $data = $remitoId . $fechaFormato;
    return base64_encode($data);
}

/**
 * Valida un código y retorna el ID de remito si es válido
 * @param string $codigo Código a validar
 * @return array|null ['id' => int, 'fecha' => string] o null si es inválido
 */
function decodificarCodigoRemito(string $codigo): ?array
{
    $codigo = str_replace(['-', '_'], ['+', '/'], $codigo);
    $padding = strlen($codigo) % 4;
    if ($padding) {
        $codigo .= str_repeat('=', 4 - $padding);
    }

    $decoded = base64_decode($codigo, true);
    if (!$decoded || strlen($decoded) < 9) {
        return null;
    }

    // Los últimos 8 caracteres son la fecha (YYYYMMDD)
    $fecha = substr($decoded, -8);
    $id = substr($decoded, 0, -8);

    if (!is_numeric($id) || !is_numeric($fecha)) {
        return null;
    }

    return [
        'id' => (int)$id,
        'fecha' => $fecha
    ];
}

/**
 * Valida que el código corresponda a un remito real
 * @param string $codigo Código a validar
 * @param PDO $pdo Conexión a BD
 * @return int|null ID de remito si es válido, null si no
 */
function validarCodigoRemito(string $codigo, PDO $pdo): ?int
{
    $data = decodificarCodigoRemito($codigo);
    if (!$data) {
        return null;
    }

    $stmt = $pdo->prepare("SELECT ERT_IdErt FROM sige_ert_encrto WHERE ERT_IdErt = ? AND DATE_FORMAT(ERT_FecErt, '%Y%m%d') = ?");
    $stmt->execute([$data['id'], $data['fecha']]);
    $result = $stmt->fetch();

    return $result ? (int)$result['ERT_IdErt'] : null;
}

/**
 * Genera el link completo para ver un remito
 */
function getLinkRemito(int $remitoId, string $fecha): string
{
    $codigo = generarCodigoRemito($remitoId, $fecha);
    return "ver_remito.php?id=" . $codigo;
}

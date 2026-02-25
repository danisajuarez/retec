<?php
/**
 * API de Remitos - Filtrado por CLIENTE
 * Sistema de Facturación RETEC
 */

require_once '../auth_api.php';
require_once '../config.php';

header('Content-Type: application/json; charset=utf-8');

$clienteId = getClienteId();
$draw = isset($_POST['draw']) ? (int)$_POST['draw'] : 1;
$start = isset($_POST['start']) ? (int)$_POST['start'] : 0;
$length = isset($_POST['length']) ? (int)$_POST['length'] : 25;
$tipo = isset($_POST['tipo']) ? trim($_POST['tipo']) : '';
$fechaDesde = isset($_POST['fecha_desde']) ? trim($_POST['fecha_desde']) : '';
$fechaHasta = isset($_POST['fecha_hasta']) ? trim($_POST['fecha_hasta']) : '';

// Cache key
$cacheKey = "remitos_{$clienteId}_{$start}_{$length}_{$tipo}_{$fechaDesde}_{$fechaHasta}";

// Verificar cache (5 minutos)
if (isset($_SESSION[$cacheKey]) && isset($_SESSION[$cacheKey . '_time'])) {
    if (time() - $_SESSION[$cacheKey . '_time'] < 300) {
        echo json_encode($_SESSION[$cacheKey]);
        exit;
    }
}

try {
    $pdo = getConnection();

    // Construir query base con factura asociada
    $sql = "
        SELECT
            ert.ERT_IdErt as id,
            ert.ERT_FecErt as fecha_raw,
            DATE_FORMAT(ert.ERT_FecErt, '%d/%m/%Y') as fecha,
            tcp.TCP_DesTipoComp as comprobante,
            ert.ERT_ClaseComErt as clase,
            LPAD(ert.ERT_PuestoComErt, 4, '0') as puesto,
            LPAD(ert.ERT_NumComErt, 8, '0') as numero,
            ert.ERT_ImpTotalErt as total,
            efc.EFC_FecEfc as factura_fecha,
            efc.EFC_ClaseComEfc as factura_clase,
            efc.EFC_PuestoComEfc as factura_puesto,
            efc.EFC_NumComEfc as factura_numero
        FROM sige_ert_encrto ert
        INNER JOIN sige_tcp_tipocomp tcp ON ert.ERT_TipoErt = tcp.TCP_IDTipoComp
        LEFT JOIN sige_efc_encfac efc ON (ert.ERT_TipoCompVD = efc.EFC_TipoEfc) AND (ert.ERT_IdVieneDe = efc.EFC_IdEfc)
        WHERE ert.TER_IDTercero = ?
    ";

    $params = [$clienteId];

    // Filtro por tipo
    if ($tipo !== '') {
        $sql .= " AND ert.ERT_TipoErt = ?";
        $params[] = $tipo;
    }

    // Filtro por fecha desde
    if ($fechaDesde !== '') {
        $sql .= " AND ert.ERT_FecErt >= ?";
        $params[] = $fechaDesde;
    }

    // Filtro por fecha hasta
    if ($fechaHasta !== '') {
        $sql .= " AND ert.ERT_FecErt <= ?";
        $params[] = $fechaHasta . ' 23:59:59';
    }

    // Ordenar por fecha descendente
    $sql .= " ORDER BY ert.ERT_FecErt DESC, ert.ERT_IdErt DESC";

    // Paginación
    $sql .= " LIMIT " . ($length + 1) . " OFFSET " . $start;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $remitos = $stmt->fetchAll();

    // Verificar si hay más registros
    $hasMore = count($remitos) > $length;
    if ($hasMore) {
        array_pop($remitos);
    }

    // Convertir encoding y preparar datos
    $data = [];
    foreach ($remitos as $remito) {
        // Construir string de factura asociada si existe
        $facturaAsociada = '';
        if (!empty($remito['factura_numero'])) {
            $facPuesto = str_pad($remito['factura_puesto'], 4, '0', STR_PAD_LEFT);
            $facNumero = str_pad($remito['factura_numero'], 8, '0', STR_PAD_LEFT);
            $facFecha = !empty($remito['factura_fecha']) ? date('d/m/Y', strtotime($remito['factura_fecha'])) : '';
            $facturaAsociada = $remito['factura_clase'] . ' ' . $facPuesto . '-' . $facNumero;
            if ($facFecha) {
                $facturaAsociada .= ' (' . $facFecha . ')';
            }
        }

        $data[] = [
            'id' => $remito['id'],
            'fecha' => $remito['fecha'],
            'comprobante' => mb_convert_encoding($remito['comprobante'] ?? '', 'UTF-8', 'ISO-8859-1'),
            'clase' => $remito['clase'],
            'puesto' => $remito['puesto'],
            'numero' => $remito['numero'],
            'total' => $remito['total'],
            'factura_asociada' => $facturaAsociada,
            'link_ver' => getLinkRemito((int)$remito['id'], $remito['fecha_raw'])
        ];
    }

    $response = [
        'draw' => $draw,
        'data' => $data,
        'hasMore' => $hasMore,
        'recordsTotal' => count($data),
        'recordsFiltered' => count($data)
    ];

    // Guardar en cache
    $_SESSION[$cacheKey] = $response;
    $_SESSION[$cacheKey . '_time'] = time();

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al obtener remitos: ' . $e->getMessage()]);
}

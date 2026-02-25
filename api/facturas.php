<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

$input = !empty($_POST) ? $_POST : $_GET;
$draw = intval($input['draw'] ?? 1);

function jsonResponse($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_SESSION['cliente_id'])) {
    jsonResponse(['draw'=>$draw,'data'=>[],'hasMore'=>false]);
}

try {
    require_once '../config.php';

    $clienteId = $_SESSION['cliente_id'];

    // Parámetros de la consulta
    $start = intval($input['start'] ?? 0);
    $length = intval($input['length'] ?? 25);
    $tipo = $input['tipo'] ?? '';
    $proveedor = $input['proveedor'] ?? '';
    $fechaDesde = $input['fecha_desde'] ?? '';
    $fechaHasta = $input['fecha_hasta'] ?? '';

    // Clave única para este conjunto de parámetros
    $cacheKey = "facturas_{$clienteId}_{$start}_{$length}_{$tipo}_{$proveedor}_{$fechaDesde}_{$fechaHasta}";

    // Verificar si tenemos cache válido (máximo 5 minutos)
    if (isset($_SESSION[$cacheKey]) && isset($_SESSION[$cacheKey . '_time'])) {
        $cacheAge = time() - $_SESSION[$cacheKey . '_time'];
        if ($cacheAge < 300) { // 5 minutos
            jsonResponse($_SESSION[$cacheKey]);
        }
    }

    $pdo = getConnection();

    $filtroProveedor = !empty($proveedor);

    $whereClause = "e.TER_IdTercero = ? AND e.EFC_ClaseComEfc <> 'X'";
    $params = [$clienteId];

    // Filtro por tipo
    if (!empty($tipo)) {
        $whereClause .= " AND e.EFC_TipoEfc = ?";
        $params[] = $tipo;
    }

    // Paginación: pedimos 1 registro extra para saber si hay más
    $limit = "LIMIT $start, " . ($length + 1);

    // Filtro por proveedor
    if ($filtroProveedor) {
        $whereClause .= " AND p.ter_razonsocialter LIKE ?";
        $params[] = $proveedor . '%';
    }

    // Filtro por fecha desde
    if (!empty($fechaDesde)) {
        $whereClause .= " AND e.EFC_FecEfc >= ?";
        $params[] = $fechaDesde;
    }

    // Filtro por fecha hasta
    if (!empty($fechaHasta)) {
        $whereClause .= " AND e.EFC_FecEfc <= ?";
        $params[] = $fechaHasta . ' 23:59:59';
    }

    $sql = "SELECT e.EFC_IdEfc, e.EFC_TipoEfc, DATE_FORMAT(e.EFC_FecEfc, '%d/%m/%Y') as fecha,
            e.EFC_ClaseComEfc, e.EFC_PuestoComEfc, e.EFC_NumComEfc, e.EFC_ImpEfc, e.EFC_CAE, t.TCP_DesTipoComp,
            p.ter_razonsocialter AS Proveedor
            FROM sige_efc_encfac e
            INNER JOIN sige_tcp_tipocomp t ON t.TCP_IDTipoComp = e.EFC_TipoEfc
            LEFT JOIN sige_efo_encfacc p ON (e.EFC_TipoCompVD = p.EFO_TipoEfc) AND (e.EFC_IdVieneDe = p.EFO_IdEfc)
            WHERE $whereClause
            ORDER BY e.EFC_FecEfc DESC $limit";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // Si vienen más registros que $length, hay más páginas
    $hasMore = count($rows) > $length;

    // Quitamos el registro extra si existe
    if ($hasMore) {
        array_pop($rows);
    }

    $data = [];
    foreach ($rows as $r) {
        $data[] = [
            'id' => $r['EFC_IdEfc'],
            'tipo' => $r['EFC_TipoEfc'],
            'fecha' => $r['fecha'],
            'comprobante' => mb_convert_encoding($r['TCP_DesTipoComp'] ?? '', 'UTF-8', 'ISO-8859-1'),
            'clase' => $r['EFC_ClaseComEfc'],
            'puesto' => str_pad($r['EFC_PuestoComEfc'], 4, '0', STR_PAD_LEFT),
            'numero' => str_pad($r['EFC_NumComEfc'], 8, '0', STR_PAD_LEFT),
            'total' => number_format(floatval($r['EFC_ImpEfc']), 2, '.', ''),
            'proveedor' => $r['Proveedor'] ? mb_convert_encoding($r['Proveedor'], 'UTF-8', 'ISO-8859-1') : '',
            'link_descarga' => !empty($r['EFC_CAE']) ? getLinkFactura((int)$r['EFC_IdEfc'], $r['EFC_CAE']) : ''
        ];
    }

    // Respuesta
    $response = [
        'draw' => $draw,
        'data' => $data,
        'hasMore' => $hasMore,
        'page' => floor($start / $length) + 1,
        'cached' => false
    ];

    // Guardar en cache
    $_SESSION[$cacheKey] = $response;
    $_SESSION[$cacheKey . '_time'] = time();

    jsonResponse($response);

} catch (Exception $e) {
    jsonResponse(['draw'=>$draw,'data'=>[],'hasMore'=>false,'error'=>$e->getMessage()]);
}

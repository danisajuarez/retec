<?php
/**
 * OCR con OCR.space API (gratuito)
 * Sistema de Facturación RETEC
 * 25,000 escaneos/mes gratis
 */

header('Content-Type: application/json; charset=utf-8');

// API Key de OCR.space
define('OCRSPACE_API_KEY', 'K82169580788957');

/**
 * Extrae texto de una imagen o PDF usando OCR.space
 * @param string $filePath Ruta al archivo
 * @return array Resultado con texto extraído y datos estructurados
 */
function extraerTextoOCRSpace(string $filePath): array
{
    if (!file_exists($filePath)) {
        return ['error' => 'Archivo no encontrado: ' . $filePath];
    }

    // Verificar tamaño (máx 1MB para tier gratuito)
    $fileSize = filesize($filePath);
    if ($fileSize > 1024 * 1024) {
        return ['error' => 'Archivo muy grande. Máximo 1MB. Tamaño actual: ' . round($fileSize / 1024 / 1024, 2) . 'MB'];
    }

    // Leer archivo y convertir a base64
    $fileContent = file_get_contents($filePath);
    $base64File = base64_encode($fileContent);

    // Detectar tipo de archivo
    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $mimeTypes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'bmp' => 'image/bmp',
        'pdf' => 'application/pdf'
    ];
    $mimeType = $mimeTypes[$extension] ?? 'image/jpeg';

    // Preparar datos para OCR.space
    $postData = [
        'apikey' => OCRSPACE_API_KEY,
        'base64Image' => 'data:' . $mimeType . ';base64,' . $base64File,
        'language' => 'spa',
        'isOverlayRequired' => 'false',
        'detectOrientation' => 'true',
        'scale' => 'true',
        'OCREngine' => '1'
    ];

    // Para PDF usar configuración especial
    if ($extension === 'pdf') {
        $postData['filetype'] = 'PDF';
        $postData['OCREngine'] = '2';  // Engine 2 mejor para PDFs
        $postData['isTable'] = 'true';
    }

    // Llamar a la API
    $ch = curl_init('https://api.ocr.space/parse/image');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return ['error' => 'Error de conexión: ' . $curlError];
    }

    $result = json_decode($response, true);

    // Verificar respuesta
    if (!$result) {
        return ['error' => 'Respuesta inválida de OCR.space'];
    }

    if (isset($result['IsErroredOnProcessing']) && $result['IsErroredOnProcessing']) {
        $errorMsg = $result['ErrorMessage'][0] ?? 'Error desconocido';
        return ['error' => 'Error OCR: ' . $errorMsg];
    }

    if (!isset($result['ParsedResults']) || empty($result['ParsedResults'])) {
        return ['error' => 'No se pudo extraer texto de la imagen'];
    }

    // Extraer texto completo
    $textoCompleto = '';
    foreach ($result['ParsedResults'] as $parsed) {
        $textoCompleto .= $parsed['ParsedText'] ?? '';
    }

    // Parsear datos de factura
    $datosFactura = parsearFactura($textoCompleto);

    return [
        'success' => true,
        'texto_completo' => $textoCompleto,
        'datos_factura' => $datosFactura
    ];
}

/**
 * Parsea el texto extraído para identificar datos de factura argentina
 */
function parsearFactura(string $texto): array
{
    $datos = [
        'tipo_comprobante' => null,
        'punto_venta' => null,
        'numero' => null,
        'fecha' => null,
        'cuit_emisor' => null,
        'razon_social_emisor' => null,
        'cuit_receptor' => null,
        'razon_social_receptor' => null,
        'cae' => null,
        'vencimiento_cae' => null,
        'neto_gravado' => null,
        'neto_no_gravado' => null,
        'iva' => null,
        'total' => null,
        'items' => []
    ];

    // Normalizar texto - eliminar saltos de línea extras
    $textoLinea = preg_replace('/[\r\n]+/', ' ', $texto);

    // ============================================
    // TIPO DE COMPROBANTE
    // ============================================
    // Factura (A), Factura Electronica (A), etc.
    if (preg_match('/Factura[s]?\s*(?:Electr[oó]nica)?\s*(?:CTA\s*CTE)?\s*\(([A-C])\)/i', $texto, $m)) {
        $datos['tipo_comprobante'] = 'Factura ' . strtoupper($m[1]);
    } elseif (preg_match('/FACTURA[S]?\s+([A-C])\b/i', $texto, $m)) {
        $datos['tipo_comprobante'] = 'Factura ' . strtoupper($m[1]);
    } elseif (preg_match('/\(([A-C])\)/', $texto, $m)) {
        $datos['tipo_comprobante'] = 'Factura ' . strtoupper($m[1]);
    } elseif (preg_match('/COD[.\s-]*0*1\b/i', $texto)) {
        $datos['tipo_comprobante'] = 'Factura A';
    } elseif (preg_match('/COD[.\s-]*0*6\b/i', $texto)) {
        $datos['tipo_comprobante'] = 'Factura B';
    } elseif (preg_match('/COD[.\s-]*0*11\b/i', $texto)) {
        $datos['tipo_comprobante'] = 'Factura C';
    }

    // ============================================
    // PUNTO DE VENTA Y NUMERO
    // ============================================
    // Formato: 0003-00155708, Punto de Venta: 0003, PV: 0003
    if (preg_match('/(?:Punto\s*(?:de)?\s*Venta|PV|P\.V\.)[:\s]*(\d{3,5})/i', $texto, $m)) {
        $datos['punto_venta'] = ltrim($m[1], '0') ?: '0';
    }
    // Formato combinado: 0003-00012345
    if (preg_match('/(\d{4,5})\s*[-–]\s*(\d{6,8})/', $texto, $m)) {
        if (!$datos['punto_venta']) {
            $datos['punto_venta'] = ltrim($m[1], '0') ?: '0';
        }
        $datos['numero'] = ltrim($m[2], '0') ?: '0';
    }
    // Nro, N°, Numero
    if (!$datos['numero'] && preg_match('/(?:Comp\.?\s*)?(?:Nro|N[°º]|Numero)[:\s]*(\d{6,8})/i', $texto, $m)) {
        $datos['numero'] = ltrim($m[1], '0') ?: '0';
    }

    // ============================================
    // FECHA
    // ============================================
    // Múltiples formatos: dd/mm/yyyy, dd-mm-yyyy
    if (preg_match('/(?:Fecha\s*(?:de)?\s*(?:Emisi[oóe]n)?|Emisi[oóe]n)[:\s]*(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})/i', $texto, $m)) {
        $year = strlen($m[3]) == 2 ? '20' . $m[3] : $m[3];
        $datos['fecha'] = sprintf('%02d/%02d/%s', $m[1], $m[2], $year);
    } elseif (preg_match('/(\d{1,2})[\/\-](\d{1,2})[\/\-](20\d{2})/', $texto, $m)) {
        $datos['fecha'] = sprintf('%02d/%02d/%s', $m[1], $m[2], $m[3]);
    }

    // ============================================
    // CUIT (buscar todos y asignar)
    // ============================================
    // Formato: 30-12345678-9 o 30 12345678 9 o 30123456789
    preg_match_all('/(\d{2})[\s.-]?(\d{8})[\s.-]?(\d{1})/', $texto, $cuits, PREG_SET_ORDER);
    $cuitsUnicos = [];
    foreach ($cuits as $c) {
        $cuit = $c[1] . '-' . $c[2] . '-' . $c[3];
        if (!in_array($cuit, $cuitsUnicos)) {
            $cuitsUnicos[] = $cuit;
        }
    }
    if (count($cuitsUnicos) >= 1) {
        $datos['cuit_emisor'] = $cuitsUnicos[0];
    }
    if (count($cuitsUnicos) >= 2) {
        $datos['cuit_receptor'] = $cuitsUnicos[1];
    }

    // ============================================
    // CAE
    // ============================================
    // 14 dígitos
    if (preg_match('/C\.?A\.?E\.?[:\s]*(\d{14})/i', $texto, $m)) {
        $datos['cae'] = $m[1];
    } elseif (preg_match('/(\d{14})/', $texto, $m)) {
        // Si no encontró con etiqueta, buscar cualquier número de 14 dígitos
        $datos['cae'] = $m[1];
    }

    // ============================================
    // VENCIMIENTO CAE
    // ============================================
    if (preg_match('/(?:Vto\.?\s*(?:C\.?A\.?E\.?)?|Vencimiento(?:\s*C\.?A\.?E\.?)?|Venc\.?)[:\s]*(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})/i', $texto, $m)) {
        $year = strlen($m[3]) == 2 ? '20' . $m[3] : $m[3];
        $datos['vencimiento_cae'] = sprintf('%02d/%02d/%s', $m[1], $m[2], $year);
    }

    // ============================================
    // MONTOS
    // ============================================
    // Patrón para capturar montos (con o sin separador de miles)
    $patronMonto = '([\d]{1,3}(?:[.,]\d{3})*[.,]\d{2}|\d+[.,]\d{2})';

    // Neto No Gravado - buscar PRIMERO para no confundir con Neto Gravado
    if (preg_match('/(?:Neto\s+)?No\s*Gravado[\s:]*\$?\s*' . $patronMonto . '/i', $texto, $m)) {
        $datos['neto_no_gravado'] = normalizarMonto($m[1]);
    }

    // Neto Gravado - buscar después de extraer No Gravado
    // Usar el texto completo pero buscar el patrón correcto
    if (preg_match('/Importe\s+Neto\s+Gravado[\s:]*\$?\s*' . $patronMonto . '/i', $texto, $m)) {
        $datos['neto_gravado'] = normalizarMonto($m[1]);
    } elseif (preg_match('/Neto\s+Gravado[\s:]*\$?\s*' . $patronMonto . '/i', $texto, $m)) {
        // Verificar que no sea "Neto No Gravado" chequeando el contexto
        $fullMatch = $m[0];
        if (stripos($fullMatch, 'no gravado') === false && stripos($fullMatch, 'no grav') === false) {
            $datos['neto_gravado'] = normalizarMonto($m[1]);
        }
    } elseif (preg_match('/Subtotal[\s:]*\$?\s*' . $patronMonto . '/i', $texto, $m)) {
        $datos['neto_gravado'] = normalizarMonto($m[1]);
    }

    // IVA - buscar específicamente "I.V.A." con los puntos
    if (preg_match('/I\.V\.A\.[\s:]*\$?\s*' . $patronMonto . '/i', $texto, $m)) {
        $datos['iva'] = normalizarMonto($m[1]);
    } elseif (preg_match('/IVA\s*(?:21|10[,.]5|27)\s*%?[\s:]*\$?\s*' . $patronMonto . '/i', $texto, $m)) {
        $datos['iva'] = normalizarMonto($m[1]);
    } elseif (preg_match('/(?:^|[\s\n])IVA[\s:]+\$?\s*' . $patronMonto . '/im', $texto, $m)) {
        $datos['iva'] = normalizarMonto($m[1]);
    }

    // Total - buscar específicamente "Total" o "Importe Total"
    if (preg_match('/(?:Importe\s*)?Total[\s:]*\$?\s*' . $patronMonto . '/i', $texto, $m)) {
        $datos['total'] = normalizarMonto($m[1]);
    } else {
        // Fallback: buscar el monto más grande
        preg_match_all('/([\d]{1,3}(?:[\.,]\d{3})*[\.,]\d{2})/', $texto, $montos);
        if (!empty($montos[1])) {
            $maxMonto = 0;
            foreach ($montos[1] as $monto) {
                $valor = (float) normalizarMonto($monto);
                if ($valor > $maxMonto) {
                    $maxMonto = $valor;
                    $datos['total'] = number_format($valor, 2, '.', '');
                }
            }
        }
    }

    // ============================================
    // RAZON SOCIAL
    // ============================================
    if (preg_match('/(?:Raz[oó]n\s*Social|Cliente|Señor(?:es)?)[:\s]*([A-ZÁÉÍÓÚÑ][A-ZÁÉÍÓÚÑa-záéíóúñ\s\.]+(?:S\.?A\.?|S\.?R\.?L\.?|S\.?A\.?S\.?)?)/i', $texto, $m)) {
        $datos['razon_social_receptor'] = trim($m[1]);
    }

    return $datos;
}

/**
 * Normaliza un monto en formato argentino a número
 */
function normalizarMonto(string $monto): string
{
    if (preg_match('/\d+\.\d{3}/', $monto) && strpos($monto, ',') !== false) {
        $monto = str_replace('.', '', $monto);
        $monto = str_replace(',', '.', $monto);
    } elseif (substr_count($monto, '.') > 1) {
        $monto = str_replace('.', '', $monto);
    }
    return $monto;
}

// ============================================
// ENDPOINT API
// ============================================

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {

    // Verificar si se subió un archivo
    if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
        $tmpFile = $_FILES['archivo']['tmp_name'];
        $resultado = extraerTextoOCRSpace($tmpFile);
        echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Verificar si se envió una ruta de archivo
    $input = json_decode(file_get_contents('php://input'), true);
    if (isset($input['archivo_path'])) {
        $resultado = extraerTextoOCRSpace($input['archivo_path']);
        echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['error' => 'No se proporcionó ningún archivo']);
    exit;
}

// Si se accede por GET, mostrar info
if (!defined('INCLUDED_AS_LIB')) {
    echo json_encode([
        'api' => 'OCR.space API',
        'version' => '1.0',
        'limite' => '25,000 escaneos/mes gratis',
        'uso' => [
            'POST con archivo' => 'multipart/form-data con campo "archivo"',
            'POST con ruta' => 'JSON con campo "archivo_path"'
        ],
        'formatos' => ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'pdf']
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

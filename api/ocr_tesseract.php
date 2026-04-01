<?php
/**
 * OCR con Tesseract (local, gratuito)
 * Sistema de Facturación RETEC
 */

header('Content-Type: application/json; charset=utf-8');

// Ruta a Tesseract
define('TESSERACT_PATH', 'C:/Program Files/Tesseract-OCR/tesseract.exe');

// Ruta a Ghostscript (para PDF)
define('GHOSTSCRIPT_PATH', 'C:/Program Files/gs/gs10.05.0/bin/gswin64c.exe');

/**
 * Extrae texto de una imagen usando Tesseract OCR
 * @param string $filePath Ruta al archivo (imagen)
 * @return array Resultado con texto extraído y datos estructurados
 */
function extraerTextoTesseract(string $filePath): array
{
    if (!file_exists($filePath)) {
        return ['error' => 'Archivo no encontrado: ' . $filePath];
    }

    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

    // Si es PDF, convertir a imagen primero
    if ($extension === 'pdf') {
        $imagePath = convertirPDFaImagen($filePath);
        if (!$imagePath) {
            return ['error' => 'No se pudo convertir el PDF a imagen. Verificar que Ghostscript esté instalado.'];
        }
        $filePath = $imagePath;
    }

    // Crear archivo temporal para output
    $outputBase = sys_get_temp_dir() . '/ocr_' . uniqid();
    $outputFile = $outputBase . '.txt';

    // Construir comando Tesseract
    // -l eng = idioma (eng por defecto, spa si está disponible)
    // --psm 3 = modo automático de segmentación de página
    $lang = file_exists('C:/Program Files/Tesseract-OCR/tessdata/spa.traineddata') ? 'spa' : 'eng';
    $cmd = sprintf(
        '"%s" "%s" "%s" -l %s --psm 3 2>&1',
        TESSERACT_PATH,
        realpath($filePath),
        $outputBase,
        $lang
    );

    exec($cmd, $output, $returnCode);

    // Verificar si Tesseract está instalado
    if ($returnCode === 127 || strpos(implode(' ', $output), 'not found') !== false) {
        return [
            'error' => 'Tesseract no está instalado o no está en el PATH',
            'instalar' => 'Descargar desde: https://github.com/UB-Mannheim/tesseract/wiki'
        ];
    }

    // Leer resultado
    if (!file_exists($outputFile)) {
        return [
            'error' => 'No se pudo procesar la imagen',
            'detalle' => implode("\n", $output)
        ];
    }

    $textoCompleto = file_get_contents($outputFile);
    unlink($outputFile); // Limpiar archivo temporal

    // Parsear datos de factura
    $datosFactura = parsearFactura($textoCompleto);

    return [
        'success' => true,
        'texto_completo' => $textoCompleto,
        'datos_factura' => $datosFactura
    ];
}

/**
 * Convierte un PDF a imagen usando Ghostscript
 * @param string $pdfPath Ruta al PDF
 * @return string|null Ruta a la imagen generada o null si falla
 */
function convertirPDFaImagen(string $pdfPath): ?string
{
    $outputPath = sys_get_temp_dir() . '/ocr_' . uniqid() . '.png';

    // Verificar que Ghostscript existe
    if (!file_exists(GHOSTSCRIPT_PATH)) {
        // Intentar buscar en otras rutas comunes
        $rutas = [
            'C:/Program Files/gs/gs10.05.0/bin/gswin64c.exe',
            'C:/Program Files/gs/gs10.04.0/bin/gswin64c.exe',
            'C:/Program Files/gs/gs10.03.0/bin/gswin64c.exe',
            'C:/Program Files/gs/gs10.02.0/bin/gswin64c.exe',
            'C:/Program Files/gs/gs10.01.0/bin/gswin64c.exe',
            'C:/Program Files/gs/gs10.00.0/bin/gswin64c.exe',
            'C:/Program Files/gs/gs9.56.1/bin/gswin64c.exe',
            'C:/Program Files (x86)/gs/gs10.05.0/bin/gswin32c.exe',
        ];

        $gsPath = null;
        foreach ($rutas as $ruta) {
            if (file_exists($ruta)) {
                $gsPath = $ruta;
                break;
            }
        }

        if (!$gsPath) {
            return null;
        }
    } else {
        $gsPath = GHOSTSCRIPT_PATH;
    }

    // Comando Ghostscript para convertir PDF a PNG
    $cmd = sprintf(
        '"%s" -dNOPAUSE -dBATCH -dSAFER -sDEVICE=png16m -r300 -dFirstPage=1 -dLastPage=1 -sOutputFile="%s" "%s" 2>&1',
        $gsPath,
        $outputPath,
        $pdfPath
    );

    exec($cmd, $output, $returnCode);

    if ($returnCode === 0 && file_exists($outputPath)) {
        return $outputPath;
    }

    return null;
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
        'iva' => null,
        'total' => null,
        'items' => []
    ];

    // Normalizar texto (remover acentos problemáticos del OCR)
    $textoNorm = $texto;

    // Tipo de comprobante (Factura A, B, C, etc.)
    if (preg_match('/FACTURA[S]?\s+(?:DE\s+)?(?:VENTA[S]?)?\s*\n?\s*([A-C])/i', $texto, $m)) {
        $datos['tipo_comprobante'] = 'Factura ' . strtoupper($m[1]);
    } elseif (preg_match('/\bFACTURA\s+([A-C])\b/i', $texto, $m)) {
        $datos['tipo_comprobante'] = 'Factura ' . strtoupper($m[1]);
    } elseif (preg_match('/COD[.\s]*001/i', $texto)) {
        // COD. 001 = Factura A en AFIP
        $datos['tipo_comprobante'] = 'Factura A';
    }

    // Punto de venta - buscar "Punto de Venta: 0003"
    if (preg_match('/Punto\s+de\s+Venta[:\s]*(\d{4,5})/i', $texto, $m)) {
        $datos['punto_venta'] = ltrim($m[1], '0') ?: '0';
    }

    // Número de comprobante - buscar "Comp. Nro: 00155708" o "Nro: 00155708"
    if (preg_match('/(?:Comp\.?\s*)?Nro[:\s]*(\d{8})/i', $texto, $m)) {
        $datos['numero'] = ltrim($m[1], '0') ?: '0';
    }

    // Fecha de emisión - buscar "Fecha de Emisión: 27/02/2026" (con variaciones de OCR)
    // El OCR puede poner "Emisién" en lugar de "Emisión"
    if (preg_match('/(?:Fecha\s+de\s+)?Emisi[oóéa-z]*[:\s]*(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})/i', $texto, $m)) {
        $year = strlen($m[3]) == 2 ? '20' . $m[3] : $m[3];
        $datos['fecha'] = sprintf('%02d/%02d/%s', $m[1], $m[2], $year);
    }

    // CUIT emisor (generalmente el primero)
    if (preg_match('/C\.?U\.?I\.?T\.?[:\s]*(\d{2})[.-]?(\d{8})[.-]?(\d{1})/i', $texto, $m)) {
        $datos['cuit_emisor'] = $m[1] . '-' . $m[2] . '-' . $m[3];
    }

    // CUIT receptor (buscar en sección de cliente)
    if (preg_match('/(?:Cliente|C\.U\.I\.T\.)[:\s]*.*?(\d{2})[.-](\d{8})[.-](\d{1})/is', $texto, $m)) {
        $cuit = $m[1] . '-' . $m[2] . '-' . $m[3];
        if ($cuit !== $datos['cuit_emisor']) {
            $datos['cuit_receptor'] = $cuit;
        }
    }

    // CAE (14 dígitos)
    if (preg_match('/C\.?A\.?E\.?[:\s]*(\d{14})/i', $texto, $m)) {
        $datos['cae'] = $m[1];
    }

    // Vencimiento CAE
    if (preg_match('/(?:Vto\.?\s*C\.?A\.?E\.?|Vencimiento\s*C\.?A\.?E\.?)[:\s]*(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})/i', $texto, $m)) {
        $year = strlen($m[3]) == 2 ? '20' . $m[3] : $m[3];
        $datos['vencimiento_cae'] = sprintf('%02d/%02d/%s', $m[1], $m[2], $year);
    }

    // Neto Gravado
    if (preg_match('/Neto\s+Gravado[:\s]*\$?\s*([\d.,]+)/i', $texto, $m)) {
        $datos['neto_gravado'] = normalizarMonto($m[1]);
    }

    // IVA total - buscar "IVA: 116.260,03"
    if (preg_match('/^IVA[:\s]+(\d{1,3}(?:\.\d{3})*,\d{2})/im', $texto, $m)) {
        $datos['iva'] = normalizarMonto($m[1]);
    }

    // Total - buscar el número más grande (que será el total de la factura)
    // Primero buscar todos los montos con formato argentino
    preg_match_all('/(\d{1,3}(?:\.\d{3})*,\d{2})/', $texto, $montos);
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

    // También buscar formato con espacio: "1 .027.780,33"
    if (preg_match('/(\d)\s*\.(\d{3})\.(\d{3}),(\d{2})/', $texto, $m)) {
        $total = (float) ($m[1] . $m[2] . $m[3] . '.' . $m[4]);
        if ($total > (float)($datos['total'] ?? 0)) {
            $datos['total'] = number_format($total, 2, '.', '');
        }
    }

    // Cliente
    if (preg_match('/Cliente[:\s]*(\d+\s+)?([A-ZÁÉÍÓÚÑ\s]+)/i', $texto, $m)) {
        $datos['razon_social_receptor'] = trim($m[2]);
    }

    return $datos;
}

/**
 * Normaliza un monto en formato argentino a número
 */
function normalizarMonto(string $monto): string
{
    // Formato argentino: 1.234.567,89
    // Detectar si usa coma como decimal
    if (preg_match('/\d+\.\d{3}/', $monto) && strpos($monto, ',') !== false) {
        // Formato 1.234,56
        $monto = str_replace('.', '', $monto);
        $monto = str_replace(',', '.', $monto);
    } elseif (substr_count($monto, '.') > 1) {
        // Múltiples puntos como separador de miles: 1.234.567
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
        $resultado = extraerTextoTesseract($tmpFile);
        echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Verificar si se envió una ruta de archivo
    $input = json_decode(file_get_contents('php://input'), true);
    if (isset($input['archivo_path'])) {
        $resultado = extraerTextoTesseract($input['archivo_path']);
        echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['error' => 'No se proporcionó ningún archivo']);
    exit;
}

// Si se accede por GET, mostrar info
if (!defined('INCLUDED_AS_LIB')) {
    echo json_encode([
        'api' => 'OCR Tesseract (local)',
        'version' => '1.0',
        'uso' => [
            'POST con archivo' => 'multipart/form-data con campo "archivo"',
            'POST con ruta' => 'JSON con campo "archivo_path"'
        ],
        'formatos' => ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff']
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

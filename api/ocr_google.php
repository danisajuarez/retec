<?php
/**
 * OCR con Google Cloud Vision API
 * Sistema de Facturación
 *
 * Costos: 1,000 imágenes/mes GRATIS, luego $1.50/1000
 */

header('Content-Type: application/json; charset=utf-8');

// API Key de Google Cloud Vision
define('GOOGLE_VISION_API_KEY', 'AIzaSyAreXaonLx3rbg1WtGR9WI2Ew8LUP8NYgI');

/**
 * Extrae texto de una imagen usando Google Cloud Vision
 */
function extraerTextoGoogle(string $filePath): array
{
    if (!file_exists($filePath)) {
        return ['error' => 'Archivo no encontrado'];
    }

    // Leer archivo y convertir a base64
    $imageContent = file_get_contents($filePath);
    $base64Image = base64_encode($imageContent);

    // Preparar request para Google Vision
    $requestBody = [
        'requests' => [
            [
                'image' => [
                    'content' => $base64Image
                ],
                'features' => [
                    ['type' => 'DOCUMENT_TEXT_DETECTION']
                ]
            ]
        ]
    ];

    // Llamar a la API
    $url = 'https://vision.googleapis.com/v1/images:annotate?key=' . GOOGLE_VISION_API_KEY;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestBody));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return ['error' => 'Error de conexión: ' . $curlError];
    }

    if ($httpCode !== 200) {
        $error = json_decode($response, true);
        return ['error' => 'Error de API: ' . ($error['error']['message'] ?? 'HTTP ' . $httpCode)];
    }

    $result = json_decode($response, true);

    // Extraer texto completo
    $textoCompleto = '';
    if (isset($result['responses'][0]['fullTextAnnotation']['text'])) {
        $textoCompleto = $result['responses'][0]['fullTextAnnotation']['text'];
    } elseif (isset($result['responses'][0]['textAnnotations'][0]['description'])) {
        $textoCompleto = $result['responses'][0]['textAnnotations'][0]['description'];
    }

    if (empty($textoCompleto)) {
        return ['error' => 'No se pudo extraer texto de la imagen'];
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

    // ============================================
    // TIPO DE COMPROBANTE
    // ============================================
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
    if (preg_match('/(?:Punto\s*(?:de)?\s*Venta|PV|P\.V\.)[:\s]*(\d{3,5})/i', $texto, $m)) {
        $datos['punto_venta'] = ltrim($m[1], '0') ?: '0';
    }
    if (preg_match('/(\d{4,5})\s*[-–]\s*(\d{6,8})/', $texto, $m)) {
        if (!$datos['punto_venta']) {
            $datos['punto_venta'] = ltrim($m[1], '0') ?: '0';
        }
        $datos['numero'] = ltrim($m[2], '0') ?: '0';
    }
    if (!$datos['numero'] && preg_match('/(?:Comp\.?\s*)?(?:Nro|N[°º]|Numero)[:\s]*(\d{6,8})/i', $texto, $m)) {
        $datos['numero'] = ltrim($m[1], '0') ?: '0';
    }

    // ============================================
    // FECHA
    // ============================================
    if (preg_match('/(?:Fecha\s*(?:de)?\s*(?:Emisi[oóe]n)?|Emisi[oóe]n)[:\s]*(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})/i', $texto, $m)) {
        $year = strlen($m[3]) == 2 ? '20' . $m[3] : $m[3];
        $datos['fecha'] = sprintf('%02d/%02d/%s', $m[1], $m[2], $year);
    } elseif (preg_match('/(\d{1,2})[\/\-](\d{1,2})[\/\-](20\d{2})/', $texto, $m)) {
        $datos['fecha'] = sprintf('%02d/%02d/%s', $m[1], $m[2], $m[3]);
    }

    // ============================================
    // CUIT
    // ============================================
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
    if (preg_match('/C\.?A\.?E\.?[:\s]*(\d{14})/i', $texto, $m)) {
        $datos['cae'] = $m[1];
    } elseif (preg_match('/(\d{14})/', $texto, $m)) {
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
    $patronMonto = '([\d]{1,3}(?:[.,]\d{3})*[.,]\d{2}|\d+[.,]\d{2})';

    // Formato especial: el OCR lee columnas mezcladas
    // "Neto Gravado:\nI.V.A.:\n2.073.952,15\n339.750,78"
    if (preg_match('/Neto\s+Gravado\s*:\s*\n\s*I\.V\.A\.\s*:\s*\n\s*' . $patronMonto . '\s*\n\s*' . $patronMonto . '/i', $texto, $m)) {
        $datos['neto_gravado'] = normalizarMonto($m[1]);
        $datos['iva'] = normalizarMonto($m[2]);
    } else {
        // Formato normal: "Neto Gravado: 2.073.952,15"
        if (preg_match('/Neto\s+Gravado\s*:\s*' . $patronMonto . '/i', $texto, $m)) {
            $datos['neto_gravado'] = normalizarMonto($m[1]);
        }

        // I.V.A. - formato: "I.V.A.: 339.750,78" (sin porcentaje = no es alícuota)
        if (preg_match('/I\.V\.A\.\s*:\s*' . $patronMonto . '(?!\s*\n\s*Neto)/i', $texto, $m)) {
            $datos['iva'] = normalizarMonto($m[1]);
        }
    }

    // Neto No Gravado - formato: "Neto No Gravado:\n0,00"
    if (preg_match('/Neto\s+No\s+Gravado\s*:\s*\n?\s*' . $patronMonto . '/i', $texto, $m)) {
        $datos['neto_no_gravado'] = normalizarMonto($m[1]);
    }

    // Total - formato: "TOTAL:\n2.413.702,93"
    if (preg_match('/\nTOTAL\s*:\s*\n\s*' . $patronMonto . '/i', $texto, $m)) {
        $datos['total'] = normalizarMonto($m[1]);
    } elseif (preg_match('/TOTAL\s*:\s*' . $patronMonto . '/i', $texto, $m)) {
        $datos['total'] = normalizarMonto($m[1]);
    }

    // Si tenemos Neto Gravado e IVA pero no Total, calcularlo
    if (!$datos['total'] && $datos['neto_gravado'] && $datos['iva']) {
        $neto = (float)$datos['neto_gravado'];
        $iva = (float)$datos['iva'];
        $noGravado = $datos['neto_no_gravado'] ? (float)$datos['neto_no_gravado'] : 0;
        $datos['total'] = number_format($neto + $iva + $noGravado, 2, '.', '');
    }

    // Fallback: si no encontramos Total, buscar el monto más grande
    if (!$datos['total']) {
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

    // ============================================
    // ITEMS DE LA FACTURA
    // ============================================
    $datos['items'] = parsearItems($texto);

    return $datos;
}

/**
 * Parsea los ítems/líneas de detalle de la factura
 * Maneja el formato de Google Vision donde cada columna puede estar en líneas separadas
 */
function parsearItems(string $texto): array
{
    $items = [];
    $lineas = explode("\n", $texto);
    $totalLineas = count($lineas);

    // Patrón para monto (número con formato argentino)
    $patronMonto = '/^[\d]{1,3}(?:[.,]\d{3})*[.,]\d{2}$/';

    // Patrón para cantidad + descripción: "3 Adaptador de Red..."
    $patronCantDesc = '/^(\d+)\s+(.+)$/';

    // Líneas a ignorar (encabezados, footer, etc.)
    $palabrasIgnorar = [
        'Codigo', 'Cant.', 'Descripcion', 'Unitario', '%IVA', '%DESC.', 'II.II.', 'TOTAL',
        'ARCA', 'Comprobante', 'Autorizado', 'Neto Gravado', 'I.V.A.:', 'Percepción',
        'Cliente:', 'Domicilio:', 'Localidad:', 'CUIT:', 'C.U.I.T.:', 'Punto de Venta',
        'Fecha de Emisión', 'Comp. Nro', 'Factura', 'RETEC', 'Condición Fiscal',
        'Responsable Inscripto', 'COD.', 'INGRESOS BRUTOS', 'INICIO ACTIVIDAD',
        'Cond. de Venta', 'MÁS DE UNO', 'MÁS DE TODOS', 'AV.', 'CABA', 'Argentina',
        'Piso:', 'Dpto:', 'Totales', 'alícuot', 'Neto No Gravado', 'Imp. Internos'
    ];

    $i = 0;
    while ($i < $totalLineas) {
        $linea = trim($lineas[$i]);

        // Saltar líneas vacías
        if (empty($linea) || strlen($linea) < 2) {
            $i++;
            continue;
        }

        // Verificar si es una línea a ignorar
        $ignorar = false;
        foreach ($palabrasIgnorar as $palabra) {
            if (stripos($linea, $palabra) !== false) {
                $ignorar = true;
                break;
            }
        }
        if ($ignorar) {
            $i++;
            continue;
        }

        // Verificar si es un monto (no es código)
        if (preg_match($patronMonto, $linea)) {
            $i++;
            continue;
        }

        // ¿Es un código de producto? (letras y números, típicamente 3-15 caracteres)
        // Ejemplos: UE300, UE300C, UC400, TAPO C100, TAPO C520WS, UH5020C
        if (preg_match('/^[A-Z][A-Z0-9\s]{1,14}$/i', $linea)) {
            $codigo = trim($linea);

            $item = [
                'codigo' => $codigo,
                'cantidad' => null,
                'descripcion' => '',
                'unitario' => null,
                'iva' => null,
                'descuento' => null,
                'imp_internos' => null,
                'total' => null
            ];

            $i++;
            $valores = [];

            // Leer las siguientes líneas para completar el ítem
            while ($i < $totalLineas && count($valores) < 5) {
                $siguiente = trim($lineas[$i]);

                if (empty($siguiente)) {
                    $i++;
                    continue;
                }

                // ¿Es un monto?
                if (preg_match($patronMonto, $siguiente)) {
                    $valores[] = normalizarMonto($siguiente);
                    $i++;
                    continue;
                }

                // ¿Es cantidad + descripción? (ej: "3 Adaptador de Red...")
                if ($item['cantidad'] === null && preg_match($patronCantDesc, $siguiente, $m)) {
                    $item['cantidad'] = $m[1];
                    $item['descripcion'] = $m[2];
                    $i++;
                    continue;
                }

                // ¿Es solo un número (cantidad sola)?
                if ($item['cantidad'] === null && preg_match('/^(\d+)$/', $siguiente, $m)) {
                    $item['cantidad'] = $m[1];
                    $i++;
                    continue;
                }

                // ¿Es otro código de producto? (fin del ítem actual)
                if (preg_match('/^[A-Z][A-Z0-9\s]{1,14}$/i', $siguiente) && count($valores) >= 5) {
                    break;
                }

                // ¿Es continuación de descripción?
                if ($item['cantidad'] !== null && count($valores) == 0) {
                    // Verificar que no sea una palabra a ignorar
                    $esIgnorar = false;
                    foreach ($palabrasIgnorar as $palabra) {
                        if (stripos($siguiente, $palabra) !== false) {
                            $esIgnorar = true;
                            break;
                        }
                    }
                    if (!$esIgnorar) {
                        $item['descripcion'] .= ' ' . $siguiente;
                    }
                    $i++;
                    continue;
                }

                // Si ya tenemos valores y encontramos texto, podría ser otro ítem
                if (count($valores) > 0 && !preg_match($patronMonto, $siguiente)) {
                    break;
                }

                $i++;
            }

            // Asignar valores al ítem
            if (count($valores) >= 5) {
                $item['unitario'] = $valores[0];
                $item['iva'] = $valores[1];
                $item['descuento'] = $valores[2];
                $item['imp_internos'] = $valores[3];
                $item['total'] = $valores[4];
            }

            // Limpiar descripción
            $item['descripcion'] = trim($item['descripcion']);

            // Solo agregar si tiene datos mínimos (cantidad y total)
            if ($item['cantidad'] !== null && $item['total'] !== null) {
                $items[] = $item;
            }
        } else {
            $i++;
        }
    }

    return $items;
}

/**
 * Normaliza un monto en formato argentino a número
 */
function normalizarMonto(string $monto): string
{
    // Formato argentino: 1.234.567,89 -> 1234567.89
    if (preg_match('/\d+\.\d{3}/', $monto) && strpos($monto, ',') !== false) {
        $monto = str_replace('.', '', $monto);
        $monto = str_replace(',', '.', $monto);
    } elseif (substr_count($monto, '.') > 1) {
        $monto = str_replace('.', '', $monto);
    } elseif (strpos($monto, ',') !== false && strpos($monto, '.') === false) {
        // Solo coma decimal: 1234,56 -> 1234.56
        $monto = str_replace(',', '.', $monto);
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
        $resultado = extraerTextoGoogle($tmpFile);
        echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Verificar si se envió una imagen en base64
    $input = json_decode(file_get_contents('php://input'), true);
    if (isset($input['imagen_base64'])) {
        $tmpFile = sys_get_temp_dir() . '/ocr_' . uniqid() . '.png';
        file_put_contents($tmpFile, base64_decode($input['imagen_base64']));
        $resultado = extraerTextoGoogle($tmpFile);
        unlink($tmpFile);
        echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Verificar si se envió una ruta de archivo
    if (isset($input['archivo_path'])) {
        $resultado = extraerTextoGoogle($input['archivo_path']);
        echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['error' => 'No se proporcionó ningún archivo']);
    exit;
}

// Si se accede por GET, mostrar info
if (!defined('INCLUDED_AS_LIB')) {
    echo json_encode([
        'api' => 'OCR Google Cloud Vision',
        'version' => '1.0',
        'costo' => '1,000 imágenes/mes GRATIS, luego $1.50/1000',
        'uso' => [
            'POST con archivo' => 'multipart/form-data con campo "archivo"',
            'POST con base64' => 'JSON con campo "imagen_base64"',
            'POST con ruta' => 'JSON con campo "archivo_path"'
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

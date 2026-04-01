<?php
/**
 * OCR con Google Cloud Vision API
 * Sistema de Facturación RETEC
 */

// Asegurar que siempre devuelva JSON
ini_set('display_errors', 0);
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');

// API Key de Google Cloud Vision
define('GOOGLE_VISION_API_KEY', 'AIzaSyAyD-XwaYRLWI93cC_I5gsvK9FSthPljnQ');

/**
 * Extrae texto de una imagen o PDF usando Google Cloud Vision
 * @param string $filePath Ruta al archivo
 * @return array Resultado con texto extraído y datos estructurados
 */
function extraerTextoOCR(string $filePath): array
{
    if (!file_exists($filePath)) {
        return ['error' => 'Archivo no encontrado'];
    }

    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

    // Si es PDF, convertir primera página a imagen
    if ($extension === 'pdf') {
        $imagePath = convertirPDFaImagen($filePath);
        if (!$imagePath) {
            return ['error' => 'No se pudo convertir el PDF a imagen'];
        }
        $filePath = $imagePath;
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
                    ['type' => 'TEXT_DETECTION'],
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
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        $error = json_decode($response, true);
        return ['error' => 'Error de API: ' . ($error['error']['message'] ?? 'Desconocido')];
    }

    $result = json_decode($response, true);

    // Extraer texto completo
    $textoCompleto = '';
    if (isset($result['responses'][0]['fullTextAnnotation']['text'])) {
        $textoCompleto = $result['responses'][0]['fullTextAnnotation']['text'];
    } elseif (isset($result['responses'][0]['textAnnotations'][0]['description'])) {
        $textoCompleto = $result['responses'][0]['textAnnotations'][0]['description'];
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
 * Convierte un PDF a imagen (requiere ImageMagick o Ghostscript)
 */
function convertirPDFaImagen(string $pdfPath): ?string
{
    $outputPath = sys_get_temp_dir() . '/ocr_' . uniqid() . '.png';

    // Intentar con ImageMagick
    if (extension_loaded('imagick')) {
        try {
            $imagick = new Imagick();
            $imagick->setResolution(300, 300);
            $imagick->readImage($pdfPath . '[0]'); // Primera página
            $imagick->setImageFormat('png');
            $imagick->writeImage($outputPath);
            $imagick->clear();
            return $outputPath;
        } catch (Exception $e) {
            // Continuar con método alternativo
        }
    }

    // Intentar con Ghostscript (Windows)
    $gsPath = 'gswin64c'; // o gswin32c en sistemas de 32 bits
    $cmd = sprintf(
        '%s -dNOPAUSE -dBATCH -sDEVICE=png16m -r300 -dFirstPage=1 -dLastPage=1 -sOutputFile=%s %s 2>&1',
        escapeshellarg($gsPath),
        escapeshellarg($outputPath),
        escapeshellarg($pdfPath)
    );

    exec($cmd, $output, $returnCode);

    if ($returnCode === 0 && file_exists($outputPath)) {
        return $outputPath;
    }

    return null;
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
        $monto = str_replace(',', '.', $monto);
    }
    return $monto;
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

    // Códigos de productos conocidos (patrón general)
    // Ejemplo: UE300, UE300C, UC400, TAPO C100, TAPO C520WS, UH5020C
    $patronCodigo = '/^([A-Z]{2,}[\s]?[A-Z0-9]*)\s*$/i';

    // Patrón para cantidad + descripción
    // Ejemplo: "3 Adaptador de Red Tp-Link USB 3.0..."
    // Ejemplo: "10 Cámara IP TP-Link..."
    $patronCantDesc = '/^(\d+)\s+(.+)$/';

    // Patrón para solo cantidad (cuando está sola)
    $patronSoloCant = '/^(\d+)\s*$/';

    // Patrón para monto (número con formato argentino)
    $patronMonto = '/^[\d]{1,3}(?:[.,]\d{3})*[.,]\d{2}$/';

    // Líneas a ignorar
    $lineasIgnorar = [
        'Codigo', 'Cant.', 'Descripcion', 'Unitario', '%IVA', '%DESC.', 'II.II.', 'TOTAL',
        'ARCA', 'Comprobante', 'Autorizado', 'Neto', 'Gravado', 'I.V.A.', 'Percepción',
        'Cliente', 'Domicilio', 'Localidad', 'CUIT', 'C.U.I.T.', 'Punto', 'Fecha', 'Comp.',
        'Factura', 'RETEC', 'Condición', 'Fiscal', 'Responsable', 'Inscripto', 'COD.',
        'INGRESOS', 'BRUTOS', 'INICIO', 'ACTIVIDAD', 'Cond.', 'Venta', 'MÁS', 'DE', 'UNO',
        'TODOS', 'AV.', 'CABA', 'Argentina', 'Piso', 'Dpto', 'Totales', 'alícuot'
    ];

    $i = 0;
    while ($i < $totalLineas) {
        $linea = trim($lineas[$i]);

        // Saltar líneas vacías o a ignorar
        if (empty($linea) || strlen($linea) < 2) {
            $i++;
            continue;
        }

        // Verificar si es una línea a ignorar
        $ignorar = false;
        foreach ($lineasIgnorar as $patron) {
            if (stripos($linea, $patron) !== false) {
                $ignorar = true;
                break;
            }
        }
        if ($ignorar) {
            $i++;
            continue;
        }

        // ¿Es un código de producto? (solo letras/números, sin espacios largos)
        if (preg_match($patronCodigo, $linea) || preg_match('/^[A-Z][A-Z0-9\s]{2,15}$/i', $linea)) {
            $codigo = trim($linea);

            // Verificar que no sea un monto
            if (preg_match($patronMonto, $codigo)) {
                $i++;
                continue;
            }

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

                // ¿Es otro código? (nuevo ítem)
                if (preg_match($patronCodigo, $siguiente) && !preg_match($patronMonto, $siguiente)) {
                    // Verificar que no sea continuación de descripción
                    if ($item['cantidad'] !== null && count($valores) >= 5) {
                        break;
                    }
                    // Podría ser continuación de descripción si contiene el código del item
                    if (stripos($siguiente, $item['codigo']) !== false || strpos($siguiente, '-') === 0) {
                        $item['descripcion'] .= ' ' . $siguiente;
                        $i++;
                        continue;
                    }
                    // Es un nuevo código, pero solo si ya tenemos valores
                    if (count($valores) >= 5) {
                        break;
                    }
                }

                // ¿Es cantidad + descripción?
                if ($item['cantidad'] === null && preg_match($patronCantDesc, $siguiente, $m)) {
                    $item['cantidad'] = $m[1];
                    $item['descripcion'] = $m[2];
                    $i++;
                    continue;
                }

                // ¿Es solo cantidad?
                if ($item['cantidad'] === null && preg_match($patronSoloCant, $siguiente, $m)) {
                    $item['cantidad'] = $m[1];
                    $i++;
                    continue;
                }

                // ¿Es un monto?
                if (preg_match($patronMonto, $siguiente)) {
                    $valores[] = normalizarMonto($siguiente);
                    $i++;
                    continue;
                }

                // ¿Es continuación de descripción?
                if ($item['cantidad'] !== null && count($valores) == 0) {
                    $item['descripcion'] .= ' ' . $siguiente;
                    $i++;
                    continue;
                }

                // Línea no reconocida, avanzar
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

            // Solo agregar si tiene datos mínimos
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
 * Parsea el texto extraído para identificar datos de factura
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

    // Tipo de comprobante (Factura A, B, C, etc.)
    if (preg_match('/Factura[s]?\s*(?:Electr[oó]nica)?\s*\(([A-C])\)/i', $texto, $m)) {
        $datos['tipo_comprobante'] = 'Factura ' . strtoupper($m[1]);
    } elseif (preg_match('/FACTURA[S]?\s+([A-C])\b/i', $texto, $m)) {
        $datos['tipo_comprobante'] = 'Factura ' . strtoupper($m[1]);
    } elseif (preg_match('/\(([A-C])\)/', $texto, $m)) {
        $datos['tipo_comprobante'] = 'Factura ' . strtoupper($m[1]);
    } elseif (preg_match('/NOTA\s+DE\s+CR[EÉ]DITO\s+([A-C])/i', $texto, $m)) {
        $datos['tipo_comprobante'] = 'Nota de Crédito ' . strtoupper($m[1]);
    } elseif (preg_match('/NOTA\s+DE\s+D[EÉ]BITO\s+([A-C])/i', $texto, $m)) {
        $datos['tipo_comprobante'] = 'Nota de Débito ' . strtoupper($m[1]);
    }

    // Punto de venta y número
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

    // Fecha de emisión
    if (preg_match('/(?:Fecha\s*(?:de)?\s*(?:Emisi[oóe]n)?|Emisi[oóe]n)[:\s]*(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})/i', $texto, $m)) {
        $year = strlen($m[3]) == 2 ? '20' . $m[3] : $m[3];
        $datos['fecha'] = sprintf('%02d/%02d/%s', $m[1], $m[2], $year);
    } elseif (preg_match('/(\d{1,2})[\/\-](\d{1,2})[\/\-](20\d{2})/', $texto, $m)) {
        $datos['fecha'] = sprintf('%02d/%02d/%s', $m[1], $m[2], $m[3]);
    }

    // CUIT (buscar todos los CUITs)
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

    // CAE
    if (preg_match('/C\.?A\.?E\.?[:\s]*(\d{14})/i', $texto, $m)) {
        $datos['cae'] = $m[1];
    }

    // Vencimiento CAE
    if (preg_match('/(?:Vto\.?\s*(?:C\.?A\.?E\.?)?|Vencimiento(?:\s*C\.?A\.?E\.?)?|Venc\.?)[:\s]*(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})/i', $texto, $m)) {
        $year = strlen($m[3]) == 2 ? '20' . $m[3] : $m[3];
        $datos['vencimiento_cae'] = sprintf('%02d/%02d/%s', $m[1], $m[2], $year);
    }

    // ============================================
    // ITEMS DE LA FACTURA
    // ============================================
    $datos['items'] = parsearItems($texto);

    // ============================================
    // MONTOS
    // ============================================
    $patronMonto = '([\d]{1,3}(?:[.,]\d{3})*[.,]\d{2}|\d+[.,]\d{2})';

    // Neto No Gravado - buscar PRIMERO para no confundir
    if (preg_match('/(?:Neto\s+)?No\s*Gravado[\s:]*\$?\s*' . $patronMonto . '/i', $texto, $m)) {
        $datos['neto_no_gravado'] = normalizarMonto($m[1]);
    }

    // Neto Gravado
    if (preg_match('/Importe\s+Neto\s+Gravado[\s:]*\$?\s*' . $patronMonto . '/i', $texto, $m)) {
        $datos['neto_gravado'] = normalizarMonto($m[1]);
    } elseif (preg_match('/Neto\s+Gravado[\s:]*\$?\s*' . $patronMonto . '/i', $texto, $m)) {
        $fullMatch = $m[0];
        if (stripos($fullMatch, 'no gravado') === false) {
            $datos['neto_gravado'] = normalizarMonto($m[1]);
        }
    } elseif (preg_match('/Subtotal[\s:]*\$?\s*' . $patronMonto . '/i', $texto, $m)) {
        $datos['neto_gravado'] = normalizarMonto($m[1]);
    }

    // IVA
    if (preg_match('/I\.V\.A\.[\s:]*\$?\s*' . $patronMonto . '/i', $texto, $m)) {
        $datos['iva'] = normalizarMonto($m[1]);
    } elseif (preg_match('/IVA\s*(?:21|10[,.]5|27)\s*%?[\s:]*\$?\s*' . $patronMonto . '/i', $texto, $m)) {
        $datos['iva'] = normalizarMonto($m[1]);
    } elseif (preg_match('/(?:^|[\s\n])IVA[\s:]+\$?\s*' . $patronMonto . '/im', $texto, $m)) {
        $datos['iva'] = normalizarMonto($m[1]);
    }

    // Total
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

    return $datos;
}

// ============================================
// ENDPOINT API
// ============================================

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Verificar si se subió un archivo
        if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
            $tmpFile = $_FILES['archivo']['tmp_name'];
            $resultado = extraerTextoOCR($tmpFile);
            echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Verificar si se envió una imagen en base64
        $input = json_decode(file_get_contents('php://input'), true);
        if (isset($input['imagen_base64'])) {
            $tmpFile = sys_get_temp_dir() . '/ocr_' . uniqid() . '.png';
            file_put_contents($tmpFile, base64_decode($input['imagen_base64']));
            $resultado = extraerTextoOCR($tmpFile);
            unlink($tmpFile);
            echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Verificar si se envió una ruta de archivo
        if (isset($input['archivo_path'])) {
            $resultado = extraerTextoOCR($input['archivo_path']);
            echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode(['error' => 'No se proporcionó ningún archivo']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

// Si se accede por GET, mostrar info
echo json_encode([
    'api' => 'OCR Google Cloud Vision',
    'version' => '1.0',
    'uso' => [
        'POST con archivo' => 'multipart/form-data con campo "archivo"',
        'POST con base64' => 'JSON con campo "imagen_base64"',
        'POST con ruta' => 'JSON con campo "archivo_path"'
    ]
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

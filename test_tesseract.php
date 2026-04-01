<?php
/**
 * Test de Tesseract OCR
 */

define('INCLUDED_AS_LIB', true);
require_once 'api/ocr_tesseract.php';

echo "=== Test Tesseract OCR ===\n\n";

// Verificar instalación
echo "Verificando Tesseract...\n";
$tesseractPath = 'C:/Program Files/Tesseract-OCR/tesseract.exe';
exec('"' . $tesseractPath . '" --version 2>&1', $version, $code);
if ($code !== 0) {
    echo "ERROR: Tesseract no esta instalado.\n\n";
    echo "Instalar desde: https://github.com/UB-Mannheim/tesseract/wiki\n";
    exit(1);
}
echo "OK: " . $version[0] . "\n\n";

// Probar con factura
$archivo = 'Factura_A_0003_00155708_page-0001.jpg';
echo "Procesando: $archivo\n";
echo "Esto puede tardar unos segundos...\n\n";

$resultado = extraerTextoTesseract($archivo);

echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

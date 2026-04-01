<?php
require_once 'api/ocr.php';

echo "Probando OCR con factura de ejemplo...\n\n";

$resultado = extraerTextoOCR('Factura_A_0003_00155708_page-0001.jpg');

echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

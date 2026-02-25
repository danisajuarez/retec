<?php
// Simular una sesión para el cliente PERFABRI (ID 682)
session_start();
$_SESSION['cliente_id'] = 682;
$_SESSION['cliente_nombre'] = 'PERFABRI S.R.L.';
$_SESSION['cliente_cuit'] = '30-71155553-2';

// Simular POST de DataTables
$_POST = [
    'draw' => 1,
    'start' => 0,
    'length' => 10,
    'search' => ['value' => ''],
    'order' => [['column' => 0, 'dir' => 'desc']],
    'tipo' => '',
    'estado' => ''
];

$_SERVER['REQUEST_METHOD'] = 'POST';

// Capturar output
ob_start();
include 'api/facturas.php';
$output = ob_get_clean();

echo "=== RESPUESTA DEL API ===\n";
echo $output;
echo "\n\n=== VALIDACION JSON ===\n";
$decoded = json_decode($output);
if (json_last_error() === JSON_ERROR_NONE) {
    echo "JSON VALIDO\n";
    echo "Total records: " . $decoded->recordsTotal . "\n";
    echo "Data count: " . count($decoded->data) . "\n";
} else {
    echo "ERROR JSON: " . json_last_error_msg() . "\n";
}

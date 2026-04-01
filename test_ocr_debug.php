<?php
/**
 * Test OCR con debug completo
 */

define('GOOGLE_VISION_API_KEY', 'AIzaSyAyD-XwaYRLWI93cC_I5gsvK9FSthPljnQ');

$filePath = 'Factura_A_0003_00155708_page-0001.jpg';

echo "Archivo: $filePath\n";
echo "Existe: " . (file_exists($filePath) ? 'SI' : 'NO') . "\n";
echo "Tamaño: " . filesize($filePath) . " bytes\n\n";

// Leer y convertir a base64
$imageContent = file_get_contents($filePath);
$base64Image = base64_encode($imageContent);
echo "Base64 length: " . strlen($base64Image) . "\n\n";

// Preparar request
$requestBody = [
    'requests' => [
        [
            'image' => [
                'content' => $base64Image
            ],
            'features' => [
                ['type' => 'TEXT_DETECTION']
            ]
        ]
    ]
];

$url = 'https://vision.googleapis.com/v1/images:annotate?key=' . GOOGLE_VISION_API_KEY;

echo "Llamando a Google Vision API...\n\n";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestBody));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Para Windows

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
if ($curlError) {
    echo "CURL Error: $curlError\n";
}

echo "\nRespuesta:\n";
echo $response;

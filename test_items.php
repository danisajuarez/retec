<?php
require_once 'api/ocr_google.php';
define('INCLUDED_AS_LIB', true);

$texto = <<<EOT
UE300
3 Adaptador de Red Tp-Link USB 3.0 a Ethernet Gigabit - UE300
13.922,39
21,00
0,00
0,00
41.767,17
UE300C
6 Adaptador de red USB Tp-Link Type-C a RJ45 Gigabit Ethernet
- UE300C
16.242,81
21,00
0,00
0,00
97.456,87
UC400
10 Adaptador USB TP-Link UC400 USB-C 3.0 Super Rapido a
USB-A OTG - UC400
6.961,41
21,00
0,00
0,00
69.614,10
TAPO C100
4
Camara IP TP-Link Tapo C100 Inalambrico 1080p - TAPO C100
18.537,96
21,00
0,00
0,00
74.151,84
TAPO C200
10 Camara IP TP-Link Tapo C200 Inalambrico 1080p - TAPO C200
20.303,44
21,00
0,00
0,00
203.034,40
TAPO C230
5 Camara IP Cloud TP-Link Tapo C230 3K 5MP - TAPO C230
31.779,46
21,00
0,00
0,00
158.897,30
TAPO C310
5 Camara IP TP-Link Tapo C310 Inalambrico 1080p Exterior -
TAPO C310
33.544,94
21,00
0,00
0,00
167.724,70
TAPO C320WS
4
Camara IP TP-Link Tapo C320WS WIFI SD HD Exterior - TAPO
C320WS
42.464,11
21,00
0,00
0,00
169.856,44
TAPO C325WB
4
Camara IP TP-Link Tapo C325WB Inalambrica 2K QHD Vision
Nocturna ColorPro Deteccion IA Exterior IP66 - TAPO C325WB
79.448,72
21,00
0,00
0,00
317.794,88
TAPO C500
4 Camara IP TP-Link Tapo C500 WiFi Remoto Exterior Dia Noche
SD-TAPO C500
34.427,90
21,00
0,00
0,00
137.711,60
TAPO C520WS
20 Camara IP TP-Link Tapo C520WS WIFI 2K QHD Exterior PTZ -
TAPO C520WS
51.200,23
21,00
0,00
0,00
1.024.004,60
UH5020C
5 Hub USB-C TP-Link 5 en 1 HDMI Ethernet USB-UH5020C
32.485,77
21,00
0,00
0,00
162.428,83
EOT;

$items = parsearItems($texto);
echo "Total items encontrados: " . count($items) . "\n\n";

foreach ($items as $i => $item) {
    echo ($i+1) . ". " . $item['codigo'] . "\n";
    echo "   Cant: " . $item['cantidad'] . " | Total: $" . number_format((float)$item['total'], 2, ',', '.') . "\n";
    echo "   Desc: " . $item['descripcion'] . "\n\n";
}

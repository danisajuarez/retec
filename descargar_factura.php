<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$codigo = $_GET['id'] ?? '';
if (empty($codigo)) {
    http_response_code(400);
    die('Código no proporcionado');
}

$pdo = getConnection();

// Validar el código (Base64 de ID + CAE)
$facturaId = validarCodigoFactura($codigo, $pdo);
if ($facturaId === null) {
    http_response_code(403);
    die('Factura no encontrada o código inválido');
}

$stmt = $pdo->prepare("
    SELECT
        sige_tcp_tipocomp.TCP_DesTipoComp,
        sige_efc_encfac.EFC_FecEfc,
        sige_efc_encfac.EFC_ClaseComEfc,
        sige_efc_encfac.EFC_TipoEfc,
        sige_efc_encfac.EFC_PuestoComEfc,
        sige_efc_encfac.EFC_NumComEfc,
        sige_efc_encfac.TCP_IdTipoComAfip,
        sige_efc_encfac.TER_IDTercero,
        sige_efc_encfac.ter_razonsocialter,
        sige_efc_encfac.TER_DomicilioTer,
        sige_efc_encfac.LOC_IDLocalidad AS LOC_CodigoPostal,
        sige_loc_localidad.LOC_NomLocalidad,
        sige_efc_encfac.cvt_descripcion,
        sige_efc_encfac.IVA_NomIVA,
        sige_efc_encfac.TER_CUITTer,
        sige_efc_encfac.EFC_NetoGravEfc,
        sige_efc_encfac.EFC_ImpIVAEfc,
        sige_efc_encfac.EFC_NetoNoGravEfc,
        sige_efc_encfac.EFC_ImpPercEfc,
        sige_efc_encfac.EFC_ImpImpuIntEfc,
        sige_efc_encfac.EFC_ImpTotalEfc,
        sige_efc_encfac.EFC_CAE,
        sige_efc_encfac.EFC_CAEVto,
        sige_efo_encfacc.ter_razonsocialter AS Proveedor,
        sige_efo_encfacc.EFO_ClaseComEfc AS ClaseCompra,
        sige_efo_encfacc.EFO_PuestoComEfc AS PuestoCompra,
        sige_efo_encfacc.EFO_NumComEfc AS NumeroCompra,
        sige_efo_encfacc.EFO_FecEfc AS FechaCompra,
        sige_efo_encfacc.cvt_descripcion AS CVCompra,
        sige_cfo_cuotafacc.CFO_FecVencim AS VencimientoCompra,
        sige_efo_encfacc.val_cotizvalor AS CotizacionCompra
    FROM ((sige_tcp_tipocomp
        INNER JOIN sige_efc_encfac ON sige_tcp_tipocomp.TCP_IDTipoComp = sige_efc_encfac.EFC_TipoEfc)
        LEFT JOIN sige_efo_encfacc ON (sige_efc_encfac.EFC_TipoCompVD = sige_efo_encfacc.EFO_TipoEfc) AND (sige_efc_encfac.EFC_IdVieneDe = sige_efo_encfacc.EFO_IdEfc))
        LEFT JOIN sige_cfo_cuotafacc ON sige_efo_encfacc.EFO_IdEfc = sige_cfo_cuotafacc.EFO_IdEfc
        LEFT JOIN sige_loc_localidad ON sige_efc_encfac.LOC_IDLocalidad = sige_loc_localidad.LOC_IDLocalidad
    WHERE sige_efc_encfac.EFC_IdEfc = ?
");
$stmt->execute([$facturaId]);
$factura = $stmt->fetch();

if (!$factura) {
    http_response_code(404);
    die('Factura no encontrada');
}

$stmt = $pdo->prepare("
    SELECT
        sige_dfc_detfac.ART_IDArticulo,
        sige_dfc_detfac.ART_DesArticulo,
        sige_dfc_detfac.DFC_CantArt,
        (sige_dfc_detfac.DFC_PreUnitArt * sige_dfc_detfac.DFC_Cotizacion) AS Unitario,
        sige_dfc_detfac.DFC_PorIVARIArt,
        sige_dfc_detfac.DFC_PorIVARNIArt,
        sige_dfc_detfac.DFC_PorcDescArt,
        sige_dfc_detfac.DFC_ImpImpuIntArt,
        sige_dfc_detfac.DFC_ImpTotRen
    FROM sige_dfc_detfac
    WHERE sige_dfc_detfac.EFC_IdEfc = ?
    ORDER BY sige_dfc_detfac.DFC_RenglonDfc
");
$stmt->execute([$facturaId]);
$detalles = $stmt->fetchAll();

// Desglose de IVA
$stmtIva = $pdo->prepare("
    SELECT DFC_PorIVARIArt, SUM(DFC_ImpIVARIArt) AS ImporteIVA
    FROM sige_dfc_detfac
    WHERE EFC_IdEfc = ?
    GROUP BY DFC_PorIVARIArt
    HAVING DFC_PorIVARIArt > 0
");
$stmtIva->execute([$facturaId]);
$desgloseIva = $stmtIva->fetchAll();

function formatMoney($val) {
    return number_format(floatval($val), 2, ',', '.');
}

function toUtf8($str) {
    return mb_convert_encoding($str ?? '', 'UTF-8', 'ISO-8859-1');
}

$puestoVenta = str_pad($factura['EFC_PuestoComEfc'], 4, '0', STR_PAD_LEFT);
$numComp = str_pad($factura['EFC_NumComEfc'], 8, '0', STR_PAD_LEFT);

$qrData = [
    'ver' => 1,
    'fecha' => date('Y-m-d', strtotime($factura['EFC_FecEfc'])),
    'cuit' => 30712057285,
    'ptoVta' => intval($factura['EFC_PuestoComEfc']),
    'tipoCmp' => intval($factura['TCP_IdTipoComAfip']),
    'nroCmp' => intval($factura['EFC_NumComEfc']),
    'importe' => floatval($factura['EFC_ImpTotalEfc']),
    'moneda' => 'PES',
    'ctz' => 1,
    'tipoDocRec' => 80,
    'nroDocRec' => intval(str_replace('-', '', $factura['TER_CUITTer'] ?? '0')),
    'tipoCodAut' => 'E',
    'codAut' => intval($factura['EFC_CAE'] ?? 0)
];
$qrBase64 = base64_encode(json_encode($qrData));
$qrUrl = "https://www.afip.gob.ar/fe/qr/?p=" . $qrBase64;
$qrImageUrl = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($qrUrl);

// Construir HTML del desglose IVA
$ivaHtml = '';
foreach ($desgloseIva as $iva) {
    $ivaHtml .= 'IVA ' . formatMoney($iva['DFC_PorIVARIArt']) . '%: ' . formatMoney($iva['ImporteIVA']) . '<br>';
}

// Comprobante proveedor
$comprobante = !empty($factura['ClaseCompra']) ? $factura['ClaseCompra'] . ' ' . str_pad($factura['PuestoCompra'] ?? '', 4, '0', STR_PAD_LEFT) . ' ' . str_pad($factura['NumeroCompra'] ?? '', 8, '0', STR_PAD_LEFT) : '';

// Configuración de paginación - todas las páginas tienen header y footer completo
$ITEMS_POR_PAGINA = 16; // Ítems por página (igual en todas las páginas)

// Dividir detalles en páginas
$totalItems = count($detalles);
$paginas = [];

if ($totalItems <= $ITEMS_POR_PAGINA) {
    $paginas[] = $detalles;
} else {
    $restantes = $detalles;
    while (count($restantes) > 0) {
        $paginas[] = array_slice($restantes, 0, $ITEMS_POR_PAGINA);
        $restantes = array_slice($restantes, $ITEMS_POR_PAGINA);
    }
}

$totalPaginas = count($paginas);

$html = '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 5mm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 11px; }

        .page { width: 200mm; height: 287mm; position: relative; margin: 0 auto; }
        .page-break { page-break-before: always; }
        .factura { height: 287mm; position: relative; border: 1px solid #000; }

        /* HEADER */
        .header { width: 100%; border-bottom: 1px solid #000; }
        .header td { vertical-align: top; padding: 15px; }
        .header-left { width: 45%; border-right: 1px solid #000; }
        .header-center { width: 10%; text-align: center; border-right: 1px solid #000; padding: 10px 5px; }
        .header-right { width: 45%; }
        .logo-img { max-width: 100%; max-height: 90px; }
        .letra { font-size: 50px; font-weight: bold; }
        .letra-cod { font-size: 8px; margin-top: 5px; }
        .header-right-title { font-size: 11px; margin-bottom: 15px; }
        .tipo-comp { font-size: 16px; font-weight: bold; margin-bottom: 10px; margin-top: 10px; }
        .header-right-data { font-size: 10px; border-top: 1px solid #000; padding-top: 10px; margin-top: 10px; }
        .empresa-info { font-size: 9px; text-align: right; }

        /* CLIENTE */
        .cliente { width: 100%; border-bottom: 1px solid #000; font-size: 10px; }
        .cliente td { padding: 10px 15px; vertical-align: top; }
        .cliente-left { width: 50%; }
        .cliente-right { width: 50%; text-align: right; }
        .cliente-row { margin: 5px 0; }
        .cliente-label { font-weight: bold; }

        /* DETALLE */
        .detalle { width: 100%; border-collapse: collapse; padding: 0 15px; }
        .detalle th { border-bottom: 1px solid #000; padding: 10px 5px; text-align: left; font-size: 10px; font-weight: bold; }
        .detalle th.r { text-align: right; }
        .detalle td { padding: 6px 5px; font-size: 10px; vertical-align: top; }
        .detalle td.r { text-align: right; }
        .detalle td.codigo { white-space: nowrap; font-size: 8px; }

        /* ARCA BAR */
        .arca-bar {
            position: absolute;
            bottom: 160px;
            left: 0;
            right: 0;
            background: #555;
            color: #fff;
            padding: 6px 15px;
            font-size: 12px;
        }
        .arca-bar span { font-size: 20px; font-weight: bold; margin-right: 15px; }

        /* FOOTER */
        .footer-section {
            position: absolute;
            bottom: 35px;
            left: 0;
            right: 0;
            height: 120px;
        }
        .footer-table { width: 100%; padding: 5px 15px; }
        .footer-table td { vertical-align: top; padding-top: 10px; }

        .col-importes { width: 25%; font-size: 10px; }
        .col-iva { width: 30%; padding: 0 10px; }
        .col-qr { width: 20%; text-align: right; vertical-align: middle; }
        .col-total { width: 25%; text-align: right; }

        .totales-table td { padding: 2px 0; }
        .totales-label { font-weight: bold; }

        .iva-box { border: 1px solid #000; padding: 5px; font-size: 9px; min-height: 50px; }

        .qr-code { width: 100px; height: 100px; }

        .total-label { font-size: 28px; font-weight: bold; }
        .total-value { font-size: 28px; font-weight: bold; color: #000; }
        .cae-info { font-size: 8px; margin-top: 10px; text-align: right; }

        /* PROVEEDOR */
        .proveedor {
            position: absolute;
            bottom: 5px;
            left: 0;
            right: 0;
            border-top: 1px dashed #ccc;
            padding: 5px 15px;
            font-size: 8px;
        }
        .prov-label { font-weight: bold; }

        /* Header continuación para páginas 2+ */
        .header-cont {
            width: 100%;
            border-bottom: 1px solid #000;
            padding: 10px 15px;
            font-size: 10px;
        }
        .header-cont-left { float: left; }
        .header-cont-right { float: right; text-align: right; }
        .header-cont::after { content: ""; display: table; clear: both; }

        /* Número de página estilo libro */
        .page-number {
            position: absolute;
            bottom: 2px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 9px;
            color: #555;
        }

        /* Mensaje de continúa */
        .continua {
            text-align: center;
            padding: 10px;
            font-size: 9px;
            font-style: italic;
            color: #666;
            border-top: 1px dashed #ccc;
            margin-top: 10px;
        }
    </style>
</head>
<body>';

$logoBase64 = base64_encode(file_get_contents(__DIR__ . '/logo.png'));

// Generar cada página
for ($paginaActual = 0; $paginaActual < $totalPaginas; $paginaActual++) {
    $itemsPagina = $paginas[$paginaActual];
    $pageBreakClass = ($paginaActual > 0) ? ' page-break' : '';

    $html .= '<div class="page' . $pageBreakClass . '"><div class="factura">';

    // Header completo en TODAS las páginas
    $html .= '
            <!-- HEADER COMPLETO -->
            <table class="header" cellspacing="0" cellpadding="0">
                <tr>
                    <td class="header-left">
                        <img src="data:image/png;base64,' . $logoBase64 . '" class="logo-img">
                    </td>
                    <td class="header-center">
                        <div class="letra">' . htmlspecialchars($factura['EFC_ClaseComEfc']) . '</div>
                        <div class="letra-cod">COD. ' . str_pad($factura['TCP_IdTipoComAfip'] ?? '1', 3, '0', STR_PAD_LEFT) . '</div>
                    </td>
                    <td class="header-right">
                        <div class="header-right-title">Retec Consorcio De Cooperación Empresaria y de Exportación</div>
                        <div class="tipo-comp">' . htmlspecialchars(!empty($factura['TCP_DesTipoComp']) ? toUtf8($factura['TCP_DesTipoComp']) : 'Factura de Ventas') . '</div>
                        <div class="header-right-data">
                            <div><b>Punto de Venta:</b> ' . $puestoVenta . ' &nbsp;&nbsp; <b>Comp. Nro:</b> ' . $numComp . '</div>
                            <div><b>Fecha de Emisión:</b> ' . date('d/m/Y H:i', strtotime($factura['EFC_FecEfc'])) . '</div>
                        </div>
                        <div class="empresa-info">
                            <b>CUIT:</b> 30-71205728-5<br>
                            <b>INGRESOS BRUTOS:</b> NO ALCANZADO<br>
                            <b>INICIO ACTIVIDAD:</b> 11/2011
                        </div>
                    </td>
                </tr>
            </table>

            <!-- CLIENTE -->
            <table class="cliente" cellspacing="0" cellpadding="0">
                <tr>
                    <td class="cliente-left">
                        <div class="cliente-row"><span class="cliente-label">Cliente:</span> ' . htmlspecialchars($factura['TER_IDTercero'] . ' ' . toUtf8($factura['ter_razonsocialter'] ?? '')) . '</div>
                        <div class="cliente-row"><span class="cliente-label">Domicilio:</span> ' . htmlspecialchars(toUtf8($factura['TER_DomicilioTer'] ?? '')) . '</div>
                        <div class="cliente-row"><span class="cliente-label">Localidad:</span> ' . htmlspecialchars(toUtf8($factura['LOC_NomLocalidad'] ?? '')) . ' (' . htmlspecialchars($factura['LOC_CodigoPostal'] ?? '') . ')</div>
                    </td>
                    <td class="cliente-right">
                        <div class="cliente-row"><span class="cliente-label">Cond. de Venta:</span> ' . htmlspecialchars(toUtf8($factura['cvt_descripcion'] ?? 'CUENTA CORRIENTE')) . '</div>
                        <div class="cliente-row"><span class="cliente-label">I.V.A.:</span> ' . htmlspecialchars(toUtf8($factura['IVA_NomIVA'] ?? 'Responsable Inscripto')) . '</div>
                        <div class="cliente-row"><span class="cliente-label">C.U.I.T.:</span> ' . htmlspecialchars($factura['TER_CUITTer'] ?? '') . '</div>
                    </td>
                </tr>
            </table>';

    // Detalle de ítems
    $html .= '
            <!-- DETALLE -->
            <table class="detalle" cellspacing="0" cellpadding="0">
                <thead>
                    <tr>
                        <th style="width:10%">Codigo</th>
                        <th style="width:5%" class="r">Cant.</th>
                        <th style="width:41%">Descripcion</th>
                        <th style="width:12%" class="r">Unitario</th>
                        <th style="width:7%" class="r">%IVA</th>
                        <th style="width:7%" class="r">%DESC.</th>
                        <th style="width:7%" class="r">II.II.</th>
                        <th style="width:11%" class="r">TOTAL</th>
                    </tr>
                </thead>
                <tbody>';

    foreach ($itemsPagina as $det) {
        $html .= '
                    <tr>
                        <td class="codigo">' . htmlspecialchars($det['ART_IDArticulo']) . '</td>
                        <td class="r">' . intval($det['DFC_CantArt']) . '</td>
                        <td>' . htmlspecialchars(toUtf8($det['ART_DesArticulo'])) . '</td>
                        <td class="r">' . formatMoney($det['Unitario']) . '</td>
                        <td class="r">' . formatMoney($det['DFC_PorIVARIArt']) . '</td>
                        <td class="r">' . formatMoney($det['DFC_PorcDescArt']) . '</td>
                        <td class="r">' . formatMoney($det['DFC_ImpImpuIntArt'] ?? 0) . '</td>
                        <td class="r">' . formatMoney($det['DFC_ImpTotRen']) . '</td>
                    </tr>';
    }

    $html .= '
                </tbody>
            </table>';

    // Footer completo en TODAS las páginas
    $html .= '
            <!-- ARCA BAR -->
            <div class="arca-bar">
                <span>ARCA</span> <em>Comprobante Autorizado</em>
            </div>

            <!-- FOOTER con 4 columnas -->
            <div class="footer-section">
                <table class="footer-table" cellspacing="0" cellpadding="0">
                    <tr>
                        <!-- Columna 1: Importes detallados -->
                        <td class="col-importes">
                            <table class="totales-table" width="100%">
                                <tr><td class="totales-label">Neto Gravado:</td><td align="right">' . formatMoney($factura['EFC_NetoGravEfc']) . '</td></tr>
                                <tr><td class="totales-label">I.V.A.:</td><td align="right">' . formatMoney($factura['EFC_ImpIVAEfc']) . '</td></tr>
                                <tr><td class="totales-label">Neto No Gravado:</td><td align="right">' . formatMoney($factura['EFC_NetoNoGravEfc']) . '</td></tr>
                                <tr><td class="totales-label">Percepción:</td><td align="right">' . formatMoney($factura['EFC_ImpPercEfc']) . '</td></tr>
                                <tr><td class="totales-label">Imp. Internos:</td><td align="right">' . formatMoney($factura['EFC_ImpImpuIntEfc']) . '</td></tr>
                            </table>
                        </td>

                        <!-- Columna 2: Recuadro IVA -->
                        <td class="col-iva">
                            <div class="iva-box">
                                <b>Totales por alícuotas de I.V.A.</b><br>
                                ' . $ivaHtml . '
                            </div>
                        </td>

                        <!-- Columna 3: QR -->
                        <td class="col-qr">';

    if (!empty($factura['EFC_CAE'])) {
        $html .= '<img src="' . $qrImageUrl . '" class="qr-code">';
    }

    $html .= '
                        </td>

                        <!-- Columna 4: Total + CAE -->
                        <td class="col-total">
                            <span class="total-label">TOTAL:</span> <span class="total-value">' . formatMoney($factura['EFC_ImpTotalEfc']) . '</span>';

    if (!empty($factura['EFC_CAE'])) {
        $html .= '
                            <div class="cae-info">
                                <b>CAE:</b> ' . htmlspecialchars($factura['EFC_CAE']) . '<br>
                                <b>Vto. CAE:</b> ' . ($factura['EFC_CAEVto'] ? date('d/m/Y', strtotime($factura['EFC_CAEVto'])) : '-') .
                                ($totalPaginas > 1 ? '<br>Página ' . ($paginaActual + 1) . ' de ' . $totalPaginas : '') . '
                            </div>';
    }

    $html .= '
                        </td>
                    </tr>
                </table>
            </div>

            <!-- PROVEEDOR -->
            <div class="proveedor">
                <table width="100%">
                    <tr>
                        <td width="45%">
                            <span class="prov-label">Proveedor:</span> ' . htmlspecialchars(toUtf8($factura['Proveedor'] ?? '')) . ' &nbsp;
                            <span class="prov-label">Comp:</span> ' . htmlspecialchars($comprobante) . '
                        </td>
                        <td width="55%" align="right">
                            <span class="prov-label">Cotiz:</span> ' . formatMoney($factura['CotizacionCompra'] ?? 0) . ' |
                            <span class="prov-label">Cond.Vta:</span> ' . htmlspecialchars($factura['CVCompra'] ?? '') . ' |
                            <span class="prov-label">Fecha:</span> ' . (!empty($factura['FechaCompra']) ? date('d/m/y', strtotime($factura['FechaCompra'])) : '') . ' |
                            <span class="prov-label">Vto:</span> ' . (!empty($factura['VencimientoCompra']) ? date('d/m/y', strtotime($factura['VencimientoCompra'])) : '') . '
                        </td>
                    </tr>
                </table>
            </div>';

    $html .= '</div>'; // cierra .factura
    $html .= '</div>'; // cierra .page
}

$html .= '</body></html>';

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);
$options->set('defaultFont', 'Arial');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = sprintf('Factura_%s_%s_%s.pdf', $factura['EFC_ClaseComEfc'], $puestoVenta, $numComp);
$dompdf->stream($filename, ['Attachment' => true]);

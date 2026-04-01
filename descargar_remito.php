<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$codigo = $_GET['id'] ?? '';
if (empty($codigo)) {
    http_response_code(400);
    die('Codigo no proporcionado');
}

$pdo = getConnection();

// Validar el código
$remitoId = validarCodigoRemito($codigo, $pdo);
if ($remitoId === null) {
    http_response_code(403);
    die('Remito no encontrado o codigo invalido');
}

// Obtener encabezado del remito con factura asociada
$stmt = $pdo->prepare("
    SELECT
        ert.ERT_IdErt,
        ert.ERT_FecErt,
        tcp.TCP_DesTipoComp,
        ert.ERT_ClaseComErt,
        ert.ERT_PuestoComErt,
        ert.ERT_NumComErt,
        ert.TER_IDTercero,
        ert.TER_RazonSocialTer,
        ert.TER_DomicilioTer,
        ert.LOC_IDLocalidad,
        ert.LOC_NomLocalidad,
        ert.IVA_NomIVA,
        ert.TER_CUITTer,
        ert.cvt_descripcion,
        ert.ERT_NetoGravErt,
        ert.ERT_ImpIVAErt,
        ert.ERT_ImpTotalErt,
        efc.EFC_FecEfc as factura_fecha,
        efc.EFC_ClaseComEfc as factura_clase,
        efc.EFC_PuestoComEfc as factura_puesto,
        efc.EFC_NumComEfc as factura_numero
    FROM sige_ert_encrto ert
    INNER JOIN sige_tcp_tipocomp tcp ON ert.ERT_TipoErt = tcp.TCP_IDTipoComp
    LEFT JOIN sige_efc_encfac efc ON (ert.ERT_TipoCompVD = efc.EFC_TipoEfc) AND (ert.ERT_IdVieneDe = efc.EFC_IdEfc)
    WHERE ert.ERT_IdErt = ?
");
$stmt->execute([$remitoId]);
$remito = $stmt->fetch();

if (!$remito) {
    http_response_code(404);
    die('Remito no encontrado');
}

// Obtener detalles del remito
$stmt = $pdo->prepare("
    SELECT
        drt.ART_IDArticulo,
        drt.ART_DesArticulo,
        drt.DRT_CantArt,
        (drt.DRT_ImpTotRen / NULLIF(drt.DRT_CantArt, 0)) AS Unitario,
        drt.DRT_ImpTotRen
    FROM sige_drt_detrto drt
    WHERE drt.ERT_IdErt = ?
    ORDER BY drt.DRT_RenglonDrt
");
$stmt->execute([$remitoId]);
$detalles = $stmt->fetchAll();

function formatMoney($val) {
    return number_format(floatval($val), 2, ',', '.');
}

function toUtf8($str) {
    return mb_convert_encoding($str ?? '', 'UTF-8', 'ISO-8859-1');
}

$puestoVenta = str_pad($remito['ERT_PuestoComErt'], 4, '0', STR_PAD_LEFT);
$numComp = str_pad($remito['ERT_NumComErt'], 8, '0', STR_PAD_LEFT);

// Construir string de factura asociada
$facturaAsociada = '';
if (!empty($remito['factura_numero'])) {
    $facPuesto = str_pad($remito['factura_puesto'], 4, '0', STR_PAD_LEFT);
    $facNumero = str_pad($remito['factura_numero'], 8, '0', STR_PAD_LEFT);
    $facFecha = !empty($remito['factura_fecha']) ? date('d/m/Y', strtotime($remito['factura_fecha'])) : '';
    $facturaAsociada = $remito['factura_clase'] . ' ' . $facPuesto . '-' . $facNumero;
    if ($facFecha) {
        $facturaAsociada .= ' (' . $facFecha . ')';
    }
}

// Configuración de paginación - todas las páginas tienen header y footer completo
$ITEMS_POR_PAGINA = 18; // Ítems por página (igual en todas las páginas)

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
        .remito { height: 287mm; position: relative; border: 1px solid #000; }

        /* HEADER */
        .header { width: 100%; border-bottom: 1px solid #000; }
        .header td { vertical-align: top; padding: 15px; }
        .header-left { width: 45%; border-right: 1px solid #000; }
        .header-center { width: 10%; text-align: center; border-right: 1px solid #000; padding: 10px 5px; }
        .header-right { width: 45%; }
        .logo-img { max-width: 100%; max-height: 90px; }
        .letra { font-size: 50px; font-weight: bold; }
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

        /* FOOTER */
        .footer-section { position: absolute; bottom: 15px; left: 0; right: 0; padding: 15px; border-top: 1px solid #000; }
        .footer-table { width: 100%; }
        .footer-table td { vertical-align: middle; }
        .col-importes { width: 50%; font-size: 10px; }
        .col-total { width: 50%; text-align: right; }
        .totales-table td { padding: 2px 0; }
        .totales-label { font-weight: bold; }
        .total-label { font-size: 28px; font-weight: bold; }
        .total-value { font-size: 28px; font-weight: bold; color: #000; }

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

        /* Número de página */
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

    $html .= '<div class="page' . $pageBreakClass . '"><div class="remito">';

    // Header completo en TODAS las páginas
    $html .= '
            <!-- HEADER COMPLETO -->
            <table class="header" cellspacing="0" cellpadding="0">
                <tr>
                    <td class="header-left">
                        <img src="data:image/png;base64,' . $logoBase64 . '" class="logo-img">
                    </td>
                    <td class="header-center">
                        <div class="letra">' . htmlspecialchars($remito['ERT_ClaseComErt']) . '</div>
                    </td>
                    <td class="header-right">
                        <div class="header-right-title">Retec Consorcio De Cooperacion Empresaria y de Exportacion</div>
                        <div class="tipo-comp">' . htmlspecialchars(toUtf8($remito['TCP_DesTipoComp'])) . '</div>
                        <div class="header-right-data">
                            <div><b>Punto de Venta:</b> ' . $puestoVenta . ' &nbsp;&nbsp; <b>Comp. Nro:</b> ' . $numComp . '</div>
                            <div><b>Fecha de Emision:</b> ' . date('d/m/Y H:i', strtotime($remito['ERT_FecErt'])) . '</div>
                            ' . ($facturaAsociada ? '<div><b>Factura Asociada:</b> ' . htmlspecialchars($facturaAsociada) . '</div>' : '') . '
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
                        <div class="cliente-row"><span class="cliente-label">Cliente:</span> ' . htmlspecialchars($remito['TER_IDTercero'] . ' ' . toUtf8($remito['TER_RazonSocialTer'] ?? '')) . '</div>
                        <div class="cliente-row"><span class="cliente-label">Domicilio:</span> ' . htmlspecialchars(toUtf8($remito['TER_DomicilioTer'] ?? '')) . '</div>
                        <div class="cliente-row"><span class="cliente-label">Localidad:</span> ' . htmlspecialchars(toUtf8($remito['LOC_NomLocalidad'] ?? '')) . ' (' . htmlspecialchars($remito['LOC_IDLocalidad'] ?? '') . ')</div>
                    </td>
                    <td class="cliente-right">
                        <div class="cliente-row"><span class="cliente-label">Cond. de Venta:</span> ' . htmlspecialchars(toUtf8($remito['cvt_descripcion'] ?? 'CUENTA CORRIENTE')) . '</div>
                        <div class="cliente-row"><span class="cliente-label">I.V.A.:</span> ' . htmlspecialchars(toUtf8($remito['IVA_NomIVA'] ?? 'Responsable Inscripto')) . '</div>
                        <div class="cliente-row"><span class="cliente-label">C.U.I.T.:</span> ' . htmlspecialchars($remito['TER_CUITTer'] ?? '') . '</div>
                    </td>
                </tr>
            </table>';

    // Detalle de ítems
    $html .= '
            <!-- DETALLE -->
            <table class="detalle" cellspacing="0" cellpadding="0">
                <thead>
                    <tr>
                        <th style="width:15%">Codigo</th>
                        <th style="width:10%" class="r">Cant.</th>
                        <th style="width:45%">Descripcion</th>
                        <th style="width:15%" class="r">Unitario</th>
                        <th style="width:15%" class="r">TOTAL</th>
                    </tr>
                </thead>
                <tbody>';

    foreach ($itemsPagina as $det) {
        $html .= '
                    <tr>
                        <td style="font-size: 9px;">' . htmlspecialchars($det['ART_IDArticulo']) . '</td>
                        <td class="r">' . number_format(floatval($det['DRT_CantArt']), 2, ',', '.') . '</td>
                        <td>' . htmlspecialchars(toUtf8($det['ART_DesArticulo'])) . '</td>
                        <td class="r">' . formatMoney($det['Unitario'] ?? 0) . '</td>
                        <td class="r">' . formatMoney($det['DRT_ImpTotRen']) . '</td>
                    </tr>';
    }

    $html .= '
                </tbody>
            </table>';

    // Footer completo en TODAS las páginas
    $html .= '
            <!-- FOOTER -->
            <div class="footer-section">
                <table class="footer-table" cellspacing="0" cellpadding="0">
                    <tr>
                        <td class="col-importes">
                            <table class="totales-table" width="60%">
                                <tr><td class="totales-label">Neto Gravado:</td><td align="right">' . formatMoney($remito['ERT_NetoGravErt']) . '</td></tr>
                                <tr><td class="totales-label">I.V.A.:</td><td align="right">' . formatMoney($remito['ERT_ImpIVAErt']) . '</td></tr>
                            </table>
                        </td>
                        <td class="col-total">
                            <span class="total-label">TOTAL:</span> <span class="total-value">' . formatMoney($remito['ERT_ImpTotalErt']) . '</span>
                            ' . ($totalPaginas > 1 ? '<div style="font-size: 8px; margin-top: 10px; text-align: right;">Página ' . ($paginaActual + 1) . ' de ' . $totalPaginas . '</div>' : '') . '
                        </td>
                    </tr>
                </table>
            </div>';

    $html .= '</div></div>';
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

$filename = sprintf('Remito_%s_%s_%s.pdf', $remito['ERT_ClaseComErt'], $puestoVenta, $numComp);
$dompdf->stream($filename, ['Attachment' => true]);

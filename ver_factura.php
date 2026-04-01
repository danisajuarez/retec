<?php
require_once 'config.php';

$codigo = $_GET['id'] ?? '';
if (empty($codigo)) {
    die('Código de factura no válido');
}

$pdo = getConnection();

// Validar el código (Base64 de ID + CAE)
$id = validarCodigoFactura($codigo, $pdo);
if (!$id) {
    die('Factura no encontrada o código inválido');
}

$linkDescarga = "descargar_factura.php?id=" . $codigo;

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
$stmt->execute([$id]);
$factura = $stmt->fetch();

if (!$factura) {
    die('Factura no encontrada o no autorizada');
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
$stmt->execute([$id]);
$detalles = $stmt->fetchAll();

// Desglose de IVA
$stmtIva = $pdo->prepare("
    SELECT DFC_PorIVARIArt, SUM(DFC_ImpIVARIArt) AS ImporteIVA
    FROM sige_dfc_detfac
    WHERE EFC_IdEfc = ?
    GROUP BY DFC_PorIVARIArt
    HAVING DFC_PorIVARIArt > 0
");
$stmtIva->execute([$id]);
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Factura <?= htmlspecialchars($factura['EFC_ClaseComEfc']) ?> <?= $puestoVenta ?> <?= $numComp ?></title>
    <link rel="icon" type="image/png" href="logo.png">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 11px; background: #f0f0f0; }
        .page {
            width: 210mm;
            min-height: 297mm;
            margin: 20px auto;
            background: #fff;
            padding: 5mm;
            page-break-after: always;
        }
        .page:last-of-type {
            page-break-after: avoid;
        }
        .factura {
            min-height: calc(297mm - 10mm);
            position: relative;
            border: 1px solid #000;
            padding: 0;
        }

        /* === HEADER === */
        .header {
            display: table;
            width: 100%;
            border-bottom: 1px solid #000;
        }
        .header-left {
            display: table-cell;
            width: 45%;
            vertical-align: top;
            padding: 15px;
            border-right: 1px solid #000;
        }
        .header-center {
            display: table-cell;
            width: 10%;
            text-align: center;
            vertical-align: top;
            border-right: 1px solid #000;
            padding: 10px 5px;
        }
        .header-right {
            display: table-cell;
            width: 45%;
            vertical-align: top;
            padding: 15px;
        }
        .logo-img { max-width: 100%; max-height: 90px; }
        .letra {
            font-size: 50px;
            font-weight: bold;
            display: block;
        }
        .letra-cod {
            font-size: 8px;
            margin-top: 5px;
            display: block;
        }
        .header-right-title {
            font-size: 11px;
            text-align: left;
            margin-bottom: 15px;
        }
        .tipo-comp {
            font-size: 16px;
            font-weight: bold;
            text-align: left;
            margin-bottom: 10px;
            margin-top: 10px;
        }
        .header-right-data {
            font-size: 10px;
            text-align: left;
            border-top: 1px solid #000;
            padding-top: 10px;
            margin-top: 10px;
        }
        .header-right-data div {
            margin: 3px 0;
        }
        .empresa-info {
            font-size: 9px;
            text-align: right;
        }

        /* === CLIENTE === */
        .cliente {
            display: table;
            width: 100%;
            padding: 10px 15px;
            border-bottom: 1px solid #000;
            font-size: 10px;
        }
        .cliente-left, .cliente-right {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }
        .cliente-right {
            text-align: right;
        }
        .cliente-row {
            margin: 5px 0;
            font-size: 10px;
        }
        .cliente-label {
            font-weight: bold;
        }

        /* === DETALLE === */
        .detalle {
            padding: 0 15px;
        }
        .detalle table {
            width: 100%;
            border-collapse: collapse;
        }
        .detalle th {
            border-bottom: 1px solid #000;
            padding: 10px 5px;
            text-align: left;
            font-size: 10px;
            font-weight: bold;
        }
        .detalle th.r { text-align: right; }
        .detalle td {
            padding: 6px 5px;
            font-size: 10px;
            vertical-align: top;
        }
        .detalle td.r { text-align: right; }
        .detalle td.codigo { white-space: nowrap; font-size: 8px; }

        /* === ARCA BAR === */
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
        .arca-bar span {
            font-size: 20px;
            font-weight: bold;
            margin-right: 15px;
        }
        .arca-bar em {
            font-style: italic;
        }

        /* === FOOTER === */
        /* === FOOTER REESTRUCTURADO === */
        .footer-section {
            position: absolute;
            bottom: 35px; /* Subimos un poco para dar espacio al proveedor */
            left: 0;
            right: 0;
            height: 120px;
        }
        .footer {
            display: table;
            width: 100%;
            height: 100%;
            padding: 5px 15px;
            table-layout: fixed; /* Mantiene anchos constantes */
        }
        .footer-col {
            display: table-cell;
            vertical-align: top;
            padding-top: 10px;
        }
        /* Columna 1: Importes detallados */
        .col-importes { 
    width: 25%; /* Bajamos de 30% a 25% */
    font-size: 10px; 
}
        /* Columna 2: Recuadro IVA */
        .col-iva { 
    width: 30%; 
    padding: 0 10px; /* Agregamos aire a los costados */
}
        /* Columna 3: QR */
  .col-qr { 
    width: 20%; 
    text-align: right; 
    vertical-align: middle; 
}
        /* Columna 4: Total Grande */
       .col-total { 
    width: 25%; 
    text-align: right; 
}

   .totales-row {
    display: flex;
    justify-content: space-between; /* Empuja la etiqueta a la izquierda y el número a la derecha */
    align-items: center;
    width: 100%;
    margin-bottom: 4px;
}      .totales-label {
    font-weight: bold;
    text-align: left;
    flex: 1; /* Ocupa el espacio restante */
}
        
        .iva-box {
            border: 1px solid #000;
            padding: 5px;
            font-size: 9px;
            min-height: 50px;
        }
      /* Ajustamos la columna para que alinee el contenido a la derecha */

/* Aplicamos el tamaño de 100px y quitamos márgenes automáticos */
.qr-code { 
    width: 100px; 
    height: 100px; 
    display: inline-block; 
    margin: 0; 
}
        
        .total-label { font-size: 28px; font-weight: bold; }
        .total-value { font-size: 28px; font-weight: bold; color: #000; }
       .totales-value {
    text-align: right;
    min-width: 80px; /* Asegura que todos los números empiecen desde el mismo "margen" imaginario */
    font-family: 'Courier New', monospace; /* Opcional: fuente monoespaciada para que los números alineen mejor */
}
        /* Ajuste para que el proveedor quede bien abajo */
        .proveedor {
            position: absolute;
            bottom: 5px;
            left: 0;
            right: 0;
            border-top: 1px dashed #ccc;
            padding: 5px 15px;
            font-size: 8px;
        }
   
        .proveedor-table {
            display: table;
            width: 100%;
        }
        .proveedor-left, .proveedor-right {
            display: table-cell;
            vertical-align: top;
        }
        .proveedor-left { width: 45%; }
        .proveedor-right { width: 55%; }
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

        /* === PRINT === */
        @media print {
            @page {
                size: A4 portrait;
                margin: 0;
            }
            html, body {
                width: 210mm;
                height: auto;
                margin: 0;
                padding: 0;
                background: #fff;
                overflow: hidden;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                color-adjust: exact !important;
            }
            .page {
                width: 210mm;
                height: 297mm;
                max-height: 297mm;
                margin: 0;
                padding: 5mm;
                page-break-after: always;
                page-break-inside: avoid;
                overflow: hidden;
            }
            .page:last-of-type {
                page-break-after: avoid;
            }
            .factura {
                height: calc(297mm - 10mm);
                max-height: calc(297mm - 10mm);
                overflow: hidden;
            }
            .arca-bar {
                background: #555 !important;
                color: #fff !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .no-print { display: none !important; }
        }
        .btn-container {
            position: fixed;
            top: 10px;
            right: 10px;
            display: flex;
            gap: 10px;
            z-index: 100;
        }
        .btn {
            padding: 10px 20px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
        }
        .btn:hover { background: #218838; }
        .btn-secondary { background: #6c757d; }
        .btn-secondary:hover { background: #5a6268; }
        .btn-link { background: #17a2b8; }
        .btn-link:hover { background: #138496; }
        .btn-download { background: #dc3545; }
        .btn-download:hover { background: #c82333; }
        .toast {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: #333;
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            opacity: 0;
            transition: opacity 0.3s;
        }
        .toast.show { opacity: 1; }
    </style>
</head>
<body>
    <div class="btn-container no-print">
        <a href="dashboard.php" class="btn btn-secondary">Volver</a>
        <button class="btn" onclick="window.print()">Imprimir / PDF</button>
        <a href="<?= htmlspecialchars($linkDescarga) ?>" class="btn btn-download" target="_blank">Descargar PDF</a>
    </div>
    <div id="toast" class="toast no-print">Link copiado al portapapeles</div>

    <?php for ($paginaActual = 0; $paginaActual < $totalPaginas; $paginaActual++):
        $itemsPagina = $paginas[$paginaActual];
    ?>
    <div class="page">
        <div class="factura">
            <!-- HEADER COMPLETO en todas las páginas -->
            <div class="header">
                <div class="header-left">
                    <img src="logo.png" class="logo-img">
                </div>
                <div class="header-center">
                    <div class="letra"><?= htmlspecialchars($factura['EFC_ClaseComEfc']) ?></div>
                    <div class="letra-cod">COD. <?= str_pad($factura['TCP_IdTipoComAfip'] ?? '1', 3, '0', STR_PAD_LEFT) ?></div>
                </div>
                <div class="header-right">
                    <div class="header-right-title">Retec Consorcio De Cooperación Empresaria y de Exportación</div>
                    <div class="tipo-comp"><?= htmlspecialchars(!empty($factura['TCP_DesTipoComp']) ? toUtf8($factura['TCP_DesTipoComp']) : 'Factura de Ventas') ?></div>
                    <div class="header-right-data">
                        <div><b>Punto de Venta:</b> <?= $puestoVenta ?> &nbsp;&nbsp; <b>Comp. Nro:</b> <?= $numComp ?></div>
                        <div><b>Fecha de Emisión:</b> <?= date('d/m/Y H:i', strtotime($factura['EFC_FecEfc'])) ?></div>
                    </div>
                    <div class="empresa-info">
                        <b>CUIT:</b> 30-71205728-5<br>
                        <b>INGRESOS BRUTOS:</b> NO ALCANZADO<br>
                        <b>INICIO ACTIVIDAD:</b> 11/2011
                    </div>
                </div>
            </div>

            <!-- CLIENTE -->
            <div class="cliente">
                <div class="cliente-left">
                    <div class="cliente-row"><span class="cliente-label">Cliente:</span> <?= htmlspecialchars($factura['TER_IDTercero'] . ' ' . toUtf8($factura['ter_razonsocialter'] ?? '')) ?></div>
                    <div class="cliente-row"><span class="cliente-label">Domicilio:</span> <?= htmlspecialchars(toUtf8($factura['TER_DomicilioTer'] ?? '')) ?></div>
                    <div class="cliente-row"><span class="cliente-label">Localidad:</span> <?= htmlspecialchars(toUtf8($factura['LOC_NomLocalidad'] ?? '')) ?> (<?= htmlspecialchars($factura['LOC_CodigoPostal'] ?? '') ?>)</div>
                </div>
                <div class="cliente-right">
                    <div class="cliente-row"><span class="cliente-label">Cond. de Venta:</span> <?= htmlspecialchars(toUtf8($factura['cvt_descripcion'] ?? 'CUENTA CORRIENTE')) ?></div>
                    <div class="cliente-row"><span class="cliente-label">I.V.A.:</span> <?= htmlspecialchars(toUtf8($factura['IVA_NomIVA'] ?? 'Responsable Inscripto')) ?></div>
                    <div class="cliente-row"><span class="cliente-label">C.U.I.T.:</span> <?= htmlspecialchars($factura['TER_CUITTer'] ?? '') ?></div>
                </div>
            </div>

            <!-- DETALLE -->
            <div class="detalle">
                <table>
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
                    <tbody>
                        <?php foreach ($itemsPagina as $det): ?>
                        <tr>
                            <td class="codigo"><?= htmlspecialchars($det['ART_IDArticulo']) ?></td>
                            <td class="r"><?= intval($det['DFC_CantArt']) ?></td>
                            <td><?= htmlspecialchars(toUtf8($det['ART_DesArticulo'])) ?></td>
                            <td class="r"><?= formatMoney($det['Unitario']) ?></td>
                            <td class="r"><?= formatMoney($det['DFC_PorIVARIArt']) ?></td>
                            <td class="r"><?= formatMoney($det['DFC_PorcDescArt']) ?></td>
                            <td class="r"><?= formatMoney($det['DFC_ImpImpuIntArt'] ?? 0) ?></td>
                            <td class="r"><?= formatMoney($det['DFC_ImpTotRen']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- ARCA BAR - en todas las páginas -->
            <div class="arca-bar">
                <span>ARCA</span> <em>Comprobante Autorizado</em>
            </div>

            <!-- FOOTER - en todas las páginas -->
            <div class="footer-section">
                <div class="footer">
                    <div class="footer-col col-importes">
                        <div class="totales-row">
                            <span class="totales-label">Neto Gravado:</span>
                            <span><?= formatMoney($factura['EFC_NetoGravEfc']) ?></span>
                        </div>
                        <div class="totales-row">
                            <span class="totales-label">I.V.A.:</span>
                            <span><?= formatMoney($factura['EFC_ImpIVAEfc']) ?></span>
                        </div>
                        <div class="totales-row">
                            <span class="totales-label">Neto No Gravado:</span>
                            <span><?= formatMoney($factura['EFC_NetoNoGravEfc']) ?></span>
                        </div>
                        <div class="totales-row">
                            <span class="totales-label">Percepción:</span>
                            <span><?= formatMoney($factura['EFC_ImpPercEfc']) ?></span>
                        </div>
                        <div class="totales-row">
                            <span class="totales-label">Imp. Internos:</span>
                            <span><?= formatMoney($factura['EFC_ImpImpuIntEfc']) ?></span>
                        </div>
                    </div>

                    <div class="footer-col col-iva">
                        <div class="iva-box">
                            <b>Totales por alícuotas de I.V.A.</b><br>
                            <?php foreach ($desgloseIva as $iva): ?>
                                IVA <?= formatMoney($iva['DFC_PorIVARIArt']) ?>%: <?= formatMoney($iva['ImporteIVA']) ?><br>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="footer-col col-qr">
                        <?php if (!empty($factura['EFC_CAE'])): ?>
                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?= urlencode($qrUrl) ?>" alt="QR AFIP" class="qr-code">
                        <?php endif; ?>
                    </div>

                    <div class="footer-col col-total">
                        <div class="total-container">
                            <span class="total-label">TOTAL:</span>
                            <span class="total-value"><?= formatMoney($factura['EFC_ImpTotalEfc']) ?></span>
                        </div>
                        <?php if (!empty($factura['EFC_CAE'])): ?>
                        <div class="cae-info" style="font-size: 8px; margin-top: 10px; text-align: right;">
                            <b>CAE:</b> <?= htmlspecialchars($factura['EFC_CAE']) ?><br>
                            <b>Vto. CAE:</b> <?= $factura['EFC_CAEVto'] ? date('d/m/Y', strtotime($factura['EFC_CAEVto'])) : '-' ?>
                            <?php if ($totalPaginas > 1): ?><br>Página <?= $paginaActual + 1 ?> de <?= $totalPaginas ?><?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="proveedor">
                <div class="proveedor-table">
                    <div class="proveedor-left">
                        <span class="prov-label">Proveedor:</span> <?= htmlspecialchars(toUtf8($factura['Proveedor'] ?? '')) ?> &nbsp;
                        <span class="prov-label">Comp:</span> <?= !empty($factura['ClaseCompra']) ? htmlspecialchars($factura['ClaseCompra'] . ' ' . str_pad($factura['PuestoCompra'] ?? '', 4, '0', STR_PAD_LEFT) . ' ' . str_pad($factura['NumeroCompra'] ?? '', 8, '0', STR_PAD_LEFT)) : '' ?>
                    </div>
                    <div class="proveedor-right" style="text-align: right;">
                        <span class="prov-label">Cotiz:</span> <?= formatMoney($factura['CotizacionCompra'] ?? 0) ?> |
                        <span class="prov-label">Cond.Vta:</span> <?= htmlspecialchars($factura['CVCompra'] ?? '') ?> |
                        <span class="prov-label">Fecha:</span> <?= !empty($factura['FechaCompra']) ? date('d/m/y', strtotime($factura['FechaCompra'])) : '' ?> |
                        <span class="prov-label">Vto:</span> <?= !empty($factura['VencimientoCompra']) ? date('d/m/y', strtotime($factura['VencimientoCompra'])) : '' ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endfor; ?>

    <script>
    function copiarLink() {
        const link = '<?= addslashes($linkDescarga) ?>';
        navigator.clipboard?.writeText(link).then(mostrarToast).catch(() => {
            const ta = document.createElement('textarea');
            ta.value = link;
            ta.style.cssText = 'position:fixed;opacity:0';
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
            mostrarToast();
        });
    }
    function mostrarToast() {
        const t = document.getElementById('toast');
        t.classList.add('show');
        setTimeout(() => t.classList.remove('show'), 2000);
    }
    </script>
</body>
</html>

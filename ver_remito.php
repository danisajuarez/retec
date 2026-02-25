<?php
require_once 'config.php';

$codigo = $_GET['id'] ?? '';
if (empty($codigo)) {
    die('Codigo de remito no valido');
}

$pdo = getConnection();

// Validar el código
$id = validarCodigoRemito($codigo, $pdo);
if (!$id) {
    die('Remito no encontrado o codigo invalido');
}

$linkDescarga = "descargar_remito.php?id=" . $codigo;

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
$stmt->execute([$id]);
$remito = $stmt->fetch();

if (!$remito) {
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
$stmt->execute([$id]);
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Remito <?= htmlspecialchars($remito['ERT_ClaseComErt']) ?> <?= $puestoVenta ?> <?= $numComp ?></title>
    <link rel="icon" type="image/png" href="logo.png">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 11px; background: #f0f0f0; }
        .page {
            width: 210mm;
            height: 297mm;
            margin: 0 auto;
            background: #fff;
            padding: 5mm;
        }
        .remito {
            height: 100%;
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

        /* === FOOTER === */
        .footer-section {
            position: absolute;
            bottom: 15px;
            left: 0;
            right: 0;
            padding: 15px;
            border-top: 1px solid #000;
        }
        .footer {
            display: table;
            width: 100%;
        }
        .footer-col {
            display: table-cell;
            vertical-align: middle;
        }
        .col-importes {
            width: 50%;
            font-size: 10px;
        }
        .col-total {
            width: 50%;
            text-align: right;
        }
        .totales-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 60%;
            margin-bottom: 4px;
        }
        .totales-label {
            font-weight: bold;
            text-align: left;
        }
        .total-label { font-size: 28px; font-weight: bold; }
        .total-value { font-size: 28px; font-weight: bold; color: #000; }

        /* === PRINT === */
        @media print {
            @page {
                size: A4 portrait;
                margin: 0;
            }
            html, body {
                width: 210mm;
                height: 297mm;
                margin: 0;
                padding: 0;
                background: #fff;
            }
            .page {
                width: 210mm;
                height: 297mm;
                margin: 0;
                padding: 5mm;
                page-break-after: avoid;
            }
            .remito {
                height: calc(297mm - 10mm);
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
        .btn-download { background: #dc3545; }
        .btn-download:hover { background: #c82333; }
    </style>
</head>
<body>
    <div class="btn-container no-print">
        <a href="remitos.php" class="btn btn-secondary">Volver</a>
        <button class="btn" onclick="window.print()">Imprimir / PDF</button>
        <a href="<?= htmlspecialchars($linkDescarga) ?>" class="btn btn-download" target="_blank">Descargar PDF</a>
    </div>

    <div class="page">
        <div class="remito">
            <!-- HEADER -->
            <div class="header">
                <div class="header-left">
                    <img src="logo.png" class="logo-img">
                </div>
                <div class="header-center">
                    <div class="letra"><?= htmlspecialchars($remito['ERT_ClaseComErt']) ?></div>
                </div>
                <div class="header-right">
                    <div class="header-right-title">Retec Consorcio De Cooperacion Empresaria y de Exportacion</div>
                    <div class="tipo-comp"><?= htmlspecialchars(toUtf8($remito['TCP_DesTipoComp'])) ?></div>
                    <div class="header-right-data">
                        <div><b>Punto de Venta:</b> <?= $puestoVenta ?> &nbsp;&nbsp; <b>Comp. Nro:</b> <?= $numComp ?></div>
                        <div><b>Fecha de Emision:</b> <?= date('d/m/Y H:i', strtotime($remito['ERT_FecErt'])) ?></div>
                        <?php if ($facturaAsociada): ?>
                        <div><b>Factura Asociada:</b> <?= htmlspecialchars($facturaAsociada) ?></div>
                        <?php endif; ?>
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
                    <div class="cliente-row"><span class="cliente-label">Cliente:</span> <?= htmlspecialchars($remito['TER_IDTercero'] . ' ' . toUtf8($remito['TER_RazonSocialTer'] ?? '')) ?></div>
                    <div class="cliente-row"><span class="cliente-label">Domicilio:</span> <?= htmlspecialchars(toUtf8($remito['TER_DomicilioTer'] ?? '')) ?></div>
                    <div class="cliente-row"><span class="cliente-label">Localidad:</span> <?= htmlspecialchars(toUtf8($remito['LOC_NomLocalidad'] ?? '')) ?> (<?= htmlspecialchars($remito['LOC_IDLocalidad'] ?? '') ?>)</div>
                </div>
                <div class="cliente-right">
                    <div class="cliente-row"><span class="cliente-label">Cond. de Venta:</span> <?= htmlspecialchars(toUtf8($remito['cvt_descripcion'] ?? 'CUENTA CORRIENTE')) ?></div>
                    <div class="cliente-row"><span class="cliente-label">I.V.A.:</span> <?= htmlspecialchars(toUtf8($remito['IVA_NomIVA'] ?? 'Responsable Inscripto')) ?></div>
                    <div class="cliente-row"><span class="cliente-label">C.U.I.T.:</span> <?= htmlspecialchars($remito['TER_CUITTer'] ?? '') ?></div>
                </div>
            </div>

            <!-- DETALLE -->
            <div class="detalle">
                <table>
                    <thead>
                        <tr>
                            <th style="width:15%">Codigo</th>
                            <th style="width:10%" class="r">Cant.</th>
                            <th style="width:45%">Descripcion</th>
                            <th style="width:15%" class="r">Unitario</th>
                            <th style="width:15%" class="r">TOTAL</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($detalles as $det): ?>
                        <tr>
                            <td style="font-size: 9px;"><?= htmlspecialchars($det['ART_IDArticulo']) ?></td>
                            <td class="r"><?= number_format(floatval($det['DRT_CantArt']), 2, ',', '.') ?></td>
                            <td><?= htmlspecialchars(toUtf8($det['ART_DesArticulo'])) ?></td>
                            <td class="r"><?= formatMoney($det['Unitario'] ?? 0) ?></td>
                            <td class="r"><?= formatMoney($det['DRT_ImpTotRen']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- FOOTER -->
            <div class="footer-section">
                <div class="footer">
                    <div class="footer-col col-importes">
                        <div class="totales-row">
                            <span class="totales-label">Neto Gravado:</span>
                            <span><?= formatMoney($remito['ERT_NetoGravErt']) ?></span>
                        </div>
                        <div class="totales-row">
                            <span class="totales-label">I.V.A.:</span>
                            <span><?= formatMoney($remito['ERT_ImpIVAErt']) ?></span>
                        </div>
                    </div>
                    <div class="footer-col col-total">
                        <span class="total-label">TOTAL:</span>
                        <span class="total-value"><?= formatMoney($remito['ERT_ImpTotalErt']) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

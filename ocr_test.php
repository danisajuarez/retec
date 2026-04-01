<?php
/**
 * Página de prueba OCR con Tesseract
 * Sistema RETEC
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OCR Facturas - RETEC</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .resultado-ocr {
            background: #1e1e1e;
            color: #d4d4d4;
            border-radius: 8px;
            padding: 20px;
            max-height: 300px;
            overflow-y: auto;
            font-family: 'Consolas', monospace;
            font-size: 12px;
            white-space: pre-wrap;
        }
        .dato-extraido {
            background: #e7f3ff;
            border-left: 4px solid #0066cc;
            padding: 10px 15px;
            margin: 5px 0;
            border-radius: 0 4px 4px 0;
        }
        .dato-extraido.success {
            background: #d4edda;
            border-left-color: #28a745;
        }
        .loading {
            display: none;
        }
        .loading.active {
            display: block;
        }
        .drop-zone {
            border: 3px dashed #dee2e6;
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        .drop-zone:hover, .drop-zone.dragover {
            border-color: #0d6efd;
            background: #f8f9ff;
        }
        .drop-zone i {
            font-size: 48px;
            color: #6c757d;
        }
        .preview-img {
            max-width: 100%;
            max-height: 200px;
            border-radius: 8px;
            margin-top: 15px;
        }
        .total-grande {
            font-size: 2rem;
            font-weight: bold;
            color: #198754;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <!-- Header -->
                <div class="d-flex align-items-center mb-4">
                    <img src="logo.png" alt="RETEC" height="50" class="me-3">
                    <div>
                        <h4 class="mb-0">Lector OCR de Facturas</h4>
                        <small class="text-muted">Extrae datos automaticamente de facturas escaneadas</small>
                    </div>
                </div>

                <div class="row">
                    <!-- Panel izquierdo: Subir archivo -->
                    <div class="col-md-5">
                        <div class="card shadow-sm h-100">
                            <div class="card-header bg-primary text-white">
                                <i class="bi bi-upload me-2"></i>Subir Factura
                            </div>
                            <div class="card-body">
                                <form id="formOCR" enctype="multipart/form-data">
                                    <div class="drop-zone" id="dropZone">
                                        <i class="bi bi-cloud-arrow-up"></i>
                                        <p class="mt-2 mb-0">Arrastra una imagen aqui</p>
                                        <small class="text-muted">o click para seleccionar</small>
                                        <input type="file" class="d-none" id="archivo" name="archivo"
                                               accept="image/jpeg,image/png,image/gif,image/bmp">
                                        <img id="preview" class="preview-img d-none">
                                    </div>
                                    <div class="mt-3">
                                        <small class="text-muted">
                                            <i class="bi bi-info-circle"></i>
                                            Formatos: JPG, PNG, BMP, GIF
                                        </small>
                                    </div>
                                    <button type="submit" class="btn btn-primary w-100 mt-3" id="btnProcesar" disabled>
                                        <i class="bi bi-cpu me-2"></i>Procesar con OCR
                                    </button>
                                </form>

                                <hr>

                                <button type="button" class="btn btn-outline-secondary w-100" id="btnProbarLocal">
                                    <i class="bi bi-file-earmark-text me-2"></i>Probar con factura de ejemplo
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Panel derecho: Resultados -->
                    <div class="col-md-7">
                        <div class="card shadow-sm h-100">
                            <div class="card-header bg-success text-white">
                                <i class="bi bi-card-checklist me-2"></i>Datos Extraidos
                            </div>
                            <div class="card-body">
                                <!-- Loading -->
                                <div class="loading text-center py-5" id="loading">
                                    <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                                        <span class="visually-hidden">Procesando...</span>
                                    </div>
                                    <p class="mt-3 text-muted">Analizando imagen con Tesseract OCR...</p>
                                    <small class="text-muted">Esto puede tardar unos segundos</small>
                                </div>

                                <!-- Placeholder inicial -->
                                <div id="placeholder" class="text-center py-5 text-muted">
                                    <i class="bi bi-file-earmark-image" style="font-size: 48px;"></i>
                                    <p class="mt-3">Subi una factura para extraer los datos</p>
                                </div>

                                <!-- Resultados -->
                                <div id="resultados" style="display: none;">
                                    <div class="row">
                                        <div class="col-6">
                                            <h6><i class="bi bi-receipt me-2"></i>Comprobante</h6>
                                            <div id="datosComprobante"></div>
                                        </div>
                                        <div class="col-6">
                                            <h6><i class="bi bi-building me-2"></i>Datos Fiscales</h6>
                                            <div id="datosFiscales"></div>
                                        </div>
                                    </div>

                                    <div class="row mt-3">
                                        <div class="col-6">
                                            <h6><i class="bi bi-calculator me-2"></i>Importes</h6>
                                            <div id="datosImportes"></div>
                                        </div>
                                        <div class="col-6">
                                            <div class="card bg-light mt-4">
                                                <div class="card-body text-center">
                                                    <small class="text-muted">TOTAL</small>
                                                    <div class="total-grande" id="totalGrande">$0,00</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <hr>

                                    <div class="d-flex gap-2 mb-3">
                                        <button type="button" class="btn btn-outline-primary" id="btnDescargarTxt">
                                            <i class="bi bi-download me-2"></i>Descargar TXT
                                        </button>
                                    </div>

                                    <details>
                                        <summary class="text-muted" style="cursor: pointer;">
                                            <i class="bi bi-code-slash me-2"></i>Ver texto completo extraido
                                        </summary>
                                        <div class="resultado-ocr mt-2" id="textoCompleto"></div>
                                    </details>
                                </div>

                                <!-- Error -->
                                <div class="alert alert-danger mt-4" id="error" style="display: none;">
                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                    <span id="errorText"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Info -->
                <div class="card mt-4 border-info">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-auto">
                                <i class="bi bi-lightbulb text-info" style="font-size: 24px;"></i>
                            </div>
                            <div class="col">
                                <strong>Motor OCR:</strong> Tesseract v5.5 (local, gratuito)
                                <br>
                                <small class="text-muted">
                                    El procesamiento se realiza en el servidor, sin enviar datos a servicios externos.
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('archivo');
        const preview = document.getElementById('preview');
        const btnProcesar = document.getElementById('btnProcesar');

        // Drag and drop
        dropZone.addEventListener('click', () => fileInput.click());

        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('dragover');
        });

        dropZone.addEventListener('dragleave', () => {
            dropZone.classList.remove('dragover');
        });

        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('dragover');
            if (e.dataTransfer.files.length) {
                fileInput.files = e.dataTransfer.files;
                mostrarPreview(e.dataTransfer.files[0]);
            }
        });

        fileInput.addEventListener('change', () => {
            if (fileInput.files.length) {
                mostrarPreview(fileInput.files[0]);
            }
        });

        function mostrarPreview(file) {
            const reader = new FileReader();
            reader.onload = (e) => {
                preview.src = e.target.result;
                preview.classList.remove('d-none');
                dropZone.querySelector('i').classList.add('d-none');
                dropZone.querySelector('p').classList.add('d-none');
                dropZone.querySelector('small').classList.add('d-none');
                btnProcesar.disabled = false;
            };
            reader.readAsDataURL(file);
        }

        document.getElementById('formOCR').addEventListener('submit', async function(e) {
            e.preventDefault();
            if (!fileInput.files.length) return;
            procesarArchivo(fileInput.files[0]);
        });

        document.getElementById('btnProbarLocal').addEventListener('click', async function() {
            document.getElementById('loading').classList.add('active');
            document.getElementById('resultados').style.display = 'none';
            document.getElementById('placeholder').style.display = 'none';
            document.getElementById('error').style.display = 'none';

            try {
                const response = await fetch('api/ocr_tesseract.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ archivo_path: 'Factura_A_0003_00155708_page-0001.jpg' })
                });

                const data = await response.json();
                mostrarResultados(data);
            } catch (error) {
                document.getElementById('errorText').textContent = error.message;
                document.getElementById('error').style.display = 'block';
            } finally {
                document.getElementById('loading').classList.remove('active');
            }
        });

        async function procesarArchivo(file) {
            document.getElementById('loading').classList.add('active');
            document.getElementById('resultados').style.display = 'none';
            document.getElementById('placeholder').style.display = 'none';
            document.getElementById('error').style.display = 'none';

            const formData = new FormData();
            formData.append('archivo', file);

            try {
                const response = await fetch('api/ocr_tesseract.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();
                mostrarResultados(data);
            } catch (error) {
                document.getElementById('errorText').textContent = error.message;
                document.getElementById('error').style.display = 'block';
            } finally {
                document.getElementById('loading').classList.remove('active');
            }
        }

        function formatMoney(value) {
            if (!value) return '-';
            const num = parseFloat(value);
            return '$' + num.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function mostrarResultados(data) {
            if (data.error) {
                document.getElementById('errorText').textContent = data.error;
                document.getElementById('error').style.display = 'block';
                return;
            }

            const d = data.datos_factura;

            // Datos del comprobante
            let html = '';
            if (d.tipo_comprobante) html += `<div class="dato-extraido success"><strong>Tipo:</strong> ${d.tipo_comprobante}</div>`;
            if (d.punto_venta || d.numero) html += `<div class="dato-extraido"><strong>Nro:</strong> ${d.punto_venta || '?'}-${d.numero || '?'}</div>`;
            if (d.fecha) html += `<div class="dato-extraido"><strong>Fecha:</strong> ${d.fecha}</div>`;
            document.getElementById('datosComprobante').innerHTML = html || '<p class="text-muted small">No detectado</p>';

            // Datos fiscales
            html = '';
            if (d.cuit_emisor) html += `<div class="dato-extraido"><strong>CUIT Emisor:</strong> ${d.cuit_emisor}</div>`;
            if (d.cuit_receptor) html += `<div class="dato-extraido"><strong>CUIT Cliente:</strong> ${d.cuit_receptor}</div>`;
            if (d.cae) html += `<div class="dato-extraido success"><strong>CAE:</strong> ${d.cae}</div>`;
            if (d.vencimiento_cae) html += `<div class="dato-extraido"><strong>Vto CAE:</strong> ${d.vencimiento_cae}</div>`;
            document.getElementById('datosFiscales').innerHTML = html || '<p class="text-muted small">No detectado</p>';

            // Importes
            html = '';
            if (d.neto_gravado) html += `<div class="dato-extraido"><strong>Neto:</strong> ${formatMoney(d.neto_gravado)}</div>`;
            if (d.iva) html += `<div class="dato-extraido"><strong>IVA:</strong> ${formatMoney(d.iva)}</div>`;
            document.getElementById('datosImportes').innerHTML = html || '<p class="text-muted small">No detectado</p>';

            // Total grande
            document.getElementById('totalGrande').textContent = formatMoney(d.total);

            // Texto completo
            document.getElementById('textoCompleto').textContent = data.texto_completo || 'Sin texto';

            document.getElementById('resultados').style.display = 'block';
        }

        // Variable para guardar los últimos datos
        let ultimosDatos = null;

        // Modificar mostrarResultados para guardar los datos
        const originalMostrarResultados = mostrarResultados;
        mostrarResultados = function(data) {
            ultimosDatos = data;
            originalMostrarResultados(data);
        };

        // Descargar TXT estructurado
        document.getElementById('btnDescargarTxt').addEventListener('click', function() {
            if (!ultimosDatos || !ultimosDatos.datos_factura) {
                alert('No hay datos para descargar');
                return;
            }

            const d = ultimosDatos.datos_factura;
            const fechaArchivo = new Date().toISOString().slice(0, 10);

            let txt = '========================================\n';
            txt += '       DATOS DE FACTURA - OCR RETEC\n';
            txt += '========================================\n';
            txt += 'Fecha de procesamiento: ' + new Date().toLocaleString('es-AR') + '\n';
            txt += '----------------------------------------\n\n';

            txt += '[COMPROBANTE]\n';
            txt += 'Tipo................: ' + (d.tipo_comprobante || 'No detectado') + '\n';
            txt += 'Punto de Venta......: ' + (d.punto_venta || 'No detectado') + '\n';
            txt += 'Numero..............: ' + (d.numero || 'No detectado') + '\n';
            txt += 'Fecha Emision.......: ' + (d.fecha || 'No detectado') + '\n';
            txt += '\n';

            txt += '[DATOS FISCALES]\n';
            txt += 'CUIT Emisor.........: ' + (d.cuit_emisor || 'No detectado') + '\n';
            txt += 'Razon Social Emisor.: ' + (d.razon_social_emisor || 'No detectado') + '\n';
            txt += 'CUIT Receptor.......: ' + (d.cuit_receptor || 'No detectado') + '\n';
            txt += 'Razon Social Client.: ' + (d.razon_social_receptor || 'No detectado') + '\n';
            txt += 'CAE.................: ' + (d.cae || 'No detectado') + '\n';
            txt += 'Vencimiento CAE.....: ' + (d.vencimiento_cae || 'No detectado') + '\n';
            txt += '\n';

            txt += '[IMPORTES]\n';
            txt += 'Neto Gravado........: ' + (d.neto_gravado ? '$' + parseFloat(d.neto_gravado).toLocaleString('es-AR', {minimumFractionDigits: 2}) : 'No detectado') + '\n';
            txt += 'IVA.................: ' + (d.iva ? '$' + parseFloat(d.iva).toLocaleString('es-AR', {minimumFractionDigits: 2}) : 'No detectado') + '\n';
            txt += 'TOTAL...............: ' + (d.total ? '$' + parseFloat(d.total).toLocaleString('es-AR', {minimumFractionDigits: 2}) : 'No detectado') + '\n';
            txt += '\n';

            txt += '========================================\n';
            txt += '            TEXTO OCR COMPLETO\n';
            txt += '========================================\n';
            txt += ultimosDatos.texto_completo || 'Sin texto';
            txt += '\n\n';
            txt += '--- Fin del archivo ---\n';

            // Crear y descargar archivo
            const blob = new Blob([txt], { type: 'text/plain;charset=utf-8' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'factura_' + (d.tipo_comprobante || 'X').replace(/\s+/g, '_') + '_' + (d.punto_venta || '0000') + '_' + (d.numero || '00000000') + '.txt';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        });
    </script>
</body>
</html>

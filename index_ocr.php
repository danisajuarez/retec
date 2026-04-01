<?php
/**
 * Escanear Factura con OCR - Página Pública
 * Sistema de Facturación RETEC
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Escanear Factura - OCR</title>
    <link rel="icon" type="image/png" href="logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            padding: 30px 15px;
        }

        .main-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header-section {
            text-align: center;
            margin-bottom: 30px;
            color: #fff;
        }

        .header-section h1 {
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .header-section p {
            color: rgba(255,255,255,0.7);
            margin: 0;
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        }

        .card-header {
            background: #fff;
            border-bottom: 1px solid #eee;
            padding: 20px 25px;
            border-radius: 12px 12px 0 0 !important;
        }

        .card-header h5 {
            margin: 0;
            font-weight: 600;
            color: #333;
        }

        .card-body {
            padding: 25px;
        }

        /* Upload Area */
        .upload-area {
            border: 2px dashed #dee2e6;
            border-radius: 12px;
            padding: 40px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: #fafafa;
        }

        .upload-area:hover,
        .upload-area.dragover {
            border-color: #28a745;
            background: #f0fff4;
        }

        .upload-area i {
            font-size: 3rem;
            color: #28a745;
            margin-bottom: 15px;
        }

        .upload-area p {
            margin: 0;
            color: #666;
        }

        .upload-area .formats {
            font-size: 0.85rem;
            color: #999;
            margin-top: 10px;
        }

        /* Preview */
        .preview-container {
            max-height: 400px;
            overflow: hidden;
            border-radius: 8px;
            margin-top: 20px;
            display: none;
            background: #f8f9fa;
            padding: 20px;
            text-align: center;
        }

        .preview-container img {
            max-width: 100%;
            max-height: 350px;
            object-fit: contain;
        }

        /* Results */
        .resultado-ocr {
            display: none;
        }

        .dato-factura {
            padding: 12px 15px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .dato-factura .label {
            font-weight: 600;
            color: #333;
            font-size: 0.9rem;
        }

        .dato-factura .value {
            color: #28a745;
            font-weight: 500;
            font-size: 1.1rem;
        }

        .dato-factura .value.total {
            font-size: 1.3rem;
            font-weight: 700;
        }

        .btn-copy {
            padding: 2px 8px;
            font-size: 0.8rem;
        }

        /* Loading */
        .processing {
            display: none;
            text-align: center;
            padding: 40px;
        }

        .processing .spinner-border {
            width: 3rem;
            height: 3rem;
        }

        .btn-success {
            background-color: #28a745;
            border-color: #28a745;
        }

        .btn-success:hover {
            background-color: #218838;
            border-color: #1e7e34;
        }

        .footer-text {
            text-align: center;
            margin-top: 30px;
            color: rgba(255,255,255,0.5);
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- Header -->
        <div class="header-section">
            <i class="bi bi-upc-scan" style="font-size: 3rem; color: #28a745;"></i>
            <h1>Escanear Factura</h1>
            <p>Sube una imagen o PDF de factura para extraer los datos automaticamente</p>

        </div>

        <div class="row">
            <!-- Upload Section -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5><i class="bi bi-cloud-upload me-2"></i>Subir Factura</h5>
                    </div>
                    <div class="card-body">
                        <div class="upload-area" id="uploadArea">
                            <i class="bi bi-file-earmark-image"></i>
                            <p>Arrastra una imagen o PDF aqui o haz clic para seleccionar</p>
                            <p class="formats">Formatos: JPG, PNG, GIF, BMP, PDF</p>
                        </div>
                        <input type="file" id="fileInput" accept="image/*,.pdf,application/pdf" style="display: none;">

                        <div class="preview-container" id="previewContainer">
                            <img id="previewImage" src="" alt="Vista previa">
                        </div>

                        <div class="d-grid gap-2 mt-3">
                            <button type="button" id="btnProcesar" class="btn btn-success btn-lg" disabled>
                                <i class="bi bi-cpu me-2"></i>Procesar con OCR
                            </button>
                            <button type="button" id="btnLimpiar" class="btn btn-outline-secondary" style="display: none;">
                                <i class="bi bi-x-circle me-2"></i>Limpiar
                            </button>
                        </div>

                        <div class="processing" id="processing">
                            <div class="spinner-border text-success" role="status"></div>
                            <p class="mt-3 mb-0">Procesando imagen con OCR...</p>
                            <p class="text-muted small">Esto puede tardar unos segundos</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Results Section -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="bi bi-file-text me-2"></i>Datos Extraidos</h5>
                        <button type="button" id="btnCopiarTodo" class="btn btn-sm btn-outline-success" style="display: none;">
                            <i class="bi bi-clipboard me-1"></i>Copiar Todo
                        </button>
                    </div>
                    <div class="card-body">
                        <div id="sinResultados" class="text-center text-muted py-5">
                            <i class="bi bi-file-earmark-text" style="font-size: 3rem;"></i>
                            <p class="mt-3">Sube una imagen de factura para extraer los datos</p>
                        </div>

                        <div class="resultado-ocr" id="resultadoOCR">
                            <div class="dato-factura">
                                <div>
                                    <span class="label">Tipo Comprobante</span>
                                </div>
                                <div>
                                    <span class="value" id="tipoComprobante">-</span>
                                    <button class="btn btn-outline-secondary btn-copy ms-2" onclick="copiar('tipoComprobante')">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="dato-factura">
                                <div>
                                    <span class="label">Punto de Venta</span>
                                </div>
                                <div>
                                    <span class="value" id="puntoVenta">-</span>
                                    <button class="btn btn-outline-secondary btn-copy ms-2" onclick="copiar('puntoVenta')">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="dato-factura">
                                <div>
                                    <span class="label">Numero</span>
                                </div>
                                <div>
                                    <span class="value" id="numero">-</span>
                                    <button class="btn btn-outline-secondary btn-copy ms-2" onclick="copiar('numero')">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="dato-factura">
                                <div>
                                    <span class="label">Fecha</span>
                                </div>
                                <div>
                                    <span class="value" id="fecha">-</span>
                                    <button class="btn btn-outline-secondary btn-copy ms-2" onclick="copiar('fecha')">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="dato-factura">
                                <div>
                                    <span class="label">CUIT Emisor</span>
                                </div>
                                <div>
                                    <span class="value" id="cuitEmisor">-</span>
                                    <button class="btn btn-outline-secondary btn-copy ms-2" onclick="copiar('cuitEmisor')">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="dato-factura">
                                <div>
                                    <span class="label">CUIT Receptor</span>
                                </div>
                                <div>
                                    <span class="value" id="cuitReceptor">-</span>
                                    <button class="btn btn-outline-secondary btn-copy ms-2" onclick="copiar('cuitReceptor')">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="dato-factura">
                                <div>
                                    <span class="label">CAE</span>
                                </div>
                                <div>
                                    <span class="value" id="cae">-</span>
                                    <button class="btn btn-outline-secondary btn-copy ms-2" onclick="copiar('cae')">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="dato-factura">
                                <div>
                                    <span class="label">Vto. CAE</span>
                                </div>
                                <div>
                                    <span class="value" id="vtoCAE">-</span>
                                    <button class="btn btn-outline-secondary btn-copy ms-2" onclick="copiar('vtoCAE')">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="dato-factura">
                                <div>
                                    <span class="label">Neto Gravado</span>
                                </div>
                                <div>
                                    <span class="value" id="netoGravado">-</span>
                                    <button class="btn btn-outline-secondary btn-copy ms-2" onclick="copiar('netoGravado')">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="dato-factura">
                                <div>
                                    <span class="label">IVA</span>
                                </div>
                                <div>
                                    <span class="value" id="iva">-</span>
                                    <button class="btn btn-outline-secondary btn-copy ms-2" onclick="copiar('iva')">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="dato-factura" style="background: #d4edda;">
                                <div>
                                    <span class="label">TOTAL</span>
                                </div>
                                <div>
                                    <span class="value total" id="total">-</span>
                                    <button class="btn btn-outline-success btn-copy ms-2" onclick="copiar('total')">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="mt-4">
                                <button class="btn btn-outline-secondary w-100" type="button" data-bs-toggle="collapse" data-bs-target="#textoCompleto">
                                    <i class="bi bi-file-text me-2"></i>Ver Texto Completo
                                </button>
                                <div class="collapse mt-3" id="textoCompleto">
                                    <pre class="bg-light p-3 rounded" style="max-height: 300px; overflow-y: auto; font-size: 0.85rem;" id="textoOCR"></pre>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script>
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    </script>
    <script>
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('fileInput');
        const previewContainer = document.getElementById('previewContainer');
        const previewImage = document.getElementById('previewImage');
        const btnProcesar = document.getElementById('btnProcesar');
        const btnLimpiar = document.getElementById('btnLimpiar');
        const processing = document.getElementById('processing');
        const sinResultados = document.getElementById('sinResultados');
        const resultadoOCR = document.getElementById('resultadoOCR');
        const btnCopiarTodo = document.getElementById('btnCopiarTodo');

        let archivoSeleccionado = null;


        // Click en area de upload
        uploadArea.addEventListener('click', () => fileInput.click());

        // Drag and drop
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });

        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });

        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                procesarArchivo(files[0]);
            }
        });

        // Seleccion de archivo
        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                procesarArchivo(e.target.files[0]);
            }
        });

        function procesarArchivo(file) {
            // Validar que sea imagen o PDF
            const esImagen = file.type.startsWith('image/');
            const esPDF = file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf');

            if (!esImagen && !esPDF) {
                alert('Por favor selecciona una imagen (JPG, PNG, etc.) o un PDF');
                return;
            }

            archivoSeleccionado = file;

            // Mostrar preview
            if (esImagen) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    previewImage.src = e.target.result;
                    previewContainer.style.display = 'block';
                    uploadArea.style.display = 'none';
                    btnProcesar.disabled = false;
                    btnLimpiar.style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                // Para PDF mostrar icono
                previewImage.src = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIxMjgiIGhlaWdodD0iMTI4IiB2aWV3Qm94PSIwIDAgMjQgMjQiPjxwYXRoIGZpbGw9IiNlNzRjM2MiIGQ9Ik0xOSAzSDVjLTEuMSAwLTIgLjktMiAydjE0YzAgMS4xLjkgMiAyIDJoMTRjMS4xIDAgMi0uOSAyLTJWNWMwLTEuMS0uOS0yLTItMm0tOS41IDguNWMwIC44My0uNjcgMS41LTEuNSAxLjVINy41djJINnYtNmgybGItLjUgMS41YzAgLjMxLjA5LjU4LjIyLjgxbS01IDBoM2MuODMgMCAxLjUuNjcgMS41IDEuNXYyYzAgLjgzLS42NyAxLjUtMS41IDEuNWgtM3ptMTAgNC41aC0xLjVsLTEuMTctMS44SDlWMTZINy41di02SDExYy44MyAwIDEuNS42NyAxLjUgMS41djEuNjhjMCAuNi0uMzEgMS4xNC0uOCAxLjQ2em0tNS0yLjVoMS41di0ySDkuNXoiLz48cGF0aCBmaWxsPSIjZTc0YzNjIiBkPSJNMTIgMTAuNWgtLjV2Mkg0djEuNWgxLjV2LTF6Ii8+PC9zdmc+';
                previewContainer.style.display = 'block';
                uploadArea.style.display = 'none';
                btnProcesar.disabled = false;
                btnLimpiar.style.display = 'block';
            }
        }

        // Limpiar
        btnLimpiar.addEventListener('click', () => {
            archivoSeleccionado = null;
            fileInput.value = '';
            previewContainer.style.display = 'none';
            uploadArea.style.display = 'block';
            btnProcesar.disabled = true;
            btnLimpiar.style.display = 'none';
            resultadoOCR.style.display = 'none';
            sinResultados.style.display = 'block';
            btnCopiarTodo.style.display = 'none';
        });

        // Convertir PDF a imagen usando pdf.js
        async function convertirPDFaImagen(file) {
            const arrayBuffer = await file.arrayBuffer();
            const pdf = await pdfjsLib.getDocument({ data: arrayBuffer }).promise;
            const page = await pdf.getPage(1); // Primera página

            const scale = 2; // Mayor escala = mejor calidad
            const viewport = page.getViewport({ scale: scale });

            const canvas = document.createElement('canvas');
            const context = canvas.getContext('2d');
            canvas.height = viewport.height;
            canvas.width = viewport.width;

            await page.render({
                canvasContext: context,
                viewport: viewport
            }).promise;

            // Convertir canvas a blob
            return new Promise((resolve) => {
                canvas.toBlob((blob) => {
                    resolve(new File([blob], 'factura.png', { type: 'image/png' }));
                }, 'image/png', 0.95);
            });
        }

        // Procesar OCR
        btnProcesar.addEventListener('click', async () => {
            if (!archivoSeleccionado) return;

            // Mostrar loading
            btnProcesar.disabled = true;
            processing.style.display = 'block';
            resultadoOCR.style.display = 'none';
            sinResultados.style.display = 'none';

            try {
                let archivoParaEnviar = archivoSeleccionado;

                // Si es PDF, convertir a imagen primero
                const esPDF = archivoSeleccionado.type === 'application/pdf' ||
                              archivoSeleccionado.name.toLowerCase().endsWith('.pdf');

                if (esPDF) {
                    processing.querySelector('p').textContent = 'Convirtiendo PDF a imagen...';
                    archivoParaEnviar = await convertirPDFaImagen(archivoSeleccionado);
                    processing.querySelector('p').textContent = 'Procesando imagen con OCR...';
                }

                const formData = new FormData();
                formData.append('archivo', archivoParaEnviar);

                const response = await fetch('api/ocr_google.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                processing.style.display = 'none';
                processing.querySelector('p').textContent = 'Procesando imagen con OCR...';
                btnProcesar.disabled = false;

                if (data.error) {
                    alert('Error: ' + data.error);
                    sinResultados.style.display = 'block';
                    return;
                }

                // Mostrar resultados
                mostrarResultados(data);

            } catch (error) {
                processing.style.display = 'none';
                processing.querySelector('p').textContent = 'Procesando imagen con OCR...';
                btnProcesar.disabled = false;
                alert('Error al procesar: ' + error.message);
                sinResultados.style.display = 'block';
            }
        });

        function mostrarResultados(data) {
            const factura = data.datos_factura;

            document.getElementById('tipoComprobante').textContent = factura.tipo_comprobante || '-';
            document.getElementById('puntoVenta').textContent = factura.punto_venta ? String(factura.punto_venta).padStart(4, '0') : '-';
            document.getElementById('numero').textContent = factura.numero ? String(factura.numero).padStart(8, '0') : '-';
            document.getElementById('fecha').textContent = factura.fecha || '-';
            document.getElementById('cuitEmisor').textContent = factura.cuit_emisor || '-';
            document.getElementById('cuitReceptor').textContent = factura.cuit_receptor || '-';
            document.getElementById('cae').textContent = factura.cae || '-';
            document.getElementById('vtoCAE').textContent = factura.vencimiento_cae || '-';
            document.getElementById('netoGravado').textContent = factura.neto_gravado ? formatMoney(factura.neto_gravado) : '-';
            document.getElementById('iva').textContent = factura.iva ? formatMoney(factura.iva) : '-';
            document.getElementById('total').textContent = factura.total ? formatMoney(factura.total) : '-';
            document.getElementById('textoOCR').textContent = data.texto_completo || '';

            resultadoOCR.style.display = 'block';
            btnCopiarTodo.style.display = 'inline-block';
        }

        function formatMoney(value) {
            const num = parseFloat(value);
            return '$ ' + num.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function copiar(elementId) {
            const text = document.getElementById(elementId).textContent;
            navigator.clipboard.writeText(text.replace('$ ', '')).then(() => {
                // Feedback visual
                const btn = event.target.closest('button');
                const originalHTML = btn.innerHTML;
                btn.innerHTML = '<i class="bi bi-check"></i>';
                btn.classList.remove('btn-outline-secondary', 'btn-outline-success');
                btn.classList.add('btn-success');
                setTimeout(() => {
                    btn.innerHTML = originalHTML;
                    btn.classList.remove('btn-success');
                    btn.classList.add('btn-outline-secondary');
                }, 1000);
            });
        }

        // Copiar todo
        btnCopiarTodo.addEventListener('click', () => {
            const datos = [
                'Tipo: ' + document.getElementById('tipoComprobante').textContent,
                'Punto Venta: ' + document.getElementById('puntoVenta').textContent,
                'Numero: ' + document.getElementById('numero').textContent,
                'Fecha: ' + document.getElementById('fecha').textContent,
                'CUIT Emisor: ' + document.getElementById('cuitEmisor').textContent,
                'CUIT Receptor: ' + document.getElementById('cuitReceptor').textContent,
                'CAE: ' + document.getElementById('cae').textContent,
                'Vto CAE: ' + document.getElementById('vtoCAE').textContent,
                'Neto Gravado: ' + document.getElementById('netoGravado').textContent,
                'IVA: ' + document.getElementById('iva').textContent,
                'Total: ' + document.getElementById('total').textContent
            ].join('\n');

            navigator.clipboard.writeText(datos).then(() => {
                btnCopiarTodo.innerHTML = '<i class="bi bi-check me-1"></i>Copiado!';
                setTimeout(() => {
                    btnCopiarTodo.innerHTML = '<i class="bi bi-clipboard me-1"></i>Copiar Todo';
                }, 1500);
            });
        });
    </script>
</body>
</html>

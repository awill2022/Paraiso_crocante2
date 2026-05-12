<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
protegerPagina();

if (isset($_SESSION['usuario_rol']) && !in_array($_SESSION['usuario_rol'], ['administrador', 'admin'])) {
    header("Location: ../dashboard.php");
    exit;
}

require_once '../includes/Database.php';

$db = new Database();
$conn = $db->getConnection();

$insumos = [];
$result = $conn->query("SELECT id, nombre, stock_actual, unidad_medida FROM insumos ORDER BY nombre");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $insumos[] = $row;
    }
}

$success = $_GET['success'] ?? false;
$error = $_GET['error'] ?? false;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registrar Compra - Paraíso Crocante</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: #f0f2f5; padding: 20px; font-family: 'Inter', Arial, sans-serif; color: #1a1a2e; }

        .page-wrapper { max-width: 960px; margin: 0 auto; }

        /* Header Card */
        .header-card {
            background: linear-gradient(135deg, #FF6B6B 0%, #ee4545 100%);
            color: white;
            padding: 22px 28px;
            border-radius: 14px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 20px rgba(255,107,107,0.35);
        }
        .header-card h1 { font-size: 1.45em; font-weight: 700; }
        .header-card p { font-size: 0.85em; opacity: 0.85; margin-top: 4px; }
        .btn-back {
            background: rgba(255,255,255,0.2);
            color: white;
            text-decoration: none;
            padding: 9px 18px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9em;
            transition: background 0.2s;
            white-space: nowrap;
        }
        .btn-back:hover { background: rgba(255,255,255,0.35); }

        /* Main card */
        .card { background: white; border-radius: 14px; padding: 28px; box-shadow: 0 2px 12px rgba(0,0,0,0.07); margin-bottom: 20px; }

        /* Alerts */
        .alert { padding: 14px 18px; border-radius: 10px; margin-bottom: 20px; font-weight: 500; font-size: 0.95em; }
        .alert-success { background: #d4f5e2; color: #1a6e40; border-left: 4px solid #28a745; }
        .alert-error   { background: #fde8e8; color: #7d1c1c; border-left: 4px solid #dc3545; }

        /* Form global */
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 0.88em; color: #555; text-transform: uppercase; letter-spacing: 0.03em; }
        .form-group input, .form-group select {
            width: 100%; padding: 10px 13px;
            border: 1.5px solid #dde2ea;
            border-radius: 8px;
            font-size: 0.95em;
            color: #1a1a2e;
            transition: border-color 0.2s, box-shadow 0.2s;
            background: #fafbfc;
        }
        .form-group input:focus, .form-group select:focus {
            outline: none; border-color: #FF6B6B;
            box-shadow: 0 0 0 3px rgba(255,107,107,0.12);
            background: white;
        }

        /* Section title */
        .section-title {
            font-size: 1em; font-weight: 700; color: #444;
            border-bottom: 2px solid #f0f2f5;
            padding-bottom: 10px; margin-bottom: 18px;
            display: flex; align-items: center; gap: 8px;
        }

        /* Insumo row */
        .insumo-row {
            background: #fafbfc;
            border: 1.5px solid #e8ecf0;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 14px;
            transition: box-shadow 0.2s;
        }
        .insumo-row:hover { box-shadow: 0 3px 12px rgba(0,0,0,0.08); }

        /* Row top: insumo select + IVA + remove */
        .row-top { display: flex; gap: 12px; align-items: flex-end; margin-bottom: 14px; }
        .row-top .col-insumo { flex: 3; }
        .row-top .col-iva    { flex: 1.2; min-width: 130px; }
        .row-top .col-remove { flex: 0 0 auto; }

        /* Row bottom: campos de cantidad/precio */
        .row-bottom { display: grid; gap: 10px; }
        .row-bottom.modo-unidad  { grid-template-columns: repeat(4, 1fr); }
        .row-bottom.modo-paquete { grid-template-columns: repeat(5, 1fr); }

        /* Modo toggle */
        .modo-toggle {
            display: flex; gap: 0; border-radius: 8px; overflow: hidden;
            border: 1.5px solid #dde2ea; width: fit-content; margin-bottom: 14px;
        }
        .modo-btn {
            padding: 7px 14px; font-size: 0.82em; font-weight: 600;
            border: none; cursor: pointer; background: white; color: #888;
            transition: all 0.2s; white-space: nowrap;
        }
        .modo-btn.active { background: #FF6B6B; color: white; }

        /* Field label small */
        .field-label { font-size: 0.78em; font-weight: 600; color: #666; text-transform: uppercase; letter-spacing: 0.03em; margin-bottom: 5px; }

        input[type="number"], input[type="text"], select {
            width: 100%; padding: 9px 11px;
            border: 1.5px solid #dde2ea; border-radius: 8px;
            font-size: 0.92em; color: #1a1a2e;
            background: #fafbfc;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        input:focus, select:focus {
            outline: none; border-color: #FF6B6B;
            box-shadow: 0 0 0 3px rgba(255,107,107,0.12);
            background: white;
        }
        input[readonly] { background: #f0f2f5 !important; color: #444; font-weight: 700; border-color: #dde2ea !important; }

        /* Precio unitario calculado highlight */
        .precio-calc { background: #fff8e6 !important; border-color: #f0b429 !important; color: #7d5a00 !important; }

        /* Info bubble */
        .info-paquete {
            font-size: 0.78em; color: #f0b429; font-weight: 600;
            margin-top: 4px; display: none;
        }

        /* Buttons */
        .btn-remove {
            background: #fde8e8; color: #dc3545; border: none;
            padding: 9px 13px; border-radius: 8px; cursor: pointer;
            font-weight: 700; font-size: 0.9em; transition: all 0.2s;
        }
        .btn-remove:hover { background: #dc3545; color: white; }

        .btn-add {
            background: #eef2ff; color: #4361ee;
            border: 1.5px dashed #4361ee;
            padding: 10px 20px; border-radius: 9px;
            cursor: pointer; font-weight: 600; font-size: 0.92em;
            transition: all 0.2s; width: 100%; margin-bottom: 20px;
        }
        .btn-add:hover { background: #4361ee; color: white; }

        /* Totals */
        .totals-box {
            background: #f8f9ff; border: 1.5px solid #e0e4ff;
            border-radius: 12px; padding: 18px 22px; margin-top: 10px;
        }
        .totals-row { display: flex; justify-content: space-between; align-items: center; padding: 6px 0; font-size: 0.95em; }
        .totals-row.divider { border-top: 1.5px solid #e0e4ff; margin-top: 8px; padding-top: 14px; }
        .totals-row.grand { font-size: 1.25em; font-weight: 700; color: #FF6B6B; }
        .totals-label { color: #555; }
        .totals-value { font-weight: 600; }

        /* Submit */
        .btn-submit {
            background: linear-gradient(135deg, #FF6B6B, #ee4545);
            color: white; border: none; padding: 14px 30px;
            border-radius: 10px; font-size: 1.05em; font-weight: 700;
            cursor: pointer; width: 100%; margin-top: 10px;
            box-shadow: 0 4px 16px rgba(255,107,107,0.35);
            transition: transform 0.15s, box-shadow 0.15s;
        }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(255,107,107,0.45); }
        .btn-submit:active { transform: translateY(0); }

        /* IVA badge */
        .iva-badge {
            display: inline-block; font-size: 0.72em; font-weight: 700;
            padding: 2px 7px; border-radius: 20px; margin-left: 6px;
            background: #e8f5e9; color: #2e7d32;
        }
        .iva-badge.sin-iva { background: #fce4ec; color: #c62828; }

        @media (max-width: 700px) {
            .row-top { flex-wrap: wrap; }
            .row-bottom.modo-unidad, .row-bottom.modo-paquete { grid-template-columns: 1fr 1fr; }
            .header-card { flex-direction: column; gap: 12px; text-align: center; }
        }
    </style>
</head>
<body>
<div class="page-wrapper">

    <div class="header-card">
        <div>
            <h1>🛒 Registrar Compra / Factura</h1>
            <p>El inventario se actualizará automáticamente al guardar.</p>
        </div>
        <a href="../dashboard.php" class="btn-back">← Volver</a>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success">✅ Compra registrada y stock actualizado exitosamente.</div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error">❌ <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="card">
        <form id="compraForm" action="procesar_compra.php" method="POST">

            <!-- Datos generales -->
            <div class="form-group">
                <label>Proveedor / Descripción de la Factura (Opcional)</label>
                <input type="text" name="proveedor" placeholder="Ej: Supermaxi, Mercado, Factura #1234">
            </div>

            <div class="section-title">📦 Detalle de Insumos</div>

            <div id="insumos-list">
                <!-- Filas generadas por JS -->
            </div>

            <button type="button" class="btn-add" onclick="agregarInsumo()">+ Agregar Insumo</button>

            <!-- Totales -->
            <div class="totals-box">
                <div class="totals-row">
                    <span class="totals-label">Subtotal (sin IVA)</span>
                    <span class="totals-value">$<span id="totalSubtotal">0.00</span></span>
                </div>
                <div class="totals-row">
                    <span class="totals-label">IVA Total</span>
                    <span class="totals-value">$<span id="totalIva">0.00</span></span>
                </div>
                <div class="totals-row divider grand">
                    <span>TOTAL FACTURA</span>
                    <span>$<span id="totalFactura">0.00</span></span>
                </div>
            </div>

            <button type="submit" class="btn-submit">💾 Guardar Compra y Actualizar Inventario</button>

        </form>
    </div>
</div>

<script>
    let contador = 0;

    // Opciones de insumos para JS
    const insumosData = [
        <?php foreach ($insumos as $ins): ?>
        { id: <?php echo $ins['id']; ?>, nombre: <?php echo json_encode($ins['nombre']); ?>, unidad: <?php echo json_encode($ins['unidad_medida']); ?> },
        <?php endforeach; ?>
    ];

    function buildInsumosOptions() {
        let html = '<option value="">Seleccionar insumo...</option>';
        insumosData.forEach(ins => {
            html += `<option value="${ins.id}">${ins.nombre} (${ins.unidad})</option>`;
        });
        return html;
    }

    function agregarInsumo() {
        const list = document.getElementById('insumos-list');
        const idx = contador++;
        const div = document.createElement('div');
        div.className = 'insumo-row';
        div.dataset.idx = idx;
        div.innerHTML = buildInsumoHTML(idx);
        list.appendChild(div);
        actualizarTotales();
    }

    function buildInsumoHTML(idx) {
        return `
        <!-- Modo toggle -->
        <div style="display:flex; align-items:center; gap:12px; margin-bottom:12px; flex-wrap:wrap;">
            <div class="modo-toggle">
                <button type="button" class="modo-btn active" onclick="setModo(${idx},'unidad',this)">Por Unidad</button>
                <button type="button" class="modo-btn"        onclick="setModo(${idx},'paquete',this)">Por Paquete</button>
            </div>
            <span class="info-paquete" id="info_${idx}">💡 Ingresa cantidad/paquete y valor del paquete — el precio unitario se calcula solo</span>
        </div>

        <!-- Fila principal: insumo + IVA + eliminar -->
        <div class="row-top">
            <div class="col-insumo">
                <div class="field-label">Insumo</div>
                <select name="insumos[${idx}][id_insumo]" required onchange="calcularLinea(${idx})">
                    ${buildInsumosOptions()}
                </select>
            </div>
            <div class="col-iva">
                <div class="field-label">IVA</div>
                <select name="insumos[${idx}][iva]" onchange="calcularLinea(${idx})">
                    <option value="0">Sin IVA (0%)</option>
                    <option value="12">IVA 12%</option>
                    <option value="15">IVA 15%</option>
                </select>
            </div>
            <div class="col-remove" style="padding-bottom:0px;">
                <div class="field-label">&nbsp;</div>
                <button type="button" class="btn-remove" onclick="eliminarLinea(this)">✕</button>
            </div>
        </div>

        <!-- Fila de cantidades/precios -->
        <div class="row-bottom modo-unidad" id="rowbottom_${idx}">
            <!-- Modo unidad: cantidad, precio unit, subtotal sin IVA, total con IVA -->
            <div id="col_unidades_${idx}">
                <div class="field-label">Cantidad</div>
                <input type="number" step="0.01" min="0.01"
                    name="insumos[${idx}][cantidad]"
                    id="cantidad_${idx}"
                    class="cantidad-input" placeholder="0.00" required
                    oninput="calcularLinea(${idx})">
            </div>
            <div id="col_xpaquete_${idx}" style="display:none;">
                <div class="field-label">Unid./Paquete</div>
                <input type="number" step="1" min="1"
                    id="xpaquete_${idx}"
                    class="xpaquete-input" placeholder="50"
                    oninput="calcularLinea(${idx})">
            </div>
            <div id="col_precio_${idx}">
                <div class="field-label" id="label_precio_${idx}">Precio Unitario ($)</div>
                <input type="number" step="0.0001" min="0"
                    name="insumos[${idx}][precio_unitario]"
                    id="precio_${idx}"
                    class="precio-input" placeholder="0.00" required
                    oninput="calcularLinea(${idx})">
            </div>
            <div id="col_preciounit_${idx}" style="display:none;">
                <div class="field-label">Precio Unit. Calc. ($)</div>
                <input type="number" step="0.0001"
                    id="precio_calc_${idx}"
                    class="precio-calc" readonly placeholder="auto" tabindex="-1">
            </div>
            <div>
                <div class="field-label">Subtotal (sin IVA)</div>
                <input type="text" id="subtotal_${idx}" class="subtotal-input" readonly value="0.00">
            </div>
            <div>
                <div class="field-label">Total c/IVA</div>
                <input type="text" id="totallinea_${idx}" class="totallinea-input" readonly value="0.00"
                    style="background:#f0f8ff; font-weight:700; color:#2563eb; border-color:#bcd4f5;">
            </div>
        </div>
        <!-- Hidden: precio unitario calculado que enviaremos -->
        <input type="hidden" name="insumos[${idx}][total_con_iva]" id="totaliva_hidden_${idx}" value="0">
        `;
    }

    function setModo(idx, modo, btn) {
        // Toggle botones
        btn.closest('.modo-toggle').querySelectorAll('.modo-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        const rowBottom = document.getElementById(`rowbottom_${idx}`);
        const colXpaquete = document.getElementById(`col_xpaquete_${idx}`);
        const colPrecioCalc = document.getElementById(`col_preciounit_${idx}`);
        const labelPrecio = document.getElementById(`label_precio_${idx}`);
        const infoBubble = document.getElementById(`info_${idx}`);
        const precioInput = document.getElementById(`precio_${idx}`);
        const cantInput = document.getElementById(`cantidad_${idx}`);
        const colUnidades = document.getElementById(`col_unidades_${idx}`);

        if (modo === 'paquete') {
            rowBottom.className = 'row-bottom modo-paquete';
            colXpaquete.style.display = '';
            colPrecioCalc.style.display = '';
            labelPrecio.textContent = 'Valor del Paquete ($)';
            infoBubble.style.display = 'inline-block';
            // En modo paquete la "cantidad" es número de paquetes comprados
            colUnidades.querySelector('.field-label').textContent = 'N° de Paquetes';
            cantInput.placeholder = '1';
        } else {
            rowBottom.className = 'row-bottom modo-unidad';
            colXpaquete.style.display = 'none';
            colPrecioCalc.style.display = 'none';
            labelPrecio.textContent = 'Precio Unitario ($)';
            infoBubble.style.display = 'none';
            colUnidades.querySelector('.field-label').textContent = 'Cantidad';
            cantInput.placeholder = '0.00';
        }
        calcularLinea(idx);
    }

    function calcularLinea(idx) {
        const rowBottom = document.getElementById(`rowbottom_${idx}`);
        const esPaquete = rowBottom.classList.contains('modo-paquete');

        const ivaSelect = document.querySelector(`[name="insumos[${idx}][iva]"]`);
        const ivaPct = parseFloat(ivaSelect ? ivaSelect.value : 0) / 100;

        let cantidad = parseFloat(document.getElementById(`cantidad_${idx}`)?.value) || 0;
        let precioPaquete = parseFloat(document.getElementById(`precio_${idx}`)?.value) || 0;
        let xpaquete = parseFloat(document.getElementById(`xpaquete_${idx}`)?.value) || 0;

        let precioUnitario, cantidadReal, subtotal;

        if (esPaquete) {
            // cantidad = número de paquetes comprados
            // xpaquete = unidades por paquete
            // precioPaquete = valor de 1 paquete
            cantidadReal = cantidad * xpaquete;  // total unidades que entran al stock
            precioUnitario = (xpaquete > 0) ? (precioPaquete / xpaquete) : 0;
            subtotal = cantidad * precioPaquete; // subtotal sin IVA = paquetes * valor paquete

            // Mostrar precio unitario calculado
            const precioCalcInput = document.getElementById(`precio_calc_${idx}`);
            if (precioCalcInput) precioCalcInput.value = precioUnitario > 0 ? precioUnitario.toFixed(4) : '';

            // Actualizar hidden precio_unitario con el calculado
            const precioHidden = document.querySelector(`[name="insumos[${idx}][precio_unitario]"]`);
            if (precioHidden) precioHidden.value = precioUnitario.toFixed(6);

            // Actualizar hidden cantidad con cantidadReal
            const cantHidden = document.querySelector(`[name="insumos[${idx}][cantidad]"]`);
            if (cantHidden) cantHidden.value = cantidadReal.toFixed(4);

        } else {
            cantidadReal = cantidad;
            precioUnitario = precioPaquete;
            subtotal = cantidadReal * precioUnitario;
        }

        const ivaValor = subtotal * ivaPct;
        const totalLinea = subtotal + ivaValor;

        // Mostrar subtotal
        const subtotalInput = document.getElementById(`subtotal_${idx}`);
        if (subtotalInput) subtotalInput.value = subtotal.toFixed(2);

        // Mostrar total con IVA
        const totalLineaInput = document.getElementById(`totallinea_${idx}`);
        if (totalLineaInput) totalLineaInput.value = totalLinea.toFixed(2);

        // Hidden total con IVA
        const totalIvaHidden = document.getElementById(`totaliva_hidden_${idx}`);
        if (totalIvaHidden) totalIvaHidden.value = totalLinea.toFixed(4);

        actualizarTotales();
    }

    function actualizarTotales() {
        let subtotalGlobal = 0;
        let ivaGlobal = 0;

        document.querySelectorAll('.subtotal-input').forEach(inp => {
            subtotalGlobal += parseFloat(inp.value) || 0;
        });
        document.querySelectorAll('.totallinea-input').forEach(inp => {
            ivaGlobal += parseFloat(inp.value) || 0;
        });
        ivaGlobal = ivaGlobal - subtotalGlobal; // iva puro

        document.getElementById('totalSubtotal').textContent = subtotalGlobal.toFixed(2);
        document.getElementById('totalIva').textContent = ivaGlobal.toFixed(2);
        document.getElementById('totalFactura').textContent = (subtotalGlobal + ivaGlobal).toFixed(2);
    }

    function eliminarLinea(btn) {
        const rows = document.querySelectorAll('.insumo-row');
        if (rows.length <= 1) {
            alert('Debe haber al menos un insumo en la compra.');
            return;
        }
        btn.closest('.insumo-row').remove();
        actualizarTotales();
    }

    // Agregar la primera fila al cargar
    agregarInsumo();
</script>
</body>
</html>
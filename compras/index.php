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
    <style>
        .container { max-width: 800px; margin: 40px auto; padding: 20px; background: white; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .header { background: #FF6B6B; color: white; padding: 15px; border-radius: 10px 10px 0 0; display: flex; justify-content: space-between; align-items: center; margin: -20px -20px 20px -20px; }
        .header h1 { margin: 0; font-size: 1.5em; }
        .header .btn-back { background: white; color: #FF6B6B; text-decoration: none; padding: 8px 15px; border-radius: 5px; font-weight: bold; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; }
        .insumo-item { border: 1px solid #eee; padding: 15px; margin-bottom: 15px; border-radius: 5px; background: #f9f9f9; display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; }
        .insumo-item > div { flex: 1; min-width: 150px; }
        .btn { background: #FF6B6B; color: white; padding: 12px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 1.1em; font-weight: bold; }
        .btn:hover { background: #e05e5e; }
        .btn-secondary { background: #6c757d; color: white; padding: 10px 15px; border: none; border-radius: 5px; cursor: pointer; font-size: 0.9em; margin-bottom: 15px; font-weight: bold; }
        .btn-remove { background: #dc3545; color: white; border: none; padding: 10px 15px; border-radius: 5px; cursor: pointer; font-weight: bold; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .total-display { font-size: 1.5em; font-weight: bold; text-align: right; margin-top: 15px; padding-top: 15px; border-top: 2px solid #eee; }
        @media (max-width: 768px) {
            .header { flex-direction: column; text-align: center; gap: 10px; }
            .insumo-item { flex-direction: column; align-items: stretch; }
        }
    </style>
</head>
<body style="background: #f4f4f4; padding: 20px; font-family: Arial, sans-serif;">
    <div class="container">
        <div class="header">
            <h1>Registrar Compra / Factura</h1>
            <a href="../dashboard.php" class="btn-back">Volver al Dashboard</a>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success">✅ Compra registrada y stock actualizado exitosamente.</div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error">❌ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <p style="color: #666; margin-bottom: 20px;">Ingrese los insumos adquiridos. El inventario se actualizará automáticamente y se registrará un gasto general por la compra.</p>

        <form id="compraForm" action="procesar_compra.php" method="POST">
            <div class="form-group">
                <label>Proveedor / Descripción de la Factura (Opcional):</label>
                <input type="text" name="proveedor" placeholder="Ej: Supermaxi, Mercado, Factura #1234">
            </div>
            
            <h3 style="margin-top: 30px; border-bottom: 1px solid #ccc; padding-bottom: 10px;">Detalle de Insumos</h3>
            
            <div id="insumos-list">
                <div class="insumo-item">
                    <div>
                        <label>Insumo</label>
                        <select name="insumos[0][id_insumo]" required class="insumo-select" onchange="calcularLinea(this)">
                            <option value="">Seleccionar Insumo...</option>
                            <?php foreach ($insumos as $insumo): ?>
                                <option value="<?php echo $insumo['id']; ?>"><?php echo htmlspecialchars($insumo['nombre']); ?> (<?php echo htmlspecialchars($insumo['unidad_medida']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>Cantidad</label>
                        <input type="number" step="0.01" min="0.01" name="insumos[0][cantidad]" class="cantidad-input" placeholder="0.00" required oninput="calcularLinea(this)">
                    </div>
                    <div>
                        <label>Precio Unitario ($)</label>
                        <input type="number" step="0.01" min="0" name="insumos[0][precio_unitario]" class="precio-input" placeholder="0.00" required oninput="calcularLinea(this)">
                    </div>
                    <div>
                        <label>Subtotal ($)</label>
                        <input type="text" class="subtotal-input" readonly value="0.00" style="background:#eee; font-weight:bold;">
                    </div>
                    <div style="flex: 0 0 auto;">
                        <button type="button" class="btn-remove" onclick="eliminarLinea(this)">X</button>
                    </div>
                </div>
            </div>
            
            <button type="button" class="btn-secondary" onclick="agregarInsumo()">+ Agregar Otro Insumo</button>
            
            <div class="total-display">
                Total Compra: $<span id="totalFactura">0.00</span>
            </div>
            
            <div style="margin-top: 30px; text-align: center;">
                <button type="submit" class="btn" style="width: 100%;">💾 Guardar Compra y Actualizar Inventario</button>
            </div>
        </form>
    </div>

    <script>
        let contador = 1;
        const insumosOptions = `<option value="">Seleccionar Insumo...</option>` + 
            `<?php foreach ($insumos as $insumo): ?>` + 
            `<option value="<?php echo $insumo['id']; ?>"><?php echo addslashes(htmlspecialchars($insumo['nombre'])); ?> (<?php echo addslashes(htmlspecialchars($insumo['unidad_medida'])); ?>)</option>` + 
            `<?php endforeach; ?>`;

        function agregarInsumo() {
            const list = document.getElementById('insumos-list');
            const item = document.createElement('div');
            item.className = 'insumo-item';
            item.innerHTML = `
                <div>
                    <label>Insumo</label>
                    <select name="insumos[${contador}][id_insumo]" required class="insumo-select" onchange="calcularLinea(this)">
                        ${insumosOptions}
                    </select>
                </div>
                <div>
                    <label>Cantidad</label>
                    <input type="number" step="0.01" min="0.01" name="insumos[${contador}][cantidad]" class="cantidad-input" placeholder="0.00" required oninput="calcularLinea(this)">
                </div>
                <div>
                    <label>Precio Unitario ($)</label>
                    <input type="number" step="0.01" min="0" name="insumos[${contador}][precio_unitario]" class="precio-input" placeholder="0.00" required oninput="calcularLinea(this)">
                </div>
                <div>
                    <label>Subtotal ($)</label>
                    <input type="text" class="subtotal-input" readonly value="0.00" style="background:#eee; font-weight:bold;">
                </div>
                <div style="flex: 0 0 auto;">
                    <button type="button" class="btn-remove" onclick="eliminarLinea(this)">X</button>
                </div>
            `;
            list.appendChild(item);
            contador++;
            calcularTotal();
        }

        function eliminarLinea(btn) {
            const row = btn.closest('.insumo-item');
            if (document.querySelectorAll('.insumo-item').length > 1) {
                row.remove();
                calcularTotal();
            } else {
                alert("Debe haber al menos un insumo en la compra.");
            }
        }

        function calcularLinea(element) {
            const row = element.closest('.insumo-item');
            const cant = parseFloat(row.querySelector('.cantidad-input').value) || 0;
            const precio = parseFloat(row.querySelector('.precio-input').value) || 0;
            const subtotal = cant * precio;
            row.querySelector('.subtotal-input').value = subtotal.toFixed(2);
            calcularTotal();
        }

        function calcularTotal() {
            let total = 0;
            document.querySelectorAll('.subtotal-input').forEach(input => {
                total += parseFloat(input.value) || 0;
            });
            document.getElementById('totalFactura').innerText = total.toFixed(2);
        }
    </script>
</body>
</html>
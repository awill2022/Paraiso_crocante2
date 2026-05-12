<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

protegerPagina();

$db = new Database();
$conn = $db->getConnection();

$errores = [];
$mensaje_exito = '';
$valores = [
    'nombre'           => '',
    'unidad_medida'    => 'ml',
    'precio_paquete'   => '0.00',
    'cantidad_paquete' => '1.00',
    'stock_actual'     => '0.00',
    'stock_minimo'     => '0.00'
];

$unidades_medida = [
    'ml'     => 'Mililitros (ml)',
    'g'      => 'Gramos (g)',
    'unidad' => 'Unidad / Pieza',
    'l'      => 'Litros (l)',
    'kg'     => 'Kilogramos (kg)'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $valores['nombre']           = trim($_POST['nombre']);
    $valores['unidad_medida']    = $_POST['unidad_medida'];
    $valores['precio_paquete']   = trim($_POST['precio_paquete']);
    $valores['cantidad_paquete'] = trim($_POST['cantidad_paquete']);
    $valores['stock_actual']     = trim($_POST['stock_actual']);
    $valores['stock_minimo']     = trim($_POST['stock_minimo']);

    if (empty($valores['nombre']))          $errores[] = "El nombre del insumo es obligatorio.";
    elseif (strlen($valores['nombre']) > 255) $errores[] = "El nombre no puede exceder 255 caracteres.";
    if (!array_key_exists($valores['unidad_medida'], $unidades_medida)) $errores[] = "Unidad de medida no válida.";

    foreach (['precio_paquete' => 'Precio del paquete', 'cantidad_paquete' => 'Cantidad del paquete', 'stock_actual' => 'Stock actual', 'stock_minimo' => 'Stock mínimo'] as $campo => $nombre) {
        if (!is_numeric($valores[$campo])) $errores[] = "$nombre debe ser un número válido.";
        elseif (floatval($valores[$campo]) < 0) $errores[] = "$nombre no puede ser negativo.";
    }
    if (floatval($valores['cantidad_paquete']) <= 0) $errores[] = "La cantidad del paquete debe ser mayor a cero.";

    if (empty($errores)) {
        try {
            $precio_unitario = floatval($valores['precio_paquete']) / floatval($valores['cantidad_paquete']);
            $stmt = $conn->prepare("INSERT INTO insumos (nombre, unidad_medida, precio_unitario, stock_actual, stock_minimo) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssddd", $valores['nombre'], $valores['unidad_medida'], $precio_unitario, $valores['stock_actual'], $valores['stock_minimo']);
            if ($stmt->execute()) {
                $mensaje_exito = "Insumo '" . htmlspecialchars($valores['nombre']) . "' agregado correctamente. Precio unitario calculado: $" . number_format($precio_unitario, 4);
                $valores = ['nombre' => '', 'unidad_medida' => $valores['unidad_medida'], 'precio_paquete' => '0.00', 'cantidad_paquete' => '1.00', 'stock_actual' => '0.00', 'stock_minimo' => '0.00'];
            } else {
                $errores[] = "Error al agregar el insumo: " . $stmt->error;
            }
            $stmt->close();
        } catch (Exception $e) {
            $errores[] = "Error de base de datos: " . $e->getMessage();
        }
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Insumo - Paraíso Crocante</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="/img/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="../assets/css/app.css">
</head>
<body class="app-body">

<header class="app-header">
    <div>
        <h1>🌾 Agregar Nuevo Insumo</h1>
        <p>El precio unitario se calcula automáticamente.</p>
    </div>
    <nav>
        <a href="listar_insumos.php" class="btn-nav">Ver Lista</a>
        <a href="../dashboard.php" class="btn-nav">← Dashboard</a>
    </nav>
</header>

<div class="app-page narrow">

    <?php if (!empty($errores)): ?>
    <div class="app-alert error">
        <span>❌</span>
        <div>
            <strong>Por favor, corrija los siguientes errores:</strong>
            <ul><?php foreach ($errores as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?></ul>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($mensaje_exito): ?>
    <div class="app-alert success">✅ <?php echo $mensaje_exito; ?></div>
    <?php endif; ?>

    <div class="app-card">
        <form action="agregar_insumo.php" method="post">

            <div class="form-group">
                <label class="form-label" for="nombre">Nombre del Insumo *</label>
                <input class="form-control" type="text" id="nombre" name="nombre"
                       value="<?php echo htmlspecialchars($valores['nombre']); ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="unidad_medida">Unidad de Medida *</label>
                <select class="form-control" id="unidad_medida" name="unidad_medida" required>
                    <?php foreach ($unidades_medida as $val => $texto): ?>
                    <option value="<?php echo $val; ?>" <?php echo ($valores['unidad_medida'] == $val) ? 'selected' : ''; ?>>
                        <?php echo $texto; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label" for="precio_paquete">Precio del Paquete ($) *</label>
                    <input class="form-control" type="number" id="precio_paquete" name="precio_paquete"
                           step="0.01" min="0"
                           value="<?php echo htmlspecialchars($valores['precio_paquete']); ?>"
                           required oninput="calcPrecio()">
                    <div class="form-hint">Precio total que pagas por el paquete/bolsa/envase</div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="cantidad_paquete">Cantidad en el Paquete *</label>
                    <input class="form-control" type="number" id="cantidad_paquete" name="cantidad_paquete"
                           step="0.01" min="0.01"
                           value="<?php echo htmlspecialchars($valores['cantidad_paquete']); ?>"
                           required oninput="calcPrecio()">
                    <div class="form-hint">Cantidad total contenida en el paquete</div>
                </div>
            </div>

            <!-- Precio unitario calculado (visual) -->
            <div class="form-group">
                <label class="form-label">Precio Unitario Calculado</label>
                <input class="form-control" type="text" id="precio_unitario_display" readonly
                       placeholder="Se calcula automáticamente"
                       style="background:#fff8e6; color:#7d5a00; font-weight:700; border-color:#f0b429;">
                <div class="form-hint">= Precio del paquete ÷ Cantidad del paquete</div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label" for="stock_actual">Stock Actual *</label>
                    <input class="form-control" type="number" id="stock_actual" name="stock_actual"
                           step="0.01" min="0"
                           value="<?php echo htmlspecialchars($valores['stock_actual']); ?>" required>
                    <div class="form-hint">Cantidad actual en inventario</div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="stock_minimo">Stock Mínimo</label>
                    <input class="form-control" type="number" id="stock_minimo" name="stock_minimo"
                           step="0.01" min="0"
                           value="<?php echo htmlspecialchars($valores['stock_minimo']); ?>">
                    <div class="form-hint">Alerta cuando el stock baje de este valor</div>
                </div>
            </div>

            <div class="btn-group">
                <button type="submit" class="btn btn-primary btn-lg">💾 Guardar Insumo</button>
                <a href="listar_insumos.php" class="btn btn-secondary">Ver Lista</a>
                <a href="../dashboard.php" class="btn btn-secondary">← Dashboard</a>
            </div>
        </form>
    </div>
</div>

<script>
function calcPrecio() {
    const precio = parseFloat(document.getElementById('precio_paquete').value) || 0;
    const cant   = parseFloat(document.getElementById('cantidad_paquete').value) || 0;
    const display = document.getElementById('precio_unitario_display');
    if (cant > 0) {
        display.value = '$' + (precio / cant).toFixed(4);
    } else {
        display.value = '';
    }
}
calcPrecio();
</script>
</body>
</html>
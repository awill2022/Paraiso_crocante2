<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

protegerPagina(); // Asegura que el usuario esté logueado

$db = new Database();
$conn = $db->getConnection();

$errores = [];
$mensaje_exito = '';
$valores_persistentes = [
    'nombre' => '',
    'unidad_medida' => 'ml', // Valor por defecto
    'precio_paquete' => '0.00',
    'cantidad_paquete' => '1.00',
    'stock_actual' => '0.00',
    'stock_minimo' => '0.00'
];

// Unidades de medida predefinidas
$unidades_medida = [
    'ml' => 'Mililitros (ml)',
    'g' => 'Gramos (g)',
    'unidad' => 'Unidad/Pieza',
    'l' => 'Litros (l)',
    'kg' => 'Kilogramos (kg)'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recoger y limpiar datos
    $valores_persistentes['nombre'] = trim($_POST['nombre']);
    $valores_persistentes['unidad_medida'] = $_POST['unidad_medida'];
    $valores_persistentes['precio_paquete'] = trim($_POST['precio_paquete']);
    $valores_persistentes['cantidad_paquete'] = trim($_POST['cantidad_paquete']);
    $valores_persistentes['stock_actual'] = trim($_POST['stock_actual']);
    $valores_persistentes['stock_minimo'] = trim($_POST['stock_minimo']);

    // Validaciones
    if (empty($valores_persistentes['nombre'])) {
        $errores[] = "El nombre del insumo es obligatorio.";
    } elseif (strlen($valores_persistentes['nombre']) > 255) {
        $errores[] = "El nombre del insumo no puede exceder los 255 caracteres.";
    }

    if (!array_key_exists($valores_persistentes['unidad_medida'], $unidades_medida)) {
        $errores[] = "Unidad de medida no válida.";
    }

    // Validación de precios y cantidades
    $campos_numericos = [
        'precio_paquete' => 'Precio del paquete',
        'cantidad_paquete' => 'Cantidad del paquete',
        'stock_actual' => 'Stock actual',
        'stock_minimo' => 'Stock mínimo'
    ];

    foreach ($campos_numericos as $campo => $nombre) {
        if (!is_numeric($valores_persistentes[$campo])) {
            $errores[] = "$nombre debe ser un número válido.";
        } elseif (floatval($valores_persistentes[$campo]) < 0) {
            $errores[] = "$nombre no puede ser negativo.";
        }
    }

    // Validación especial para cantidad_paquete
    if (floatval($valores_persistentes['cantidad_paquete']) <= 0) {
        $errores[] = "La cantidad del paquete debe ser mayor a cero.";
    }

    if (empty($errores)) {
        try {
            // Calcular precio unitario automáticamente
            $precio_unitario = floatval($valores_persistentes['precio_paquete']) / floatval($valores_persistentes['cantidad_paquete']);
            
            $stmt = $conn->prepare("INSERT INTO insumos 
                                  (nombre, unidad_medida, precio_unitario, stock_actual, stock_minimo) 
                                  VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssddd", 
                $valores_persistentes['nombre'],
                $valores_persistentes['unidad_medida'],
                $precio_unitario,
                $valores_persistentes['stock_actual'],
                $valores_persistentes['stock_minimo']
            );

            if ($stmt->execute()) {
                $mensaje_exito = "Insumo '" . htmlspecialchars($valores_persistentes['nombre']) . "' agregado correctamente.";
                
                // Resetear campos (excepto unidad de medida)
                $valores_persistentes = [
                    'nombre' => '',
                    'unidad_medida' => $valores_persistentes['unidad_medida'], // Mantener la misma unidad
                    'precio_paquete' => '0.00',
                    'cantidad_paquete' => '1.00',
                    'stock_actual' => '0.00',
                    'stock_minimo' => '0.00'
                ];
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
    <title>Agregar Insumo</title>
      <link rel="icon" href="/img/favicon.ico" type="image/x-icon" />
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/styles_productos.css">
    <style>
        .form-container { max-width: 600px; margin: 40px auto; padding: 20px; }
        .alert { margin-bottom: 15px; }
        .info-text { font-size: 0.9em; color: #666; margin-top: 5px; }
    </style>
</head>
<body>
    <div class="form-container product-form-container">
        <h1>Agregar Nuevo Insumo</h1>

        <?php if (!empty($errores)): ?>
            <div class="product-alert error">
                <p><strong>Por favor, corrija los siguientes errores:</strong></p>
                <ul>
                    <?php foreach ($errores as $error_msg): ?>
                        <li><?php echo htmlspecialchars($error_msg); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (isset($mensaje_exito)): ?>
            <div class="product-alert success">
                <?php echo htmlspecialchars($mensaje_exito); ?>
            </div>
        <?php endif; ?>

        <form action="agregar_insumo.php" method="post">
            <div class="product-form-group">
                <label for="nombre">Nombre del Insumo:</label>
                <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($valores_persistentes['nombre']); ?>" required>
            </div>

            <div class="product-form-group">
                <label for="unidad_medida">Unidad de Medida:</label>
                <select id="unidad_medida" name="unidad_medida" required>
                    <?php foreach ($unidades_medida as $valor => $texto): ?>
                        <option value="<?php echo $valor; ?>" <?php echo ($valores_persistentes['unidad_medida'] == $valor) ? 'selected' : ''; ?>>
                            <?php echo $texto; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="product-form-group">
                <label for="precio_paquete">Precio del Paquete ($):</label>
                <input type="number" id="precio_paquete" name="precio_paquete" step="0.01" min="0" 
                       value="<?php echo htmlspecialchars($valores_persistentes['precio_paquete']); ?>" required>
                <div class="info-text">Precio total que pagas por el paquete/bolsa/envase</div>
            </div>

            <div class="product-form-group">
                <label for="cantidad_paquete">Cantidad en el Paquete:</label>
                <input type="number" id="cantidad_paquete" name="cantidad_paquete" step="0.01" min="0.01" 
                       value="<?php echo htmlspecialchars($valores_persistentes['cantidad_paquete']); ?>" required>
                <div class="info-text">Cantidad total contenida en el paquete (en la unidad seleccionada)</div>
            </div>

            <div class="product-form-group">
                <label for="stock_actual">Stock Actual:</label>
                <input type="number" id="stock_actual" name="stock_actual" step="0.01" min="0" 
                       value="<?php echo htmlspecialchars($valores_persistentes['stock_actual']); ?>" required>
                <div class="info-text">Cantidad actual en inventario (en la unidad seleccionada)</div>
            </div>

            <div class="product-form-group">
                <label for="stock_minimo">Stock Mínimo (Opcional):</label>
                <input type="number" id="stock_minimo" name="stock_minimo" step="0.01" min="0" 
                       value="<?php echo htmlspecialchars($valores_persistentes['stock_minimo']); ?>">
                <div class="info-text">Cantidad mínima deseada en inventario</div>
            </div>

            <div class="product-button-container">
                <button type="submit" class="product-btn">Guardar Insumo</button>
                <a href="listar_insumos.php" class="product-btn cancel">Ver Lista de Insumos</a>
                <a href="../dashboard.php" class="product-btn cancel">Volver al Dashboard</a>
            </div>
        </form>
    </div>
</body>
</html>
<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

protegerPagina(); // Asegura que el usuario esté logueado

$db = new Database();
$conn = $db->getConnection();

$errores = [];
$nombre_persistente = '';
$unidad_medida_persistente = '';
$stock_actual_persistente = '0.00';
$stock_minimo_persistente = '0.00';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre_persistente = trim($_POST['nombre']);
    $unidad_medida_persistente = trim($_POST['unidad_medida']);
    $stock_actual_persistente = trim($_POST['stock_actual']);
    $stock_minimo_persistente = trim($_POST['stock_minimo']);

    // Validaciones
    if (empty($nombre_persistente)) {
        $errores[] = "El nombre del insumo es obligatorio.";
    } elseif (strlen($nombre_persistente) > 255) {
        $errores[] = "El nombre del insumo no puede exceder los 255 caracteres.";
    }

    if (empty($unidad_medida_persistente)) {
        $errores[] = "La unidad de medida es obligatoria.";
    } elseif (strlen($unidad_medida_persistente) > 50) {
        $errores[] = "La unidad de medida no puede exceder los 50 caracteres.";
    }

    if ($stock_actual_persistente === '') {
        $errores[] = "El stock actual es obligatorio.";
    } elseif (!is_numeric($stock_actual_persistente) || floatval($stock_actual_persistente) < 0) {
        $errores[] = "El stock actual debe ser un número no negativo.";
    } else {
        $stock_actual_persistente = number_format(floatval($stock_actual_persistente), 2, '.', '');
    }

    // Stock mínimo es opcional, pero si se provee, debe ser válido
    if ($stock_minimo_persistente !== '' && (!is_numeric($stock_minimo_persistente) || floatval($stock_minimo_persistente) < 0)) {
        $errores[] = "El stock mínimo debe ser un número no negativo si se especifica.";
    } elseif ($stock_minimo_persistente === '') {
        $stock_minimo_persistente = '0.00'; // Valor por defecto si está vacío
    } else {
        $stock_minimo_persistente = number_format(floatval($stock_minimo_persistente), 2, '.', '');
    }


    if (empty($errores)) {
        try {
            $stmt = $conn->prepare("INSERT INTO insumos (nombre, unidad_medida, stock_actual, stock_minimo) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssdd", $nombre_persistente, $unidad_medida_persistente, $stock_actual_persistente, $stock_minimo_persistente);

            if ($stmt->execute()) {
                // Limpiar campos para el próximo ingreso o redirigir
                $nombre_persistente = '';
                $unidad_medida_persistente = '';
                $stock_actual_persistente = '0.00';
                $stock_minimo_persistente = '0.00';
                // Podríamos tener un mensaje de éxito flash o redirigir a listar_insumos.php
                // header("Location: listar_insumos.php?success=1");
                // exit;
                $mensaje_exito = "Insumo '" . htmlspecialchars($nombre_persistente) . "' agregado correctamente."; // El nombre ya se limpió
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
    <link rel="stylesheet" href="../assets/css/style.css">
    <!-- Podríamos crear insumos_styles.css o usar styles_productos.css si es compatible -->
    <link rel="stylesheet" href="../assets/css/styles_productos.css">
    <style>
        /* Estilos adicionales o específicos para insumos si es necesario */
        .form-container { max-width: 600px; margin: 40px auto; padding: 20px; }
        .alert { margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="form-container product-form-container"> <!-- Reutilizando clases de productos para consistencia -->
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
                <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($nombre_persistente); ?>" required>
            </div>

            <div class="product-form-group">
                <label for="unidad_medida">Unidad de Medida (ej: gr, ml, unidad):</label>
                <input type="text" id="unidad_medida" name="unidad_medida" value="<?php echo htmlspecialchars($unidad_medida_persistente); ?>" required>
            </div>

            <div class="product-form-group">
                <label for="stock_actual">Stock Actual:</label>
                <input type="number" id="stock_actual" name="stock_actual" step="0.01" min="0" value="<?php echo htmlspecialchars($stock_actual_persistente); ?>" required>
            </div>

            <div class="product-form-group">
                <label for="stock_minimo">Stock Mínimo (Opcional):</label>
                <input type="number" id="stock_minimo" name="stock_minimo" step="0.01" min="0" value="<?php echo htmlspecialchars($stock_minimo_persistente); ?>">
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

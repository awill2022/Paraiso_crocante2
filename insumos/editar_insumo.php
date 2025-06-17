<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

protegerPagina();

$db = new Database();
$conn = $db->getConnection();

$insumo_id = 0;
$nombre_persistente = '';
$unidad_medida_persistente = '';
$stock_actual_persistente = '';
$stock_minimo_persistente = '';
$errores = [];
$mensaje_exito = '';

// Obtener ID del insumo de GET
if (isset($_GET['id'])) {
    $insumo_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if ($insumo_id === false || $insumo_id <= 0) {
        header("Location: listar_insumos.php?error=invalid_id");
        exit;
    }
} else if (!isset($_POST['id'])) { // Si no hay ID en GET ni en POST (para persistencia tras error)
    header("Location: listar_insumos.php?error=no_id");
    exit;
}

// Procesamiento del formulario POST para actualizar
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $insumo_id = filter_var($_POST['id'], FILTER_VALIDATE_INT); // Re-validar ID de POST
    $nombre_persistente = trim($_POST['nombre']);
    $unidad_medida_persistente = trim($_POST['unidad_medida']);
    $stock_actual_persistente = trim($_POST['stock_actual']);
    $stock_minimo_persistente = trim($_POST['stock_minimo']);

    if ($insumo_id === false || $insumo_id <= 0) {
        $errores[] = "ID de insumo inválido.";
    }
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

    if ($stock_minimo_persistente !== '' && (!is_numeric($stock_minimo_persistente) || floatval($stock_minimo_persistente) < 0)) {
        $errores[] = "El stock mínimo debe ser un número no negativo si se especifica.";
    } elseif ($stock_minimo_persistente === '') {
        $stock_minimo_persistente = '0.00';
    } else {
        $stock_minimo_persistente = number_format(floatval($stock_minimo_persistente), 2, '.', '');
    }

    if (empty($errores)) {
        try {
            $stmt = $conn->prepare("UPDATE insumos SET nombre = ?, unidad_medida = ?, stock_actual = ?, stock_minimo = ? WHERE id = ?");
            $stmt->bind_param("ssddi", $nombre_persistente, $unidad_medida_persistente, $stock_actual_persistente, $stock_minimo_persistente, $insumo_id);

            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    header("Location: listar_insumos.php?success=updated");
                    exit;
                } else {
                    // No afectó filas, podría ser que no hubo cambios o el ID no existe (aunque se carga antes)
                    $mensaje_exito = "No se realizaron cambios en el insumo (o los datos son iguales).";
                }
            } else {
                $errores[] = "Error al actualizar el insumo: " . $stmt->error;
            }
            $stmt->close();
        } catch (Exception $e) {
            $errores[] = "Error de base de datos: " . $e->getMessage();
        }
    }
} elseif ($insumo_id > 0) { // Cargar datos para GET request (primera carga del formulario)
    try {
        $stmt = $conn->prepare("SELECT nombre, unidad_medida, stock_actual, stock_minimo FROM insumos WHERE id = ?");
        $stmt->bind_param("i", $insumo_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 1) {
            $insumo = $result->fetch_assoc();
            $nombre_persistente = $insumo['nombre'];
            $unidad_medida_persistente = $insumo['unidad_medida'];
            $stock_actual_persistente = number_format($insumo['stock_actual'], 2, '.', '');
            $stock_minimo_persistente = number_format($insumo['stock_minimo'], 2, '.', '');
        } else {
            header("Location: listar_insumos.php?error=not_found");
            exit;
        }
        $stmt->close();
    } catch (Exception $e) {
        $errores[] = "Error al cargar el insumo: " . $e->getMessage();
        // Podríamos mostrar un error más genérico o redirigir
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Insumo</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/styles_productos.css">
     <style>
        .form-container { max-width: 600px; margin: 40px auto; padding: 20px; }
        .alert { margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="form-container product-form-container">
        <h1>Editar Insumo #<?php echo htmlspecialchars($insumo_id); ?></h1>

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

        <?php if ($mensaje_exito && empty($errores)): // Mostrar solo si no hay errores nuevos ?>
            <div class="product-alert info"><?php echo htmlspecialchars($mensaje_exito); ?></div>
        <?php endif; ?>

        <form action="editar_insumo.php" method="post">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($insumo_id); ?>">

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
                <button type="submit" class="product-btn">Actualizar Insumo</button>
                <a href="listar_insumos.php" class="product-btn cancel">Cancelar y Volver a la Lista</a>
            </div>
        </form>
    </div>
</body>
</html>

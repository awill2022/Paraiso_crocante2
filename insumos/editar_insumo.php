<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../includes/config.php';
require_once '../includes/functions.php';

protegerPagina(); // Asegura que el usuario esté logueado

$db = new Database();
$conn = $db->getConnection();

$nombre_persistente = '';
$unidad_medida_persistente = '';
$stock_actual_persistente = '';
$stock_minimo_persistente = '';
$precio_unitario_persistente = '';
$errores = [];
$mensaje_exito = '';

// Unidades de medida predefinidas
$unidades_medida = [
    'ml' => 'Mililitros (ml)',
    'g' => 'Gramos (g)',
    'unidad' => 'Unidad/Pieza',
    'l' => 'Litros (l)',
    'kg' => 'Kilogramos (kg)'
];

// Obtener ID del insumo
if (isset($_GET['id'])) {
    $insumo_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if ($insumo_id === false || $insumo_id <= 0) {
        header("Location: listar_insumos.php?error=invalid_id");
        exit;
    }
} elseif (isset($_POST['id'])) {
    $insumo_id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
} else {
    header("Location: listar_insumos.php?error=no_id");
    exit;
}

// Procesar formulario POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre_persistente = trim($_POST['nombre']);
    $unidad_medida_persistente = $_POST['unidad_medida'];
    $stock_actual_persistente = trim($_POST['stock_actual']);
    $stock_minimo_persistente = trim($_POST['stock_minimo']);
    $precio_unitario_persistente = trim($_POST['precio_unitario']);

    // Validaciones
    if (empty($nombre_persistente)) {
        $errores[] = "El nombre del insumo es obligatorio.";
    }
    if (!array_key_exists($unidad_medida_persistente, $unidades_medida)) {
        $errores[] = "Unidad de medida no válida.";
    }
    if (!is_numeric($stock_actual_persistente) || $stock_actual_persistente < 0) {
        $errores[] = "El stock actual debe ser un número no negativo.";
    } else {
        $stock_actual_persistente = (float)$stock_actual_persistente;
    }
    if ($stock_minimo_persistente === '') {
        $stock_minimo_persistente = 0.00;
    } elseif (!is_numeric($stock_minimo_persistente) || $stock_minimo_persistente < 0) {
        $errores[] = "El stock mínimo debe ser un número no negativo.";
    } else {
        $stock_minimo_persistente = (float)$stock_minimo_persistente;
    }
    if (!is_numeric($precio_unitario_persistente) || $precio_unitario_persistente < 0) {
        $errores[] = "El precio unitario es obligatorio y debe ser un número no negativo.";
    } else {
        $precio_unitario_persistente = (float)$precio_unitario_persistente;
    }

    // Actualizar en la base de datos
    if (empty($errores)) {
        $stmt = $conn->prepare("UPDATE insumos SET nombre = ?, unidad_medida = ?, stock_actual = ?, stock_minimo = ?, precio_unitario = ? WHERE id = ?");
        $stmt->bind_param(
            "ssdddi",
            $nombre_persistente,
            $unidad_medida_persistente,
            $stock_actual_persistente,
            $stock_minimo_persistente,
            $precio_unitario_persistente,
            $insumo_id
        );
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                header("Location: listar_insumos.php?success=updated");
                exit;
            } else {
                $mensaje_exito = "No se realizaron cambios (o los datos son iguales).";
            }
        } else {
            $errores[] = "Error al actualizar: " . $stmt->error;
        }
        $stmt->close();
    }
} else {
    // Cargar datos existentes
    $stmt = $conn->prepare("SELECT nombre, unidad_medida, stock_actual, stock_minimo, precio_unitario FROM insumos WHERE id = ?");
    $stmt->bind_param("i", $insumo_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $insumo = $result->fetch_assoc();
        $nombre_persistente = $insumo['nombre'];
        $unidad_medida_persistente = $insumo['unidad_medida'];
        $stock_actual_persistente = $insumo['stock_actual'];
        $stock_minimo_persistente = $insumo['stock_minimo'];
        $precio_unitario_persistente = $insumo['precio_unitario'];
    } else {
        header("Location: listar_insumos.php?error=not_found");
        exit;
    }
    $stmt->close();
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
        .product-alert { margin-bottom: 15px; padding: 10px; border-radius: 5px; }
        .product-alert.error { background: #f8d7da; color: #842029; }
        .product-alert.success { background: #d1e7dd; color: #0f5132; }
        .product-form-group { margin-bottom: 15px; }
        label { display: block; font-weight: bold; margin-bottom: 5px; }
        input, select { width: 100%; padding: 8px; box-sizing: border-box; }
        .product-button-container { margin-top: 20px; }
        .product-btn { padding: 10px 20px; margin-right: 10px; text-decoration: none; display: inline-block; }
        .product-btn.cancel { background: #ccc; color: #000; }
        .product-btn:hover { opacity: 0.9; }
    </style>
</head>
<body>
<div class="form-container product-form-container">
    <h1>Editar Insumo #<?php echo htmlspecialchars($insumo_id); ?></h1>

    <?php if (!empty($errores)): ?>
        <div class="product-alert error">
            <ul>
                <?php foreach ($errores as $e): ?>
                    <li><?php echo htmlspecialchars($e); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($mensaje_exito): ?>
        <div class="product-alert success"><?php echo htmlspecialchars($mensaje_exito); ?></div>
    <?php endif; ?>

    <form action="editar_insumo.php?id=<?php echo htmlspecialchars($insumo_id); ?>" method="post">
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($insumo_id); ?>">

        <div class="product-form-group">
            <label for="nombre">Nombre del Insumo:</label>
            <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($nombre_persistente); ?>" required>
        </div>

        <div class="product-form-group">
            <label for="unidad_medida">Unidad de Medida:</label>
            <select id="unidad_medida" name="unidad_medida" required>
                <?php foreach ($unidades_medida as $valor => $texto): ?>
                    <option value="<?php echo $valor; ?>" <?php echo ($unidad_medida_persistente == $valor) ? 'selected' : ''; ?>>
                        <?php echo $texto; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="product-form-group">
            <label for="stock_actual">Stock Actual:</label>
            <input type="number" id="stock_actual" name="stock_actual" step="0.01" min="0" value="<?php echo htmlspecialchars($stock_actual_persistente); ?>" required>
        </div>

        <div class="product-form-group">
            <label for="stock_minimo">Stock Mínimo:</label>
            <input type="number" id="stock_minimo" name="stock_minimo" step="0.01" min="0" value="<?php echo htmlspecialchars($stock_minimo_persistente); ?>">
        </div>

        <div class="product-form-group">
            <label for="precio_unitario">Precio Unitario ($):</label>
            <input type="number" id="precio_unitario" name="precio_unitario"  value="<?php echo htmlspecialchars($precio_unitario_persistente); ?>" required>
        </div>

        <div class="product-button-container">
            <button type="submit" class="product-btn">Actualizar Insumo</button>
            <a href="listar_insumos.php" class="product-btn cancel">Cancelar</a>
        </div>
    </form>
</div>
</body>
</html>

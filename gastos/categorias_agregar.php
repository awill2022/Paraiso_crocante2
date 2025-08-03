<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

protegerPagina();

$db = new Database();
$conn = $db->getConnection();

$nombre_persistente = '';
$descripcion_persistente = '';
$errores = [];
$mensaje_exito = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre_persistente = trim($_POST['nombre']);
    $descripcion_persistente = trim($_POST['descripcion']);

    // Validaciones
    if (empty($nombre_persistente)) {
        $errores[] = "El nombre de la categoría es obligatorio.";
    } elseif (strlen($nombre_persistente) > 255) {
        $errores[] = "El nombre de la categoría no puede exceder los 255 caracteres.";
    } else {
        // Verificar unicidad del nombre
        try {
            $stmt_check = $conn->prepare("SELECT id FROM categorias_gastos WHERE nombre = ?");
            $stmt_check->bind_param("s", $nombre_persistente);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            if ($result_check->num_rows > 0) {
                $errores[] = "Ya existe una categoría de gasto con este nombre.";
            }
            $stmt_check->close();
        } catch (Exception $e) {
            $errores[] = "Error al verificar el nombre de la categoría: " . $e->getMessage();
        }
    }

    if (strlen($descripcion_persistente) > 65535) { // TEXT max length
        $errores[] = "La descripción es demasiado larga.";
    }

    if (empty($errores)) {
        try {
            $stmt = $conn->prepare("INSERT INTO categorias_gastos (nombre, descripcion) VALUES (?, ?)");
            $stmt->bind_param("ss", $nombre_persistente, $descripcion_persistente);

            if ($stmt->execute()) {
                $mensaje_exito = "Categoría de gasto '" . htmlspecialchars($nombre_persistente) . "' agregada correctamente.";
                // Limpiar campos para el próximo ingreso
                $nombre_persistente = '';
                $descripcion_persistente = '';
                // Considerar redirigir a listar: header("Location: categorias_listar.php?success=added"); exit;
            } else {
                $errores[] = "Error al agregar la categoría de gasto: " . $stmt->error;
            }
            $stmt->close();
        } catch (Exception $e) {
            $errores[] = "Error de base de datos: " . $e->getMessage();
        }
    }
}
// $conn->close(); // Cerrar la conexión al final del script si no hay más operaciones.
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Categoría de Gasto</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/styles_productos.css"> <!-- Reutilizar si es adecuado -->
    <style>
        .form-container { max-width: 600px; margin: 40px auto; padding: 20px; }
    </style>
</head>
<body>
    <div class="form-container product-form-container">
        <h1>Agregar Nueva Categoría de Gasto</h1>

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

        <?php if ($mensaje_exito): ?>
            <div class="product-alert success">
                <?php echo htmlspecialchars($mensaje_exito); ?>
            </div>
        <?php endif; ?>

        <form action="categorias_agregar.php" method="post">
            <div class="product-form-group">
                <label for="nombre">Nombre:</label>
                <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($nombre_persistente); ?>" required>
            </div>

            <div class="product-form-group">
                <label for="descripcion">Descripción (Opcional):</label>
                <textarea id="descripcion" name="descripcion" rows="4"><?php echo htmlspecialchars($descripcion_persistente); ?></textarea>
            </div>

            <div class="product-button-container">
                <button type="submit" class="product-btn">Guardar Categoría</button>
                <a href="categorias_listar.php" class="product-btn cancel">Ver Lista de Categorías</a>
                <a href="../dashboard.php" class="product-btn cancel" style="margin-left: 10px;">Volver al Dashboard</a>
            </div>
        </form>
    </div>
<?php if(isset($conn)) $conn->close(); ?>
</body>
</html>

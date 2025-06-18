<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

protegerPagina();

$db = new Database();
$conn = $db->getConnection();

$categoria_id = 0;
$nombre_persistente = '';
$descripcion_persistente = '';
$errores = [];
$mensaje_exito = ''; // Para mensajes como "no hubo cambios"

// Obtener ID de la categoría de gasto
if (isset($_GET['id'])) {
    $categoria_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if (!$categoria_id || $categoria_id <= 0) {
        header("Location: categorias_listar.php?error=invalid_id");
        exit;
    }
} elseif (isset($_POST['id'])) {
    $categoria_id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
     if (!$categoria_id || $categoria_id <= 0) {
        // Esto sería un error grave si el ID del POST es manipulado incorrectamente
        header("Location: categorias_listar.php?error=invalid_id_post");
        exit;
    }
} else {
    header("Location: categorias_listar.php?error=no_id");
    exit;
}

// Procesamiento del formulario POST para actualizar
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre_persistente = trim($_POST['nombre']);
    $descripcion_persistente = trim($_POST['descripcion']);

    // Validaciones
    if (empty($nombre_persistente)) {
        $errores[] = "El nombre de la categoría es obligatorio.";
    } elseif (strlen($nombre_persistente) > 255) {
        $errores[] = "El nombre de la categoría no puede exceder los 255 caracteres.";
    } else {
        // Verificar unicidad del nombre (excluyendo la categoría actual)
        try {
            $stmt_check = $conn->prepare("SELECT id FROM categorias_gastos WHERE nombre = ? AND id != ?");
            $stmt_check->bind_param("si", $nombre_persistente, $categoria_id);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            if ($result_check->num_rows > 0) {
                $errores[] = "Ya existe otra categoría de gasto con este nombre.";
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
            $stmt = $conn->prepare("UPDATE categorias_gasto SET nombre = ?, descripcion = ? WHERE id = ?");
            $stmt->bind_param("ssi", $nombre_persistente, $descripcion_persistente, $categoria_id);

            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    header("Location: categorias_listar.php?success=updated");
                    exit;
                } else {
                    $mensaje_exito = "No se realizaron cambios (o los datos son iguales a los existentes).";
                }
            } else {
                $errores[] = "Error al actualizar la categoría: " . $stmt->error;
            }
            $stmt->close();
        } catch (Exception $e) {
            $errores[] = "Error de base de datos: " . $e->getMessage();
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') { // Cargar datos para el formulario (solo en GET inicial)
    if ($categoria_id > 0) {
        try {
            $stmt_load = $conn->prepare("SELECT nombre, descripcion FROM categorias_gasto WHERE id = ?");
            $stmt_load->bind_param("i", $categoria_id);
            $stmt_load->execute();
            $result_load = $stmt_load->get_result();
            if ($result_load->num_rows === 1) {
                $categoria = $result_load->fetch_assoc();
                $nombre_persistente = $categoria['nombre'];
                $descripcion_persistente = $categoria['descripcion'];
            } else {
                header("Location: categorias_listar.php?error=not_found");
                exit;
            }
            $stmt_load->close();
        } catch (Exception $e) {
            $errores[] = "Error al cargar la categoría: " . $e->getMessage();
        }
    } else { // Si $categoria_id no es > 0 después de la validación GET inicial.
        header("Location: categorias_listar.php?error=invalid_id");
        exit;
    }
}
// $conn->close(); // Se cierra al final
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Categoría de Gasto</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/styles_productos.css">
     <style>
        .form-container { max-width: 600px; margin: 40px auto; padding: 20px; }
    </style>
</head>
<body>
    <div class="form-container product-form-container">
        <h1>Editar Categoría de Gasto #<?php echo htmlspecialchars($categoria_id); ?></h1>

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

        <?php if ($mensaje_exito && empty($errores)): ?>
            <div class="product-alert success"><?php echo htmlspecialchars($mensaje_exito); ?></div>
        <?php endif; ?>

        <form action="categorias_editar.php" method="post">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($categoria_id); ?>">

            <div class="product-form-group">
                <label for="nombre">Nombre:</label>
                <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($nombre_persistente); ?>" required>
            </div>

            <div class="product-form-group">
                <label for="descripcion">Descripción (Opcional):</label>
                <textarea id="descripcion" name="descripcion" rows="4"><?php echo htmlspecialchars($descripcion_persistente); ?></textarea>
            </div>

            <div class="product-button-container">
                <button type="submit" class="product-btn">Actualizar Categoría</button>
                <a href="categorias_listar.php" class="product-btn cancel">Cancelar y Volver a la Lista</a>
            </div>
        </form>
    </div>
<?php if(isset($conn)) $conn->close(); ?>
</body>
</html>

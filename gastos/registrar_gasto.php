<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

protegerPagina(); // Asegura que el usuario esté logueado

$db = new Database();
$conn = $db->getConnection();

// Cargar categorías de gasto para el selector
$categorias_gasto = [];
try {
    $result_cat = $conn->query("SELECT id, nombre FROM categorias_gastos ORDER BY nombre ASC");
    if ($result_cat) {
        while ($row_cat = $result_cat->fetch_assoc()) {
            $categorias_gasto[] = $row_cat;
        }
    } else {
        $errores[] = "Error al cargar las categorías de gasto: " . $conn->error;
    }
} catch (Exception $e) {
    $errores[] = "Excepción al cargar categorías: " . $e->getMessage();
}


$errores = [];
$mensaje_exito = '';

// Variables de persistencia
$fecha_persistente = date('Y-m-d'); // Hoy por defecto
$monto_persistente = '';
$descripcion_persistente = '';
$categoria_id_persistente = '';
$proveedor_persistente = '';
$recurrente_persistente = 0; // 0 por defecto (no marcado)
$nombre_archivo_comprobante = null;


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fecha_persistente = trim($_POST['fecha']);
    $monto_persistente = trim($_POST['monto']);
    $descripcion_persistente = trim($_POST['descripcion']);
    $categoria_id_persistente = trim($_POST['categoria_id']);
    $proveedor_persistente = trim($_POST['proveedor']);
    $recurrente_persistente = isset($_POST['recurrente']) ? 1 : 0;
    $usuario_id = $_SESSION['usuario_id'];

    // Validaciones
    if (empty($fecha_persistente)) {
        $errores[] = "La fecha del gasto es obligatoria.";
    } else {
        // Validar formato YYYY-MM-DD
        $d = DateTime::createFromFormat('Y-m-d', $fecha_persistente);
        if (!$d || $d->format('Y-m-d') !== $fecha_persistente) {
            $errores[] = "Formato de fecha inválido. Use YYYY-MM-DD.";
        }
    }

    if (empty($monto_persistente)) {
        $errores[] = "El monto del gasto es obligatorio.";
    } elseif (!is_numeric($monto_persistente) || floatval($monto_persistente) <= 0) {
        $errores[] = "El monto debe ser un número positivo.";
    } else {
        $monto_persistente = number_format(floatval($monto_persistente), 2, '.', '');
    }

    if (empty($descripcion_persistente)) {
        $errores[] = "La descripción del gasto es obligatoria.";
    }

    if (empty($categoria_id_persistente) || !filter_var($categoria_id_persistente, FILTER_VALIDATE_INT) || (int)$categoria_id_persistente <=0) {
        $errores[] = "Debe seleccionar una categoría válida.";
    } else {
        // Verificar si la categoría existe
        $stmt_check_cat = $conn->prepare("SELECT id FROM categorias_gastos WHERE id = ?");
        $stmt_check_cat->bind_param("i", $categoria_id_persistente);
        $stmt_check_cat->execute();
        if ($stmt_check_cat->get_result()->num_rows === 0) {
            $errores[] = "La categoría seleccionada no existe.";
        }
        $stmt_check_cat->close();
    }

    if (strlen($proveedor_persistente) > 255) {
        $errores[] = "El nombre del proveedor no puede exceder los 255 caracteres.";
    }

    // Manejo de subida de archivo (comprobante)
    if (isset($_FILES['comprobante']) && $_FILES['comprobante']['error'] === UPLOAD_ERR_OK) {
        $comprobante_info = $_FILES['comprobante'];
        $nombre_original = $comprobante_info['name'];
        $tamaño_archivo = $comprobante_info['size'];
        $tipo_archivo = $comprobante_info['type'];
        $nombre_temporal = $comprobante_info['tmp_name'];

        $extension_archivo = strtolower(pathinfo($nombre_original, PATHINFO_EXTENSION));
        $tipos_permitidos = ['jpg', 'jpeg', 'png', 'pdf'];
        $tamaño_maximo_mb = 5; // 5MB
        $tamaño_maximo_bytes = $tamaño_maximo_mb * 1024 * 1024;

        if (!in_array($extension_archivo, $tipos_permitidos)) {
            $errores[] = "Tipo de archivo de comprobante no permitido. Solo JPG, JPEG, PNG, PDF.";
        } elseif ($tamaño_archivo > $tamaño_maximo_bytes) {
            $errores[] = "El archivo de comprobante excede el tamaño máximo de " . $tamaño_maximo_mb . "MB.";
        } else {
            $directorio_subida = '../assets/comprobantes_gastos/';
            // Asegurarse de que el directorio existe y tiene permisos de escritura (esto se debe hacer manualmente en el servidor)
            // if (!is_dir($directorio_subida)) { mkdir($directorio_subida, 0755, true); } // PHP no siempre puede crear directorios por permisos

            $nombre_archivo_comprobante = "comprobante_gasto_" . $usuario_id . "_" . time() . "." . $extension_archivo;
            $ruta_destino = $directorio_subida . $nombre_archivo_comprobante;

            if (!move_uploaded_file($nombre_temporal, $ruta_destino)) {
                $errores[] = "Error al mover el archivo de comprobante subido. Verifique los permisos del directorio.";
                $nombre_archivo_comprobante = null; // No guardar si falla la subida
            }
        }
    } elseif (isset($_FILES['comprobante']) && $_FILES['comprobante']['error'] !== UPLOAD_ERR_NO_FILE) {
        $errores[] = "Error al subir el comprobante. Código de error: " . $_FILES['comprobante']['error'];
    }


    if (empty($errores)) {
        try {
            $sql = "INSERT INTO gastos (fecha, monto, descripcion, categoria_id, usuario_id, proveedor, recurrente, comprobante)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sdsiiiss",
                $fecha_persistente,
                $monto_persistente,
                $descripcion_persistente,
                $categoria_id_persistente,
                $usuario_id,
                $proveedor_persistente, // Puede ser cadena vacía si es opcional y no se llenó
                $recurrente_persistente,
                $nombre_archivo_comprobante // Puede ser NULL si no se subió archivo
            );

            if ($stmt->execute()) {
                $mensaje_exito = "Gasto registrado correctamente.";
                // Limpiar campos para el próximo ingreso
                $fecha_persistente = date('Y-m-d');
                $monto_persistente = '';
                $descripcion_persistente = '';
                $categoria_id_persistente = '';
                $proveedor_persistente = '';
                $recurrente_persistente = 0;
                $nombre_archivo_comprobante = null;
                // Podríamos redirigir: header("Location: listar_gastos.php?success=added"); exit;
            } else {
                $errores[] = "Error al registrar el gasto: " . $stmt->error;
            }
            $stmt->close();
        } catch (Exception $e) {
            $errores[] = "Error de base de datos: " . $e->getMessage();
        }
    }
}
// $conn->close(); // Se cierra al final del script
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registrar Nuevo Gasto</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/styles_productos.css"> <!-- Reutilizar si es adecuado -->
    <style>
        .form-container { max-width: 700px; margin: 40px auto; padding: 20px; }
        .checkbox-group { display: flex; align-items: center; margin-bottom: 15px;}
        .checkbox-group input[type="checkbox"] { margin-right: 8px; width:auto; }
        .checkbox-group label { margin-bottom: 0; font-weight: normal;}
    </style>
</head>
<body>
    <div class="form-container product-form-container">
        <h1>Registrar Nuevo Gasto</h1>

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

        <form action="registrar_gasto.php" method="post" enctype="multipart/form-data">
            <div class="product-form-group">
                <label for="fecha">Fecha del Gasto:</label>
                <input type="date" id="fecha" name="fecha" value="<?php echo htmlspecialchars($fecha_persistente); ?>" required>
            </div>

            <div class="product-form-group">
                <label for="monto">Monto:</label>
                <input type="number" id="monto" name="monto" step="0.01" min="0.01" value="<?php echo htmlspecialchars($monto_persistente); ?>" required>
            </div>

            <div class="product-form-group">
                <label for="descripcion">Descripción:</label>
                <textarea id="descripcion" name="descripcion" rows="3" required><?php echo htmlspecialchars($descripcion_persistente); ?></textarea>
            </div>

            <div class="product-form-group">
                <label for="categoria_id">Categoría del Gasto:</label>
                <select id="categoria_id" name="categoria_id" required>
                    <option value="">Seleccione una categoría...</option>
                    <?php foreach ($categorias_gasto as $categoria): ?>
                        <option value="<?php echo $categoria['id']; ?>" <?php echo ($categoria_id_persistente == $categoria['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($categoria['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="product-form-group">
                <label for="proveedor">Proveedor (Opcional):</label>
                <input type="text" id="proveedor" name="proveedor" value="<?php echo htmlspecialchars($proveedor_persistente); ?>">
            </div>

            <div class="product-form-group checkbox-group">
                <input type="checkbox" id="recurrente" name="recurrente" value="1" <?php echo $recurrente_persistente ? 'checked' : ''; ?>>
                <label for="recurrente">¿Es un gasto recurrente?</label>
            </div>

            <div class="product-form-group">
                <label for="comprobante">Comprobante (Opcional - JPG, PNG, PDF, máx 5MB):</label>
                <input type="file" id="comprobante" name="comprobante" accept="image/jpeg,image/png,application/pdf">
            </div>

            <div class="product-button-container">
                <button type="submit" class="product-btn">Guardar Gasto</button>
                <!-- <a href="listar_gastos.php" class="product-btn cancel">Ver Lista de Gastos</a> -->
                <a href="../dashboard.php" class="product-btn cancel" style="margin-left: 10px;">Volver al Dashboard</a>
            </div>
        </form>
    </div>
<?php if(isset($conn)) $conn->close(); ?>
</body>
</html>

<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

protegerPagina();

$db = new Database();
$conn = $db->getConnection();

$categorias_result = $conn->query("SELECT * FROM categorias"); // Renombrado para evitar conflicto en el while

$errores = [];
$nombre_persistente = '';
$categoria_id_persistente = '';
$precio_persistente = '';
$descripcion_persistente = '';
$stock_persistente = ''; // Variable para persistencia del stock

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recoger y sanitizar datos del formulario
    $nombre_persistente = trim($_POST['nombre']);
    $categoria_id_persistente = $_POST['categoria_id']; // Se validará como entero después
    $precio_persistente = $_POST['precio']; // Se validará como float después
    $stock_persistente = $_POST['stock']; // Se validará como entero después
    $descripcion_persistente = trim($_POST['descripcion']);
    $foto = 'default.jpg'; // Valor por defecto

    // 1. Validaciones de campos
    // Nombre
    if (empty($nombre_persistente)) {
        $errores[] = "El nombre del producto es obligatorio.";
    } elseif (strlen($nombre_persistente) > 255) {
        $errores[] = "El nombre del producto no puede exceder los 255 caracteres.";
    }

    // Categoría ID
    if (empty($categoria_id_persistente)) {
        $errores[] = "Debe seleccionar una categoría.";
    } elseif (!filter_var($categoria_id_persistente, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]])) {
        $errores[] = "La categoría seleccionada no es válida.";
    } else {
        $categoria_id_persistente = (int)$categoria_id_persistente; // Convertir a entero si es válido
    }


    // Precio
    if (empty($precio_persistente)) {
        $errores[] = "El precio del producto es obligatorio.";
    } elseif (!filter_var($precio_persistente, FILTER_VALIDATE_FLOAT) || (float)$precio_persistente <= 0) {
        $errores[] = "El precio debe ser un número mayor que 0.";
    } else {
        $precio_persistente = (float)$precio_persistente; // Convertir a float si es válido
    }

    // Descripción
    if (!empty($descripcion_persistente) && strlen($descripcion_persistente) > 1000) {
        $errores[] = "La descripción no puede exceder los 1000 caracteres.";
    }

    // Stock
    // Primero verificar si está seteado y no es una cadena vacía antes de filter_var
    if (!isset($_POST['stock']) || $_POST['stock'] === '') {
        $errores[] = "El stock del producto es obligatorio.";
    } elseif (!filter_var($stock_persistente, FILTER_VALIDATE_INT) || (int)$stock_persistente < 0) {
        $errores[] = "El stock debe ser un número entero igual o mayor que 0.";
    } else {
        $stock_persistente = (int)$stock_persistente; // Convertir a entero si es válido
    }

    // 2. Validación de subida de imagen (foto)
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK && $_FILES['foto']['size'] > 0) {
        $nombre_archivo_original = $_FILES['foto']['name'];
        $extension_archivo = strtolower(pathinfo($nombre_archivo_original, PATHINFO_EXTENSION));
        $tipos_permitidos = ['jpg', 'jpeg', 'png', 'gif'];
        $tamaño_maximo = 2 * 1024 * 1024; // 2MB

        if (!in_array($extension_archivo, $tipos_permitidos)) {
            $errores[] = "Error en la imagen: Solo se permiten archivos JPG, JPEG, PNG y GIF.";
        } elseif ($_FILES['foto']['size'] > $tamaño_maximo) {
            $errores[] = "Error en la imagen: El archivo no debe exceder los 2MB.";
        } else {
            // Generar un nombre único para la imagen
            $foto_temporal = $_FILES['foto']['tmp_name'];
            $nuevo_nombre_foto = 'producto-' . time() . '.' . $extension_archivo;
            $ruta_destino = '../../assets/img/productos/' . $nuevo_nombre_foto;

            // Generar un nombre único para la imagen
            $foto_temporal = $_FILES['foto']['tmp_name'];
            $nuevo_nombre_foto = 'producto-' . time() . '.' . $extension_archivo;
            $ruta_directorio_destino = '../../assets/img/productos/';
            $ruta_destino = $ruta_directorio_destino . $nuevo_nombre_foto;

            // Solo si no hay otros errores Y el directorio es escribible, intentar mover el archivo
       /*  if (empty($errores)) {
                if (!is_writable($ruta_directorio_destino)) {
                    $errores[] = "Error de configuración: El directorio de imágenes no tiene permisos de escritura. Contacte al administrador.";
                } elseif (move_uploaded_file($foto_temporal, $ruta_destino)) {
                    $foto = $nuevo_nombre_foto;
                } else {
                    // Este error podría ser por otros motivos además de permisos, ej. disco lleno.
                    $errores[] = "Error al subir la imagen. Inténtelo de nuevo o contacte al administrador si el problema persiste.";
                }
            }*/
        }
    } elseif (isset($_FILES['foto']) && $_FILES['foto']['error'] !== UPLOAD_ERR_OK && $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE) {
        // Error real en la subida, no es que simplemente no se subió archivo
        $errores[] = "Ocurrió un error al subir el archivo de imagen. Código de error: " . $_FILES['foto']['error'];
    }


    // 3. Manejo de Errores y Guardado
    if (empty($errores)) {
        $stmt = $conn->prepare("INSERT INTO productos (nombre, categoria_id, precio, descripcion, foto, stock) VALUES (?, ?, ?, ?, ?, ?)");
        // Usar las variables sanitizadas y validadas
        $stmt->bind_param("siddsi", $nombre_persistente, $categoria_id_persistente, $precio_persistente, $descripcion_persistente, $foto, $stock_persistente);

        if ($stmt->execute()) {
            header("Location: listar.php?success=1");
            exit;
        } else {
            // Este error es improbable si las validaciones son exhaustivas, pero se mantiene por si acaso
            $errores[] = "Error al agregar el producto en la base de datos: " . $stmt->error;
        }
    }
    // Si hay errores, la ejecución continúa y se muestran los errores y el formulario con valores persistentes.
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Producto</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/styles_productos.css">
</head>
<body>
<div class="product-form-container">
    <h1>Agregar Nuevo Producto</h1>

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
    
    <form action="agregar.php" method="post" enctype="multipart/form-data">
        <div class="product-form-group">
            <label for="nombre">Nombre:</label>
            <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($nombre_persistente); ?>" required>
        </div>
        
        <div class="product-form-group">
            <label for="categoria_id">Categoría:</label>
            <select id="categoria_id" name="categoria_id" required>
                <option value="">Seleccione una categoría</option>
                <?php
                // Resetear el puntero del resultado de categorías si ya fue iterado
                if (isset($categorias_result) && $categorias_result instanceof mysqli_result) {
                    $categorias_result->data_seek(0);
                }
                while($categoria = $categorias_result->fetch_assoc()): ?>
                <option value="<?php echo $categoria['id']; ?>" <?php echo ($categoria_id_persistente == $categoria['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($categoria['nombre']); ?>
                </option>
                <?php endwhile; ?>
            </select>
        </div>
        
        <div class="product-form-group">
            <label for="precio">Precio:</label>
            <input type="number" id="precio" name="precio" step="0.01" min="0.01" value="<?php echo htmlspecialchars($precio_persistente); ?>" required>
        </div>

        <div class="product-form-group">
            <label for="stock">Stock Inicial:</label>
            <input type="number" id="stock" name="stock" min="0" value="<?php echo htmlspecialchars($stock_persistente); ?>" required>
        </div>
        
        <div class="product-form-group">
            <label for="descripcion">Descripción:</label>
            <textarea id="descripcion" name="descripcion"><?php echo htmlspecialchars($descripcion_persistente); ?></textarea>
        </div>
        
        <div class="product-form-group">
            <label for="foto">Imagen del Producto:</label>
            <input type="file" id="foto" name="foto" accept="image/*">
            <img id="image-preview" alt="Vista previa de la imagen">
        </div>
        
        <div class="product-button-container">
            <button type="submit" class="product-btn">Guardar Producto</button>
            <a href="listar.php" class="product-btn cancel">Cancelar</a>
        </div>
    </form>
</div>
<script>
document.getElementById('foto').addEventListener('change', function(event) {
    const file = event.target.files[0];
    const preview = document.getElementById('image-preview');
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(file);
    }
});
</script>
</body>
</html>
<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

protegerPagina();

$db = new Database();
$conn = $db->getConnection();

$producto_id = 0;
$errores = [];
$mensaje_exito = '';

// Variables de persistencia para el producto principal
$nombre_persistente = '';
$categoria_id_persistente = '';
$precio_persistente = '';
// $stock_persistente = ''; // Eliminado
$descripcion_persistente = '';
$foto_actual = ''; // Para mostrar la imagen actual

// Variables para insumos
$lista_insumos_php = []; // Todos los insumos disponibles: id, nombre, unidad_medida
$insumos_vinculados_php = []; // Insumos ya vinculados a este producto: insumo_id, cantidad_consumida

// --- Carga de Insumos Disponibles ---
try {
    $result_insumos_disp = $conn->query("SELECT id, nombre, unidad_medida FROM insumos ORDER BY nombre ASC");
    if ($result_insumos_disp) {
        while ($row = $result_insumos_disp->fetch_assoc()) {
            $lista_insumos_php[] = $row;
        }
    } else {
        $errores[] = "Error al cargar la lista de insumos disponibles: " . $conn->error;
    }
} catch (Exception $e) {
    $errores[] = "Excepción al cargar insumos disponibles: " . $e->getMessage();
}


// --- Identificar y Cargar Datos del Producto (GET Request) ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $producto_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if (!$producto_id || $producto_id <= 0) {
        header("Location: listar.php?error=invalid_id");
        exit;
    }

    try {
        // Cargar datos del producto principal
        $stmt_producto = $conn->prepare("SELECT * FROM productos WHERE id = ?");
        $stmt_producto->bind_param("i", $producto_id);
        $stmt_producto->execute();
        $result_producto = $stmt_producto->get_result();

        if ($result_producto->num_rows === 1) {
            $producto = $result_producto->fetch_assoc();
            $nombre_persistente = $producto['nombre'];
            $categoria_id_persistente = $producto['categoria_id'];
            $precio_persistente = number_format($producto['precio'], 2, '.', '');
            // $stock_persistente = $producto['stock']; // Eliminado
            $descripcion_persistente = $producto['descripcion'];
            $foto_actual = $producto['foto'];
        } else {
            header("Location: listar.php?error=not_found");
            exit;
        }
        $stmt_producto->close();

        // Cargar insumos vinculados a este producto
        $stmt_insumos_vinc = $conn->prepare("SELECT insumo_id, cantidad_consumida FROM producto_insumos WHERE producto_id = ?");
        $stmt_insumos_vinc->bind_param("i", $producto_id);
        $stmt_insumos_vinc->execute();
        $result_insumos_vinc = $stmt_insumos_vinc->get_result();
        while ($row = $result_insumos_vinc->fetch_assoc()) {
            $insumos_vinculados_php[] = $row;
        }
        $stmt_insumos_vinc->close();

    } catch (Exception $e) {
        $errores[] = "Error al cargar datos del producto o insumos: " . $e->getMessage();
        // Considerar no salir aquí para que el usuario vea el error en el formulario
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- Procesamiento del Formulario (POST Request) ---
    $producto_id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
    if (!$producto_id || $producto_id <= 0) {
        $errores[] = "ID de producto inválido.";
        // No salir, mostrar error en el formulario
    }

    $nombre_persistente = trim($_POST['nombre']);
    $categoria_id_persistente = $_POST['categoria_id'];
    $precio_persistente = $_POST['precio'];
    // $stock_persistente = $_POST['stock']; // Eliminado
    $descripcion_persistente = trim($_POST['descripcion']);
    $foto_actual = $_POST['foto_actual']; // Mantener la foto actual si no se sube una nueva

    // Validaciones del producto principal (similar a agregar.php)
    if (empty($nombre_persistente)) $errores[] = "El nombre es obligatorio.";
    if (empty($categoria_id_persistente) || !filter_var($categoria_id_persistente, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]])) {
        $errores[] = "Categoría inválida.";
    } else { $categoria_id_persistente = (int)$categoria_id_persistente; }

    if (empty($precio_persistente) || !filter_var($precio_persistente, FILTER_VALIDATE_FLOAT) || (float)$precio_persistente <= 0) {
        $errores[] = "Precio inválido.";
    } else { $precio_persistente = (float)$precio_persistente; }

    // Stock del producto ya no se valida aquí

    if (strlen($descripcion_persistente) > 1000) $errores[] = "Descripción demasiado larga.";

    // Manejo de la nueva foto (similar a agregar.php)
    $nueva_foto_nombre = $foto_actual;
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK && $_FILES['foto']['size'] > 0) {
        // ... (lógica de validación y subida de imagen igual a agregar.php)
        // Si la subida es exitosa, $nueva_foto_nombre se actualiza.
        // Por brevedad, se omite la repetición, pero debe estar aquí.
        // Ejemplo simplificado:
        $extension = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        $tipos_permitidos = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array($extension, $tipos_permitidos) && $_FILES['foto']['size'] <= 2 * 1024 * 1024) {
            $nuevo_nombre_foto_temp = 'producto-' . $producto_id . '-' . time() . '.' . $extension;
            $ruta_destino = '../../assets/img/productos/' . $nuevo_nombre_foto_temp;
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $ruta_destino)) {
                // Opcional: eliminar foto anterior si no es 'default.jpg' y $foto_actual != $nuevo_nombre_foto_temp
                if ($foto_actual && $foto_actual !== 'default.jpg' && file_exists('../../assets/img/productos/' . $foto_actual)) {
                    // unlink('../../assets/img/productos/' . $foto_actual); // Descomentar con cuidado
                }
                $nueva_foto_nombre = $nuevo_nombre_foto_temp;
            } else {
                $errores[] = "Error al mover la nueva imagen.";
            }
        } else {
            $errores[] = "Archivo de imagen inválido (tipo o tamaño).";
        }
    }

    // Validaciones de Insumos
    $insumos_ids_post = isset($_POST['insumo_id']) ? $_POST['insumo_id'] : [];
    $cantidades_consumidas_post = isset($_POST['cantidad_consumida']) ? $_POST['cantidad_consumida'] : [];
    $insumos_para_guardar = [];

    if (count($insumos_ids_post) !== count($cantidades_consumidas_post)) {
        $errores[] = "Error en los datos de insumos: la cantidad de IDs no coincide con la cantidad de consumos.";
    } else {
        for ($i = 0; $i < count($insumos_ids_post); $i++) {
            $insumo_id_val = filter_var($insumos_ids_post[$i], FILTER_VALIDATE_INT);
            $cantidad_val = filter_var($cantidades_consumidas_post[$i], FILTER_VALIDATE_FLOAT); // Usar float para cantidades

            if ($insumo_id_val && $insumo_id_val > 0 && $cantidad_val && $cantidad_val > 0) {
                $insumos_para_guardar[] = ['id' => $insumo_id_val, 'cantidad' => $cantidad_val];
            } elseif ($insumo_id_val || !empty($cantidades_consumidas_post[$i])) {
                // Si uno está presente pero el otro no o es inválido (excepto si ambos están vacíos)
                $errores[] = "Error en la línea de insumo #" . ($i + 1) . ": ID de insumo o cantidad consumida inválida o faltante.";
            }
        }
    }
    // Para la persistencia en el formulario si hay error, re-poblar $insumos_vinculados_php desde el POST
    if(!empty($errores) && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $insumos_vinculados_php = []; // Limpiar lo cargado de la BD
        for ($i = 0; $i < count($insumos_ids_post); $i++) {
             if (!empty($insumos_ids_post[$i]) || !empty($cantidades_consumidas_post[$i])) {
                $insumos_vinculados_php[] = [
                    'insumo_id' => $insumos_ids_post[$i],
                    'cantidad_consumida' => $cantidades_consumidas_post[$i]
                ];
            }
        }
    }


    if (empty($errores) && $producto_id > 0) {
        $conn->begin_transaction();
        try {
            // 1. Actualizar producto principal (sin stock)
            $stmt_update_prod = $conn->prepare("UPDATE productos SET nombre = ?, categoria_id = ?, precio = ?, descripcion = ?, foto = ? WHERE id = ?");
            $stmt_update_prod->bind_param("sidssi", $nombre_persistente, $categoria_id_persistente, $precio_persistente, $descripcion_persistente, $nueva_foto_nombre, $producto_id);
            $stmt_update_prod->execute();
            $stmt_update_prod->close();

            // 2. Eliminar insumos anteriores para este producto
            $stmt_delete_insumos = $conn->prepare("DELETE FROM producto_insumos WHERE producto_id = ?");
            $stmt_delete_insumos->bind_param("i", $producto_id);
            $stmt_delete_insumos->execute();
            $stmt_delete_insumos->close();

            // 3. Insertar nuevos insumos vinculados
            if (!empty($insumos_para_guardar)) {
                $stmt_insert_insumo = $conn->prepare("INSERT INTO producto_insumos (producto_id, insumo_id, cantidad_consumida) VALUES (?, ?, ?)");
                foreach ($insumos_para_guardar as $insumo_vinc) {
                    $stmt_insert_insumo->bind_param("iid", $producto_id, $insumo_vinc['id'], $insumo_vinc['cantidad']);
                    $stmt_insert_insumo->execute();
                }
                $stmt_insert_insumo->close();
            }

            $conn->commit();
            header("Location: listar.php?success=updated");
            exit;

        } catch (Exception $e) {
            $conn->rollback();
            $errores[] = "Error al actualizar el producto o sus insumos: " . $e->getMessage();
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] !== 'GET') { // Si no es GET inicial ni POST, es un error o intento no válido
    header("Location: listar.php?error=invalid_request");
    exit;
}

$categorias_result = $conn->query("SELECT * FROM categorias"); // Para el select de categorías
// $conn->close(); // Se cierra al final del script o si hay un exit antes.

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Producto #<?php echo htmlspecialchars($producto_id); ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/styles_productos.css">
    <style>
        #insumos-container .insumo-fila { display: flex; align-items: center; margin-bottom: 10px; }
        #insumos-container .insumo-fila select,
        #insumos-container .insumo-fila input[type="number"] { margin-right: 10px; padding: 8px; }
        #insumos-container .insumo-fila input[type="number"] { width: 100px; }
        /* Añadir más estilos según sea necesario */
    </style>
</head>
<body>
<div class="product-form-container">
    <h1>Editar Producto #<?php echo htmlspecialchars($producto_id); ?></h1>

    <?php if (!empty($errores)): ?>
    <div class="product-alert error">
        <p><strong>Por favor, corrija los siguientes errores:</strong></p>
        <ul><?php foreach ($errores as $error_msg): ?><li><?php echo htmlspecialchars($error_msg); ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>
     <?php if ($mensaje_exito): ?>
        <div class="product-alert success"><?php echo htmlspecialchars($mensaje_exito); ?></div>
    <?php endif; ?>

    <form action="editar_producto.php" method="post" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($producto_id); ?>">
        <input type="hidden" name="foto_actual" value="<?php echo htmlspecialchars($foto_actual); ?>">

        <div class="product-form-group">
            <label for="nombre">Nombre:</label>
            <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($nombre_persistente); ?>" required>
        </div>

        <div class="product-form-group">
            <label for="categoria_id">Categoría:</label>
            <select id="categoria_id" name="categoria_id" required>
                <option value="">Seleccione una categoría</option>
                <?php
                if ($categorias_result && $categorias_result->num_rows > 0) {
                    $categorias_result->data_seek(0); // Resetear puntero
                    while($categoria = $categorias_result->fetch_assoc()): ?>
                    <option value="<?php echo $categoria['id']; ?>" <?php echo ($categoria_id_persistente == $categoria['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($categoria['nombre']); ?>
                    </option>
                <?php endwhile;
                }?>
            </select>
        </div>

        <div class="product-form-group">
            <label for="precio">Precio:</label>
            <input type="number" id="precio" name="precio" step="0.01" min="0.01" value="<?php echo htmlspecialchars($precio_persistente); ?>" required>
        </div>

        <!-- Campo de stock del producto eliminado -->

        <div class="product-form-group">
            <label for="descripcion">Descripción:</label>
            <textarea id="descripcion" name="descripcion"><?php echo htmlspecialchars($descripcion_persistente); ?></textarea>
        </div>

        <div class="product-form-group">
            <label for="foto">Imagen del Producto (dejar vacío para no cambiar):</label>
            <input type="file" id="foto" name="foto" accept="image/*">
            <?php if ($foto_actual): ?>
                <p>Imagen actual: <?php echo htmlspecialchars($foto_actual); ?></p>
                <img src="../../assets/img/productos/<?php echo htmlspecialchars($foto_actual); ?>" alt="Imagen actual" style="max-width: 100px; max-height: 100px; margin-top: 5px;">
            <?php endif; ?>
            <img id="image-preview" alt="Vista previa de la nueva imagen" style="display:none; max-width: 100px; max-height: 100px; margin-top: 5px;">
        </div>

        <fieldset class="product-form-group">
            <legend>Insumos Requeridos</legend>
            <div id="insumos-container">
                <!-- Las filas de insumos se añadirán aquí por JavaScript -->
            </div>
            <button type="button" id="btn-agregar-insumo" class="product-btn-icon add" style="margin-top:10px;"><i class="fas fa-plus"></i> Añadir Insumo</button>
        </fieldset>

        <div class="product-button-container">
            <button type="submit" class="product-btn">Actualizar Producto</button>
            <a href="listar.php" class="product-btn cancel">Cancelar</a>
        </div>
    </form>
</div>

<script>
    const listaInsumosGlobal = <?php echo json_encode($lista_insumos_php); ?>;
    const insumosVinculadosGlobal = <?php echo json_encode($insumos_vinculados_php); ?>;

    function crearFilaInsumo(insumoIdSeleccionado = null, cantidadConsumida = '') {
        const container = document.getElementById('insumos-container');
        const filaDiv = document.createElement('div');
        filaDiv.classList.add('insumo-fila');

        // Select para Insumo
        const selectInsumo = document.createElement('select');
        selectInsumo.name = 'insumo_id[]';
        selectInsumo.required = true;
        let optionHtml = '<option value="">Seleccione un insumo...</option>';
        listaInsumosGlobal.forEach(insumo => {
            const selected = (insumo.id == insumoIdSeleccionado) ? 'selected' : '';
            optionHtml += `<option value="${insumo.id}" ${selected}>${insumo.nombre} (${insumo.unidad_medida})</option>`;
        });
        selectInsumo.innerHTML = optionHtml;

        // Input para Cantidad Consumida
        const inputCantidad = document.createElement('input');
        inputCantidad.type = 'number';
        inputCantidad.name = 'cantidad_consumida[]';
        inputCantidad.step = '0.01';
        inputCantidad.min = '0.01'; // O 0 si se permite cantidad 0
        inputCantidad.placeholder = 'Cantidad';
        inputCantidad.value = cantidadConsumida;
        inputCantidad.required = true;

        // Botón para Eliminar Fila
        const btnEliminar = document.createElement('button');
        btnEliminar.type = 'button';
        btnEliminar.innerHTML = '<i class="fas fa-trash"></i>'; // Asumiendo FontAwesome
        btnEliminar.classList.add('product-btn-icon', 'delete');
        btnEliminar.title = 'Eliminar este insumo';
        btnEliminar.onclick = function() {
            container.removeChild(filaDiv);
        };

        filaDiv.appendChild(selectInsumo);
        filaDiv.appendChild(inputCantidad);
        filaDiv.appendChild(btnEliminar);
        container.appendChild(filaDiv);
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Poblar con insumos vinculados existentes al cargar la página
        insumosVinculadosGlobal.forEach(vinc => {
            crearFilaInsumo(vinc.insumo_id, vinc.cantidad_consumida);
        });

        // Event listener para el botón "Añadir Insumo"
        document.getElementById('btn-agregar-insumo').addEventListener('click', function() {
            crearFilaInsumo();
        });

        // Preview de imagen nueva (igual que en agregar.php)
        const fotoInput = document.getElementById('foto');
        const preview = document.getElementById('image-preview');
        if (fotoInput) {
            fotoInput.addEventListener('change', function(event) {
                const file = event.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        if(preview) {
                            preview.src = e.target.result;
                            preview.style.display = 'block';
                        }
                    };
                    reader.readAsDataURL(file);
                } else {
                     if(preview) preview.style.display = 'none';
                }
            });
        }
    });
</script>
<?php if($conn) $conn->close(); ?>
</body>
</html>

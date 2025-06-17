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
// $stock_persistente = ''; // Eliminado para el stock del producto

// Variables para insumos
$lista_insumos_php = [];
$insumos_seleccionados_persistentes = []; // Para repoblar en caso de error [{insumo_id: X, cantidad_consumida: Y}]

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


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recoger y sanitizar datos del formulario principal
    $nombre_persistente = trim($_POST['nombre']);
    $categoria_id_persistente = $_POST['categoria_id'];
    $precio_persistente = $_POST['precio'];
    // $stock_persistente = $_POST['stock']; // Eliminado
    $descripcion_persistente = trim($_POST['descripcion']);
    $foto = 'default.jpg';

    // Recoger datos de insumos para persistencia en caso de error
    $insumos_ids_post = isset($_POST['insumo_id']) ? $_POST['insumo_id'] : [];
    $cantidades_consumidas_post = isset($_POST['cantidad_consumida']) ? $_POST['cantidad_consumida'] : [];
    for ($i = 0; $i < count($insumos_ids_post); $i++) {
        if (!empty($insumos_ids_post[$i]) || !empty($cantidades_consumidas_post[$i])) {
            $insumos_seleccionados_persistentes[] = [
                'insumo_id' => $insumos_ids_post[$i],
                'cantidad_consumida' => $cantidades_consumidas_post[$i]
            ];
        }
    }

    // 1. Validaciones de campos principales
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

    // Stock del producto ya no se valida aquí

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


    // Validaciones de Insumos (similar a editar_producto.php)
    $insumos_para_guardar = [];
    // $insumos_ids_post y $cantidades_consumidas_post ya se obtuvieron para persistencia
    if (count($insumos_ids_post) !== count($cantidades_consumidas_post)) {
        $errores[] = "Error en los datos de insumos: la cantidad de IDs no coincide con la cantidad de consumos.";
    } else {
        for ($i = 0; $i < count($insumos_ids_post); $i++) {
            $insumo_id_val = filter_var($insumos_ids_post[$i], FILTER_VALIDATE_INT);
            $cantidad_val = filter_var($cantidades_consumidas_post[$i], FILTER_VALIDATE_FLOAT);

            if ($insumo_id_val && $insumo_id_val > 0 && $cantidad_val && $cantidad_val > 0) {
                $insumos_para_guardar[] = ['id' => $insumo_id_val, 'cantidad' => $cantidad_val];
            } elseif ($insumo_id_val || !empty($cantidades_consumidas_post[$i])) {
                $errores[] = "Error en la línea de insumo #" . ($i + 1) . ": ID de insumo o cantidad consumida inválida o faltante.";
            }
        }
    }


    // 3. Manejo de Errores y Guardado
    if (empty($errores)) {
        $conn->begin_transaction();
        try {
            // Insertar producto principal (sin stock)
            $stmt_producto = $conn->prepare("INSERT INTO productos (nombre, categoria_id, precio, descripcion, foto) VALUES (?, ?, ?, ?, ?)");
            $stmt_producto->bind_param("sidds", $nombre_persistente, $categoria_id_persistente, $precio_persistente, $descripcion_persistente, $foto);
            $stmt_producto->execute();
            $nuevo_producto_id = $conn->insert_id;
            $stmt_producto->close();

            if ($nuevo_producto_id > 0) {
                // Insertar insumos vinculados
                if (!empty($insumos_para_guardar)) {
                    $stmt_insert_insumo = $conn->prepare("INSERT INTO producto_insumos (producto_id, insumo_id, cantidad_consumida) VALUES (?, ?, ?)");
                    foreach ($insumos_para_guardar as $insumo_vinc) {
                        $stmt_insert_insumo->bind_param("iid", $nuevo_producto_id, $insumo_vinc['id'], $insumo_vinc['cantidad']);
                        $stmt_insert_insumo->execute();
                    }
                    $stmt_insert_insumo->close();
                }
                $conn->commit();
                header("Location: listar.php?success=added"); // Cambiado success=1 a success=added
                exit;
            } else {
                throw new Exception("No se pudo obtener el ID del nuevo producto.");
            }
        } catch (Exception $e) {
            $conn->rollback();
            $errores[] = "Error al agregar el producto o sus insumos: " . $e->getMessage();
        }
    }
    // Si hay errores, la ejecución continúa y se muestran los errores y el formulario con valores persistentes.
}

$categorias_result = $conn->query("SELECT * FROM categorias"); // Para el select de categorías
// $conn->close(); // Se cierra al final del script
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Producto</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/styles_productos.css">
    <style> /* Mismos estilos para insumos que en editar_producto.php */
        #insumos-container .insumo-fila { display: flex; align-items: center; margin-bottom: 10px; }
        #insumos-container .insumo-fila select,
        #insumos-container .insumo-fila input[type="number"] { margin-right: 10px; padding: 8px; }
        #insumos-container .insumo-fila input[type="number"] { width: 100px; }
    </style>
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
        <!-- Campos del producto principal -->
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
                     $categorias_result->data_seek(0);
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
            <label for="foto">Imagen del Producto:</label>
            <input type="file" id="foto" name="foto" accept="image/*">
            <img id="image-preview" alt="Vista previa de la imagen" style="display:none; max-width: 100px; max-height: 100px; margin-top: 5px;">
        </div>

        <!-- Sección de Insumos -->
        <fieldset class="product-form-group">
            <legend>Insumos Requeridos para este Producto</legend>
            <div id="insumos-container">
                <!-- Las filas de insumos se añadirán aquí por JavaScript -->
            </div>
            <button type="button" id="btn-agregar-insumo" class="product-btn-icon add" style="margin-top:10px;"><i class="fas fa-plus"></i> Añadir Insumo</button>
        </fieldset>
        
        <div class="product-button-container">
            <button type="submit" class="product-btn">Guardar Producto</button>
            <a href="listar.php" class="product-btn cancel">Cancelar</a>
        </div>
    </form>
</div>

<script>
    const listaInsumosGlobal = <?php echo json_encode($lista_insumos_php); ?>;
    // Para agregar.php, insumosVinculadosGlobal empieza vacío, pero lo usamos para repoblar en caso de error de validación
    const insumosVinculadosGlobal = <?php echo json_encode($insumos_seleccionados_persistentes); ?>;

    function crearFilaInsumo(insumoIdSeleccionado = null, cantidadConsumida = '') {
        const container = document.getElementById('insumos-container');
        const filaDiv = document.createElement('div');
        filaDiv.classList.add('insumo-fila');

        const selectInsumo = document.createElement('select');
        selectInsumo.name = 'insumo_id[]';
        selectInsumo.required = true;
        let optionHtml = '<option value="">Seleccione un insumo...</option>';
        listaInsumosGlobal.forEach(insumo => {
            const selected = (insumo.id == insumoIdSeleccionado) ? 'selected' : '';
            optionHtml += `<option value="${insumo.id}" ${selected}>${insumo.nombre} (${insumo.unidad_medida})</option>`;
        });
        selectInsumo.innerHTML = optionHtml;

        const inputCantidad = document.createElement('input');
        inputCantidad.type = 'number';
        inputCantidad.name = 'cantidad_consumida[]';
        inputCantidad.step = '0.01';
        inputCantidad.min = '0.01';
        inputCantidad.placeholder = 'Cantidad Consumida';
        inputCantidad.value = cantidadConsumida;
        inputCantidad.required = true;

        const btnEliminar = document.createElement('button');
        btnEliminar.type = 'button';
        btnEliminar.innerHTML = '<i class="fas fa-trash"></i>';
        btnEliminar.classList.add('product-btn-icon', 'delete');
        btnEliminar.title = 'Eliminar este insumo';
        btnEliminar.onclick = function() { container.removeChild(filaDiv); };

        filaDiv.appendChild(selectInsumo);
        filaDiv.appendChild(inputCantidad);
        filaDiv.appendChild(btnEliminar);
        container.appendChild(filaDiv);
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Repoblar con insumos seleccionados si hubo un error de validación y se recargó la página
        insumosVinculadosGlobal.forEach(vinc => {
            crearFilaInsumo(vinc.insumo_id, vinc.cantidad_consumida);
        });

        document.getElementById('btn-agregar-insumo').addEventListener('click', function() {
            crearFilaInsumo();
        });

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
<?php if(isset($conn) && $conn) $conn->close(); ?>
</body>
</body>
</html>
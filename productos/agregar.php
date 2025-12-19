<?php
// Habilitar reporte de errores para diagnóstico
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../includes/config.php';
require_once '../includes/functions.php';

protegerPagina();

$db = new Database();
$conn = $db->getConnection();

// Inicializar variables
$errores = [];
$nombre_persistente = '';
$categoria_id_persistente = '';
$precio_persistente = '';
$descripcion_persistente = '';
$instrucciones_persistente = '';
$lista_elementos_php = [];
$insumos_seleccionados_persistentes = [];

// Cargar categorías
$lista_categorias_php = [];
$result = $conn->query("SELECT id, nombre FROM categorias ORDER BY nombre ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $lista_categorias_php[] = $row;
    }
}

// Cargar insumos y recetas
$lista_insumos_php = [];
$result_insumos = $conn->query("SELECT id, nombre, unidad_medida FROM insumos ORDER BY nombre ASC");
if ($result_insumos) {
    while ($row = $result_insumos->fetch_assoc()) {
        $row['tipo'] = 'insumo';
        $lista_insumos_php[] = $row;
    }
}

$lista_recetas_php = [];
$result_recetas = $conn->query("SELECT id, nombre, costo_unitario FROM recetas ORDER BY nombre ASC");
if ($result_recetas) {
    while ($row = $result_recetas->fetch_assoc()) {
        $row['tipo'] = 'receta';
        $row['unidad_medida'] = 'unidad'; // Asumiendo que las recetas se miden por unidad
        $lista_recetas_php[] = $row;
    }
}

// Unir insumos + recetas
$lista_elementos_php = array_merge($lista_insumos_php, $lista_recetas_php);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recoger y sanitizar datos del formulario
    $nombre_persistente = trim($_POST['nombre'] ?? '');
    $categoria_id_persistente = intval($_POST['categoria'] ?? 0);
    $precio_persistente = floatval($_POST['precio'] ?? 0);
    $descripcion_persistente = trim($_POST['descripcion'] ?? '');
    $instrucciones_persistente = trim($_POST['instrucciones'] ?? '');
    $foto = null;

    // Procesar imagen
    if (!empty($_FILES['foto']['name'])) {
        $nombre_archivo_original = $_FILES['foto']['name'];
        $extension_archivo = strtolower(pathinfo($nombre_archivo_original, PATHINFO_EXTENSION));
        $tipos_permitidos = ['jpg', 'jpeg', 'png', 'gif'];
        $tamaño_maximo = 2 * 1024 * 1024; // 2MB

        if (!in_array($extension_archivo, $tipos_permitidos)) {
            $errores[] = "Error en la imagen: Solo se permiten archivos JPG, JPEG, PNG y GIF.";
        } elseif ($_FILES['foto']['size'] > $tamaño_maximo) {
            $errores[] = "Error en la imagen: El archivo no debe exceder los 2MB.";
        } else {
            $nuevo_nombre_foto = 'producto-' . time() . '.' . $extension_archivo;
            $ruta_destino = "../assets/img/productos/" . $nuevo_nombre_foto;
            
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $ruta_destino)) {
                $foto = $nuevo_nombre_foto;
            } else {
                $errores[] = "Error al subir la imagen. Inténtelo de nuevo.";
            }
        }
    }

    // Procesar insumos/recetas
    $insumos_para_guardar = [];
    if (!empty($_POST['insumos']) && !empty($_POST['cantidades'])) {
     foreach ($_POST['insumos'] as $idx => $valor) {
    list($tipo, $id_elemento) = explode("-", $valor); // ej: insumo-3 o receta-2
    $id_elemento = intval($id_elemento);
    $cantidad = floatval($_POST['cantidades'][$idx] ?? 0);

    if ($id_elemento > 0 && $cantidad > 0) {
        $insumos_para_guardar[] = [
            'tipo' => $tipo,
            'id' => $id_elemento,
            'cantidad' => $cantidad
        ];
        $insumos_seleccionados_persistentes[] = [
            'tipo' => $tipo,
            'id' => $id_elemento,
            'cantidad' => $cantidad
        ];
    }
}

    }

    // Validaciones
    if (empty($nombre_persistente)) {
        $errores[] = "El nombre del producto es obligatorio.";
    }

    if ($categoria_id_persistente <= 0) {
        $errores[] = "Debe seleccionar una categoría válida.";
    }

    if ($precio_persistente <= 0) {
        $errores[] = "El precio debe ser mayor que 0.";
    }

    // Calcular costo total
// Calcular costo total (RESPETA tipo)
$costo_total = 0.0;

foreach ($insumos_para_guardar as $elem) {
    if ($elem['tipo'] === 'insumo') {
        $stmt = $conn->prepare("SELECT precio_unitario, nombre FROM insumos WHERE id=?");
        $stmt->bind_param("i", $elem['id']);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($r) {
            $costo_total += $elem['cantidad'] * (float)$r['precio_unitario'];
        }
    } elseif ($elem['tipo'] === 'receta') {
        $stmt = $conn->prepare("SELECT costo_unitario, nombre, unidad FROM recetas WHERE id=?");
        $stmt->bind_param("i", $elem['id']);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($r) {
            // IMPORTANTE: la cantidad que ingresas debe estar en la MISMA unidad de la receta (gr/ml/unidad)
            $costo_total += $elem['cantidad'] * (float)$r['costo_unitario'];
        }
    }
}

    // Guardar en base de datos si no hay errores
    if (empty($errores)) {
        $conn->begin_transaction();
        try {
            // Insertar producto
            $stmt_producto = $conn->prepare("INSERT INTO productos (nombre, categoria_id, precio, costo, descripcion, foto, instrucciones_preparacion) 
                                         VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt_producto->bind_param("siddsss", 
                $nombre_persistente, 
                $categoria_id_persistente, 
                $precio_persistente, 
                $costo_total, 
                $descripcion_persistente, 
                $foto, 
                $instrucciones_persistente
            );

            if ($stmt_producto->execute()) {
                $producto_id = $stmt_producto->insert_id;

                // Guardar insumos/recetas asociados
                $stmt_detalle = $conn->prepare("INSERT INTO producto_insumos (producto_id, insumo_id, receta_id, cantidad_consumida) VALUES (?, ?, ?, ?)");

                    foreach ($insumos_para_guardar as $elemento_vinc) {
                        $insumo_id = ($elemento_vinc['tipo'] === 'insumo') ? $elemento_vinc['id'] : null;
                        $receta_id = ($elemento_vinc['tipo'] === 'receta') ? $elemento_vinc['id'] : null;

                        $stmt_detalle->bind_param("iiid", $producto_id, $insumo_id, $receta_id, $elemento_vinc['cantidad']);
                        $stmt_detalle->execute();
                    }
                    $stmt_detalle->close();

                $conn->commit();
                header("Location: listar.php?success=added");
                exit;
            } else {
                throw new Exception("Error al guardar el producto: " . $stmt_producto->error);
            }
            $stmt_producto->close();
        } catch (Exception $e) {
            $conn->rollback();
            $errores[] = "Error en la transacción: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Producto</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/styles_productos.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="icon" href="/img/favicon.ico" type="image/x-icon" />
    <style>
        .product-form-container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        
        .product-form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        input[type="text"],
        input[type="number"],
        select,
        textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        
        textarea {
            min-height: 100px;
        }
        
        .product-btn {
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-right: 10px;
        }
        
        .product-btn-icon {
            padding: 8px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .add {
            background-color: #4CAF50;
            color: white;
        }
        
        .delete {
            background-color: #f44336;
            color: white;
        }
        
        .product-alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .error {
            background-color: #ffdddd;
            border-left: 6px solid #f44336;
        }
        
        #tabla-insumos {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        
        #tabla-insumos th, #tabla-insumos td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        
        #tabla-insumos th {
            background-color: #f2f2f2;
        }
        
        #image-preview {
            max-width: 200px;
            max-height: 200px;
            margin-top: 10px;
            display: none;
        }
        
        .product-button-container {
            margin-top: 20px;
            text-align: right;
        }
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
    
    <form method="post" enctype="multipart/form-data">
        <div class="product-form-group">
            <label for="nombre">Nombre del Producto:</label>
            <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($nombre_persistente); ?>" required>
        </div>
        
        <div class="product-form-group">
            <label for="categoria">Categoría:</label>
            <select id="categoria" name="categoria" required>
                <option value="">-- Seleccionar --</option>
                <?php foreach ($lista_categorias_php as $cat): ?>
                    <option value="<?= $cat['id']; ?>" <?= ($categoria_id_persistente == $cat['id']) ? 'selected' : ''; ?>>
                        <?= htmlspecialchars($cat['nombre']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="product-form-group">
            <label for="precio">Precio de venta:</label>
            <input type="number" id="precio" name="precio" step="0.01" min="0.01" value="<?php echo htmlspecialchars($precio_persistente); ?>" required>
        </div>
        
        <div class="product-form-group">
            <label for="descripcion">Descripción:</label>
            <textarea id="descripcion" name="descripcion"><?php echo htmlspecialchars($descripcion_persistente); ?></textarea>
        </div>

        <div class="product-form-group">
            <label for="instrucciones">Instrucciones de preparación:</label>
            <textarea id="instrucciones" name="instrucciones"><?php echo htmlspecialchars($instrucciones_persistente); ?></textarea>
        </div>
        
        <div class="product-form-group">
            <label for="foto">Imagen del Producto:</label>
            <input type="file" id="foto" name="foto" accept="image/*">
            <img id="image-preview" alt="Vista previa de la imagen">
        </div>

        <div class="product-form-group">
            <h3>Insumos / Recetas</h3>
            <table id="tabla-insumos">
                <thead>
                    <tr>
                        <th>Elemento</th>
                        <th>Cantidad</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody id="insumos-body">
                    <!-- Las filas de insumos se añadirán aquí por JavaScript -->
                </tbody>
            </table>
            <button type="button" id="btn-agregar-insumo" class="product-btn-icon add">
                <i class="fas fa-plus"></i> Agregar Elemento
            </button>
        </div>
        
        <div class="product-button-container">
            <button type="submit" class="product-btn" style="background-color: #4CAF50; color: white;">Guardar Producto</button>
            <a href="listar.php" class="product-btn" style="background-color: #f44336; color: white;">Cancelar</a>
        </div>
    </form>
</div>

<script>
const listaElementosGlobal = <?php echo json_encode($lista_elementos_php); ?>;
const insumosSeleccionadosGlobal = <?php echo json_encode($insumos_seleccionados_persistentes); ?>;

function crearFilaInsumo(insumoIdSeleccionado = "", cantidad = "") {
    let optionHtml = "<option value=''>-- Seleccionar --</option>";
    listaElementosGlobal.forEach(elem => {
        const valor = elem.tipo + "-" + elem.id; // 👈 Guardamos tipo-id
        const selected = (valor == insumoIdSeleccionado) ? 'selected' : '';
        let tipoLabel = elem.tipo === 'insumo' ? 'INSUMO' : 'RECETA';
        optionHtml += `<option value="${valor}" ${selected}>${tipoLabel} - ${elem.nombre}${elem.unidad_medida ? ' ('+elem.unidad_medida+')' : ''}</option>`;
    });

    return `
        <tr>
            <td>
                <select name="insumos[]" required>${optionHtml}</select>
            </td>
            <td><input type="number" step="0.01" min="0.01" name="cantidades[]" value="${cantidad}" required></td>
            <td><button type="button" onclick="eliminarFila(this)" class="product-btn-icon delete"><i class="fas fa-trash"></i></button></td>
        </tr>
    `;
}

function agregarFila() {
    const fila = crearFilaInsumo();
    document.querySelector("#insumos-body").insertAdjacentHTML("beforeend", fila);
}

function eliminarFila(btn) {
    btn.closest("tr").remove();
}

document.addEventListener('DOMContentLoaded', function() {
    // Repoblar con insumos seleccionados si hubo un error de validación
    insumosSeleccionadosGlobal.forEach(vinc => {
        const fila = crearFilaInsumo(`${vinc.tipo}-${vinc.id}`, vinc.cantidad);
        document.querySelector("#insumos-body").insertAdjacentHTML("beforeend", fila);
    });

    // Añadir al menos una fila vacía si no hay insumos
    if (insumosSeleccionadosGlobal.length === 0) {
        agregarFila();
    }

    // Configurar botón para agregar filas
    document.getElementById('btn-agregar-insumo').addEventListener('click', agregarFila);

    // Vista previa de imagen
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
</body>
</html>
<?php 
if(isset($conn) && $conn) {
    $conn->close();
}
?>
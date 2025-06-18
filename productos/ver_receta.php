<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

protegerPagina();

$db = new Database();
$conn = $db->getConnection();

$producto_id = 0;
$producto_data = null;
$insumos_receta = [];
$error_message = '';

if (isset($_GET['id'])) {
    $producto_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if ($producto_id === false || $producto_id <= 0) {
        $error_message = "ID de producto inválido.";
        $producto_id = 0;
    }
} else {
    $error_message = "No se especificó un ID de producto.";
}

if ($producto_id > 0) {
    try {
        // 1. Obtener datos del producto principal
        $stmt_producto = $conn->prepare("SELECT nombre, foto, instrucciones_preparacion FROM productos WHERE id = ? AND activo = 1");
        // Considerar si mostrar recetas de productos inactivos. Por ahora, solo activos.
        $stmt_producto->bind_param("i", $producto_id);
        $stmt_producto->execute();
        $result_producto = $stmt_producto->get_result();
        if ($result_producto->num_rows === 1) {
            $producto_data = $result_producto->fetch_assoc();
        } else {
            $error_message = "Producto no encontrado o no está activo.";
        }
        $stmt_producto->close();

        // 2. Obtener insumos del producto (receta) si el producto fue encontrado
        if ($producto_data) {
            $stmt_insumos = $conn->prepare(
                "SELECT i.nombre, i.unidad_medida, pi.cantidad_consumida
                 FROM producto_insumos pi
                 JOIN insumos i ON pi.insumo_id = i.id
                 WHERE pi.producto_id = ?
                 ORDER BY i.nombre ASC"
            );
            $stmt_insumos->bind_param("i", $producto_id);
            $stmt_insumos->execute();
            $result_insumos = $stmt_insumos->get_result();
            while ($row = $result_insumos->fetch_assoc()) {
                $insumos_receta[] = $row;
            }
            $stmt_insumos->close();
        }

    } catch (Exception $e) {
        $error_message = "Error al cargar los datos de la receta: " . $e->getMessage();
        error_log("Error en ver_receta.php: " . $e->getMessage());
    }
}

// $conn->close(); // Se cierra al final
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <?php if ($producto_data && !$error_message): ?>
        <title>Receta: <?php echo htmlspecialchars($producto_data['nombre']); ?></title>
    <?php else: ?>
        <title>Error Viendo Receta</title>
    <?php endif; ?>
    <link rel="stylesheet" href="../assets/css/style.css">
    <!-- <link rel="stylesheet" href="../assets/css/styles_productos.css"> -->
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        .container {
            width: 80%;
            max-width: 900px;
            margin: 20px auto;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .recipe-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        .recipe-header h1 {
            color: #333;
            margin-bottom: 10px;
        }
        .product-image-container {
            text-align: center;
            margin-bottom: 20px;
        }
        .product-image {
            max-width: 100%;
            max-height: 300px;
            border-radius: 5px;
            border: 1px solid #ddd;
        }
        .recipe-section {
            margin-bottom: 30px;
        }
        .recipe-section h2 {
            color: #555;
            border-bottom: 2px solid #FF6B6B; /* Usando un color del dashboard */
            padding-bottom: 5px;
            margin-bottom: 15px;
        }
        .ingredients-list {
            list-style: none;
            padding: 0;
        }
        .ingredients-list li {
            padding: 8px 0;
            border-bottom: 1px dotted #eee;
        }
        .ingredients-list li:last-child {
            border-bottom: none;
        }
        .instructions-content {
            white-space: pre-wrap; /* Respeta saltos de línea y espacios múltiples */
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #eee;
        }
        .error-message {
            color: #D8000C;
            background-color: #FFD2D2;
            border: 1px solid #D8000C;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            text-align: center;
        }
        .navigation-links {
            margin-top: 30px;
            text-align: center;
        }
        .navigation-links a, .print-button {
            display: inline-block;
            padding: 10px 20px;
            background-color: #FF6B6B;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-right: 10px;
            border: none;
            cursor: pointer;
        }
        .print-button:hover {
            background-color: #e05252;
        }
        @media print {
            body {
                background-color: #fff;
                font-size: 12pt;
                margin: 20px; /* Márgenes para impresión */
                padding: 0;
            }
            .container {
                width: 100%;
                max-width: none;
                margin: 0 auto;
                box-shadow: none;
                border: none;
                padding: 0;
            }
            .navigation-links, .print-button-container { /* Ocultar botones */
                display: none !important;
            }
            .recipe-section h2 {
                border-bottom: 2px solid #333; /* Color más simple para impresión */
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($error_message): ?>
            <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
        <?php elseif ($producto_data): ?>
            <div class="recipe-header">
                <h1><?php echo htmlspecialchars($producto_data['nombre']); ?></h1>
            </div>

            <?php if (!empty($producto_data['foto']) && $producto_data['foto'] !== 'default.jpg'): ?>
                <div class="product-image-container">
                    <img src="../assets/img/productos/<?php echo htmlspecialchars($producto_data['foto']); ?>" alt="Foto de <?php echo htmlspecialchars($producto_data['nombre']); ?>" class="product-image">
                </div>
            <?php endif; ?>

            <div class="recipe-section ingredients-section">
                <h2>Ingredientes (Insumos)</h2>
                <?php if (!empty($insumos_receta)): ?>
                    <ul class="ingredients-list">
                        <?php foreach ($insumos_receta as $insumo): ?>
                            <li>
                                <?php echo htmlspecialchars(number_format($insumo['cantidad_consumida'], 2)); ?>
                                <?php echo htmlspecialchars($insumo['unidad_medida']); ?>
                                - <?php echo htmlspecialchars($insumo['nombre']); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>Este producto no tiene insumos definidos en su receta.</p>
                <?php endif; ?>
            </div>

            <div class="recipe-section instructions-section">
                <h2>Instrucciones de Preparación</h2>
                <?php if (!empty($producto_data['instrucciones_preparacion'])): ?>
                    <div class="instructions-content">
                        <?php echo nl2br(htmlspecialchars($producto_data['instrucciones_preparacion'])); ?>
                    </div>
                <?php else: ?>
                    <p>No se han especificado instrucciones de preparación para este producto.</p>
                <?php endif; ?>
            </div>

            <div class="print-button-container" style="text-align:center; margin-top:20px;">
                 <button class="print-button" onclick="window.print();">Imprimir Receta</button>
            </div>

        <?php endif; // Fin de if ($producto_data) ?>

        <div class="navigation-links">
            <a href="listar.php">Volver a la Lista de Productos</a>
            <a href="../dashboard.php">Volver al Dashboard</a>
        </div>
    </div>
<?php if(isset($conn)) $conn->close(); ?>
</body>
</html>

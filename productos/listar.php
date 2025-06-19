<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

protegerPagina();

$db = new Database();
$conn = $db->getConnection();

$productos = [];
$error_db = '';
$success_message = '';
$error_message = '';

// Manejo de mensajes de feedback
if (isset($_GET['success'])) {
    $key = htmlspecialchars($_GET['success']);
    $success_map = [
        'added' => 'Producto agregado correctamente.',
        'updated' => 'Producto actualizado correctamente.',
        'estado_cambiado' => 'Estado del producto cambiado correctamente.',
        'product_deleted' => 'Producto eliminado correctamente.'
    ];
    if (array_key_exists($key, $success_map)) {
        $success_message = $success_map[$key];
    }
}
if (isset($_GET['error'])) {
    $key = htmlspecialchars($_GET['error']);
    $error_map = [
        'not_found' => 'El producto especificado no fue encontrado.',
        'db_error' => 'Ocurrió un error en la base de datos al procesar la solicitud.',
        'estado_invalido' => 'El estado proporcionado para el cambio es inválido.',
        'id_invalido' => 'ID de producto inválido.',
        'no_id' => 'No se especificó ID de producto.',
        'product_in_use' => 'El producto no puede ser eliminado porque tiene ventas asociadas.',
        'delete_failed' => 'No se pudo eliminar el producto o sus datos asociados.'
    ];
     if (array_key_exists($key, $error_map)) {
        $error_message = $error_map[$key];
    }
}

try {
    $sql = "SELECT p.id, p.nombre, p.precio, p.foto, p.activo, c.nombre AS categoria_nombre
            FROM productos p
            LEFT JOIN categorias c ON p.categoria_id = c.id
            ORDER BY p.nombre ASC";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $productos[] = $row;
        }
    } else {
        $error_db = "Error al cargar los productos: " . $conn->error;
    }
} catch (Exception $e) {
    $error_db = "Error de base de datos: " . $e->getMessage();
}

// $conn->close(); // Se cierra al final
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Lista de Productos</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <!-- <link rel="stylesheet" href="../assets/css/styles_productos.css"> -->
    <link rel="stylesheet" href="../assets/css/table_styles.css">
</head>
<body>
    <div class="table-container">
        <h1>Lista de Productos</h1>

        <div class="page-action-buttons">
            <a href="agregar.php" class="btn-main">Agregar Nuevo Producto</a>
            <a href="../dashboard.php" class="btn-secondary">Volver al Dashboard</a>
        </div>

        <?php if ($success_message): ?>
            <div class="alert success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="alert error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        <?php if ($error_db): ?>
            <div class="alert error"><?php echo htmlspecialchars($error_db); ?></div>
        <?php endif; ?>

        <?php if (empty($productos) && !$error_db && !$error_message): ?>
            <div class="alert info">No hay productos registrados.</div>
        <?php elseif (!empty($productos)): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th class="text-center">Foto</th>
                        <th>Nombre</th>
                        <th>Categoría</th>
                        <th class="text-right">Precio</th>
                        <th class="text-center">Estado</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($productos as $producto): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($producto['id']); ?></td>
                            <td class="text-center">
                                <?php if (!empty($producto['foto']) && $producto['foto'] !== 'default.jpg'): ?>
                                    <img src="../assets/img/productos/<?php echo htmlspecialchars($producto['foto']); ?>" alt="<?php echo htmlspecialchars($producto['nombre']); ?>" class="thumbnail-photo">
                                <?php else: ?>
                                    <img src="../assets/img/productos/default.jpg" alt="Sin imagen" class="thumbnail-photo">
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($producto['nombre']); ?></td>
                            <td><?php echo htmlspecialchars($producto['categoria_nombre'] ?? 'N/A'); ?></td>
                            <td class="text-right">$<?php echo htmlspecialchars(number_format($producto['precio'], 2)); ?></td>
                            <td class="text-center">
                                <?php if ($producto['activo']): ?>
                                    <span class="status status-active">Activo</span>
                                <?php else: ?>
                                    <span class="status-inactive">Inactivo</span>
                                <?php endif; ?>
                            </td>
                            <td class="action-links">
                                <a href="editar_producto.php?id=<?php echo $producto['id']; ?>" class="product-btn-icon edit" title="Editar"><i class="fas fa-edit"></i></a>
                                <?php if ($producto['activo']): ?>
                                    <a href="cambiar_estado_producto.php?id=<?php echo $producto['id']; ?>&actual=1"
                                       class="product-btn-icon deactivate" title="Desactivar"
                                       onclick="return confirm('¿Está seguro de que desea DESACTIVAR este producto?');">
                                       <i class="fas fa-toggle-off"></i>
                                    </a>
                                <?php else: ?>
                                    <a href="cambiar_estado_producto.php?id=<?php echo $producto['id']; ?>&actual=0"
                                       class="product-btn-icon activate" title="Activar"
                                       onclick="return confirm('¿Está seguro de que desea ACTIVAR este producto?');">
                                       <i class="fas fa-toggle-on"></i>
                                    </a>
                                <?php endif; ?>
                                <a href="ver_receta.php?id=<?php echo $producto['id']; ?>" class="product-btn-icon view-recipe" title="Ver Receta (Insumos)"><i class="fas fa-receipt"></i></a>
                                <a href="eliminar_producto.php?id=<?php echo $producto['id']; ?>"
                                   class="product-btn-icon delete" title="Eliminar Producto"
                                   onclick="return confirm('¿Está seguro de que desea ELIMINAR este producto?\n\nEsta acción no se puede deshacer y solo se permitirá si el producto no tiene ventas asociadas.');">
                                   <i class="fas fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
<?php if(isset($conn)) $conn->close(); ?>
</body>
</html>

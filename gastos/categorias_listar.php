<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

protegerPagina();

$db = new Database();
$conn = $db->getConnection();

$categorias_gasto = [];
$error_db = '';
$success_message = '';
$error_message = '';

// Mensajes de operaciones CRUD
if (isset($_GET['success'])) {
    $key = htmlspecialchars($_GET['success']);
    $success_map = [
        'added' => 'Categoría de gasto agregada correctamente.',
        'updated' => 'Categoría de gasto actualizada correctamente.',
        'deleted' => 'Categoría de gasto eliminada correctamente.'
    ];
    if (array_key_exists($key, $success_map)) {
        $success_message = $success_map[$key];
    }
}
if (isset($_GET['error'])) {
    $key = htmlspecialchars($_GET['error']);
    $error_map = [
        'category_in_use' => 'Esta categoría no puede ser eliminada porque está en uso por uno o más gastos.',
        'not_found' => 'La categoría de gasto especificada no fue encontrada.',
        'db_error' => 'Ocurrió un error en la base de datos.',
        'invalid_id' => 'ID de categoría inválido.',
        'no_id' => 'No se especificó ID de categoría.'
    ];
     if (array_key_exists($key, $error_map)) {
        $error_message = $error_map[$key];
    }
}


try {
    $result = $conn->query("SELECT id, nombre, descripcion FROM categorias_gasto ORDER BY nombre ASC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $categorias_gasto[] = $row;
        }
    } else {
        $error_db = "Error al cargar las categorías de gasto: " . $conn->error;
    }
} catch (Exception $e) {
    $error_db = "Error de base de datos: " . $e->getMessage();
}

// $conn->close(); // Se cierra al final del script
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Categorías de Gasto</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/styles_productos.css"> <!-- Reutilizar si es adecuado -->
    <style>
        .table-container { max-width: 900px; margin: 40px auto; padding: 20px; }
        .action-links a { margin-right: 10px; text-decoration: none; }
        .add-button-container { margin-bottom: 20px; text-align: right; }
    </style>
</head>
<body>
    <div class="table-container product-table-container">
        <h1>Categorías de Gasto</h1>

        <div class="add-button-container">
            <a href="categorias_agregar.php" class="product-btn">Agregar Nueva Categoría</a>
            <a href="../dashboard.php" class="product-btn cancel" style="margin-left:10px;">Volver al Dashboard</a>
        </div>

        <?php if ($success_message): ?>
            <div class="product-alert success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="product-alert error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        <?php if ($error_db): ?>
            <div class="product-alert error"><?php echo htmlspecialchars($error_db); ?></div>
        <?php endif; ?>

        <?php if (empty($categorias_gasto) && !$error_db && !$error_message): ?>
            <div class="product-alert info">No hay categorías de gasto registradas.</div>
        <?php elseif (!empty($categorias_gasto)): ?>
            <table class="product-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Descripción</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categorias_gasto as $categoria): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($categoria['id']); ?></td>
                            <td><?php echo htmlspecialchars($categoria['nombre']); ?></td>
                            <td><?php echo nl2br(htmlspecialchars($categoria['descripcion'] ?? 'N/A')); ?></td>
                            <td class="action-links">
                                <a href="categorias_editar.php?id=<?php echo $categoria['id']; ?>" class="product-btn-icon edit" title="Editar"><i class="fas fa-edit"></i></a>
                                <a href="categorias_eliminar.php?id=<?php echo $categoria['id']; ?>" class="product-btn-icon delete" title="Eliminar" onclick="return confirm('¿Está seguro de que desea eliminar esta categoría de gasto?');"><i class="fas fa-trash"></i></a>
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

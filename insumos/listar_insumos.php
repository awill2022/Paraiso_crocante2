<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

protegerPagina();

$db = new Database();
$conn = $db->getConnection();

$insumos = [];
$error_db = '';

try {
    $result = $conn->query("SELECT id, nombre, unidad_medida, stock_actual, stock_minimo FROM insumos ORDER BY nombre ASC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $insumos[] = $row;
        }
    } else {
        $error_db = "Error al cargar los insumos: " . $conn->error;
    }
} catch (Exception $e) {
    $error_db = "Error de base de datos: " . $e->getMessage();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Lista de Insumos</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <!-- Podríamos crear insumos_styles.css o usar styles_productos.css si es compatible -->
    <link rel="stylesheet" href="../assets/css/styles_productos.css">
    <style>
        /* Estilos adicionales o específicos para la tabla de insumos */
        .table-container { max-width: 900px; margin: 40px auto; padding: 20px; }
        .table-insumos { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .table-insumos th, .table-insumos td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        .table-insumos th { background-color: #f2f2f2; }
        .table-insumos tr:nth-child(even) { background-color: #f9f9f9; }
        .text-right { text-align: right !important; }
        .action-links a { margin-right: 10px; text-decoration: none; }
        .add-button-container { margin-bottom: 20px; text-align: right; }
    </style>
</head>
<body>
    <div class="table-container product-table-container"> <!-- Reutilizando clases de productos -->
        <h1>Lista de Insumos</h1>

        <div class="add-button-container">
            <a href="agregar_insumo.php" class="product-btn">Agregar Nuevo Insumo</a>
            <a href="../dashboard.php" class="product-btn cancel" style="margin-left:10px;">Volver al Dashboard</a>
        </div>

        <?php if ($error_db): ?>
            <div class="product-alert error"><?php echo htmlspecialchars($error_db); ?></div>
        <?php endif; ?>

        <?php if (isset($_GET['success']) && $_GET['success'] == '1'): ?>
            <div class="product-alert success">Insumo agregado/actualizado correctamente.</div>
        <?php endif; ?>

        <?php if (empty($insumos) && !$error_db): ?>
            <div class="product-alert info">No hay insumos registrados.</div>
        <?php else: ?>
            <table class="table-insumos product-table"> <!-- Reutilizando clases de productos -->
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Unidad de Medida</th>
                        <th class="text-right">Stock Actual</th>
                        <th class="text-right">Stock Mínimo</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($insumos as $insumo): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($insumo['id']); ?></td>
                            <td><?php echo htmlspecialchars($insumo['nombre']); ?></td>
                            <td><?php echo htmlspecialchars($insumo['unidad_medida']); ?></td>
                            <td class="text-right"><?php echo htmlspecialchars(number_format($insumo['stock_actual'], 2)); ?></td>
                            <td class="text-right"><?php echo htmlspecialchars(number_format($insumo['stock_minimo'], 2)); ?></td>
                            <td class="action-links">
                                <a href="editar_insumo.php?id=<?php echo $insumo['id']; ?>" class="product-btn-icon edit" title="Editar"><i class="fas fa-edit"></i></a>
                                <a href="eliminar_insumo.php?id=<?php echo $insumo['id']; ?>" class="product-btn-icon delete" title="Eliminar" onclick="return confirm('¿Está seguro de que desea eliminar este insumo?');"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>

<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

protegerPagina();

$db = new Database();
$conn = $db->getConnection();

$insumos = [];
$error_db = '';

try {
    $result = $conn->query("SELECT id, nombre, unidad_medida, stock_actual, stock_minimo, precio_unitario FROM insumos ORDER BY nombre ASC");
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
    <link rel="stylesheet" href="../assets/css/table_styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="icon" href="/img/favicon.ico" type="image/x-icon" />
</head>
<body>
    <div class="table-container">
        <h1>Lista de Insumos</h1>

        <div class="page-action-buttons">
            <a href="agregar_insumo.php" class="btn-main">Agregar Nuevo Insumo</a>
            <a href="../dashboard.php" class="btn-secondary">Volver al Dashboard</a>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="alert success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="alert error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        <?php if ($error_db): ?>
            <div class="alert error"><?php echo htmlspecialchars($error_db); ?></div>
        <?php endif; ?>

        <?php if (empty($insumos) && !$error_db && empty($error_message)): ?>
            <div class="alert info">No hay insumos registrados.</div>
        <?php elseif (!empty($insumos)): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Unidad de Medida</th>
                        <th class="text-right">Precio Unitario ($)</th>
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
                            <td class="text-right"><?php echo number_format($insumo['precio_unitario'], 4); ?></td>
                            <td class="text-right"><?php echo number_format($insumo['stock_actual'], 2); ?></td>
                            <td class="text-right"><?php echo number_format($insumo['stock_minimo'], 2); ?></td>
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

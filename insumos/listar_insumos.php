<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
protegerPagina();

$db = new Database();
$conn = $db->getConnection();

$insumos = [];
$error_db = '';
$success_msg = '';

if (isset($_GET['success'])) {
    if ($_GET['success'] === 'updated') $success_msg = "Insumo actualizado correctamente.";
    if ($_GET['success'] === 'deleted') $success_msg = "Insumo eliminado correctamente.";
}
if (isset($_GET['error'])) {
    $error_db = "Acción no válida o insumo no encontrado.";
}

try {
    $result = $conn->query("SELECT id, nombre, unidad_medida, stock_actual, stock_minimo, precio_unitario FROM insumos ORDER BY nombre ASC");
    if ($result) {
        while ($row = $result->fetch_assoc()) $insumos[] = $row;
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
    <title>Lista de Insumos - Paraíso Crocante</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="/img/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="../assets/css/app.css">
</head>
<body class="app-body">

<header class="app-header">
    <div>
        <h1>📦 Gestionar Insumos</h1>
        <p><?php echo count($insumos); ?> insumo(s) registrado(s)</p>
    </div>
    <nav>
        <a href="agregar_insumo.php" class="btn-nav">+ Agregar Insumo</a>
        <a href="../dashboard.php" class="btn-nav">← Dashboard</a>
    </nav>
</header>

<div class="app-page wide">

    <?php if ($success_msg): ?>
    <div class="app-alert success">✅ <?php echo $success_msg; ?></div>
    <?php endif; ?>
    <?php if ($error_db): ?>
    <div class="app-alert error">❌ <?php echo htmlspecialchars($error_db); ?></div>
    <?php endif; ?>

    <?php if (empty($insumos) && !$error_db): ?>
    <div class="app-alert info">ℹ️ No hay insumos registrados. <a href="agregar_insumo.php">Agrega el primero</a>.</div>
    <?php else: ?>
    <div class="app-card" style="padding:0; overflow:hidden;">
        <div class="table-wrapper">
            <table class="app-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nombre</th>
                        <th>Unidad</th>
                        <th class="text-right">Precio Unit.</th>
                        <th class="text-right">Stock Actual</th>
                        <th class="text-right">Stock Mín.</th>
                        <th class="text-center">Estado</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($insumos as $ins):
                        $bajo = $ins['stock_actual'] <= $ins['stock_minimo'] && $ins['stock_minimo'] > 0;
                    ?>
                    <tr class="<?php echo $bajo ? 'row-danger' : ''; ?>">
                        <td><?php echo $ins['id']; ?></td>
                        <td><strong><?php echo htmlspecialchars($ins['nombre']); ?></strong></td>
                        <td><?php echo htmlspecialchars($ins['unidad_medida']); ?></td>
                        <td class="text-right">$<?php echo number_format($ins['precio_unitario'], 4); ?></td>
                        <td class="text-right"><?php echo number_format($ins['stock_actual'], 2); ?></td>
                        <td class="text-right"><?php echo number_format($ins['stock_minimo'], 2); ?></td>
                        <td class="text-center">
                            <?php if ($bajo): ?>
                                <span class="badge badge-danger">⚠️ Bajo</span>
                            <?php else: ?>
                                <span class="badge badge-success">✓ OK</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <div class="d-flex gap-1" style="justify-content:center;">
                                <a href="editar_insumo.php?id=<?php echo $ins['id']; ?>"
                                   class="btn btn-secondary btn-sm">✏️ Editar</a>
                                <a href="eliminar_insumo.php?id=<?php echo $ins['id']; ?>"
                                   class="btn btn-danger btn-sm"
                                   onclick="return confirm('¿Eliminar este insumo?')">🗑️</a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

</div>
</body>
</html>

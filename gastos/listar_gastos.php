<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

protegerPagina();

$db = new Database();
$conn = $db->getConnection();

$gastos = [];
$error_db = '';
$success_message = '';
$error_message = ''; // Para errores específicos de esta página o de redirecciones

// Manejo de mensajes de feedback (ej. desde eliminar_gasto.php si se implementara)
if (isset($_GET['success'])) {
    $key = htmlspecialchars($_GET['success']);
    $success_map = [
        'added' => 'Gasto registrado correctamente.',
        'deleted' => 'Gasto eliminado correctamente.',
        'updated' => 'Gasto actualizado correctamente.'
    ];
    if (array_key_exists($key, $success_map)) {
        $success_message = $success_map[$key];
    }
}
if (isset($_GET['error'])) {
    $key = htmlspecialchars($_GET['error']);
    $error_map = [
        'not_found' => 'El gasto especificado no fue encontrado.',
        'db_error' => 'Ocurrió un error en la base de datos al procesar la solicitud.',
        'generic' => 'Ocurrió un error.'
    ];
     if (array_key_exists($key, $error_map)) {
        $error_message = $error_map[$key];
    }
}


try {
    $sql = "SELECT
                g.id,
                g.fecha,
                g.monto,
                g.descripcion,
                g.proveedor,
                g.recurrente,
                g.comprobante,
                cg.nombre AS categoria_nombre,
                u.nombre AS usuario_nombre,
                u.username AS usuario_username
            FROM gastos g
            JOIN categorias_gasto cg ON g.categoria_id = cg.id
            JOIN usuarios u ON g.usuario_id = u.id
            ORDER BY g.fecha DESC, g.id DESC";

    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $gastos[] = $row;
        }
    } else {
        $error_db = "Error al cargar los gastos: " . $conn->error;
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
    <title>Lista de Gastos</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/styles_productos.css"> <!-- Reutilizar si es adecuado -->
    <style>
        .table-container { max-width: 1200px; margin: 40px auto; padding: 20px; }
        .action-links a { margin-right: 10px; text-decoration: none; }
        .add-button-container { margin-bottom: 20px; text-align: right; }
        .comprobante-link { display: inline-block; padding: 3px 6px; border: 1px solid #007bff; border-radius:3px; color: #007bff; text-decoration:none; }
        .comprobante-link:hover { background-color: #007bff; color:white; }
        .text-center { text-align:center; }
        .text-right { text-align:right; }
    </style>
</head>
<body>
    <div class="table-container product-table-container">
        <h1>Lista de Gastos Registrados</h1>

        <div class="add-button-container">
            <a href="registrar_gasto.php" class="product-btn">Registrar Nuevo Gasto</a>
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

        <?php if (empty($gastos) && !$error_db && !$error_message): ?>
            <div class="product-alert info">No hay gastos registrados.</div>
        <?php elseif (!empty($gastos)): ?>
            <table class="product-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Fecha</th>
                        <th>Descripción</th>
                        <th class="text-right">Monto</th>
                        <th>Categoría</th>
                        <th>Proveedor</th>
                        <th>Usuario</th>
                        <th class="text-center">Recurrente</th>
                        <th class="text-center">Comprobante</th>
                        <!-- <th>Acciones</th> -->
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($gastos as $gasto): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($gasto['id']); ?></td>
                            <td><?php echo htmlspecialchars(date("d/m/Y", strtotime($gasto['fecha']))); ?></td>
                            <td><?php echo nl2br(htmlspecialchars($gasto['descripcion'])); ?></td>
                            <td class="text-right">$<?php echo htmlspecialchars(number_format($gasto['monto'], 2)); ?></td>
                            <td><?php echo htmlspecialchars($gasto['categoria_nombre']); ?></td>
                            <td><?php echo htmlspecialchars($gasto['proveedor'] ?: 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($gasto['usuario_nombre'] ?: $gasto['usuario_username']); ?></td>
                            <td class="text-center"><?php echo $gasto['recurrente'] ? 'Sí' : 'No'; ?></td>
                            <td class="text-center">
                                <?php if (!empty($gasto['comprobante'])): ?>
                                    <a href="../assets/comprobantes_gastos/<?php echo htmlspecialchars($gasto['comprobante']); ?>"
                                       target="_blank" class="comprobante-link">Ver</a>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                            <!--
                            <td class="action-links text-center">
                                <a href="editar_gasto.php?id=<?php echo $gasto['id']; ?>" class="product-btn-icon edit" title="Editar"><i class="fas fa-edit"></i></a>
                                <a href="eliminar_gasto.php?id=<?php echo $gasto['id']; ?>" class="product-btn-icon delete" title="Eliminar" onclick="return confirm('¿Está seguro de eliminar este gasto?');"><i class="fas fa-trash"></i></a>
                            </td>
                            -->
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
<?php if(isset($conn)) $conn->close(); ?>
</body>
</html>

<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Ya no se necesita el placeholder, protegerPaginaAdmin() está en functions.php
protegerPaginaAdmin();

$db = new Database();
$conn = $db->getConnection();

$usuarios = [];
$error_db = '';
$success_message = '';
$error_message = '';

// Manejo de mensajes de feedback (ej. desde crear.php, editar.php, cambiar_estado.php)
if (isset($_GET['success'])) {
    $key = htmlspecialchars($_GET['success']);
    $success_map = [
        'created' => 'Usuario creado correctamente.',
        'updated' => 'Usuario actualizado correctamente.',
        'status_changed' => 'Estado del usuario cambiado correctamente.',
        'deleted' => 'Usuario eliminado correctamente.' // Si se implementa delete
    ];
    if (array_key_exists($key, $success_map)) {
        $success_message = $success_map[$key];
    }
}
if (isset($_GET['error'])) {
    $key = htmlspecialchars($_GET['error']);
    $error_map = [
        'not_found' => 'El usuario especificado no fue encontrado.',
        'db_error' => 'Ocurrió un error en la base de datos.',
        'invalid_id' => 'ID de usuario inválido.',
        'self_action' => 'No puedes cambiar tu propio estado o rol por esta vía.' // Ejemplo
    ];
     if (array_key_exists($key, $error_map)) {
        $error_message = $error_map[$key];
    }
}

try {
    $sql = "SELECT id, username, nombre, rol, activo
            FROM usuarios
            ORDER BY username ASC";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $usuarios[] = $row;
        }
    } else {
        $error_db = "Error al cargar los usuarios: " . $conn->error;
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
    <title>Gestión de Usuarios</title>
    <link rel="stylesheet" href="../../assets/css/style.css"> <!-- Estilos base -->
    <link rel="stylesheet" href="../../assets/css/table_styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="table-container">
        <h1>Gestión de Usuarios del Sistema</h1>

        <div class="page-action-buttons">
            <a href="crear.php" class="btn-main">Crear Nuevo Usuario</a>
            <a href="../../dashboard.php" class="btn-secondary">Volver al Dashboard</a>
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

        <?php if (empty($usuarios) && !$error_db && !$error_message): ?>
            <div class="alert info">No hay usuarios registrados en el sistema.</div>
        <?php elseif (!empty($usuarios)): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Usuario (Username)</th>
                        <th>Nombre</th>
                        <th>Rol</th>
                        <th class="text-center">Estado</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $usuario): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($usuario['id']); ?></td>
                            <td><?php echo htmlspecialchars($usuario['username']); ?></td>
                            <td><?php echo htmlspecialchars($usuario['nombre'] ?: 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars(ucfirst($usuario['rol'])); ?></td>
                            <td class="text-center">
                                <?php if ($usuario['activo']): ?>
                                    <span class="status status-active">Activo</span>
                                <?php else: ?>
                                    <span class="status status-inactive">Inactivo</span>
                                <?php endif; ?>
                            </td>
                            <td class="action-links text-center">
                                <a href="editar.php?id=<?php echo $usuario['id']; ?>" class="edit" title="Editar Usuario"><i class="fas fa-edit"></i></a>
                                <?php
                                // No permitir que el usuario se desactive a sí mismo desde aquí
                                // o que desactive al único admin, etc. (lógica más avanzada para cambiar_estado.php)
                                if ($_SESSION['usuario_id'] != $usuario['id']):
                                ?>
                                    <?php if ($usuario['activo']): ?>
                                        <a href="cambiar_estado.php?id=<?php echo $usuario['id']; ?>&actual=1"
                                           class="deactivate" title="Desactivar Usuario"
                                           onclick="return confirm('¿Está seguro de que desea DESACTIVAR a este usuario?');">
                                           <i class="fas fa-toggle-off"></i>
                                        </a>
                                    <?php else: ?>
                                        <a href="cambiar_estado.php?id=<?php echo $usuario['id']; ?>&actual=0"
                                           class="activate" title="Activar Usuario"
                                           onclick="return confirm('¿Está seguro de que desea ACTIVAR a este usuario?');">
                                           <i class="fas fa-toggle-on"></i>
                                        </a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span title="No puedes cambiar tu propio estado aquí" style="color:#ccc; margin: 0 4px; padding:5px;"><i class="fas fa-ban"></i></span>
                                <?php endif; ?>
                                <!-- Futuro botón de eliminar:
                                <a href="eliminar.php?id=<?php echo $usuario['id']; ?>" class="delete" title="Eliminar Usuario" onclick="return confirm('¿Está seguro de ELIMINAR este usuario? Esta acción es irreversible.');"><i class="fas fa-trash"></i></a>
                                -->
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

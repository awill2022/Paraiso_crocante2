<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Ya no se necesita el placeholder, protegerPaginaAdmin() está en functions.php
protegerPaginaAdmin();

$db = new Database();
$conn = $db->getConnection();

$usuario_id_a_modificar = 0;
$estado_actual_recibido = -1;
$redirect_url = 'listar.php';

// 1. Obtención y Validación de Parámetros GET
if (isset($_GET['id'])) {
    $usuario_id_a_modificar = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if ($usuario_id_a_modificar === false || $usuario_id_a_modificar <= 0) {
        header("Location: " . $redirect_url . "?error=invalid_id");
        exit;
    }
} else {
    header("Location: " . $redirect_url . "?error=no_id");
    exit;
}

if (isset($_GET['actual'])) {
    $estado_actual_recibido = filter_var($_GET['actual'], FILTER_VALIDATE_INT);
    if (!in_array($estado_actual_recibido, [0, 1])) {
        header("Location: " . $redirect_url . "?error=invalid_status_param&id_user=" . $usuario_id_a_modificar);
        exit;
    }
} else {
    header("Location: " . $redirect_url . "?error=no_status_param&id_user=" . $usuario_id_a_modificar);
    exit;
}

// 2. Autoprotección: Evitar que el admin cambie su propio estado
$admin_logueado_id = $_SESSION['usuario_id'];
if ($usuario_id_a_modificar == $admin_logueado_id) {
    header("Location: " . $redirect_url . "?error=self_status_change_not_allowed");
    exit;
}

$error_key = '';
$success_key = '';

try {
    // 3. Verificar que el usuario a modificar exista
    $stmt_check_user = $conn->prepare("SELECT activo FROM usuarios WHERE id = ?");
    $stmt_check_user->bind_param("i", $usuario_id_a_modificar);
    $stmt_check_user->execute();
    $result_check_user = $stmt_check_user->get_result();
    if ($result_check_user->num_rows === 0) {
        $stmt_check_user->close();
        header("Location: " . $redirect_url . "?error=not_found");
        exit;
    }
    // Opcional: verificar si el estado actual en BD coincide con el parámetro 'actual'
    // $usuario_bd = $result_check_user->fetch_assoc();
    // if ($usuario_bd['activo'] != $estado_actual_recibido) {
    //     $stmt_check_user->close();
    //     header("Location: " . $redirect_url . "?error=status_mismatch&id_user=" . $usuario_id_a_modificar);
    //     exit;
    // }
    $stmt_check_user->close();

    // 4. Determinar el nuevo estado
    $nuevo_estado = ($estado_actual_recibido == 1) ? 0 : 1;

    // 5. Actualizar el estado del usuario
    $stmt_update = $conn->prepare("UPDATE usuarios SET activo = ? WHERE id = ?");
    $stmt_update->bind_param("ii", $nuevo_estado, $usuario_id_a_modificar);

    if ($stmt_update->execute()) {
        if ($stmt_update->affected_rows > 0) {
            $success_key = 'status_changed';
        } else {
            // No afectó filas, podría ser que el estado ya era el que se intentaba poner.
            // Se puede considerar un éxito o un "sin cambios".
            $success_key = 'status_changed'; // O '?info=no_status_change_needed'
        }
    } else {
        $error_key = 'db_error';
        error_log("Error al cambiar estado para usuario ID $usuario_id_a_modificar: " . $stmt_update->error);
    }
    $stmt_update->close();

} catch (Exception $e) {
    error_log("Excepción en cambiar_estado.php para usuario ID $usuario_id_a_modificar: " . $e->getMessage());
    $error_key = 'db_error';
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}

// 6. Redirección y Feedback
if (!empty($success_key)) {
    header("Location: " . $redirect_url . "?success=" . $success_key);
} elseif (!empty($error_key)) {
    header("Location: " . $redirect_url . "?error=" . $error_key);
} else {
    // Caso improbable si no se estableció error_key ni success_key (ej. si se comentan todos los header() en el try)
    header("Location: " . $redirect_url);
}
exit;
?>

<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

protegerPagina(); // Asegura que el usuario esté logueado

$db = new Database();
$conn = $db->getConnection();

$insumo_id = 0;
$redirect_url = 'listar_insumos.php'; // URL base para redirección

if (isset($_GET['id'])) {
    $insumo_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if ($insumo_id === false || $insumo_id <= 0) {
        header("Location: " . $redirect_url . "?error=invalid_id");
        exit;
    }
} else {
    header("Location: " . $redirect_url . "?error=no_id");
    exit;
}

// Verificar si el insumo está siendo utilizado en producto_insumos
// Esta tabla y lógica son cruciales para la integridad de datos.
// Asumimos que existe una tabla 'producto_insumos' con una columna 'insumo_id'.
$error_message = '';
$success_message_key = '';

try {
    // 1. Verificar si el insumo existe antes de verificar su uso o eliminarlo
    $stmt_check_exist = $conn->prepare("SELECT id FROM insumos WHERE id = ?");
    $stmt_check_exist->bind_param("i", $insumo_id);
    $stmt_check_exist->execute();
    $result_check_exist = $stmt_check_exist->get_result();
    if ($result_check_exist->num_rows === 0) {
        $stmt_check_exist->close();
        header("Location: " . $redirect_url . "?error=not_found");
        exit;
    }
    $stmt_check_exist->close();

    // 2. Verificar si el insumo está en uso
    $stmt_check_uso = $conn->prepare("SELECT COUNT(*) as count FROM producto_insumos WHERE insumo_id = ?");
    $stmt_check_uso->bind_param("i", $insumo_id);
    $stmt_check_uso->execute();
    $result_uso = $stmt_check_uso->get_result();
    $uso_count = $result_uso->fetch_assoc()['count'];
    $stmt_check_uso->close();

    if ($uso_count > 0) {
        // El insumo está en uso, no se puede eliminar.
        header("Location: " . $redirect_url . "?error=in_use");
        exit;
    } else {
        // El insumo no está en uso, proceder a eliminar.
        $stmt_delete = $conn->prepare("DELETE FROM insumos WHERE id = ?");
        $stmt_delete->bind_param("i", $insumo_id);

        if ($stmt_delete->execute()) {
            if ($stmt_delete->affected_rows > 0) {
                $success_message_key = 'deleted';
            } else {
                // No afectó filas, podría ser que ya fue eliminado o el ID no existe (aunque se verificó antes)
                $error_message = 'not_found'; // O un error más genérico
            }
        } else {
            $error_message = 'db_error'; // Error en la ejecución del delete
        }
        $stmt_delete->close();
    }

} catch (Exception $e) {
    // Capturar cualquier otra excepción de base de datos
    error_log("Error en eliminar_insumo.php: " . $e->getMessage()); // Loggear el error real
    $error_message = 'db_error';
}

$conn->close();

// Redireccionar con mensaje de éxito o error
if (!empty($success_message_key)) {
    header("Location: " . $redirect_url . "?success=" . $success_message_key);
} elseif (!empty($error_message)) {
    header("Location: " . $redirect_url . "?error=" . $error_message);
} else {
    // Caso improbable, pero por si acaso.
    header("Location: " . $redirect_url);
}
exit;
?>

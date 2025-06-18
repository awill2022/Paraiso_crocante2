<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

protegerPagina();

$db = new Database();
$conn = $db->getConnection();

$producto_id = 0;
$estado_actual = -1; // Usar -1 para indicar que no se ha seteado válidamente

$redirect_url = 'listar.php'; // Redirigir a la lista de productos

// Validar ID del producto
if (isset($_GET['id'])) {
    $producto_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if ($producto_id === false || $producto_id <= 0) {
        header("Location: " . $redirect_url . "?error=id_invalido");
        exit;
    }
} else {
    header("Location: " . $redirect_url . "?error=no_id"); // Asumiendo que tienes un error=no_id en listar.php
    exit;
}

// Validar estado_actual
if (isset($_GET['actual'])) {
    $estado_actual_get = filter_var($_GET['actual'], FILTER_VALIDATE_INT);
    if ($estado_actual_get === 0 || $estado_actual_get === 1) {
        $estado_actual = $estado_actual_get;
    } else {
        header("Location: " . $redirect_url . "?error=estado_invalido&id_prod=" . $producto_id);
        exit;
    }
} else {
    header("Location: " . $redirect_url . "?error=no_estado&id_prod=" . $producto_id); // Asumiendo error=no_estado
    exit;
}

// Determinar el nuevo estado
$nuevo_estado = ($estado_actual == 1) ? 0 : 1;

try {
    // Verificar si el producto existe antes de actualizar
    $stmt_check = $conn->prepare("SELECT id FROM productos WHERE id = ?");
    $stmt_check->bind_param("i", $producto_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    if ($result_check->num_rows === 0) {
        $stmt_check->close();
        header("Location: " . $redirect_url . "?error=not_found");
        exit;
    }
    $stmt_check->close();

    // Actualizar el estado del producto
    $stmt_update = $conn->prepare("UPDATE productos SET activo = ? WHERE id = ?");
    $stmt_update->bind_param("ii", $nuevo_estado, $producto_id);

    if ($stmt_update->execute()) {
        if ($stmt_update->affected_rows > 0) {
            header("Location: " . $redirect_url . "?success=estado_cambiado");
            exit;
        } else {
            // No afectó filas, podría ser que el estado ya era el que se intentaba poner
            // o el producto no se encontró (aunque se verificó antes).
            // Considerarlo un éxito o un "sin cambios"
            header("Location: " . $redirect_url . "?success=estado_cambiado&info=no_change_needed"); // O un mensaje diferente
            exit;
        }
    } else {
        header("Location: " . $redirect_url . "?error=db_error&code=" . $stmt_update->errno);
        exit;
    }
    $stmt_update->close();

} catch (Exception $e) {
    error_log("Error en cambiar_estado_producto.php: " . $e->getMessage());
    header("Location: " . $redirect_url . "?error=db_error&ex");
    exit;
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>

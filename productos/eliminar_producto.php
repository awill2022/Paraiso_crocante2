<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

protegerPagina();

$db = new Database();
$conn = $db->getConnection();

$producto_id = 0;
$redirect_url = 'listar.php'; // URL base para redirección

// 1. Obtención y Validación de ID
if (isset($_GET['id'])) {
    $producto_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if ($producto_id === false || $producto_id <= 0) {
        header("Location: " . $redirect_url . "?error=id_invalido");
        exit;
    }
} else {
    header("Location: " . $redirect_url . "?error=no_id");
    exit;
}

$error_key = '';
$success_key = '';

try {
    // 2. Verificación de Producto Existente
    $stmt_check_prod = $conn->prepare("SELECT id FROM productos WHERE id = ?");
    $stmt_check_prod->bind_param("i", $producto_id);
    $stmt_check_prod->execute();
    $result_check_prod = $stmt_check_prod->get_result();
    if ($result_check_prod->num_rows === 0) {
        $stmt_check_prod->close();
        header("Location: " . $redirect_url . "?error=not_found");
        exit;
    }
    $stmt_check_prod->close();

    // 3. Verificación de Ventas Asociadas
    $stmt_check_sales = $conn->prepare("SELECT COUNT(*) as count FROM detalle_venta WHERE producto_id = ?");
    $stmt_check_sales->bind_param("i", $producto_id);
    $stmt_check_sales->execute();
    $sales_count = $stmt_check_sales->get_result()->fetch_assoc()['count'];
    $stmt_check_sales->close();

    if ($sales_count > 0) {
        $error_key = 'product_in_use';
    } else {
        // 4. Eliminación (si no hay ventas asociadas)
        $conn->begin_transaction();

        // a. Eliminar Vinculaciones de Insumos
        $stmt_delete_insumos = $conn->prepare("DELETE FROM producto_insumos WHERE producto_id = ?");
        $stmt_delete_insumos->bind_param("i", $producto_id);
        $deleted_insumos_ok = $stmt_delete_insumos->execute(); // true en éxito, false en error
        $stmt_delete_insumos->close();

        if (!$deleted_insumos_ok) {
            $conn->rollback();
            // Podríamos querer loggear $stmt_delete_insumos->error aquí
            error_log("Error al eliminar insumos para producto ID $producto_id: " . $conn->error);
            $error_key = 'delete_failed'; // Mensaje más específico podría ser útil
        } else {
            // b. Eliminar Producto
            $stmt_delete_prod = $conn->prepare("DELETE FROM productos WHERE id = ?");
            $stmt_delete_prod->bind_param("i", $producto_id);

            if ($stmt_delete_prod->execute()) {
                if ($stmt_delete_prod->affected_rows > 0) {
                    $conn->commit();
                    $success_key = 'product_deleted';
                } else {
                    // No afectó filas, el producto ya no existía (improbable si la verificación inicial pasó)
                    $conn->rollback();
                    $error_key = 'not_found'; // O delete_failed
                }
            } else {
                $conn->rollback();
                error_log("Error al eliminar producto ID $producto_id: " . $stmt_delete_prod->error);
                $error_key = 'delete_failed';
            }
            $stmt_delete_prod->close();
        }
    }

} catch (Exception $e) {
    if ($conn->inTransaction) { // Verificar si la transacción sigue activa
        $conn->rollback();
    }
    error_log("Excepción en eliminar_producto.php para producto ID $producto_id: " . $e->getMessage());
    $error_key = 'db_error';
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}

// Redireccionar con mensaje de éxito o error
if (!empty($success_key)) {
    header("Location: " . $redirect_url . "?success=" . $success_key);
} elseif (!empty($error_key)) {
    header("Location: " . $redirect_url . "?error=" . $error_key);
} else {
    // Caso improbable si no se estableció error_key ni success_key
    header("Location: " . $redirect_url);
}
exit;
?>

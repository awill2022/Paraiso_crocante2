<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

protegerPagina();

$db = new Database();
$conn = $db->getConnection();

$categoria_gasto_id = 0;
$redirect_url = 'categorias_listar.php';

if (isset($_GET['id'])) {
    $categoria_gasto_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if ($categoria_gasto_id === false || $categoria_gasto_id <= 0) {
        header("Location: " . $redirect_url . "?error=invalid_id");
        exit;
    }
} else {
    header("Location: " . $redirect_url . "?error=no_id");
    exit;
}

$error_key = '';
$success_key = '';

try {
    // 1. Verificar si la categoría de gasto existe
    $stmt_check_exist = $conn->prepare("SELECT id FROM categorias_gastos WHERE id = ?");
    $stmt_check_exist->bind_param("i", $categoria_gasto_id);
    $stmt_check_exist->execute();
    $result_check_exist = $stmt_check_exist->get_result();
    if ($result_check_exist->num_rows === 0) {
        $stmt_check_exist->close();
        header("Location: " . $redirect_url . "?error=not_found");
        exit;
    }
    $stmt_check_exist->close();

    // 2. Verificar si la categoría está siendo utilizada en la tabla `gastos`
    // Asumimos que la tabla `gastos` tiene una columna `categoria_id`
    $stmt_check_uso = $conn->prepare("SELECT COUNT(*) as count FROM gastos WHERE categoria_id = ?");
    $stmt_check_uso->bind_param("i", $categoria_gasto_id);
    $stmt_check_uso->execute();
    $result_uso = $stmt_check_uso->get_result();
    $uso_count = $result_uso->fetch_assoc()['count'];
    $stmt_check_uso->close();

    if ($uso_count > 0) {
        // La categoría está en uso, no se puede eliminar.
        $error_key = 'category_in_use';
    } else {
        // La categoría no está en uso, proceder a eliminar.
        $stmt_delete = $conn->prepare("DELETE FROM categorias_gastos WHERE id = ?");
        $stmt_delete->bind_param("i", $categoria_gasto_id);

        if ($stmt_delete->execute()) {
            if ($stmt_delete->affected_rows > 0) {
                $success_key = 'deleted';
            } else {
                // No afectó filas, podría ser que ya fue eliminado o el ID no existe (aunque se verificó)
                $error_key = 'not_found';
            }
        } else {
            $error_key = 'db_error'; // Error en la ejecución del delete
        }
        $stmt_delete->close();
    }

} catch (Exception $e) {
    error_log("Error en categorias_eliminar.php: " . $e->getMessage());
    $error_key = 'db_error';
}

if(isset($conn)) $conn->close();

// Redireccionar con mensaje de éxito o error
if (!empty($success_key)) {
    header("Location: " . $redirect_url . "?success=" . $success_key);
} elseif (!empty($error_key)) {
    header("Location: " . $redirect_url . "?error=" . $error_key);
} else {
    // Caso improbable, pero por si acaso.
    header("Location: " . $redirect_url);
}
exit;
?>

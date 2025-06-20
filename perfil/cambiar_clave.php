<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

protegerPagina(); // Asegura que el usuario esté logueado

$db = new Database();
$conn = $db->getConnection();

$errores = [];
$mensaje_exito = '';
$usuario_id = $_SESSION['usuario_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password_actual = $_POST['password_actual']; // No trim, espacios pueden ser intencionales
    $nueva_password = $_POST['nueva_password'];
    $confirmar_nueva_password = $_POST['confirmar_nueva_password'];

    // Validaciones
    if (empty($password_actual)) {
        $errores[] = "Debe ingresar su contraseña actual.";
    }
    if (empty($nueva_password)) {
        $errores[] = "Debe ingresar una nueva contraseña.";
    } elseif (strlen($nueva_password) < 8) {
        $errores[] = "La nueva contraseña debe tener al menos 8 caracteres.";
    }
    if (empty($confirmar_nueva_password)) {
        $errores[] = "Debe confirmar su nueva contraseña.";
    }
    if ($nueva_password !== $confirmar_nueva_password) {
        $errores[] = "La nueva contraseña y su confirmación no coinciden.";
    }

    if (empty($errores)) {
        try {
            // Verificar contraseña actual
            $stmt_check = $conn->prepare("SELECT password FROM usuarios WHERE id = ?");
            $stmt_check->bind_param("i", $usuario_id);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();

            if ($result_check->num_rows === 1) {
                $usuario_data = $result_check->fetch_assoc();
                if (password_verify($password_actual, $usuario_data['password'])) {
                    // Contraseña actual correcta, proceder a hashear y actualizar
                    $nueva_password_hashed = password_hash($nueva_password, PASSWORD_DEFAULT);
                    if ($nueva_password_hashed === false) {
                        $errores[] = "Error crítico al procesar la nueva contraseña.";
                    } else {
                        $stmt_update = $conn->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
                        $stmt_update->bind_param("si", $nueva_password_hashed, $usuario_id);
                        if ($stmt_update->execute()) {
                            if ($stmt_update->affected_rows > 0) {
                                $mensaje_exito = "Contraseña actualizada correctamente. Se recomienda volver a iniciar sesión.";
                                // Opcional: forzar logout
                                // session_destroy();
                                // header("Location: ../login.php?info=password_changed_relogin");
                                // exit;
                            } else {
                                // Esto podría pasar si la nueva contraseña es igual a la anterior
                                // o si el ID de usuario no se encuentra (improbable aquí)
                                $mensaje_exito = "No se realizaron cambios en la contraseña (podría ser la misma que la anterior).";
                            }
                        } else {
                            $errores[] = "Error al actualizar la contraseña: " . $stmt_update->error;
                        }
                        $stmt_update->close();
                    }
                } else {
                    $errores[] = "La contraseña actual ingresada es incorrecta.";
                }
            } else {
                // Esto no debería pasar si el usuario está logueado y su ID es válido
                $errores[] = "Error: No se pudo encontrar la información del usuario.";
            }
            $stmt_check->close();
        } catch (Exception $e) {
            $errores[] = "Error de base de datos: " . $e->getMessage();
            error_log("Error en cambiar_clave.php para usuario ID $usuario_id: " . $e->getMessage());
        }
    }
}
// $conn->close(); // Se cierra al final del script
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cambiar Contraseña</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/table_styles.css"> <!-- Para alertas y botones -->
    <style>
        /* Reutilizar algunos estilos de formularios de admin/usuarios si son adecuados */
        .form-container { max-width: 500px; margin: 40px auto; padding: 20px; background-color: #fff; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);}
        .form-container h1 { text-align: center; margin-bottom: 25px; color: #333; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #555; }
        .form-group input[type="password"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .button-container { text-align: center; margin-top: 30px; }
    </style>
</head>
<body>
    <div class="form-container">
        <h1>Cambiar Mi Contraseña</h1>

        <?php if (!empty($errores)): ?>
            <div class="alert error">
                <p><strong>Por favor, corrija los siguientes errores:</strong></p>
                <ul>
                    <?php foreach ($errores as $error_msg): ?>
                        <li><?php echo htmlspecialchars($error_msg); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($mensaje_exito): ?>
            <div class="alert success">
                <?php echo htmlspecialchars($mensaje_exito); ?>
            </div>
        <?php endif; ?>

        <form action="cambiar_clave.php" method="post">
            <div class="form-group">
                <label for="password_actual">Contraseña Actual:</label>
                <input type="password" id="password_actual" name="password_actual" required>
            </div>

            <div class="form-group">
                <label for="nueva_password">Nueva Contraseña:</label>
                <input type="password" id="nueva_password" name="nueva_password" required>
            </div>

            <div class="form-group">
                <label for="confirmar_nueva_password">Confirmar Nueva Contraseña:</label>
                <input type="password" id="confirmar_nueva_password" name="confirmar_nueva_password" required>
            </div>

            <div class="button-container page-action-buttons">
                <button type="submit" class="btn-main">Cambiar Contraseña</button>
                <a href="../dashboard.php" class="btn-secondary" style="margin-left:10px;">Volver al Dashboard</a>
            </div>
        </form>
    </div>
<?php if(isset($conn)) $conn->close(); ?>
</body>
</html>

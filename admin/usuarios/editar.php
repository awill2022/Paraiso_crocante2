<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Ya no se necesita el placeholder, protegerPaginaAdmin() está en functions.php
protegerPaginaAdmin();

$db = new Database();
$conn = $db->getConnection();

$usuario_id = 0;
$nombre_persistente = '';
$username_persistente = ''; // Se cargará y será readonly
$rol_persistente = '';
$activo_persistente = '';

$errores = [];
$mensaje_exito = ''; // Para mensajes como "no hubo cambios"

// Roles válidos
$roles_validos = ['administrador', 'cajero', 'cocinero'];

// Obtener ID del usuario de GET o POST (para persistencia tras error)
if (isset($_GET['id'])) {
    $usuario_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
} elseif (isset($_POST['id'])) {
    $usuario_id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
}

if (!$usuario_id || $usuario_id <= 0) {
    header("Location: listar.php?error=invalid_id");
    exit;
}

// Procesamiento del formulario POST para actualizar
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // $usuario_id ya fue validado arriba
    $nombre_persistente = trim($_POST['nombre']);
    $username_persistente = trim($_POST['username']); // Aunque sea readonly, se recibe
    $rol_persistente = trim($_POST['rol']);
    $activo_persistente = isset($_POST['activo']) ? (int)$_POST['activo'] : 0;

    $nueva_password = $_POST['nueva_password'];
    $confirmar_nueva_password = $_POST['confirmar_nueva_password'];
    $password_para_actualizar_hashed = null;

    // Validaciones
    if (strlen($nombre_persistente) > 255) {
        $errores[] = "El nombre completo no puede exceder los 255 caracteres.";
    }
    // Username no se valida aquí para unicidad si es readonly. Si fuera editable, se necesitaría.

    if (empty($rol_persistente) || !in_array($rol_persistente, $roles_validos)) {
        $errores[] = "Debe seleccionar un rol válido.";
    }

    if (!in_array($activo_persistente, [0, 1])) {
        $errores[] = "El estado de activación no es válido.";
    }

    // Validar y hashear nueva contraseña si se proporcionó
    if (!empty($nueva_password) || !empty($confirmar_nueva_password)) {
        if (empty($nueva_password)) {
            $errores[] = "La nueva contraseña no puede estar vacía si se intenta cambiar.";
        } elseif (strlen($nueva_password) < 8) {
            $errores[] = "La nueva contraseña debe tener al menos 8 caracteres.";
        }
        if ($nueva_password !== $confirmar_nueva_password) {
            $errores[] = "Las nuevas contraseñas no coinciden.";
        }
        if (empty($errores)) { // Solo hashear si no hay errores previos de contraseña
            $password_para_actualizar_hashed = password_hash($nueva_password, PASSWORD_DEFAULT);
            if ($password_para_actualizar_hashed === false) {
                $errores[] = "Error crítico al hashear la nueva contraseña.";
            }
        }
    }

    // Evitar que el usuario se desactive a sí mismo o cambie su propio rol si es admin
    if ($usuario_id == $_SESSION['usuario_id']) {
        // Cargar el rol actual del usuario desde la BD para compararlo
        $stmt_user_self = $conn->prepare("SELECT rol, activo FROM usuarios WHERE id = ?");
        $stmt_user_self->bind_param("i", $usuario_id);
        $stmt_user_self->execute();
        $user_self_data = $stmt_user_self->get_result()->fetch_assoc();
        $stmt_user_self->close();

        if ($user_self_data) {
            if ($activo_persistente != $user_self_data['activo']) {
                 $errores[] = "No puedes cambiar tu propio estado de activación.";
                 $activo_persistente = $user_self_data['activo']; // Revertir al valor original
            }
            if ($rol_persistente != $user_self_data['rol'] && $user_self_data['rol'] == 'administrador') {
                 // Podría haber lógica más compleja aquí, como verificar si es el único admin
                 $errores[] = "No puedes cambiar tu propio rol de administrador.";
                 $rol_persistente = $user_self_data['rol']; // Revertir
            }
        }
    }


    if (empty($errores)) {
        try {
            if ($password_para_actualizar_hashed) {
                $sql = "UPDATE usuarios SET nombre = ?, rol = ?, activo = ?, password = ? WHERE id = ?";
                $stmt_update = $conn->prepare($sql);
                $stmt_update->bind_param("ssisi", $nombre_persistente, $rol_persistente, $activo_persistente, $password_para_actualizar_hashed, $usuario_id);
            } else {
                $sql = "UPDATE usuarios SET nombre = ?, rol = ?, activo = ? WHERE id = ?";
                $stmt_update = $conn->prepare($sql);
                $stmt_update->bind_param("ssii", $nombre_persistente, $rol_persistente, $activo_persistente, $usuario_id);
            }

            if ($stmt_update->execute()) {
                if ($stmt_update->affected_rows > 0 || (!$password_para_actualizar_hashed && $stmt_update->affected_rows === 0)) {
                    // Considerar éxito también si no se cambió contraseña y otros campos no cambiaron (affected_rows = 0)
                    header("Location: listar.php?success=updated");
                    exit;
                } else if ($password_para_actualizar_hashed && $stmt_update->affected_rows === 0){
                     // Si se intentó cambiar contraseña pero no afectó filas (raro, ID no existe?)
                    $errores[] = "Error al actualizar: el usuario no fue encontrado o la contraseña no pudo ser actualizada.";
                }
                 else {
                    $mensaje_exito = "No se realizaron cambios (o los datos son iguales a los existentes).";
                }
            } else {
                $errores[] = "Error al actualizar el usuario: " . $stmt_update->error;
            }
            $stmt_update->close();
        } catch (Exception $e) {
            $errores[] = "Error de base de datos: " . $e->getMessage();
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') { // Cargar datos para el formulario (solo en GET inicial)
    try {
        $stmt_load = $conn->prepare("SELECT username, nombre, rol, activo FROM usuarios WHERE id = ?");
        $stmt_load->bind_param("i", $usuario_id);
        $stmt_load->execute();
        $result_load = $stmt_load->get_result();
        if ($result_load->num_rows === 1) {
            $usuario = $result_load->fetch_assoc();
            $username_persistente = $usuario['username'];
            $nombre_persistente = $usuario['nombre'];
            $rol_persistente = $usuario['rol'];
            $activo_persistente = $usuario['activo'];
        } else {
            header("Location: listar.php?error=not_found");
            exit;
        }
        $stmt_load->close();
    } catch (Exception $e) {
        // $errores[] = "Error al cargar datos del usuario: " . $e->getMessage(); // Podría mostrarse en la página de listar
        header("Location: listar.php?error=db_error_load");
        exit;
    }
} else { // Método no permitido o ID no válido en GET inicial
     header("Location: listar.php?error=invalid_request");
     exit;
}
// $conn->close(); // Se cierra al final
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Usuario</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/table_styles.css">
    <style>
        .form-container { max-width: 600px; margin: 40px auto; padding: 20px; background-color: #fff; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);}
        .form-container h1 { text-align: center; margin-bottom: 25px; color: #333; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #555; }
        .form-group input[type="text"],
        .form-group input[type="password"],
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .form-group input[readonly] { background-color: #e9ecef; cursor: not-allowed; }
        .password-note { font-size: 0.9em; color: #777; margin-top: 5px; }
        .button-container { text-align: center; margin-top: 30px; }
    </style>
</head>
<body>
    <div class="form-container">
        <h1>Editar Usuario: <?php echo htmlspecialchars($username_persistente); ?></h1>

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

        <?php if ($mensaje_exito && empty($errores)): ?>
            <div class="alert success"><?php echo htmlspecialchars($mensaje_exito); ?></div>
        <?php endif; ?>

        <form action="editar.php" method="post">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($usuario_id); ?>">

            <div class="form-group">
                <label for="username">Nombre de Usuario (login):</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username_persistente); ?>" readonly>
            </div>

            <div class="form-group">
                <label for="nombre">Nombre (Opcional):</label>
                <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($nombre_persistente); ?>">
            </div>

            <fieldset>
                <legend>Cambiar Contraseña (opcional)</legend>
                <div class="form-group">
                    <label for="nueva_password">Nueva Contraseña:</label>
                    <input type="password" id="nueva_password" name="nueva_password">
                    <p class="password-note">Deje en blanco si no desea cambiar la contraseña.</p>
                </div>
                <div class="form-group">
                    <label for="confirmar_nueva_password">Confirmar Nueva Contraseña:</label>
                    <input type="password" id="confirmar_nueva_password" name="confirmar_nueva_password">
                </div>
            </fieldset>

            <div class="form-group">
                <label for="rol">Rol:</label>
                <select id="rol" name="rol" required <?php if ($usuario_id == $_SESSION['usuario_id'] && $rol_persistente == 'administrador') echo 'disabled'; ?>>
                    <option value="">Seleccione un rol...</option>
                    <?php foreach ($roles_validos as $rol_opcion): ?>
                        <option value="<?php echo $rol_opcion; ?>" <?php echo ($rol_persistente == $rol_opcion) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars(ucfirst($rol_opcion)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                 <?php if ($usuario_id == $_SESSION['usuario_id'] && $rol_persistente == 'administrador'): ?>
                    <p class="password-note">No puede cambiar su propio rol de administrador.</p>
                    <input type="hidden" name="rol" value="<?php echo htmlspecialchars($rol_persistente); ?>" /> <!-- Enviar el valor si está disabled -->
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="activo">Estado:</label>
                <select id="activo" name="activo" required <?php if ($usuario_id == $_SESSION['usuario_id']) echo 'disabled'; ?>>
                    <option value="1" <?php echo ($activo_persistente == '1') ? 'selected' : ''; ?>>Activo</option>
                    <option value="0" <?php echo ($activo_persistente == '0') ? 'selected' : ''; ?>>Inactivo</option>
                </select>
                <?php if ($usuario_id == $_SESSION['usuario_id']): ?>
                    <p class="password-note">No puede cambiar su propio estado de activación.</p>
                     <input type="hidden" name="activo" value="<?php echo htmlspecialchars($activo_persistente); ?>" /> <!-- Enviar el valor si está disabled -->
                <?php endif; ?>
            </div>

            <div class="button-container page-action-buttons">
                <button type="submit" class="btn-main">Actualizar Usuario</button>
                <a href="listar.php" class="btn-secondary" style="margin-left:10px;">Volver a la Lista</a>
            </div>
        </form>
    </div>
<?php if(isset($conn)) $conn->close(); ?>
</body>
</html>

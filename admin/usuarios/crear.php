<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Ya no se necesita el placeholder, protegerPaginaAdmin() está en functions.php
protegerPaginaAdmin();

$db = new Database();
$conn = $db->getConnection();

$nombre_persistente = '';
$username_persistente = '';
// No persistir contraseñas
$rol_persistente = '';
$activo_persistente = '1'; // Activo por defecto

$errores = [];
$mensaje_exito = '';

// Roles válidos - se podrían cargar de BD o config si fuera más complejo
$roles_validos = ['administrador', 'cajero', 'cocinero'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre_persistente = trim($_POST['nombre']);
    $username_persistente = trim($_POST['username']);
    $password = $_POST['password']; // No trim, la contraseña puede tener espacios intencionales
    $password_confirm = $_POST['password_confirm'];
    $rol_persistente = trim($_POST['rol']);
    $activo_persistente = isset($_POST['activo']) ? (int)$_POST['activo'] : 0;

    // Validaciones
    if (strlen($nombre_persistente) > 255) {
        $errores[] = "El nombre completo no puede exceder los 255 caracteres.";
    }

    if (empty($username_persistente)) {
        $errores[] = "El nombre de usuario (username) es obligatorio.";
    } elseif (strlen($username_persistente) < 4 || strlen($username_persistente) > 50) {
        $errores[] = "El nombre de usuario debe tener entre 4 y 50 caracteres.";
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username_persistente)) {
        $errores[] = "El nombre de usuario solo puede contener letras, números y guiones bajos (_).";
    } else {
        // Verificar unicidad del username
        try {
            $stmt_check_user = $conn->prepare("SELECT id FROM usuarios WHERE username = ?");
            $stmt_check_user->bind_param("s", $username_persistente);
            $stmt_check_user->execute();
            $result_check_user = $stmt_check_user->get_result();
            if ($result_check_user->num_rows > 0) {
                $errores[] = "El nombre de usuario '" . htmlspecialchars($username_persistente) . "' ya está en uso.";
            }
            $stmt_check_user->close();
        } catch (Exception $e) {
            $errores[] = "Error al verificar el nombre de usuario: " . $e->getMessage();
        }
    }

    if (empty($password)) {
        $errores[] = "La contraseña es obligatoria.";
    } elseif (strlen($password) < 8) {
        $errores[] = "La contraseña debe tener al menos 8 caracteres.";
    }
    if ($password !== $password_confirm) {
        $errores[] = "Las contraseñas no coinciden.";
    }

    if (empty($rol_persistente) || !in_array($rol_persistente, $roles_validos)) {
        $errores[] = "Debe seleccionar un rol válido.";
    }

    if (!in_array($activo_persistente, [0, 1])) {
        $errores[] = "El estado de activación no es válido.";
    }

    if (empty($errores)) {
        $password_hashed = password_hash($password, PASSWORD_DEFAULT);
        if ($password_hashed === false) {
            $errores[] = "Error crítico al hashear la contraseña.";
        } else {
            try {
                $sql = "INSERT INTO usuarios (username, password, nombre, rol, activo) VALUES (?, ?, ?, ?, ?)";
                $stmt_insert = $conn->prepare($sql);
                // El nombre completo puede ser NULL si está vacío, o se guarda cadena vacía.
                // Si la DB permite NULL para nombre y se prefiere:
                // $nombre_db = !empty($nombre_persistente) ? $nombre_persistente : null;
                $stmt_insert->bind_param("ssssi",
                    $username_persistente,
                    $password_hashed,
                    $nombre_persistente, // Usar directamente, se guarda como "" si está vacío
                    $rol_persistente,
                    $activo_persistente
                );

                if ($stmt_insert->execute()) {
                    // header("Location: listar.php?success=created");
                    // exit;
                    $mensaje_exito = "Usuario '" . htmlspecialchars($username_persistente) . "' creado correctamente.";
                    // Limpiar campos para nuevo ingreso, excepto quizás 'rol' o 'activo' si se quieren defaults
                    $nombre_persistente = '';
                    $username_persistente = '';
                    $rol_persistente = ''; // O mantener el último rol seleccionado
                    $activo_persistente = '1';
                } else {
                    $errores[] = "Error al crear el usuario: " . $stmt_insert->error;
                }
                $stmt_insert->close();
            } catch (Exception $e) {
                $errores[] = "Error de base de datos: " . $e->getMessage();
            }
        }
    }
}
// $conn->close(); // Se cierra al final del script
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Crear Nuevo Usuario</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/table_styles.css"> <!-- Para alertas y botones consistentes -->
     <style>
        /* Reutilizar algunos estilos de formularios de productos si son adecuados */
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
        .form-group input[type="checkbox"] { width: auto; margin-right: 5px;}
        .button-container { text-align: center; margin-top: 30px; }
        /* Clases btn-main y btn-secondary de table_styles.css deberían funcionar para los botones/enlaces */
    </style>
</head>
<body>
    <div class="form-container">
        <h1>Crear Nuevo Usuario</h1>

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

        <form action="crear.php" method="post">
            <div class="form-group">
                <label for="nombre">Nombre (Opcional):</label>
                <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($nombre_persistente); ?>">
            </div>

            <div class="form-group">
                <label for="username">Nombre de Usuario (login):</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username_persistente); ?>" required>
            </div>

            <div class="form-group">
                <label for="password">Contraseña:</label>
                <input type="password" id="password" name="password" required>
            </div>

            <div class="form-group">
                <label for="password_confirm">Confirmar Contraseña:</label>
                <input type="password" id="password_confirm" name="password_confirm" required>
            </div>

            <div class="form-group">
                <label for="rol">Rol:</label>
                <select id="rol" name="rol" required>
                    <option value="">Seleccione un rol...</option>
                    <?php foreach ($roles_validos as $rol_opcion): ?>
                        <option value="<?php echo $rol_opcion; ?>" <?php echo ($rol_persistente == $rol_opcion) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars(ucfirst($rol_opcion)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="activo">Estado:</label>
                <select id="activo" name="activo" required>
                    <option value="1" <?php echo ($activo_persistente == '1') ? 'selected' : ''; ?>>Activo</option>
                    <option value="0" <?php echo ($activo_persistente == '0') ? 'selected' : ''; ?>>Inactivo</option>
                </select>
            </div>

            <div class="button-container page-action-buttons"> <!-- Usar page-action-buttons para consistencia si se desea -->
                <button type="submit" class="btn-main">Guardar Usuario</button>
                <a href="listar.php" class="btn-secondary" style="margin-left:10px;">Volver a la Lista</a>
                <a href="../../dashboard.php" class="btn-secondary" style="margin-left:10px;">Volver al Dashboard</a>
            </div>
        </form>
    </div>
<?php if(isset($conn)) $conn->close(); ?>
</body>
</html>

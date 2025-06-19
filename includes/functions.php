<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Database.php';

function login($username, $password) {
    $db = new Database();
    $conn = $db->getConnection();

    $stmt = $conn->prepare("SELECT * FROM usuarios WHERE username = ?");
    if (!$stmt) {
        die("Error preparando la consulta: " . $conn->error);
    }

    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
       
        // Aquí está la verificación segura de la contraseña con hash
        if (password_verify($password, $row['password'])) {
            $_SESSION['usuario_id'] = $row['id'];
            $_SESSION['usuario_nombre'] = $row['username']; // O $row['nombre_completo'] si se prefiere
            $_SESSION['usuario_rol'] = $row['rol']; // Guardar el rol del usuario en la sesión
            // echo "✅ Login exitoso"; // Evitar echo en funciones que podrían usarse en API o antes de headers
            return true;
        } else {
            echo "❌ Contraseña incorrecta";
            return false;
        }

    } else {
        echo "❌ Usuario no encontrado";
        return false;
    }
}

function obtenerProductos($categoria_id = null) {
    $db = new Database();
    $conn = $db->getConnection();
    
    $query = "SELECT p.*, c.nombre AS categoria_nombre FROM productos p JOIN categorias c ON p.categoria_id = c.id WHERE p.activo = 1";
    
    if($categoria_id) {
        $query .= " AND p.categoria_id = $categoria_id";
    }
    
    return $conn->query($query);
}

function registrarGasto($fecha, $monto, $descripcion, $categoria_id, $usuario_id) {
    $db = new Database();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("INSERT INTO gastos (fecha, monto, descripcion, categoria_id, usuario_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sdsii", $fecha, $monto, $descripcion, $categoria_id, $usuario_id);
    return $stmt->execute();
}

// Función para proteger páginas de administrador
function protegerPaginaAdmin() {
    // protegerPagina() está definida en config.php y es global una vez config.php es incluido.
    // config.php también maneja session_start().
    protegerPagina();

    if (!isset($_SESSION['usuario_rol']) || $_SESSION['usuario_rol'] !== 'administrador') {
        // BASE_URL está definida en config.php y debería estar disponible aquí.
        // Si functions.php se incluye desde un script en admin/usuarios/,
        // y dashboard.php está en la raíz, la redirección a BASE_URL . "dashboard.php" es correcta.
        if (defined('BASE_URL')) {
            header("Location: " . BASE_URL . "dashboard.php?error=acceso_denegado_admin");
        } else {
            // Fallback muy básico si BASE_URL no estuviera definida por alguna razón
            // Esto es menos ideal y depende de la estructura de directorios.
            // Asumiendo que el script que incluye esto está en admin/usuarios/ o similar
            header("Location: ../../dashboard.php?error=acceso_denegado_admin&reason=base_url_undefined");
        }
        exit;
    }
}
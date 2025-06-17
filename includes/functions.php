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
            $_SESSION['usuario_nombre'] = $row['username'];
            echo "✅ Login exitoso";
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
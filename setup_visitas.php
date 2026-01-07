<?php
// Intentar cargar configuración, pero no fallar si no conecta
file_put_contents("debug_setup.txt", "Iniciando setup...\n");

$host = 'localhost';
$user = 'root'; // Intentar primero credenciales XAMPP por defecto si estamos en local
$pass = '';
$db_name = 'parahyvc_paraisocrocante';

// Verificar si existe el config original para leer el nombre de la BD si es diferente
if (file_exists('includes/config.php')) {
    $config_content = file_get_contents('includes/config.php');
    if (preg_match("/define\('DB_NAME',\s*'([^']+)'\)/", $config_content, $matches)) {
        $db_name = $matches[1];
    }
}

// 1. Intentar conexión básica local (XAMPP default)
$conn = new mysqli($host, 'root', '', $db_name);

if ($conn->connect_error) {
    file_put_contents("debug_setup.txt", "Fallo intento 1 (root/empty): " . $conn->connect_error . "\n", FILE_APPEND);
    
    // 2. Intentar con las credenciales del config.php
    if (file_exists('includes/config.php')) {
        require_once 'includes/config.php';
        // Asumiendo que DB_HOST etc están definidos ahora
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
             file_put_contents("debug_setup.txt", "Fallo intento 2 (Config): " . $conn->connect_error . "\n", FILE_APPEND);
             die("No se pudo conectar a la base de datos con ninguna credencial. Por favor verifica tu configuración.");
        }
    }
}

// Crear tabla
$sql = "CREATE TABLE IF NOT EXISTS historial_visitas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fecha_visita DATETIME NOT NULL,
    ip_usuario VARCHAR(45) NOT NULL,
    pagina_visitada VARCHAR(255) DEFAULT 'login'
)";

if ($conn->query($sql) === TRUE) {
    echo "<h1>Éxtio</h1><p>La tabla 'historial_visitas' ha sido creada correctamente.</p>";
    file_put_contents("debug_setup.txt", "Tabla creada correctamente.\n", FILE_APPEND);
} else {
    echo "<h1>Error</h1><p>Error creando tabla: " . $conn->error . "</p>";
    file_put_contents("debug_setup.txt", "Error creando tabla: " . $conn->error . "\n", FILE_APPEND);
}

// Opcional: Insertar un dato de prueba
$conn->query("INSERT INTO historial_visitas (fecha_visita, ip_usuario) VALUES (NOW(), '127.0.0.1')");

$conn->close();
?>

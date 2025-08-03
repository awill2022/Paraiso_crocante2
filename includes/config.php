<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// Configuración de la aplicación
define('DB_HOST', 'localhost');
define('DB_USER', 'parahyvc_paraiso');
define('DB_PASS', '@paraiso2025');
define('DB_NAME', 'parahyvc_paraisocrocante');

// Iniciar sesión
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Otras configuraciones
define('SITE_NAME', 'Fresas con Crema');
define('BASE_URL', 'http://localhost/paraiso_crocante/');
define('UPLOAD_DIR', __DIR__ . '/../assets/img/productos/');

// Función para proteger páginas
function protegerPagina() {
    if(!isset($_SESSION['usuario_id'])) {
        header("Location: " . BASE_URL . "/index.php");
        exit;
    }
}
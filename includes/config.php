<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// Configuración de la aplicación
define('DB_HOST', 'localhost');
define('DB_USER', 'parahyvc_paraiso');
define('DB_PASS', '@paraiso2025');
define('DB_NAME', 'parahyvc_paraisocrocante');

// Iniciar sesión con persistencia prolongada (24 horas)
if (session_status() == PHP_SESSION_NONE) {
    // 86400 segundos = 24 horas
    ini_set('session.gc_maxlifetime', 86400);
    session_set_cookie_params(86400);
    session_start();
}

// Otras configuraciones
define('SITE_NAME', 'Fresas con Crema');
define('BASE_URL', 'http://paraisocrocante.com/Paraiso_crocante/');
define('UPLOAD_DIR', __DIR__ . '/../assets/img/productos/');

// Función para proteger páginas
function protegerPagina() {
    if(!isset($_SESSION['usuario_id'])) {
        header("Location: " . BASE_URL . "login.php");
        exit;
    }
}
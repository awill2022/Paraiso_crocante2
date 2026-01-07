<?php
session_start();
require_once 'includes/config.php';

// --- LOGICA DE CONTADOR DE VISITAS ---
try {
    require_once 'includes/Database.php';
    $db = new Database();
    $conn = $db->getConnection();
    
    // Obtener IP del cliente
    $ip = $_SERVER['REMOTE_ADDR'];
    // Insertar visita
    $stmt = $conn->prepare("INSERT INTO historial_visitas (fecha_visita, ip_usuario) VALUES (NOW(), ?)");
    $stmt->bind_param("s", $ip);
    $stmt->execute();
    $stmt->close();
} catch (Exception $e) {
    // Silencioso: Si falla la base de datos, no detener la carga de la página
    // error_log("Error registrando visita: " . $e->getMessage());
}
// -------------------------------------

if(isset($_SESSION['usuario_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    var_dump($_POST);
    require_once 'includes/Database.php';
    require_once 'includes/functions.php';
    
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    
    if(login($username, $password)) {
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Usuario o contraseña incorrectos";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Fresas con Crema</title>
    <link rel="icon" href="/img/favicon.ico" type="image/x-icon" />
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <img src="assets/img/logo.png" alt="Fresas con Crema">
            <h2 style="margin-top: 15px; color: #FF6B6B;">Bienvenido</h2>
        </div>
        
        <?php if($error): ?>
        <div class="alert error">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        </div>
        <?php endif; ?>
        
        <form action="login.php" method="post" id="loginForm">
            <div class="form-group">
                <label for="username"><i class="fas fa-user"></i> Usuario:</label>
                <input type="text" id="username" name="username" required placeholder="Ingresa tu usuario">
            </div>
            
            <div class="form-group">
                <label for="password"><i class="fas fa-lock"></i> Contraseña:</label>
                <input type="password" id="password" name="password" required placeholder="Ingresa tu contraseña">
            </div>
            
            <button type="submit" class="btn" id="loginBtn">
                <span id="btnText">Ingresar</span>
            </button>
        </form>
    </div>

    <script>
        // Animación al enviar el formulario
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('loginBtn');
            const btnText = document.getElementById('btnText');
            
            btn.classList.add('loading');
            btnText.textContent = 'Ingresando...';
            btn.disabled = true;
        });
        
        // Efecto al enfocar inputs
        const inputs = document.querySelectorAll('input');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.parentNode.querySelector('label').style.color = '#FF6B6B';
            });
            
            input.addEventListener('blur', function() {
                this.parentNode.querySelector('label').style.color = '#555';
            });
        });
    </script>
</body>
</html>
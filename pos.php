<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

protegerPagina();

$db = new Database();
$conn = $db->getConnection();

// Obtener categorías y productos
$categorias = $conn->query("SELECT * FROM categorias");
$productos = $conn->query("SELECT p.*, c.nombre AS categoria_nombre FROM productos p JOIN categorias c ON p.categoria_id = c.id WHERE p.activo = 1");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Punto de Venta - Fresas con Crema</title>
    <link rel="stylesheet" href="assets/css/pos.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="pos-container">
        <header class="pos-header">
            <h1>Punto de Venta</h1>
            <div class="user-info">
                <span><?php echo $_SESSION['usuario_nombre']; ?></span>
                <a href="dashboard.php" class="btn">Volver</a>
            </div>
        </header>
        
        <div class="pos-main">
            <div class="productos-section">
                <div class="categorias">
                    <button class="categoria-btn active" data-categoria="all">Todos</button>
                    <?php while($categoria = $categorias->fetch_assoc()): ?>
                    <button class="categoria-btn" data-categoria="<?php echo $categoria['id']; ?>">
                        <?php echo htmlspecialchars($categoria['nombre']); ?>
                    </button>
                    <?php endwhile; ?>
                </div>
                
                <div class="productos-grid">
                    <?php while($producto = $productos->fetch_assoc()): ?>
                    <div class="producto-card" 
                         data-id="<?php echo $producto['id']; ?>"
                         data-categoria="<?php echo $producto['categoria_id']; ?>"
                         data-precio="<?php echo $producto['precio']; ?>"
                         data-nombre="<?php echo htmlspecialchars($producto['nombre']); ?>">
                        <div class="producto-imagen">
                            <img src="assets/img/productos/<?php echo $producto['foto'] ?: 'default.jpg'; ?>" alt="<?php echo htmlspecialchars($producto['nombre']); ?>">
                        </div>
                        <div class="producto-info">
                            <h3><?php echo htmlspecialchars($producto['nombre']); ?></h3>
                            <p>$<?php echo number_format($producto['precio'], 2); ?></p>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
            
            <div class="venta-section">
                <div class="venta-header">
                    <h2>Venta Actual</h2>
                    <button class="btn" onclick="limpiarVenta()">Limpiar</button>
                </div>
                
                <div class="venta-items" id="venta-items">
                    <!-- Aquí se agregarán los productos -->
                </div>
                
                <div class="venta-total">
                    <h3>Total:</h3>
                    <h3 id="venta-total">$0.00</h3>
                </div>
                
                <div class="metodos-pago">
                    <button class="metodo-btn active" data-metodo="efectivo">
                        <i class="fas fa-money-bill-wave"></i> Efectivo
                    </button>
                    <!-- Cambiado de Tarjeta a De Una -->
                    <button class="metodo-btn" data-metodo="de_una" style="background-color: #d62828;"> <!-- Color distintivo opcional -->
                        <i class="fas fa-mobile-alt"></i> De Una
                    </button>
                </div>
                
                <div class="acciones-venta">
                    <button class="btn cancelar-btn" onclick="cancelarVenta()">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                    <button class="btn cobrar-btn" onclick="finalizarVenta()">
                        <i class="fas fa-cash-register"></i> Cobrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Pasar variables PHP a JavaScript
        const usuarioId = <?php echo $_SESSION['usuario_id'] ?? 0; ?>;
    </script>
    <script src="assets/js/pos.js"></script>
</body>
</html>
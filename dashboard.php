<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

protegerPagina();

$db = new Database();
$conn = $db->getConnection();

// Consultas
$ventasHoy = $conn->query("SELECT SUM(total) AS total FROM ventas WHERE DATE(fecha) = CURDATE()")->fetch_assoc();
$gastosHoy = $conn->query("SELECT SUM(monto) AS total FROM gastos WHERE DATE(fecha) = CURDATE()")->fetch_assoc();
$productosBajoStock = $conn->query("SELECT nombre, stock, 5 AS stock_minimo FROM ingredientes WHERE stock <= 5 LIMIT 5");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - Paraíso Crocante</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f9f9f9;
            margin: 0;
            padding: 0;
        }
        .dashboard {
            padding: 20px;
        }
        .header {
            background: #FF6B6B;
            color: white;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .btn {
            background: white;
            color: #FF6B6B;
            padding: 10px 15px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
        }
        .stats {
            display: flex;
            gap: 20px;
            margin: 20px 0;
        }
        .stat-card {
            flex: 1;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .positive { color: green; }
        .negative { color: red; }
        .sections {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        .section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            flex: 1;
            min-width: 300px;
            box-shadow: 0 0 10px rgba(0,0,0,0.05);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        table, th, td {
            border: 1px solid #eee;
        }
        th, td {
            padding: 10px;
            text-align: left;
        }
        .quick-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 10px;
        }
        .action-btn {
            background: #FF6B6B;
            color: white;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            flex: 1;
            text-decoration: none;
            min-width: 120px;
        }
        .action-btn span:first-child {
            font-size: 20px;
            display: block;
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <header class="header">
            <h1>Bienvenido, <?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?></h1>
            <nav>
                <a href="pos.php" class="btn">Punto de Venta</a>
                <a href="logout.php" class="btn">Cerrar Sesión</a>
            </nav>
        </header>

        <div class="stats">
            <div class="stat-card">
                <h3>Ventas Hoy</h3>
                <p>$<?php echo number_format($ventasHoy['total'] ?? 0, 2); ?></p>
            </div>

            <div class="stat-card">
                <h3>Gastos Hoy</h3>
                <p>$<?php echo number_format($gastosHoy['total'] ?? 0, 2); ?></p>
            </div>

            <div class="stat-card">
                <h3>Balance</h3>
                <p class="<?php echo (($ventasHoy['total'] ?? 0) - ($gastosHoy['total'] ?? 0)) >= 0 ? 'positive' : 'negative'; ?>">
                    $<?php echo number_format(($ventasHoy['total'] ?? 0) - ($gastosHoy['total'] ?? 0), 2); ?>
                </p>
            </div>
        </div>

        <div class="sections">
            <section class="section">
                <h2>Productos con Bajo Stock</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Stock</th>
                            <th>Stock Mínimo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($producto = $productosBajoStock->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($producto['nombre']); ?></td>
                            <td><?php echo $producto['stock']; ?></td>
                            <td><?php echo $producto['stock_minimo']; ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </section>

            <section class="section">
                <h2>Acciones Rápidas</h2>
                <div class="quick-actions">
                    <a href="pos.php" class="action-btn">
                        <span>➕</span>
                        <span>Nueva Venta</span>
                    </a>
                    <a href="productos/agregar.php" class="action-btn"> <!-- Productos -->
                        <span>🍓</span>
                        <span>Agregar Producto</span>
                    </a>
                     <a href="productos/listar.php" class="action-btn"> <!-- Productos -->
                        <span>📋</span>
                        <span>Gestionar Productos</span>
                    </a>
                    <?php if (isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'administrador'): ?>
                    <a href="admin/usuarios/listar.php" class="action-btn"> <!-- Admin Usuarios -->
                        <span>🧑‍💼</span>
                        <span>Administrar Usuarios</span>
                    </a>
                    <?php endif; ?>
                    <a href="insumos/agregar_insumo.php" class="action-btn"> <!-- Insumos -->
                        <span>🌾</span>
                        <span>Agregar Insumo</span>
                    </a>
                    <a href="insumos/listar_insumos.php" class="action-btn"> <!-- Insumos -->
                        <span>📦</span>
                        <span>Gestionar Insumos</span>
                    </a>
                    <a href="gastos/registrar_gasto.php" class="action-btn"> <!-- Gastos -->
                        <span>💸</span>
                        <span>Registrar Gasto</span>
                    </a>
                    <a href="gastos/listar_gastos.php" class="action-btn"> <!-- Gastos -->
                        <span>🧾</span>
                        <span>Listar Gastos</span>
                    </a>
                    <a href="gastos/categorias_listar.php" class="action-btn"> <!-- Gastos -->
                        <span>🏷️</span>
                        <span>Categorías de Gasto</span>
                    </a>
                    <a href="reportes/rentabilidad.php" class="action-btn"> <!-- Reportes -->
                        <span>📊</span>
                        <span>Ver Reportes</span>
                    </a>
                    <!-- Se podrían añadir más acciones rápidas aquí -->
                </div>
            </section>
        </div>
    </div>
</body>
</html>

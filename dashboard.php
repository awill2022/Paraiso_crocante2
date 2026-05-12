<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

protegerPagina();

if (isset($_SESSION['usuario_rol']) && !in_array($_SESSION['usuario_rol'], ['administrador', 'cajero', 'admin', 'cocinero'])) {
    header("Location: pos.php");
    exit;
}

$es_admin   = (isset($_SESSION['usuario_rol']) && in_array($_SESSION['usuario_rol'], ['administrador', 'admin']));
$es_cajero  = (isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'cajero');
$es_cocinero= (isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'cocinero');

$db = new Database();
$conn = $db->getConnection();

$ventasHoy          = ['total' => 0];
$gastosHoy          = ['total' => 0];
$productosBajoStock = null;
$totalVisitas       = 0;

if ($es_admin) {
    try {
        $ventasHoy = $conn->query("SELECT SUM(total) AS total FROM ventas WHERE DATE(fecha) = CURDATE()")->fetch_assoc();
        $gastosHoy = $conn->query("SELECT SUM(monto) AS total FROM gastos WHERE DATE(fecha) = CURDATE()")->fetch_assoc();
        $productosBajoStock = $conn->query("SELECT id, nombre, stock_actual, stock_minimo FROM insumos WHERE stock_actual <= stock_minimo");
        $resVisitas = $conn->query("SELECT COUNT(*) as total FROM historial_visitas");
        if ($resVisitas && $rowVisitas = $resVisitas->fetch_assoc()) {
            $totalVisitas = $rowVisitas['total'];
        }
    } catch (Exception $e) { /* silencioso */ }
}

$balance = ($ventasHoy['total'] ?? 0) - ($gastosHoy['total'] ?? 0);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - Paraíso Crocante</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="/img/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="assets/css/app.css">
    <style>
        /* Estilo específico del rol-panel */
        .role-panel {
            background: white;
            border-radius: 14px;
            padding: 24px;
            text-align: center;
            margin-bottom: 20px;
            box-shadow: 0 2px 14px rgba(0,0,0,0.06);
        }
        .role-panel h2 { font-size: 1.3em; margin-bottom: 6px; }
        .role-panel p  { color: #666; font-size: 0.92em; }

        /* Stock bajo pill */
        .stock-bajo-row td:first-child::before {
            content: '⚠️ ';
        }
    </style>
</head>
<body class="app-body">

<header class="app-header">
    <div>
        <h1>🍓 Bienvenido/a, <?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?>
            <?php
                if ($es_cajero) echo ' <small style="opacity:.8;font-size:.6em;">(Cajera)</small>';
                elseif ($es_cocinero) echo ' <small style="opacity:.8;font-size:.6em;">(Cocinero)</small>';
            ?>
        </h1>
        <p>Paraíso Crocante — Panel de Control</p>
    </div>
    <nav>
        <?php if (!$es_cocinero): ?>
            <a href="pos.php" class="btn-nav">🛒 Punto de Venta</a>
        <?php endif; ?>
        <a href="logout.php" class="btn-nav">⏻ Salir</a>
    </nav>
</header>

<div class="app-page wide">

    <?php if ($es_admin): ?>
    <!-- Estadísticas -->
    <div class="stats-grid">
        <div class="stat-card">
            <h3>Ventas Hoy</h3>
            <div class="stat-value">$<?php echo number_format($ventasHoy['total'] ?? 0, 2); ?></div>
        </div>
        <div class="stat-card">
            <h3>Gastos Hoy</h3>
            <div class="stat-value">$<?php echo number_format($gastosHoy['total'] ?? 0, 2); ?></div>
        </div>
        <div class="stat-card <?php echo $balance >= 0 ? 'positive' : 'negative'; ?>">
            <h3>Balance del Día</h3>
            <div class="stat-value">$<?php echo number_format($balance, 2); ?></div>
        </div>
        <div class="stat-card accent">
            <h3>Visitas al Inicio</h3>
            <div class="stat-value"><?php echo number_format($totalVisitas); ?></div>
        </div>
    </div>

    <?php elseif ($es_cajero): ?>
    <div class="role-panel">
        <h2>💵 Panel de Cajera</h2>
        <p>Seleccione una opción para comenzar.</p>
    </div>
    <?php elseif ($es_cocinero): ?>
    <div class="role-panel">
        <h2>👨‍🍳 Panel de Cocina</h2>
        <p>Bienvenido. Consulte las recetas de preparación.</p>
    </div>
    <?php endif; ?>

    <!-- Insumos con stock bajo (solo admin) -->
    <?php if ($es_admin && $productosBajoStock && $productosBajoStock->num_rows > 0): ?>
    <div class="app-card">
        <div class="section-title">⚠️ Insumos con Stock Bajo</div>
        <div class="table-wrapper">
            <table class="app-table">
                <thead>
                    <tr>
                        <th>Insumo</th>
                        <th>Stock Actual</th>
                        <th>Stock Mínimo</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($p = $productosBajoStock->fetch_assoc()):
                        $bajo = $p['stock_actual'] < $p['stock_minimo'];
                    ?>
                    <tr class="<?php echo $bajo ? 'stock-bajo' : ''; ?> stock-bajo-row">
                        <td><?php echo htmlspecialchars($p['nombre']); ?></td>
                        <td><?php echo number_format($p['stock_actual'], 2); ?></td>
                        <td><?php echo number_format($p['stock_minimo'], 2); ?></td>
                        <td>
                            <a href="insumos/editar_insumo.php?id=<?php echo $p['id']; ?>"
                               class="btn btn-secondary btn-sm">Ajustar Stock</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Acciones rápidas -->
    <div class="app-card">
        <div class="section-title">⚡ Acciones Rápidas</div>
        <div class="quick-actions">
            <?php if ($es_admin || $es_cajero || $es_cocinero): ?>
            <a href="caja.php" class="action-btn">
                <span class="action-icon">💵</span><span>Control de Caja</span>
            </a>
            <?php endif; ?>

            <?php if ($es_admin || $es_cajero): ?>
            <a href="pos.php" class="action-btn">
                <span class="action-icon">🛒</span><span>Punto de Venta</span>
            </a>
            <?php endif; ?>

            <?php if ($es_admin || $es_cocinero): ?>
            <a href="costos/ver_recetas.php" class="action-btn">
                <span class="action-icon">📋</span><span>Ver Recetas</span>
            </a>
            <?php endif; ?>

            <?php if ($es_admin): ?>
            <a href="productos/agregar.php" class="action-btn">
                <span class="action-icon">🍓</span><span>Agregar Producto</span>
            </a>
            <a href="compras/index.php" class="action-btn">
                <span class="action-icon">🧾</span><span>Registrar Compra</span>
            </a>
            <a href="productos/listar.php" class="action-btn">
                <span class="action-icon">📦</span><span>Gestionar Productos</span>
            </a>
            <a href="admin/usuarios/listar.php" class="action-btn">
                <span class="action-icon">🧑‍💼</span><span>Usuarios</span>
            </a>
            <a href="insumos/agregar_insumo.php" class="action-btn">
                <span class="action-icon">🌾</span><span>Agregar Insumo</span>
            </a>
            <a href="insumos/listar_insumos.php" class="action-btn">
                <span class="action-icon">📋</span><span>Gestionar Insumos</span>
            </a>
            <a href="gastos/registrar_gasto.php" class="action-btn">
                <span class="action-icon">💸</span><span>Registrar Gasto</span>
            </a>
            <a href="gastos/listar_gastos.php" class="action-btn">
                <span class="action-icon">🧾</span><span>Listar Gastos</span>
            </a>
            <a href="gastos/categorias_listar.php" class="action-btn">
                <span class="action-icon">🏷️</span><span>Categorías Gasto</span>
            </a>
            <a href="reportes/rentabilidad.php" class="action-btn">
                <span class="action-icon">📊</span><span>Ver Reportes</span>
            </a>
            <a href="costos/formulario_receta.php" class="action-btn">
                <span class="action-icon">✨</span><span>Crear Recetas</span>
            </a>
            <?php endif; ?>
        </div>
    </div>

</div><!-- /.app-page -->
</body>
</html>

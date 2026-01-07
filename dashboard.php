<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

protegerPagina();

// Redireccionar si no es administrador, cajero o cocinero
if (isset($_SESSION['usuario_rol']) && !in_array($_SESSION['usuario_rol'], ['administrador', 'cajero', 'admin', 'cocinero'])) {
    header("Location: pos.php");
    exit;
}

$es_admin = (isset($_SESSION['usuario_rol']) && in_array($_SESSION['usuario_rol'], ['administrador', 'admin']));
$es_cajero = (isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'cajero');
$es_cocinero = (isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'cocinero');

$db = new Database();
$conn = $db->getConnection();

// Consultas GENERALES (solo si es admin)
$ventasHoy = ['total' => 0];
$gastosHoy = ['total' => 0];
$productosBajoStock = null;

if ($es_admin) {
    try {
        $ventasHoy = $conn->query("SELECT SUM(total) AS total FROM ventas WHERE DATE(fecha) = CURDATE()")->fetch_assoc();
        $gastosHoy = $conn->query("SELECT SUM(monto) AS total FROM gastos WHERE DATE(fecha) = CURDATE()")->fetch_assoc();
        // Solo insumos con stock bajo
        $productosBajoStock = $conn->query("SELECT id, nombre, stock_actual, stock_minimo FROM insumos WHERE stock_actual <= stock_minimo ");
    } catch (Exception $e) {
        // Manejo silencioso o log
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - Paraíso Crocante</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <!-- RESPONSIVE -->
    <link rel="icon" href="/img/favicon.ico" type="image/x-icon" />
    <style>
        /* ===== ESTILOS GENERALES ===== */
        body { 
            font-family: Arial, sans-serif; 
            background: #f9f9f9; 
            margin: 0; 
            padding: 0; 
        }

        .dashboard { padding: 20px; }

        .header { 
            background: #FF6B6B; 
            color: white; 
            padding: 15px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            flex-wrap: wrap;
        }

        .header h1 {
            font-size: 1.5em;
            margin: 0;
        }

        .btn { 
            background: white; 
            color: #FF6B6B; 
            padding: 10px 15px; 
            text-decoration: none; 
            border-radius: 5px; 
            font-weight: bold; 
            margin: 5px; 
            display: inline-block;
        }

        .stats { 
            display: flex; 
            gap: 20px; 
            margin: 20px 0; 
            flex-wrap: wrap; 
        }

        .stat-card { 
            flex: 1; 
            background: white; 
            padding: 20px; 
            border-radius: 10px; 
            box-shadow: 0 0 10px rgba(0,0,0,0.1); 
            min-width: 220px; 
            text-align: center;
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
            font-size: 0.9em;
        }

        table, th, td { border: 1px solid #eee; }
        th, td { padding: 10px; text-align: left; }
        .stock-bajo { background-color: #ffcccc; font-weight: bold; }

        .quick-actions { 
            display: flex; 
            flex-wrap: wrap; 
            gap: 15px; 
            margin-top: 10px; 
            justify-content: center;
        }

        .action-btn { 
            background: #FF6B6B; 
            color: white; 
            padding: 15px; 
            border-radius: 10px; 
            text-align: center; 
            flex: 1 1 120px; 
            text-decoration: none; 
            font-size: 0.9em;
            transition: transform 0.2s ease;
        }

        .action-btn:hover { 
            transform: scale(1.05);
        }

        .action-btn span:first-child { 
            font-size: 20px; 
            display: block; 
        }
        
        /* Ocultar elementos para cajeros si se desea via CSS también, pero mejor PHP */
        .admin-only { display: <?php echo $es_admin ? 'block' : 'none'; ?>; }

        /* ======== RESPONSIVE ======== */
        @media (max-width: 768px) {
            .header { flex-direction: column; text-align: center; }
            .stats { flex-direction: column; }
            .sections { flex-direction: column; }
            .action-btn { flex: 1 1 45%; font-size: 0.85em; }
            .stat-card h3 { font-size: 1.1em; }
        }
        @media (min-width: 769px) and (max-width: 1024px) {
            .action-btn { padding: 20px; font-size: 1em; }
            .action-btn span:first-child { font-size: 28px; }
            .stats { gap: 15px; }
            .stat-card { padding: 15px; }
        }
        @media (max-width: 480px) {
            .dashboard { padding: 10px; }
            .header h1 { font-size: 1.2em; }
            .btn { padding: 8px 10px; font-size: 0.85em; }
            .action-btn { flex: 1 1 100%; }
            table, th, td { font-size: 0.8em; }
        }
    </style>
</head>
<body>
<div class="dashboard">
    <header class="header">
        <h1>Bienvenido, <?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?> 
            <?php 
                if ($es_cajero) echo '(Cajera)'; 
                elseif ($es_cocinero) echo '(Cocinero)'; 
            ?>
        </h1>
        <nav>
            <?php if (!$es_cocinero): ?>
                <a href="pos.php" class="btn">Punto de Venta</a>
            <?php endif; ?>
            <a href="logout.php" class="btn">Cerrar Sesión</a>
        </nav>
    </header>

    <?php if ($es_admin): ?>
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
    <?php elseif ($es_cajero): ?>
    <div style="margin: 20px 0; padding: 20px; background: white; border-radius: 10px; text-align: center;">
        <h2>Panel de Cajera</h2>
        <p>Seleccione una opción abajo para comenzar.</p>
    </div>
    <?php elseif ($es_cocinero): ?>
    <div style="margin: 20px 0; padding: 20px; background: white; border-radius: 10px; text-align: center;">
        <h2>Panel de Cocina</h2>
        <p>Bienvenido. Seleccione 'Ver Recetas' para consultar preparaciones.</p>
    </div>
    <?php endif; ?>

    <div class="sections">
        <?php if ($es_admin): ?>
        
        <!-- SECCIÓN DE ESTADÍSTICAS (CONTADOR DE VISITAS) -->
        <section class="section">
            <h2>Estadísticas de Visitas</h2>
            <?php
            $totalVisitas = 0;
            try {
                $resVisitas = $conn->query("SELECT COUNT(*) as total FROM historial_visitas");
                if ($resVisitas && $rowVisitas = $resVisitas->fetch_assoc()) {
                    $totalVisitas = $rowVisitas['total'];
                }
            } catch (Exception $e) {
                // Error silencioso
            }
            ?>
            <div style="text-align: center; padding: 20px;">
                <div style="font-size: 3em; font-weight: bold; color: #FF6B6B;">
                    <?php echo number_format($totalVisitas); ?>
                </div>
                <p style="color: #666; margin: 0;">Visitas al Inicio</p>
            </div>
        </section>

        <section class="section">
            <h2>Insumos con Stock Bajo</h2>
            <?php if ($productosBajoStock && $productosBajoStock->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Insumo</th>
                        <th>Stock Actual - Mínimo</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($producto = $productosBajoStock->fetch_assoc()): ?>
                    <?php $bajo = $producto['stock_actual'] < $producto['stock_minimo']; ?>
                    <tr class="<?php echo $bajo ? 'stock-bajo' : ''; ?>">
                        <td><?php echo htmlspecialchars($producto['nombre']); ?></td>
                        <td><?php echo number_format($producto['stock_actual'], 2) . " - " . number_format($producto['stock_minimo'], 2); ?></td>
                        <td>
                            <a href="insumos/editar_insumo.php?id=<?php echo $producto['id']; ?>" class="action-btn" style="padding:5px 10px; font-size:0.9em;">Reabastecer</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
                <p>No hay insumos con stock bajo.</p>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <section class="section">
            <h2>Acciones Rápidas</h2>
            <div class="quick-actions">
                <!-- Comunes / Cocinero -->
                <?php if ($es_admin || $es_cajero || $es_cocinero): ?>
                <a href="caja.php" class="action-btn"><span>💵</span><span>Control de Caja</span></a>
                <?php endif; ?>
                
                <?php if ($es_admin || $es_cajero): ?>
                <a href="pos.php" class="action-btn"><span>🛒</span><span>Punto de Venta</span></a>
                <?php endif; ?>
                
                <?php if ($es_admin || $es_cocinero): ?>
                <a href="costos/ver_recetas.php" class="action-btn"><span>📋</span><span>Ver Recetas</span></a>
                <?php endif; ?>

                <!-- Solo Admin -->
                <?php if ($es_admin): ?>
                <a href="productos/agregar.php" class="action-btn"><span>🍓</span><span>Agregar Producto</span></a>
                <a href="productos/listar.php" class="action-btn"><span>📋</span><span>Gestionar Productos</span></a>
                <a href="admin/usuarios/listar.php" class="action-btn"><span>🧑‍💼</span><span>Administrar Usuarios</span></a>
                <a href="insumos/agregar_insumo.php" class="action-btn"><span>🌾</span><span>Agregar Insumo</span></a>
                <a href="insumos/listar_insumos.php" class="action-btn"><span>📦</span><span>Gestionar Insumos</span></a>
                <a href="gastos/registrar_gasto.php" class="action-btn"><span>💸</span><span>Registrar Gasto</span></a>
                <a href="gastos/listar_gastos.php" class="action-btn"><span>🧾</span><span>Listar Gastos</span></a>
                <a href="gastos/categorias_listar.php" class="action-btn"><span>🏷️</span><span>Categorías de Gasto</span></a>
                <a href="reportes/rentabilidad.php" class="action-btn"><span>📊</span><span>Ver Reportes</span></a>
                <a href="costos/formulario_receta.php" class="action-btn"><span>✨</span><span>Crear Recetas</span></a>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>
</body>
</html>

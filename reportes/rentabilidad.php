<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

protegerPaginaAdmin();

$db = new Database();
$conn = $db->getConnection();

// Filtros de fecha (Por defecto: mes actual)
$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-01');
$fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-d');

// --- CONSULTAS ---

// 1. Totales Generales
$sql_totales = "SELECT 
                    (SELECT SUM(total) FROM ventas WHERE DATE(fecha) BETWEEN ? AND ?) as total_ventas,
                    (SELECT SUM(monto) FROM gastos WHERE DATE(fecha) BETWEEN ? AND ?) as total_gastos";

$stmt = $conn->prepare($sql_totales);
$stmt->bind_param("ssss", $fecha_inicio, $fecha_fin, $fecha_inicio, $fecha_fin);
$stmt->execute();
$res_totales = $stmt->get_result()->fetch_assoc();

$total_ventas = $res_totales['total_ventas'] ?? 0;
$total_gastos = $res_totales['total_gastos'] ?? 0;
$ganancia_neta = $total_ventas - $total_gastos;

// 2. Ventas por Día (Para el Gráfico)
$sql_diarias = "SELECT DATE(fecha) as dia, SUM(total) as total 
                FROM ventas 
                WHERE DATE(fecha) BETWEEN ? AND ? 
                GROUP BY DATE(fecha) 
                ORDER BY dia ASC";
$stmt_diarias = $conn->prepare($sql_diarias);
$stmt_diarias->bind_param("ss", $fecha_inicio, $fecha_fin);
$stmt_diarias->execute();
$res_diarias = $stmt_diarias->get_result();

$labels_grafico = [];
$datos_grafico = [];
while($row = $res_diarias->fetch_assoc()) {
    $labels_grafico[] = date('d/m', strtotime($row['dia']));
    $datos_grafico[] = $row['total'];
}

// 3. Top Productos (Necesita unir detalles_venta con productos)
// Asumiendo que existe tabla 'detalles_venta' con 'producto_id', 'cantidad', 'subtotal'
// Si no existe detalles_venta, habría que ver cómo se guardan los items. 
// Basado en pos.js/procesar_venta.php, revisaré si existe la tabla detalles_venta. 
// Si no estoy seguro, haré un try catch query básico.

$top_productos = [];
try {
    $sql_top = "SELECT p.nombre, SUM(dv.cantidad) as total_cantidad, SUM(dv.subtotal) as total_dinero 
                FROM detalles_venta dv 
                JOIN productos p ON dv.producto_id = p.id 
                JOIN ventas v ON dv.venta_id = v.id
                WHERE DATE(v.fecha) BETWEEN ? AND ?
                GROUP BY p.id 
                ORDER BY total_cantidad DESC 
                LIMIT 5";
    $stmt_top = $conn->prepare($sql_top);
    if ($stmt_top) {
        $stmt_top->bind_param("ss", $fecha_inicio, $fecha_fin);
        $stmt_top->execute();
        $res_top = $stmt_top->get_result();
        while($row = $res_top->fetch_assoc()) {
            $top_productos[] = $row;
        }
    }
} catch (Exception $e) {
    // Tabla detalles_venta podría no existir o tener otro nombre
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Rentabilidad - Paraíso Crocante</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/style.css"> <!-- Estilo base admin -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background: #f4f6f9; }
        .report-container { max-width: 1200px; margin: 20px auto; padding: 20px; }
        .header-report { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;}
        .dates-form { display: flex; gap: 10px; align-items: center; background: white; padding: 10px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .dates-form input { padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        .dates-form button { background: #FF6B6B; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer; }

        .cards-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .kpi-card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); text-align: center; }
        .kpi-title { font-size: 1.1em; color: #666; margin-bottom: 10px; }
        .kpi-value { font-size: 2em; font-weight: bold; color: #333; }
        .kpi-icon { font-size: 2.5em; margin-bottom: 15px; opacity: 0.8; }
        
        .c-green { color: #2ecc71; }
        .c-red { color: #e74c3c; }
        .c-blue { color: #3498db; }

        .charts-section { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 30px; }
        
        .tables-section { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; color: #555; }
        
        @media (max-width: 768px) {
            .header-report { flex-direction: column; align-items: stretch; }
            .dates-form { flex-direction: column; }
            .dates-form input { width: 100%; }
        }
    </style>
</head>
<body>

<div class="report-container">
    <div class="header-report">
        <div>
            <h1>📊 Reporte de Rentabilidad</h1>
            <a href="../dashboard.php" style="color: #666; text-decoration: none;"><i class="fas fa-arrow-left"></i> Volver al Dashboard</a>
        </div>
        
        <form class="dates-form" method="GET">
            <label>Desde:</label>
            <input type="date" name="fecha_inicio" value="<?php echo $fecha_inicio; ?>" required>
            <label>Hasta:</label>
            <input type="date" name="fecha_fin" value="<?php echo $fecha_fin; ?>" required>
            <button type="submit">Filtrar</button>
        </form>
    </div>

    <!-- KPIs -->
    <div class="cards-grid">
        <div class="kpi-card">
            <div class="kpi-icon c-blue"><i class="fas fa-shopping-cart"></i></div>
            <div class="kpi-title">Total Ventas</div>
            <div class="kpi-value">$<?php echo number_format($total_ventas, 2); ?></div>
        </div>
        
        <div class="kpi-card">
            <div class="kpi-icon c-red"><i class="fas fa-file-invoice-dollar"></i></div>
            <div class="kpi-title">Total Gastos</div>
            <div class="kpi-value">$<?php echo number_format($total_gastos, 2); ?></div>
        </div>
        
        <div class="kpi-card">
            <div class="kpi-icon <?php echo $ganancia_neta >= 0 ? 'c-green' : 'c-red'; ?>"><i class="fas fa-chart-line"></i></div>
            <div class="kpi-title">Ganancia Neta</div>
            <div class="kpi-value" style="color: <?php echo $ganancia_neta >= 0 ? '#2ecc71' : '#e74c3c'; ?>">
                $<?php echo number_format($ganancia_neta, 2); ?>
            </div>
        </div>
    </div>

    <!-- Gráfico -->
    <div class="charts-section">
        <h3>📈 Tendencia de Ventas (<?php echo date('d/m', strtotime($fecha_inicio)); ?> - <?php echo date('d/m', strtotime($fecha_fin)); ?>)</h3>
        <canvas id="salesChart" height="100"></canvas>
    </div>

    <!-- Tabla Top Productos -->
    <div class="tables-section">
        <h3>🏆 Productos Más Vendidos</h3>
        <?php if (!empty($top_productos)): ?>
        <table>
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Cantidad Vendida</th>
                    <th>Ingresos Generados</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($top_productos as $prod): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($prod['nombre']); ?></strong></td>
                    <td><?php echo $prod['total_cantidad']; ?> un.</td>
                    <td>$<?php echo number_format($prod['total_dinero'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
            <p style="padding: 20px; color: #777;">No hay datos de productos vendidos en este período.</p>
        <?php endif; ?>
    </div>
</div>

<script>
    const ctx = document.getElementById('salesChart').getContext('2d');
    const salesChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($labels_grafico); ?>,
            datasets: [{
                label: 'Ventas ($)',
                data: <?php echo json_encode($datos_grafico); ?>,
                borderColor: '#FF6B6B',
                backgroundColor: 'rgba(255, 107, 107, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) { return '$' + value; }
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Ventas: $' + context.parsed.y.toFixed(2);
                        }
                    }
                }
            }
        }
    });
</script>

</body>
</html>

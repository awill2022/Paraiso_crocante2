<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';
protegerPagina();

$db = new Database();
$conn = $db->getConnection();
$usuario_id = $_SESSION['usuario_id'];

// Verificar si hay una caja abierta
$sql = "SELECT * FROM cierres_caja WHERE usuario_id = ? AND estado = 'abierta' ORDER BY fecha_apertura DESC LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$cajaActiva = $stmt->get_result()->fetch_assoc();

// Abrir caja
if (isset($_POST['abrir_caja'])) {
    $saldo_inicial = $_POST['saldo_inicial'];

    $sql = "INSERT INTO cierres_caja (fecha_apertura, usuario_id, saldo_inicial, estado)
            VALUES (NOW(), ?, ?, 'abierta')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("id", $usuario_id, $saldo_inicial);
    $stmt->execute();

    header("Location: caja.php");
    exit;
}

// Cerrar caja
if (isset($_POST['cerrar_caja']) && $cajaActiva) {
    $caja_id = $cajaActiva['id'];

    // Calcular totales desglosados
    $sqlVentas = "SELECT 
                    SUM(CASE WHEN metodo_pago = 'efectivo' THEN total ELSE 0 END) as total_efectivo,
                    SUM(total) AS total_general
                  FROM ventas WHERE caja_id = $caja_id";
    
    $resVentas = $conn->query($sqlVentas)->fetch_assoc();
    $totalVentasGeneral = $resVentas['total_general'] ?? 0;
    $totalVentasEfectivo = $resVentas['total_efectivo'] ?? 0;
    
    $totalGastos = $conn->query("SELECT SUM(monto) AS total FROM gastos WHERE caja_id = $caja_id")->fetch_assoc()['total'] ?? 0;
    
    // Saldo Final = Dinero Físico (Inicial + Ventas Efectivo - Gastos)
    $saldoFinal = $saldoInicial + $totalVentasEfectivo - $totalGastos;

    $sql = "UPDATE cierres_caja SET 
                fecha_cierre = NOW(),
                total_ventas = ?,
                saldo_final = ?,
                estado = 'cerrada'
            WHERE id = ?";
    $stmt = $conn->prepare($sql);
    // Guardamos el total general de ventas en 'total_ventas' para registro histórico
    // Pero el 'saldo_final' refleja el efectivo en caja
    $stmt->bind_param("ddi", $totalVentasGeneral, $saldoFinal, $caja_id);
    $stmt->execute();

    header("Location: caja.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Control de Caja - Paraíso Crocante</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="assets/css/style.css">

<link rel="icon" href="/img/favicon.ico" type="image/x-icon" />
<style>
body { font-family: Arial, sans-serif; background: #f9f9f9; margin: 0; padding: 0; }
.container { max-width: 700px; margin: 40px auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
h1 { color: #FF6B6B; text-align: center; }
.btn { background: #FF6B6B; color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; }
.btn:hover { opacity: 0.9; }
input[type="number"] { padding: 10px; width: 100%; border: 1px solid #ddd; border-radius: 6px; margin-bottom: 15px; }
.summary { background: #fff7f7; padding: 15px; border-radius: 8px; margin-top: 20px; }
.summary h3 { margin-top: 0; }
.info { font-size: 1em; margin-bottom: 10px; }
.positive { color: green; font-weight: bold; }
.negative { color: red; font-weight: bold; }
body {
    background: linear-gradient(135deg, #FF6B6B, #FF8E8E);
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    background-image: url('assets/img/fresas-background.jpg');
    background-size: contain;
    background-position: center;
}
</style>
</head>
<body>
<div class="container">
    <h1>💵 Control de Caja</h1>

    <?php if (!$cajaActiva): ?>
        <form method="POST">
            <label>Saldo Inicial:</label>
            <input type="number" name="saldo_inicial" step="0.01" required>
            <button type="submit" name="abrir_caja" class="btn">Abrir Caja</button>
        </form>
    <?php else: ?>
        <?php
            $caja_id = $cajaActiva['id'];
            
            // Totales por método de pago
            $sqlVentas = "SELECT 
                            SUM(CASE WHEN metodo_pago = 'efectivo' THEN total ELSE 0 END) as total_efectivo,
                            SUM(CASE WHEN metodo_pago = 'de_una' OR metodo_pago = 'tarjeta' THEN total ELSE 0 END) as total_de_una,
                            SUM(total) AS total_general
                          FROM ventas WHERE caja_id = $caja_id";
            
            $resVentas = $conn->query($sqlVentas)->fetch_assoc();
            $totalEfectivo = $resVentas['total_efectivo'] ?? 0;
            $totalDeUna = $resVentas['total_de_una'] ?? 0;
            $totalVentas = $resVentas['total_general'] ?? 0;

            $totalGastos = $conn->query("SELECT SUM(monto) AS total FROM gastos WHERE caja_id = $caja_id")->fetch_assoc()['total'] ?? 0;
            
            // El saldo final en caja física suele ser solo lo que hay en efectivo
            // Si 'De Una' es transferencia, no suma al efectivo físico en caja.
            // Asumiremos que el Saldo Final Esperado se refiere al dinero FÍSICO (Efectivo) + Saldo Inicial - Gastos
            
            $saldoFinalEfectivoEsperado = $cajaActiva['saldo_inicial'] + $totalEfectivo - $totalGastos;
        ?>
        <div class="summary">
            <h3>📅 Caja Abierta el <?php echo date('d/m/Y H:i', strtotime($cajaActiva['fecha_apertura'])); ?></h3>
            <p class="info">Saldo Inicial: <strong>$<?php echo number_format($cajaActiva['saldo_inicial'], 2); ?></strong></p>
            
            <hr>
            <p class="info" style="color: green;">💵 Ventas Efectivo: <strong>$<?php echo number_format($totalEfectivo, 2); ?></strong></p>
            <p class="info" style="color: #d62828;">📱 Ventas De Una: <strong>$<?php echo number_format($totalDeUna, 2); ?></strong></p>
            <p class="info"><strong>Total Ventas: $<?php echo number_format($totalVentas, 2); ?></strong></p>
            <hr>
            
            <p class="info">Gastos (Salidas): <strong>$<?php echo number_format($totalGastos, 2); ?></strong></p>
            
            <p class="info" style="font-size: 1.2em; margin-top: 15px;">
                Efectivo Esperado en Caja: 
                <span class="<?php echo $saldoFinalEfectivoEsperado >= 0 ? 'positive' : 'negative'; ?>">
                    $<?php echo number_format($saldoFinalEfectivoEsperado, 2); ?>
                </span>
                <br>
                <small style="font-size: 0.6em; color: gray;">(Saldo Inicial + Ventas Efectivo - Gastos)</small>
            </p>

            <form method="POST">
                <button type="submit" name="cerrar_caja" class="btn">Cerrar Caja</button>
            </form>
        </div>
    <?php endif; ?>
       <div class="summary">
            <a href="dashboard.php" class="btn">Volver al Dashboard</a>
        </div>
</div>

</body>
</html>

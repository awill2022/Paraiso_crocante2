<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// session_start(); // Comentado si protegerPagina() ya lo hace.
protegerPagina(); // Asegura que el usuario esté logueado.

$db = new Database();
$conn = $db->getConnection();

$venta_id = 0;
if (isset($_GET['venta_id'])) {
    $venta_id = filter_var($_GET['venta_id'], FILTER_VALIDATE_INT);
    if ($venta_id === false || $venta_id <= 0) {
        $venta_id = 0; // Invalida si no es entero positivo
    }
}

if ($venta_id === 0) {
    // No es necesario incluir todo el HTML si hay un error temprano.
    http_response_code(400); // Bad Request
    echo "<!DOCTYPE html><html lang='es'><head><meta charset='UTF-8'><title>Error</title></head><body>";
    echo "<h1>Error</h1><p>ID de venta no proporcionado o inválido.</p>";
    echo "<p><a href='dashboard.php'>Volver al Dashboard</a></p>"; // O a donde sea apropiado
    echo "</body></html>";
    exit;
}

// Consulta para obtener los datos de la venta y el nombre del cajero
// Asumiendo que la tabla usuarios tiene 'nombre' o 'username'
$stmt_venta = $conn->prepare(
    "SELECT v.id AS venta_id, v.total, v.metodo_pago, v.fecha,
            u.nombre AS nombre_cajero, u.username AS username_cajero
     FROM ventas v
     LEFT JOIN usuarios u ON v.usuario_id = u.id
     WHERE v.id = ?"
);
$stmt_venta->bind_param("i", $venta_id);
$stmt_venta->execute();
$result_venta = $stmt_venta->get_result();
$venta = $result_venta->fetch_assoc();
$stmt_venta->close();

if (!$venta) {
    http_response_code(404); // Not Found
    echo "<!DOCTYPE html><html lang='es'><head><meta charset='UTF-8'><title>Error</title></head><body>";
    echo "<h1>Error</h1><p>Venta no encontrada.</p>";
    echo "<p><a href='dashboard.php'>Volver al Dashboard</a></p>";
    echo "</body></html>";
    exit;
}

// Determinar el nombre del cajero a mostrar
$nombre_cajero_display = "No disponible";
if (!empty($venta['nombre_cajero'])) {
    $nombre_cajero_display = htmlspecialchars($venta['nombre_cajero']);
} elseif (!empty($venta['username_cajero'])) {
    $nombre_cajero_display = htmlspecialchars($venta['username_cajero']);
} elseif (!empty($venta['usuario_id'])) { // Si no hay nombre ni username, mostrar ID
    $nombre_cajero_display = "ID: " . htmlspecialchars($venta['usuario_id']);
}


// Consulta para obtener los detalles de la venta (productos)
$stmt_detalles = $conn->prepare(
    "SELECT dv.cantidad, dv.precio, p.nombre AS producto_nombre
     FROM detalle_venta dv
     JOIN productos p ON dv.producto_id = p.id
     WHERE dv.venta_id = ?"
);
$stmt_detalles->bind_param("i", $venta_id);
$stmt_detalles->execute();
$result_detalles = $stmt_detalles->get_result();
$detalles_venta = [];
while ($row = $result_detalles->fetch_assoc()) {
    $detalles_venta[] = $row;
}
$stmt_detalles->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ticket de Venta #<?php echo htmlspecialchars($venta['venta_id']); ?></title>
    <style>
        body {
            font-family: 'Courier New', Courier, monospace;
            margin: 0;
            padding: 20px;
            background-color: #f4f4f4;
            color: #333;
            font-size: 14px; /* Ajustar tamaño base para tickets */
        }
        .ticket-container {
            width: 300px; /* Ancho típico de ticket de impresora térmica */
            margin: 20px auto;
            padding: 15px;
            background-color: #fff;
            border: 1px solid #ccc;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1, h2, h3 {
            text-align: center;
            margin-top: 5px;
            margin-bottom: 10px;
        }
        h1 {
            font-size: 1.2em;
        }
        h2 {
            font-size: 1em;
            border-bottom: 1px dashed #ccc;
            padding-bottom: 5px;
            margin-bottom: 10px;
        }
        .ticket-header p, .ticket-footer p {
            text-align: center;
            margin: 3px 0;
        }
        .ticket-info p {
            margin: 3px 0;
            display: flex;
            justify-content: space-between;
        }
        .ticket-info p span:first-child {
            font-weight: bold;
            margin-right: 10px;
        }
        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            margin-bottom: 10px;
        }
        table.items th, table.items td {
            border-bottom: 1px dashed #eee;
            padding: 5px 2px; /* Ajustar padding */
            text-align: left;
        }
        table.items th {
            font-size: 0.9em;
            background-color: #f9f9f9;
        }
        table.items td.qty, table.items td.price, table.items td.subtotal {
            text-align: right;
        }
        table.items td.name {
             word-break: break-word; /* Para nombres largos */
        }

        .total-section {
            margin-top: 10px;
            border-top: 1px solid #333;
            padding-top: 5px;
        }
        .total-section p {
            display: flex;
            justify-content: space-between;
            font-weight: bold;
            font-size: 1.1em;
            margin: 5px 0;
        }
        .print-button-container {
            text-align: center;
            margin-top: 20px;
        }
        .print-button {
            padding: 10px 20px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
        }
        .print-button:hover {
            background-color: #45a049;
        }

        @media print {
            body {
                background-color: #fff; /* Fondo blanco para impresión */
                font-size: 10pt; /* Tamaño más pequeño para impresión */
                margin:0;
                padding:0;
            }
            .ticket-container {
                width: 100%; /* Ocupar todo el ancho disponible en impresión */
                margin: 0;
                padding: 0;
                border: none;
                box-shadow: none;
            }
            .print-button-container {
                display: none;
            }
            h1,h2,h3,p,table {
                color: #000 !important; /* asegurar texto negro */
            }
        }
    </style>
</head>
<body>
    <div class="ticket-container">
        <div class="ticket-header">
            <h1>Fresas con Crema - Punto de Venta</h1>
            <p>Av. Siempre Viva 123, Springfield</p>
            <p>Tel: (555) 123-4567</p>
            <h2>Ticket de Venta</h2>
        </div>

        <div class="ticket-info">
            <p><span>ID Venta:</span> <span>#<?php echo htmlspecialchars($venta['venta_id']); ?></span></p>
            <p><span>Fecha:</span> <span><?php echo htmlspecialchars(date("d/m/Y H:i:s", strtotime($venta['fecha']))); ?></span></p>
            <p><span>Atendido por:</span> <span><?php echo $nombre_cajero_display; ?></span></p>
        </div>

        <table class="items">
            <thead>
                <tr>
                    <th>Producto</th>
                    <th style="text-align:right;">Cant.</th>
                    <th style="text-align:right;">Precio</th>
                    <th style="text-align:right;">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($detalles_venta as $item): ?>
                <tr>
                    <td class="name"><?php echo htmlspecialchars($item['producto_nombre']); ?></td>
                    <td class="qty"><?php echo htmlspecialchars($item['cantidad']); ?></td>
                    <td class="price">$<?php echo htmlspecialchars(number_format($item['precio'], 2)); ?></td>
                    <td class="subtotal">$<?php echo htmlspecialchars(number_format($item['cantidad'] * $item['precio'], 2)); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="total-section">
            <p><span>Total:</span> <span>$<?php echo htmlspecialchars(number_format($venta['total'], 2)); ?></span></p>
            <p><span>Método de Pago:</span> <span><?php echo htmlspecialchars(ucfirst($venta['metodo_pago'])); ?></span></p>
        </div>

        <div class="ticket-footer">
            <p>¡Gracias por su compra!</p>
        </div>
    </div>

    <div class="print-button-container">
        <button class="print-button" onclick="window.print();">Imprimir Ticket</button>
    </div>

</body>
</html>

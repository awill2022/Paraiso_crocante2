<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

header('Content-Type: application/json');

// Proteger la página
protegerPagina();

$db = new Database();
$conn = $db->getConnection();

try {
    // Obtener datos enviados desde pos.js
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['productos'], $input['total'], $input['metodo_pago'], $input['usuario_id'])) {
        throw new Exception('Datos inválidos');
    }

    $productos = $input['productos'];
    $total = floatval($input['total']);
    $metodo_pago = $input['metodo_pago'];
    $usuario_id = intval($input['usuario_id']);

    if (empty($productos) || $total <= 0 || !in_array($metodo_pago, ['efectivo', 'tarjeta']) || $usuario_id <= 0) {
        throw new Exception('Datos de la venta no válidos');
    }

    // Iniciar transacción
    $conn->begin_transaction();

    // Insertar venta
    $stmt = $conn->prepare("INSERT INTO ventas (usuario_id, total, metodo_pago, fecha) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param('ids', $usuario_id, $total, $metodo_pago);
    $stmt->execute();
    $venta_id = $conn->insert_id;

    // Insertar detalles de la venta
    $stmt = $conn->prepare("INSERT INTO detalle_venta (venta_id, producto_id, cantidad, precio) VALUES (?, ?, ?, ?)");
    foreach ($productos as $producto) {
        $producto_id = intval($producto['id']);
        $cantidad = intval($producto['cantidad']);
        $precio = floatval($producto['precio']);
        
        if ($producto_id <= 0 || $cantidad <= 0 || $precio <= 0) {
            throw new Exception('Datos de producto no válidos');
        }
        
        $stmt->bind_param('iiid', $venta_id, $producto_id, $cantidad, $precio);
        $stmt->execute();
    }

    // Confirmar transacción
    $conn->commit();

    echo json_encode([
        'success' => true,
        'venta_id' => $venta_id,
        'message' => 'Venta registrada con éxito'
    ]);

} catch (Exception $e) {
    // Revertir transacción en caso de error
    $conn->rollback();
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    $conn->close();
}
?>
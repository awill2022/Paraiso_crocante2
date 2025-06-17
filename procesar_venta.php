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
    $usuario_id = intval($input['usuario_id']); // Sanitizar IDs
    $total_enviado = floatval($input['total']); // Sanitizar total

    if (empty($productos) || $total_enviado <= 0 || !in_array($metodo_pago, ['efectivo', 'tarjeta']) || $usuario_id <= 0) {
        throw new Exception('Datos de la venta no válidos o incompletos.');
    }

    // --- INICIO DE VALIDACIONES ADICIONALES ---
    $total_recalculado_backend = 0.0;
    $productos_validados = []; // Para almacenar productos con precios de la BD

    // Preparar la consulta para verificar productos una sola vez
    $stmt_producto_check = $conn->prepare("SELECT precio, activo, stock FROM productos WHERE id = ?");

    foreach ($productos as $key => $producto_enviado) {
        $producto_id = intval($producto_enviado['id']);
        $cantidad_enviada = intval($producto_enviado['cantidad']);
        $nombre_producto_display = htmlspecialchars(isset($producto_enviado['nombre']) ? $producto_enviado['nombre'] : ('ID ' . $producto_id));


        if ($producto_id <= 0) {
            throw new Exception("ID de producto inválido encontrado: " . htmlspecialchars(isset($producto_enviado['id']) ? $producto_enviado['id'] : 'N/A'));
        }
        if ($cantidad_enviada <= 0) {
            throw new Exception("Cantidad inválida para el producto " . $nombre_producto_display . ": " . htmlspecialchars(isset($producto_enviado['cantidad']) ? $producto_enviado['cantidad'] : 'N/A'));
        }

        $stmt_producto_check->bind_param("i", $producto_id);
        $stmt_producto_check->execute();
        $resultado_producto = $stmt_producto_check->get_result();

        if ($resultado_producto->num_rows === 0) {
            $stmt_producto_check->close();
            throw new Exception("Error: El producto " . $nombre_producto_display . " no existe.");
        }

        $fila_producto = $resultado_producto->fetch_assoc();

        if (empty($fila_producto['activo'])) {
            $stmt_producto_check->close();
            throw new Exception("Error: El producto '" . $nombre_producto_display . "' no está disponible.");
        }

        $precio_bd = floatval($fila_producto['precio']);
        $stock_bd = intval($fila_producto['stock']);

        if ($precio_bd <= 0) {
            $stmt_producto_check->close();
            throw new Exception("Error: El producto '" . $nombre_producto_display . "' tiene un precio inválido en la base de datos.");
        }

        // VERIFICACIÓN DE STOCK
        if ($cantidad_enviada > $stock_bd) {
            $stmt_producto_check->close();
            throw new Exception("Error: Stock insuficiente para el producto '" . $nombre_producto_display . "'. Disponibles: " . $stock_bd . ", Solicitados: " . $cantidad_enviada);
        }

        $total_recalculado_backend += $precio_bd * $cantidad_enviada;

        // Guardar el producto con el precio y stock de la BD para la inserción y actualización de stock
        $productos_validados[] = [
            'id' => $producto_id,
            'cantidad' => $cantidad_enviada,
            'precio' => $precio_bd,
            'nombre_display' => $nombre_producto_display // Guardar para mensajes de error si es necesario después
        ];
    }
    $stmt_producto_check->close();

    // Comparar total recalculado con el total enviado
    $tolerancia = 0.015; // Aumentar ligeramente la tolerancia para cubrir más casos de redondeo en frontend vs backend.
    if (abs($total_recalculado_backend - $total_enviado) > $tolerancia) {
        // Loggear la diferencia podría ser útil aquí para depuración
        error_log("Discrepancia de total: Backend Calculado = $total_recalculado_backend, Frontend Enviado = $total_enviado, Diferencia = " . abs($total_recalculado_backend - $total_enviado));
        throw new Exception("Error: Discrepancia en el total de la venta. Total calculado: " . number_format($total_recalculado_backend, 2) . ", Total recibido: " . number_format($total_enviado, 2) . ". Por favor, intente de nuevo.");
    }
    // --- FIN DE VALIDACIONES ADICIONALES ---

    // Iniciar transacción
    $conn->begin_transaction();

    // Insertar venta
    // Usar $total_recalculado_backend para mayor seguridad, que es la suma de precios de BD * cantidad.
    $stmt_venta = $conn->prepare("INSERT INTO ventas (usuario_id, total, metodo_pago, fecha) VALUES (?, ?, ?, NOW())");
    $stmt_venta->bind_param('ids', $usuario_id, $total_recalculado_backend, $metodo_pago);
    $stmt_venta->execute();
    $venta_id = $conn->insert_id;
    if ($venta_id <= 0) {
        $conn->rollback(); // Asegurar rollback si la inserción de venta falla por alguna razón
        throw new Exception("Error crítico: No se pudo registrar la venta principal.");
    }
    $stmt_venta->close();


    // Insertar detalles de la venta usando $productos_validados
    $stmt_detalle = $conn->prepare("INSERT INTO detalle_venta (venta_id, producto_id, cantidad, precio) VALUES (?, ?, ?, ?)");
    foreach ($productos_validados as $producto) {
        $stmt_detalle->bind_param('iiid', $venta_id, $producto['id'], $producto['cantidad'], $producto['precio']);
        $stmt_detalle->execute();
        if ($stmt_detalle->affected_rows <= 0) {
            $conn->rollback();
            throw new Exception("Error crítico: No se pudo registrar el producto '" . $producto['nombre_display'] . "' en el detalle de la venta.");
        }
    }
    $stmt_detalle->close();

    // ACTUALIZACIÓN DE STOCK
    $stmt_update_stock = $conn->prepare("UPDATE productos SET stock = stock - ? WHERE id = ?");
    foreach ($productos_validados as $producto) {
        $cantidad_vendida = intval($producto['cantidad']);
        $producto_id_actualizado = intval($producto['id']);

        $stmt_update_stock->bind_param("ii", $cantidad_vendida, $producto_id_actualizado);
        $stmt_update_stock->execute();
        
        if ($stmt_update_stock->affected_rows === 0) {
            // Esto podría suceder si otro proceso eliminó el producto justo después de la verificación inicial,
            // o si el stock se modificó externamente de tal manera que stock - cantidad_vendida es negativo y hay un CHECK constraint.
            // La verificación de stock anterior debería prevenir la mayoría de estos casos.
            $conn->rollback();
            throw new Exception("Error crítico al actualizar stock para el producto '" . $producto['nombre_display'] . "'. La venta ha sido cancelada.");
        }
    }
    $stmt_update_stock->close();

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
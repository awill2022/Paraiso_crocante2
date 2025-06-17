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

    // Iniciar transacción ANTES de cualquier lectura de stock para consistencia
    $conn->begin_transaction();

    // --- VALIDACIÓN DE STOCK DE INSUMOS Y PRECIOS DE PRODUCTOS ---
    $total_recalculado_backend = 0.0;
    $productos_validados = []; // Para detalles de venta
    $necesidades_insumos_agregadas = []; // [insumo_id => cantidad_total_necesaria]

    // 1. Calcular necesidades totales de insumos y validar productos/precios
    $stmt_check_producto = $conn->prepare("SELECT nombre, precio, activo FROM productos WHERE id = ?");
    $stmt_get_insumos_producto = $conn->prepare("SELECT pi.insumo_id, i.nombre AS insumo_nombre, pi.cantidad_consumida
                                                FROM producto_insumos pi
                                                JOIN insumos i ON pi.insumo_id = i.id
                                                WHERE pi.producto_id = ?");

    foreach ($productos as $key => $producto_en_carrito) {
        $producto_id = intval($producto_en_carrito['id']);
        $cantidad_vendida = intval($producto_en_carrito['cantidad']);
        $nombre_producto_original = htmlspecialchars(isset($producto_en_carrito['nombre']) ? $producto_en_carrito['nombre'] : ('ID ' . $producto_id));

        if ($producto_id <= 0 || $cantidad_vendida <= 0) {
            throw new Exception("Datos de producto inválidos en el carrito para '" . $nombre_producto_original . "'.");
        }

        // Validar producto (activo, precio)
        $stmt_check_producto->bind_param("i", $producto_id);
        $stmt_check_producto->execute();
        $resultado_db_producto = $stmt_check_producto->get_result();
        if ($resultado_db_producto->num_rows === 0) {
            throw new Exception("El producto '" . $nombre_producto_original . "' (ID: " . $producto_id . ") no existe.");
        }
        $fila_db_producto = $resultado_db_producto->fetch_assoc();

        if (empty($fila_db_producto['activo'])) {
            throw new Exception("El producto '" . htmlspecialchars($fila_db_producto['nombre']) . "' no está disponible.");
        }
        $precio_bd = floatval($fila_db_producto['precio']);
        if ($precio_bd <= 0) {
            throw new Exception("El producto '" . htmlspecialchars($fila_db_producto['nombre']) . "' tiene un precio inválido.");
        }

        $total_recalculado_backend += $precio_bd * $cantidad_vendida;
        $productos_validados[] = [ // Para insertar en detalle_venta
            'id' => $producto_id,
            'cantidad' => $cantidad_vendida,
            'precio' => $precio_bd,
            'nombre_display' => htmlspecialchars($fila_db_producto['nombre'])
        ];

        // Obtener insumos para este producto y agregar a necesidades
        $stmt_get_insumos_producto->bind_param("i", $producto_id);
        $stmt_get_insumos_producto->execute();
        $result_insumos_req = $stmt_get_insumos_producto->get_result();

        if ($result_insumos_req->num_rows === 0 && $CHECK_PRODUCTS_HAVE_INSUMOS) { // CHECK_PRODUCTS_HAVE_INSUMOS es una constante hipotética
             // Si se requiere que todos los productos tengan insumos definidos, lanzar error.
             // Por ahora, podríamos permitir productos sin insumos definidos (ej. agua embotellada)
             // throw new Exception("El producto '".htmlspecialchars($fila_db_producto['nombre'])."' no tiene insumos definidos.");
        }

        while ($insumo_req = $result_insumos_req->fetch_assoc()) {
            $insumo_id_req = intval($insumo_req['insumo_id']);
            $cantidad_total_necesaria_insumo = floatval($insumo_req['cantidad_consumida']) * $cantidad_vendida;

            if (!isset($necesidades_insumos_agregadas[$insumo_id_req])) {
                $necesidades_insumos_agregadas[$insumo_id_req] = [
                    'cantidad' => 0,
                    'nombre' => $insumo_req['insumo_nombre'] // Guardar nombre para mensajes de error
                ];
            }
            $necesidades_insumos_agregadas[$insumo_id_req]['cantidad'] += $cantidad_total_necesaria_insumo;
        }
    }
    $stmt_check_producto->close();
    $stmt_get_insumos_producto->close();

    // 2. Verificar stock de todos los insumos agregados
    if (!empty($necesidades_insumos_agregadas)) {
        $stmt_check_stock_insumo = $conn->prepare("SELECT nombre, stock_actual FROM insumos WHERE id = ? FOR UPDATE");
        foreach ($necesidades_insumos_agregadas as $insumo_id => $data_necesidad) {
            $cantidad_total_requerida = $data_necesidad['cantidad'];
            $nombre_insumo = htmlspecialchars($data_necesidad['nombre']);

            $stmt_check_stock_insumo->bind_param("i", $insumo_id);
            $stmt_check_stock_insumo->execute();
            $result_stock_ins = $stmt_check_stock_insumo->get_result();
            if ($result_stock_ins->num_rows === 0) {
                throw new Exception("Insumo ID " . $insumo_id . " ('" . $nombre_insumo . "') no encontrado durante la verificación de stock.");
            }
            $fila_insumo_stock = $result_stock_ins->fetch_assoc();
            $stock_actual_insumo_bd = floatval($fila_insumo_stock['stock_actual']);

            if ($cantidad_total_requerida > $stock_actual_insumo_bd) {
                throw new Exception("Stock insuficiente para el insumo '" . $nombre_insumo . "'. Necesario: " . $cantidad_total_requerida . ", Disponible: " . $stock_actual_insumo_bd . ".");
            }
        }
        $stmt_check_stock_insumo->close();
    }

    // 3. Comparar total recalculado con el total enviado desde el frontend
    $tolerancia = 0.015;
    if (abs($total_recalculado_backend - $total_enviado) > $tolerancia) {
        error_log("Discrepancia de total: Backend = $total_recalculado_backend, Frontend = $total_enviado");
        throw new Exception("Error: Discrepancia en el total de la venta. Recalculado: " . number_format($total_recalculado_backend, 2) . ", Recibido: " . number_format($total_enviado, 2));
    }
    // --- FIN VALIDACIONES ---

    // --- INICIO OPERACIONES DE ESCRITURA ---
    // Insertar venta principal
    $stmt_venta = $conn->prepare("INSERT INTO ventas (usuario_id, total, metodo_pago, fecha) VALUES (?, ?, ?, NOW())");
    $stmt_venta->bind_param("ids", $usuario_id, $total_recalculado_backend, $metodo_pago);
    $stmt_venta->execute();
    $venta_id = $conn->insert_id;
    if ($venta_id <= 0) {
        throw new Exception("Error crítico: No se pudo registrar la venta principal.");
    }
    $stmt_venta->close();

    // Insertar detalles de la venta
    $stmt_detalle = $conn->prepare("INSERT INTO detalle_venta (venta_id, producto_id, cantidad, precio) VALUES (?, ?, ?, ?)");
    foreach ($productos_validados as $prod_validado) {
        $stmt_detalle->bind_param("iiid", $venta_id, $prod_validado['id'], $prod_validado['cantidad'], $prod_validado['precio']);
        $stmt_detalle->execute();
        if ($stmt_detalle->affected_rows <= 0) {
            throw new Exception("Error crítico: No se pudo registrar el producto '" . $prod_validado['nombre_display'] . "' en el detalle.");
        }
    }
    $stmt_detalle->close();

    // Descontar stock de insumos
    if (!empty($necesidades_insumos_agregadas)) {
        $stmt_update_insumo_stock = $conn->prepare("UPDATE insumos SET stock_actual = stock_actual - ? WHERE id = ?");
        foreach ($necesidades_insumos_agregadas as $insumo_id => $data_necesidad) {
            $cantidad_a_descontar = $data_necesidad['cantidad'];
            // Es importante que cantidad_a_descontar sea positivo aquí.
            // La validación previa asegura que stock_actual >= cantidad_a_descontar
            $stmt_update_insumo_stock->bind_param("di", $cantidad_a_descontar, $insumo_id); // 'd' para decimal/double
            $stmt_update_insumo_stock->execute();

            if ($stmt_update_insumo_stock->affected_rows === 0) {
                 // Si no se afectaron filas, puede ser que el insumo_id ya no exista (muy improbable aquí)
                 // o que la resta de stock falló por alguna constraint (ej. stock >= 0) que no se activó con FOR UPDATE
                 // o que el stock es exactamente igual a la cantidad a descontar y la base de datos no lo cuenta como cambio.
                 // Para ser más robustos, se podría volver a leer el stock del insumo y verificar si es < 0.
                throw new Exception("Error crítico al actualizar stock para el insumo ID " . $insumo_id . " ('" . htmlspecialchars($data_necesidad['nombre']) . "'). La venta ha sido cancelada.");
            }
        }
        $stmt_update_insumo_stock->close();
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
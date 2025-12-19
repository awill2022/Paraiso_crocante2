<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
protegerPagina();
$db = new Database();
$conn = $db->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Validación y obtención de datos
    $nombre = trim($_POST['nombre_receta']);
    $unidad = trim($_POST['unidad']);
    $rendimiento = floatval($_POST['rendimiento']);
    $ingredientes = $_POST['ingrediente_id'] ?? [];
    $cantidades = $_POST['cantidad'] ?? [];
    $fecha_creacion = date("Y-m-d H:i:s");

    // Validaciones
    if (empty($nombre) || empty($unidad)) {
        exit("Error: Nombre y unidad son campos obligatorios");
    }

    if ($rendimiento <= 0) {
        exit("Error: El rendimiento debe ser mayor a cero");
    }

    if (count($ingredientes) !== count($cantidades) || empty($ingredientes)) {
        exit("Error: Datos de ingredientes incompletos");
    }

    // 2. Cálculo de costos
    $costo_total = 0;
    $detalles_costos = [];

    foreach ($ingredientes as $i => $ingrediente_id) {
        $id_insumo = intval($ingrediente_id);
        $cantidad = floatval($cantidades[$i]);

        // Obtener información del insumo
        $stmt = $conn->prepare("SELECT nombre, precio_unitario, unidad_medida FROM insumos WHERE id = ?");
        $stmt->bind_param("i", $id_insumo);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            exit("Error: Insumo con ID $id_insumo no encontrado");
        }

        $insumo = $result->fetch_assoc();
        $costo_ingrediente = $cantidad * $insumo['precio_unitario'];
        
        $detalles_costos[] = [
            'nombre' => $insumo['nombre'],
            'cantidad' => $cantidad,
            'unidad' => $insumo['unidad_medida'],
            'precio_unitario' => $insumo['precio_unitario'],
            'costo' => $costo_ingrediente
        ];

        $costo_total += $costo_ingrediente;
        $stmt->close();
    }

    // 3. Cálculo de costo unitario
    $costo_unitario = $costo_total / $rendimiento;

    // 4. Guardar en base de datos (transacción)
    $conn->begin_transaction();

    try {
        // Insertar receta principal
        $stmt = $conn->prepare("INSERT INTO recetas (nombre, unidad, rendimiento, costo_total, costo_unitario, fecha_creacion) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssddds", $nombre, $unidad, $rendimiento, $costo_total, $costo_unitario, $fecha_creacion);
        $stmt->execute();
        $id_receta = $conn->insert_id;
        $stmt->close();

        // Insertar ingredientes
        foreach ($ingredientes as $i => $ingrediente_id) {
            $id_insumo = intval($ingrediente_id);
            $cantidad = floatval($cantidades[$i]);

            $stmt = $conn->prepare("INSERT INTO ingredientes_receta (id_receta, id_insumo, cantidad) VALUES (?, ?, ?)");
            $stmt->bind_param("iid", $id_receta, $id_insumo, $cantidad);
            $stmt->execute();
            $stmt->close();
        }

        $conn->commit();
        
        // 5. Mostrar resultados en texto plano (como en la versión original)
        $respuesta = "✅ Receta guardada exitosamente\n";
        $respuesta .= "📝 Nombre: $nombre\n";
        $respuesta .= "📊 Rendimiento: $rendimiento $unidad\n";
        $respuesta .= "💰 Costo total: $" . number_format($costo_total, 2) . "\n";
        $respuesta .= "➗ Costo unitario: $" . number_format($costo_unitario, 2) . " por $unidad\n\n";
        $respuesta .= "🧾 Detalles de costos:\n";
        
        foreach ($detalles_costos as $detalle) {
            $respuesta .= "- {$detalle['nombre']}: {$detalle['cantidad']} {$detalle['unidad']} ";
            $respuesta .= "a $" . number_format($detalle['precio_unitario'], 4) . " = ";
            $respuesta .= "$" . number_format($detalle['costo'], 2) . "\n";
        }

        echo $respuesta;

    } catch (Exception $e) {
        $conn->rollback();
        exit("❌ Error al guardar la receta: " . $e->getMessage());
    }

} else {
    http_response_code(405);
    echo "Error: Método no permitido";
}
?>
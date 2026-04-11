<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
protegerPagina();

if (isset($_SESSION['usuario_rol']) && !in_array($_SESSION['usuario_rol'], ['administrador', 'admin'])) {
    header("Location: ../dashboard.php");
    exit;
}

require_once '../includes/Database.php';

$db = new Database();
$conn = $db->getConnection();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $proveedor = trim($_POST['proveedor'] ?? '');
    if (empty($proveedor)) {
        $proveedor = "Proveedor Varios";
    }
    $fecha = date('Y-m-d H:i:s');
    $total = 0;
    
    if (empty($_POST['insumos']) || !is_array($_POST['insumos'])) {
        header("Location: index.php?error=" . urlencode("No se agregaron insumos a la compra."));
        exit;
    }

    try {
        $conn->begin_transaction();

        $detalles = [];
        foreach ($_POST['insumos'] as $data) {
            if (empty($data['id_insumo']) || !is_numeric($data['cantidad']) || !is_numeric($data['precio_unitario'])) {
                continue; // Saltar lineas vacias
            }
            $cantidad = (float)$data['cantidad'];
            $precio_unitario = (float)$data['precio_unitario'];
            if ($cantidad <= 0 || $precio_unitario < 0) {
                 throw new Exception("Cantidades o precios no pueden ser negativos.");
            }
            
            $subtotal = $cantidad * $precio_unitario;
            $total += $subtotal;
            $detalles[] = [
                'id_insumo' => (int)$data['id_insumo'],
                'cantidad' => $cantidad,
                'precio_unitario' => $precio_unitario
            ];
        }

        if (count($detalles) === 0) {
            throw new Exception("No hay insumos válidos en el formulario.");
        }

        // Insertar compra
        $stmt_compra = $conn->prepare("INSERT INTO compras_insumos (proveedor, fecha, total) VALUES (?, ?, ?)");
        $stmt_compra->bind_param("ssd", $proveedor, $fecha, $total);
        if (!$stmt_compra->execute()) {
            throw new Exception("Error al guardar la cabecera de la compra.");
        }
        $compra_id = $conn->insert_id;
        $stmt_compra->close();

        // Insertar detalles y actualizar stock
        $stmt_detalle = $conn->prepare("INSERT INTO detalle_compra (id_compra, id_insumo, cantidad, precio_unitario) VALUES (?, ?, ?, ?)");
        $stmt_stock = $conn->prepare("UPDATE insumos SET stock_actual = stock_actual + ? WHERE id = ?");

        foreach ($detalles as $det) {
            // Guardar detalle
            $stmt_detalle->bind_param("iidd", $compra_id, $det['id_insumo'], $det['cantidad'], $det['precio_unitario']);
            if (!$stmt_detalle->execute()) {
                throw new Exception("Error al guardar los detalles de un insumo.");
            }

            // Actualizar stock
            $stmt_stock->bind_param("di", $det['cantidad'], $det['id_insumo']);
            if (!$stmt_stock->execute()) {
                throw new Exception("Error al actualizar el stock del insumo ID: " . $det['id_insumo']);
            }
        }
        $stmt_detalle->close();
        $stmt_stock->close();

        // Registrar la compra automáticamente en gastos
        $descripcion_gasto = "Compra de insumos: " . $proveedor;
        $usuario_id = $_SESSION['usuario_id'] ?? 1; // Default
        
        $stmt_gasto = $conn->prepare("INSERT INTO gastos (categoria_id, descripcion, monto, fecha, usuario_id) VALUES (NULL, ?, ?, ?, ?)");
        $stmt_gasto->bind_param("sdsi", $descripcion_gasto, $total, $fecha, $usuario_id);
        $stmt_gasto->execute();
        $stmt_gasto->close();

        $conn->commit();
        header("Location: index.php?success=1");
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error en compra: " . $e->getMessage());
        header("Location: index.php?error=" . urlencode($e->getMessage()));
        exit;
    }
} else {
    header("Location: index.php");
    exit;
}
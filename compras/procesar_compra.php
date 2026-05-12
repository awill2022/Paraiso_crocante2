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
    $total_sin_iva = 0;
    $total_iva     = 0;

    if (empty($_POST['insumos']) || !is_array($_POST['insumos'])) {
        header("Location: index.php?error=" . urlencode("No se agregaron insumos a la compra."));
        exit;
    }

    try {
        $conn->begin_transaction();

        $detalles = [];
        foreach ($_POST['insumos'] as $data) {
            // Validar campos obligatorios
            if (empty($data['id_insumo']) || !is_numeric($data['cantidad']) || !is_numeric($data['precio_unitario'])) {
                continue;
            }

            $cantidad        = (float)$data['cantidad'];          // Ya viene calculado (unidades reales)
            $precio_unitario = (float)$data['precio_unitario'];   // Ya viene calculado (precio por unidad)
            $iva_pct         = (float)($data['iva'] ?? 0);        // 0, 12 o 15

            if ($cantidad <= 0 || $precio_unitario < 0) {
                throw new Exception("Cantidades o precios no pueden ser negativos o cero.");
            }
            if ($iva_pct < 0 || $iva_pct > 15) {
                $iva_pct = 0;
            }

            $subtotal_linea = $cantidad * $precio_unitario;
            $iva_linea      = $subtotal_linea * ($iva_pct / 100);
            $total_linea    = $subtotal_linea + $iva_linea;

            $total_sin_iva += $subtotal_linea;
            $total_iva     += $iva_linea;

            $detalles[] = [
                'id_insumo'      => (int)$data['id_insumo'],
                'cantidad'       => $cantidad,
                'precio_unitario'=> $precio_unitario,
                'iva_pct'        => $iva_pct,
                'subtotal'       => $subtotal_linea,
                'iva_monto'      => $iva_linea,
                'total_linea'    => $total_linea,
            ];
        }

        if (count($detalles) === 0) {
            throw new Exception("No hay insumos válidos en el formulario.");
        }

        $total_factura = $total_sin_iva + $total_iva;

        // -------------------------------------------------------
        // Verificar si la tabla detalle_compra tiene columna iva_pct
        // Si no existe, la agregamos dinámicamente (compatibilidad)
        // -------------------------------------------------------
        $cols_result = $conn->query("SHOW COLUMNS FROM detalle_compra LIKE 'iva_pct'");
        if ($cols_result && $cols_result->num_rows === 0) {
            $conn->query("ALTER TABLE detalle_compra ADD COLUMN iva_pct DECIMAL(5,2) DEFAULT 0 AFTER precio_unitario");
        }

        // Verificar si compras_insumos tiene columnas de IVA
        $cols_result2 = $conn->query("SHOW COLUMNS FROM compras_insumos LIKE 'total_iva'");
        if ($cols_result2 && $cols_result2->num_rows === 0) {
            $conn->query("ALTER TABLE compras_insumos ADD COLUMN subtotal DECIMAL(10,2) DEFAULT 0 AFTER total");
            $conn->query("ALTER TABLE compras_insumos ADD COLUMN total_iva DECIMAL(10,2) DEFAULT 0 AFTER subtotal");
        }

        // Insertar cabecera de compra
        $stmt_compra = $conn->prepare(
            "INSERT INTO compras_insumos (proveedor, fecha, total, subtotal, total_iva) VALUES (?, ?, ?, ?, ?)"
        );
        if (!$stmt_compra) {
            // Fallback si las columnas nuevas no existen aún
            $stmt_compra = $conn->prepare("INSERT INTO compras_insumos (proveedor, fecha, total) VALUES (?, ?, ?)");
            $stmt_compra->bind_param("ssd", $proveedor, $fecha, $total_factura);
        } else {
            $stmt_compra->bind_param("ssddd", $proveedor, $fecha, $total_factura, $total_sin_iva, $total_iva);
        }

        if (!$stmt_compra->execute()) {
            throw new Exception("Error al guardar la cabecera de la compra.");
        }
        $compra_id = $conn->insert_id;
        $stmt_compra->close();

        // Insertar detalles y actualizar stock
        $stmt_detalle = $conn->prepare(
            "INSERT INTO detalle_compra (id_compra, id_insumo, cantidad, precio_unitario, iva_pct) VALUES (?, ?, ?, ?, ?)"
        );
        if (!$stmt_detalle) {
            // Fallback sin iva_pct
            $stmt_detalle = $conn->prepare(
                "INSERT INTO detalle_compra (id_compra, id_insumo, cantidad, precio_unitario) VALUES (?, ?, ?, ?)"
            );
        }
        $stmt_stock = $conn->prepare("UPDATE insumos SET stock_actual = stock_actual + ? WHERE id = ?");

        foreach ($detalles as $det) {
            // Guardar detalle
            if ($stmt_detalle->param_count == 5) {
                $stmt_detalle->bind_param("iiddd", $compra_id, $det['id_insumo'], $det['cantidad'], $det['precio_unitario'], $det['iva_pct']);
            } else {
                $stmt_detalle->bind_param("iidd", $compra_id, $det['id_insumo'], $det['cantidad'], $det['precio_unitario']);
            }
            if (!$stmt_detalle->execute()) {
                throw new Exception("Error al guardar los detalles de un insumo.");
            }

            // Actualizar stock con las UNIDADES REALES
            $stmt_stock->bind_param("di", $det['cantidad'], $det['id_insumo']);
            if (!$stmt_stock->execute()) {
                throw new Exception("Error al actualizar el stock del insumo ID: " . $det['id_insumo']);
            }
        }
        $stmt_detalle->close();
        $stmt_stock->close();

        // Registrar en gastos (monto = total con IVA)
        $descripcion_gasto = "Compra de insumos: " . $proveedor;
        $usuario_id = $_SESSION['usuario_id'] ?? 1;

        $stmt_gasto = $conn->prepare(
            "INSERT INTO gastos (categoria_id, descripcion, monto, fecha, usuario_id) VALUES (NULL, ?, ?, ?, ?)"
        );
        $stmt_gasto->bind_param("sdsi", $descripcion_gasto, $total_factura, $fecha, $usuario_id);
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
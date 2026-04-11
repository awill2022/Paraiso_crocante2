<?php
<?php
require_once '../includes/config.php';
protegerPaginaAdmin();
require_once '../includes/Database.php';

$db = new Database();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $proveedor = trim($_POST['proveedor']);
    $fecha = date('Y-m-d H:i:s');
    $total = 0;
    $detalles = [];

    // Calcular total y validar
    foreach ($_POST['insumos'] as $data) {
        if (empty($data['id_insumo']) || !is_numeric($data['cantidad']) || !is_numeric($data['precio_unitario'])) {
            die("Datos inválidos en insumo.");
        }
        $cantidad = (float)$data['cantidad'];
        $precio_unitario = (float)$data['precio_unitario'];
        $subtotal = $cantidad * $precio_unitario;
        $total += $subtotal;
        $detalles[] = [(int)$data['id_insumo'], $cantidad, $precio_unitario];
    }

    // Iniciar transacción
    $db->executeQuery("START TRANSACTION");

    try {
        // Insertar compra
        $query_compra = "INSERT INTO compras_insumos (proveedor, fecha, total) VALUES (?, ?, ?)";
        $compra_id = $db->executeQuery($query_compra, [$proveedor, $fecha, $total], true);

        // Insertar detalles y actualizar stock
        foreach ($detalles as $detalle) {
            $db->executeQuery("INSERT INTO detalle_compra (id_compra, id_insumo, cantidad, precio_unitario) VALUES (?, ?, ?, ?)", 
                              [$compra_id, $detalle[0], $detalle[1], $detalle[2]]);
            $db->executeQuery("UPDATE insumos SET stock_actual = stock_actual + ? WHERE id = ?", [$detalle[1], $detalle[0]]);
        }

        $db->executeQuery("COMMIT");
        header("Location: index.php?success=1");
        exit;
    } catch (Exception $e) {
        $db->executeQuery("ROLLBACK");
        error_log("Error en compra: " . $e->getMessage());
        die("Error al registrar compra.");
    }
}
?>
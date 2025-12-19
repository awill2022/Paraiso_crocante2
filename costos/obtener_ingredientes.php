<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

$db = new Database();
$conn = $db->getConnection();

if (!$conn) {
    die("❌ Error de conexión");
}

$resultado = $conn->query("SELECT id, nombre FROM insumos");

if (!$resultado) {
    die("❌ Error en la consulta: " . $conn->error);
}

$options = "";
while ($row = $resultado->fetch_assoc()) {
    $options .= "<option value='{$row['id']}'>{$row['nombre']}</option>";
}

if (empty($options)) {
    echo "⚠️ No se encontraron insumos.";
} else {
    echo $options;
}
?>
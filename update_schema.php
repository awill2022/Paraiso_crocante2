<?php
// Script para actualizar la estructura de la base de datos en el hosting
require_once 'includes/config.php';
require_once 'includes/Database.php';

$db = new Database();
$conn = $db->getConnection();

echo "<h2>Actualizando esquema de base de datos...</h2>";

// 1. Modificar la columna metodo_pago en la tabla ventas para asegurarse que acepte el nuevo valor
// Asumimos que es un VARCHAR o un ENUM. Lo convertiremos/aseguraremos a VARCHAR(50) para flexibilidad.
$sql = "ALTER TABLE ventas MODIFY COLUMN metodo_pago VARCHAR(50) NOT NULL";

if ($conn->query($sql) === TRUE) {
    echo "✅ Columna 'metodo_pago' actualizada a VARCHAR(50) con éxito.<br>";
} else {
    echo "❌ Error actualizando columna: " . $conn->error . "<br>";
}

// 2. (Opcional) Migrar datos antiguos si se desea que 'tarjeta' ahora sea 'de_una'
// Descomentar la siguiente línea si se quiere cambiar todo el historial de 'tarjeta' a 'de_una'
// $conn->query("UPDATE ventas SET metodo_pago = 'de_una' WHERE metodo_pago = 'tarjeta'");

echo "<p>Base de datos lista para aceptar 'De Una'. Puedes borrar este archivo ahora.</p>";
?>

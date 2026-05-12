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

// Agregar tablas para compras
$conn->query("CREATE TABLE IF NOT EXISTS compras_insumos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proveedor VARCHAR(255) NOT NULL,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    total DECIMAL(10,2) NOT NULL
)");

$conn->query("CREATE TABLE IF NOT EXISTS detalle_compra (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_compra INT NOT NULL,
    id_insumo INT NOT NULL,
    cantidad DECIMAL(10,2) NOT NULL,
    precio_unitario DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (id_compra) REFERENCES compras_insumos(id),
    FOREIGN KEY (id_insumo) REFERENCES insumos(id)
)");

echo "<p>Base de datos actualizada con las tablas de compras. Puedes borrar este archivo ahora.</p>";
?>

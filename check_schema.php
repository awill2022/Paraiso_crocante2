<?php
require_once 'includes/config.php';
require_once 'includes/Database.php';

$db = new Database();
$conn = $db->getConnection();

echo "<h2>Columna metodo_pago en ventas:</h2>";
$result = $conn->query("SHOW COLUMNS FROM ventas LIKE 'metodo_pago'");
while ($row = $result->fetch_assoc()) {
    echo "Type: " . $row['Type'] . "<br>";
}

echo "<h2>Conteo de métodos de pago actuales:</h2>";
$result = $conn->query("SELECT metodo_pago, COUNT(*) as c FROM ventas GROUP BY metodo_pago");
while ($row = $result->fetch_assoc()) {
    echo $row['metodo_pago'] . ": " . $row['c'] . "<br>";
}
?>

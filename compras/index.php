<?php
<?php
require_once '../includes/config.php';
protegerPaginaAdmin(); // Solo admins pueden registrar compras
require_once '../includes/Database.php';

$db = new Database();
$insumos = $db->executeQuery("SELECT id, nombre FROM insumos ORDER BY nombre", [], false);

if ($_GET['success'] ?? false) {
    echo "<p>Compra registrada exitosamente.</p>";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <title>Registrar Compra</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <h1>Registrar Nueva Compra</h1>
    <form action="procesar_compra.php" method="POST">
        <label>Proveedor: <input type="text" name="proveedor" required></label><br>
        <h3>Detalles de Insumos</h3>
        <div id="insumos-list">
            <div class="insumo-item">
                <select name="insumos[0][id_insumo]" required>
                    <option value="">Seleccionar Insumo</option>
                    <?php foreach ($insumos as $insumo): ?>
                        <option value="<?php echo $insumo['id']; ?>"><?php echo $insumo['nombre']; ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="number" step="0.01" name="insumos[0][cantidad]" placeholder="Cantidad" required>
                <input type="number" step="0.01" name="insumos[0][precio_unitario]" placeholder="Precio Unitario" required>
            </div>
        </div>
        <button type="button" onclick="agregarInsumo()">Agregar Otro Insumo</button><br>
        <button type="submit">Registrar Compra</button>
    </form>
    <script>
        let contador = 1;
        function agregarInsumo() {
            const list = document.getElementById('insumos-list');
            const item = document.createElement('div');
            item.className = 'insumo-item';
            item.innerHTML = `
                <select name="insumos[${contador}][id_insumo]" required>
                    <option value="">Seleccionar Insumo</option>
                    <?php foreach ($insumos as $insumo): ?>
                        <option value="<?php echo $insumo['id']; ?>"><?php echo $insumo['nombre']; ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="number" step="0.01" name="insumos[${contador}][cantidad]" placeholder="Cantidad" required>
                <input type="number" step="0.01" name="insumos[${contador}][precio_unitario]" placeholder="Precio Unitario" required>
            `;
            list.appendChild(item);
            contador++;
        }
    </script>
</body>
</html>
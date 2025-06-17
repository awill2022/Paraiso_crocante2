<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';


protegerPagina();

$db = new Database();
$conn = $db->getConnection();

$categorias = $conn->query("SELECT * FROM categorias");

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $categoria_id = (int)$_POST['categoria_id'];
    $precio = (float)$_POST['precio'];
    $descripcion = trim($_POST['descripcion']);
    
    // Subir imagen
    $foto = 'default.jpg';
    if(isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $extension = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
        $foto = 'producto-' . time() . '.' . $extension;
        move_uploaded_file($_FILES['foto']['tmp_name'], '../../assets/img/productos/' . $foto);
    }
    
    $stmt = $conn->prepare("INSERT INTO productos (nombre, categoria_id, precio, descripcion, foto) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sidds", $nombre, $categoria_id, $precio, $descripcion, $foto);
    
    if($stmt->execute()) {
        header("Location: listar.php?success=1");
        exit;
    } else {
        $error = "Error al agregar el producto";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Producto</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/styles_productos.css">
</head>
<body>
<div class="product-form-container">
    <h1>Agregar Nuevo Producto</h1>
    
    <?php if(isset($error)): ?>
    <div class="product-alert error"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <form action="agregar.php" method="post" enctype="multipart/form-data">
        <div class="product-form-group">
            <label for="nombre">Nombre:</label>
            <input type="text" id="nombre" name="nombre" required>
        </div>
        
        <div class="product-form-group">
            <label for="categoria_id">Categoría:</label>
            <select id="categoria_id" name="categoria_id" required>
                <?php while($categoria = $categorias->fetch_assoc()): ?>
                <option value="<?php echo $categoria['id']; ?>"><?php echo htmlspecialchars($categoria['nombre']); ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        
        <div class="product-form-group">
            <label for="precio">Precio:</label>
            <input type="number" id="precio" name="precio" step="0.01" min="0" required>
        </div>
        
        <div class="product-form-group">
            <label for="descripcion">Descripción:</label>
            <textarea id="descripcion" name="descripcion"></textarea>
        </div>
        
        <div class="product-form-group">
            <label for="foto">Imagen del Producto:</label>
            <input type="file" id="foto" name="foto" accept="image/*">
            <img id="image-preview" alt="Vista previa de la imagen">
        </div>
        
        <div class="product-button-container">
            <button type="submit" class="product-btn">Guardar Producto</button>
            <a href="listar.php" class="product-btn cancel">Cancelar</a>
        </div>
    </form>
</div>
<script>
document.getElementById('foto').addEventListener('change', function(event) {
    const file = event.target.files[0];
    const preview = document.getElementById('image-preview');
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(file);
    }
});
</script>
</body>
</html>
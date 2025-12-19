<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

protegerPagina();

$db = new Database();
$conn = $db->getConnection();

// Verificar permiso (Admin o Cocinero)
// Si hay otros roles que puedan ver, añadirlos aquí.
if (!isset($_SESSION['usuario_rol']) || !in_array($_SESSION['usuario_rol'], ['administrador', 'admin', 'cocinero'])) {
   // Si no tiene permiso, al dashboard (que ya tiene lógica de redirección) o pos
   header("Location: ../dashboard.php");
   exit;
}

$es_admin = in_array($_SESSION['usuario_rol'], ['administrador', 'admin']);

// Consultar todas las recetas
$sql = "SELECT * FROM recetas ORDER BY nombre ASC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recetas - Paraíso Crocante</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/table_styles.css"> <!-- Reutilizamos estilos de tabla -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .recetas-container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
        }
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        .recipe-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            overflow: hidden;
        }
        .recipe-header {
            background: #FF6B6B;
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
        }
        .recipe-header h3 { margin: 0; font-size: 1.2em; }
        .recipe-details {
            padding: 20px;
            display: none; /* Oculto por defecto */
        }
        .recipe-info {
            display: flex;
            gap: 30px;
            margin-bottom: 15px;
            flex-wrap: wrap;
            background: #f9f9f9;
            padding: 15px;
            border-radius: 8px;
        }
        .info-item label { font-weight: bold; color: #666; display: block; font-size: 0.9em; }
        .info-item span { font-size: 1.1em; color: #333; }
        
        .ingredients-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.95em;
        }
        .ingredients-table th, .ingredients-table td {
            text-align: left;
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        .ingredients-table th { background-color: #f1f1f1; color: #555; }
        
        .toggle-icon { transition: transform 0.3s; }
        .open .toggle-icon { transform: rotate(180deg); }
    </style>
</head>
<body>

<div class="recetas-container">
    <div class="header-section">
        <div>
            <h1>📖 Libro de Recetas</h1>
            <a href="../dashboard.php" class="btn-secondary" style="display:inline-block; margin-top:5px; text-decoration:none; color:#555;"><i class="fas fa-arrow-left"></i> Volver al Dashboard</a>
        </div>
        <?php if ($es_admin): ?>
        <a href="formulario_receta.php" class="btn-main"><i class="fas fa-plus"></i> Nueva Receta</a>
        <?php endif; ?>
    </div>

    <?php if ($result && $result->num_rows > 0): ?>
        <?php while($receta = $result->fetch_assoc()): ?>
            <div class="recipe-card">
                <div class="recipe-header" onclick="toggleRecipe(<?php echo $receta['id']; ?>)">
                    <h3><?php echo htmlspecialchars($receta['nombre']); ?></h3>
                    <div>
                        <span style="font-size: 0.9em; margin-right: 15px; opacity: 0.9;">
                            <i class="fas fa-chart-pie"></i> Rendimiento: <?php echo $receta['rendimiento'] . ' ' . $receta['unidad']; ?>
                        </span>
                        <i class="fas fa-chevron-down toggle-icon" id="icon-<?php echo $receta['id']; ?>"></i>
                    </div>
                </div>
                
                <div class="recipe-details" id="details-<?php echo $receta['id']; ?>">
                    <div class="recipe-info">
                        <div class="info-item">
                            <label>Rendimiento</label>
                            <span><?php echo $receta['rendimiento'] . ' ' . htmlspecialchars($receta['unidad']); ?></span>
                        </div>
                        <?php if ($es_admin): ?>
                        <div class="info-item">
                            <label>Costo Total</label>
                            <span>$<?php echo number_format($receta['costo_total'], 2); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Costo Unitario</label>
                            <span>$<?php echo number_format($receta['costo_unitario'], 2); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="info-item">
                            <label>Fecha Creación</label>
                            <span><?php echo date('d/m/Y', strtotime($receta['fecha_creacion'])); ?></span>
                        </div>
                    </div>

                    <h4>Ingredientes:</h4>
                    <?php
                        // Consultar ingredientes de esta receta
                        $sql_ing = "SELECT ir.cantidad, i.nombre, i.unidad_medida 
                                    FROM ingredientes_receta ir 
                                    JOIN insumos i ON ir.id_insumo = i.id 
                                    WHERE ir.id_receta = " . $receta['id'];
                        $res_ing = $conn->query($sql_ing);
                    ?>
                    <?php if ($res_ing && $res_ing->num_rows > 0): ?>
                    <table class="ingredients-table">
                        <thead>
                            <tr>
                                <th>Ingrediente</th>
                                <th>Cantidad</th>
                                <th>Unidad</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($ing = $res_ing->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($ing['nombre']); ?></td>
                                <td><?php echo $ing['cantidad']; ?></td>
                                <td><?php echo htmlspecialchars($ing['unidad_medida']); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                        <p style="color: #888; font-style: italic;">No hay ingredientes registrados.</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="alert info">No hay recetas registradas todavía.</div>
    <?php endif; ?>
</div>

<script>
    function toggleRecipe(id) {
        var details = document.getElementById('details-' + id);
        var icon = document.getElementById('icon-' + id);
        
        if (details.style.display === 'block') {
            details.style.display = 'none';
            icon.parentElement.parentElement.classList.remove('open');
            icon.style.transform = 'rotate(0deg)';
        } else {
            details.style.display = 'block';
            icon.parentElement.parentElement.classList.add('open');
            icon.style.transform = 'rotate(180deg)';
        }
    }
</script>

</body>
</html>

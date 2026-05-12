<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

protegerPagina(); // Asegura que el usuario esté logueado

$db = new Database();
$conn = $db->getConnection();
date_default_timezone_set('America/Guayaquil'); // Ecuador

$errores = [];
$mensaje_exito = '';
$categorias_gasto = [];

// Cargar categorías
try {
    $result_cat = $conn->query("SELECT id, nombre FROM categorias_gastos ORDER BY nombre ASC");
    if ($result_cat) {
        while ($row_cat = $result_cat->fetch_assoc()) {
            $categorias_gasto[] = $row_cat;
        }
    } else {
        $errores[] = "Error al cargar las categorías: " . $conn->error;
    }
} catch (Exception $e) {
    $errores[] = "Excepción al cargar categorías: " . $e->getMessage();
}

// Variables
$fecha_persistente = date('Y-m-d');
$monto_persistente = '';
$descripcion_persistente = '';
$categoria_id_persistente = '';
$proveedor_persistente = '';
$recurrente_persistente = 0;
$nombre_archivo_comprobante = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fecha_persistente = trim($_POST['fecha']);
    $monto_persistente = trim($_POST['monto']);
    $descripcion_persistente = trim($_POST['descripcion']);
    $categoria_id_persistente = trim($_POST['categoria_id']);
    $proveedor_persistente = trim($_POST['proveedor']);
    $recurrente_persistente = isset($_POST['recurrente']) ? 1 : 0;
    $usuario_id = $_SESSION['usuario_id'];

    // Validaciones normales (igual que tu versión anterior)
    if (empty($fecha_persistente)) $errores[] = "La fecha es obligatoria.";
    if (empty($monto_persistente) || !is_numeric($monto_persistente) || floatval($monto_persistente) <= 0)
        $errores[] = "Monto inválido.";
    if (empty($descripcion_persistente)) $errores[] = "La descripción es obligatoria.";
    if (empty($categoria_id_persistente) || !filter_var($categoria_id_persistente, FILTER_VALIDATE_INT))
        $errores[] = "Seleccione una categoría válida.";

    // Comprobante (igual que antes)
    if (isset($_FILES['comprobante']) && $_FILES['comprobante']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['comprobante']['name'], PATHINFO_EXTENSION));
        $permitidos = ['jpg', 'jpeg', 'png', 'pdf'];
        if (in_array($ext, $permitidos)) {
            $nombre_archivo_comprobante = "comprobante_gasto_" . $usuario_id . "_" . time() . "." . $ext;
            move_uploaded_file($_FILES['comprobante']['tmp_name'], "../assets/comprobantes_gastos/" . $nombre_archivo_comprobante);
        }
    }

    if (empty($errores)) {
        try {
            // 🔍 Verificar si hay una caja abierta
            $stmt_caja = $conn->prepare("SELECT id FROM cierres_caja WHERE usuario_id = ? AND fecha_cierre IS NULL LIMIT 1");
            $stmt_caja->bind_param("i", $usuario_id);
            $stmt_caja->execute();
            $result_caja = $stmt_caja->get_result();
            $caja_abierta = $result_caja->fetch_assoc();
            $stmt_caja->close();

            if (!$caja_abierta) {
                throw new Exception("No hay una caja abierta. Abre una caja antes de registrar gastos.");
            }

            $caja_id = intval($caja_abierta['id']);
            $conn->begin_transaction();

            // 💾 Registrar gasto con caja_id
            $sql = "INSERT INTO gastos (fecha, monto, descripcion, categoria_id, usuario_id, proveedor, recurrente, comprobante, caja_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sdsiiissi",
                $fecha_persistente,
                $monto_persistente,
                $descripcion_persistente,
                $categoria_id_persistente,
                $usuario_id,
                $proveedor_persistente,
                $recurrente_persistente,
                $nombre_archivo_comprobante,
                $caja_id
            );
            $stmt->execute();
            $stmt->close();

            // 🔄 Descontar el gasto del total de caja
            $stmt_update_caja = $conn->prepare("UPDATE cierres_caja SET total_ventas = total_ventas - ? WHERE id = ?");
            $stmt_update_caja->bind_param("di", $monto_persistente, $caja_id);
            $stmt_update_caja->execute();
            $stmt_update_caja->close();

            $conn->commit();

            $mensaje_exito = "Gasto registrado correctamente.";
            // Reset de campos
            $monto_persistente = $descripcion_persistente = $proveedor_persistente = '';
            $categoria_id_persistente = '';
            $recurrente_persistente = 0;

        } catch (Exception $e) {
            $conn->rollback();
            $errores[] = $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registrar Gasto - Paraíso Crocante</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="/img/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="../assets/css/app.css">
</head>
<body class="app-body">

<header class="app-header">
    <div>
        <h1>💸 Registrar Nuevo Gasto</h1>
        <p>Se descuenta automáticamente del balance de caja.</p>
    </div>
    <nav>
        <a href="listar_gastos.php" class="btn-nav">Ver Gastos</a>
        <a href="../dashboard.php" class="btn-nav">← Dashboard</a>
    </nav>
</header>

<div class="app-page narrow">

    <?php if (!empty($errores)): ?>
    <div class="app-alert error">
        <span>❌</span>
        <div>
            <strong>Por favor, corrija los siguientes errores:</strong>
            <ul><?php foreach ($errores as $error_msg): ?><li><?php echo htmlspecialchars($error_msg); ?></li><?php endforeach; ?></ul>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($mensaje_exito): ?>
    <div class="app-alert success">✅ <?php echo htmlspecialchars($mensaje_exito); ?></div>
    <?php endif; ?>

    <div class="app-card">
        <form action="registrar_gasto.php" method="post" enctype="multipart/form-data">

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label" for="fecha">Fecha del Gasto *</label>
                    <input class="form-control" type="date" id="fecha" name="fecha"
                           value="<?php echo htmlspecialchars($fecha_persistente); ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="monto">Monto ($) *</label>
                    <input class="form-control" type="number" id="monto" name="monto"
                           step="0.01" min="0.01"
                           value="<?php echo htmlspecialchars($monto_persistente); ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="descripcion">Descripción *</label>
                <textarea class="form-control" id="descripcion" name="descripcion" rows="3" required><?php echo htmlspecialchars($descripcion_persistente); ?></textarea>
            </div>

            <div class="form-group">
                <label class="form-label" for="categoria_id">Categoría del Gasto *</label>
                <select class="form-control" id="categoria_id" name="categoria_id" required>
                    <option value="">Seleccione una categoría...</option>
                    <?php foreach ($categorias_gasto as $categoria): ?>
                    <option value="<?php echo $categoria['id']; ?>"
                        <?php echo ($categoria_id_persistente == $categoria['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($categoria['nombre']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="proveedor">Proveedor (Opcional)</label>
                <input class="form-control" type="text" id="proveedor" name="proveedor"
                       value="<?php echo htmlspecialchars($proveedor_persistente); ?>">
            </div>

            <div class="form-group" style="display:flex; align-items:center; gap:10px;">
                <input type="checkbox" id="recurrente" name="recurrente" value="1"
                       <?php echo $recurrente_persistente ? 'checked' : ''; ?>
                       style="width:18px; height:18px; accent-color:#FF6B6B; flex-shrink:0;">
                <label for="recurrente" style="margin:0; font-weight:500; color:#444; cursor:pointer;">
                    ¿Es un gasto recurrente?
                </label>
            </div>

            <div class="form-group">
                <label class="form-label" for="comprobante">Comprobante (Opcional — JPG, PNG, PDF)</label>
                <input class="form-control" type="file" id="comprobante" name="comprobante"
                       accept="image/jpeg,image/png,application/pdf">
                <div class="form-hint">Máximo 5 MB</div>
            </div>

            <div class="btn-group">
                <button type="submit" class="btn btn-primary btn-lg">💾 Guardar Gasto</button>
                <a href="../dashboard.php" class="btn btn-secondary">← Dashboard</a>
            </div>
        </form>
    </div>
</div>
<?php if(isset($conn)) $conn->close(); ?>
</body>
</html>

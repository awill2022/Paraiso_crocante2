<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
protegerPagina();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Crear Receta</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Estilos existentes -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/styles_productos.css">

    <!-- Estilo adicional para el formulario de receta -->
    <style>
            body {
        font-family: Arial, sans-serif;
        padding: 30px;
        background-color: #f9f9f9;
    }

    h2 {
        color: #444;
        margin-bottom: 20px;
    }

    form {
        background-color: #fff;
        padding: 20px 30px;
        border-radius: 10px;
        box-shadow: 0 0 10px rgba(0,0,0,0.1);
        max-width: 600px;
    }

    label {
        font-weight: bold;
        display: block;
        margin-bottom: 5px;
        color: #333;
    }

    input[type="text"],
    input[type="number"],
    select {
        width: 100%;
        padding: 10px;
        margin-bottom: 15px;
        border: 1px solid #ccc;
        border-radius: 5px;
        font-size: 14px;
    }

    button {
        background-color: #4CAF50;
        color: white;
        padding: 10px 18px;
        border: none;
        border-radius: 5px;
        font-size: 14px;
        cursor: pointer;
        margin-top: 10px;
    }

    button:hover {
        background-color: #45a049;
    }

    /* Estilo para cada fila de ingrediente */
    #ingredientes .ingrediente {
        display: flex;
        gap: 10px;
        align-items: center;
        margin-bottom: 10px;
    }

    #ingredientes .ingrediente select,
    #ingredientes .ingrediente input[type="number"] {
        flex: 1;
        padding: 8px;
    }

    #ingredientes h4 {
        margin-top: 20px;
        color: #555;
    }

    </style>
</head>
<body>
    <div class="product-form-container">
        <h1>Crear nueva receta</h1>
        <form id="form_receta">
            <div class="product-form-group">
                <label for="nombre_receta">Nombre de la receta:</label>
                <input type="text" id="nombre_receta" name="nombre_receta" required>
            </div>

            <div class="product-form-group">
    <label for="unidad">Unidad de medida:</label>
    <input type="text" id="unidad" name="unidad" required>
</div>

<div class="product-form-group">
    <label for="rendimiento">Rendimiento (cantidad de unidades producidas):</label>
    <input type="number" step="0.01" id="rendimiento" name="rendimiento" required>
</div>

            <div class="product-form-group" id="ingredientes">
                <h4>Ingredientes</h4>
                <div class="ingrediente" style="display: flex; gap: 10px; margin-bottom: 10px;">
                    <select name="ingrediente_id[]" required style="flex: 2;"></select>
                    <input type="number" step="0.0001" name="cantidad[]" placeholder="Cantidad" required style="flex: 1;">
                </div>
            </div>

            <div class="product-button-container">
                <button type="button" class="product-btn" onclick="agregarIngrediente()">Agregar Ingrediente</button>
                <button type="submit" class="product-btn">Guardar Receta</button>
                <a href="../dashboard.php" class="product-btn cancel">Volver al Dashboard</a>
            </div>
        </form>
    </div>

    <script>
        function cargarIngredientes() {
            $.get('obtener_ingredientes.php', function(data) {
                $('select[name="ingrediente_id[]"]').each(function() {
                    $(this).html(data);
                });
            });
        }

        function agregarIngrediente() {
            let div = $('<div class="ingrediente" style="display: flex; gap: 10px; margin-bottom: 10px;">');
            let select = $('<select name="ingrediente_id[]" required style="flex: 2;"></select>');
            let input = $('<input type="number" step="0.0001" name="cantidad[]" placeholder="Cantidad" required style="flex: 1;">');
            div.append(select).append(input);
            $("#ingredientes").append(div);
            cargarIngredientes();
        }

        $("#form_receta").on("submit", function(e) {
            e.preventDefault();
            $.post("guardar_receta.php", $(this).serialize(), function(resp) {
                alert(resp);
                $("#form_receta")[0].reset();
            });
        });

        $(document).ready(function() {
            cargarIngredientes();
        });
    </script>
</body>
</html>

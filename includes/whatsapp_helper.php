<?php
/**
 * Helper para enviar notificaciones de WhatsApp
 * Usa el servicio gratuito CallMeBot para uso personal.
 */

function enviarAlertaWhatsApp($nombreInsumo, $stockRestante) {
    // ================= CONFIGURACIÓN =================
    // 1. Añade el número de teléfono con código de país (ej. 593991234567)
    $phone = '593xxxxxxxxx'; 
    
    // 2. Añade tu API Key obtenida de CallMeBot
    $apikey = 'xxxxxx';
    // =================================================

    // Si no está configurado, no hacer nada
    if (strpos($phone, 'xxx') !== false || strpos($apikey, 'xxx') !== false) {
        error_log("WhatsApp no configurado: Faltan credenciales en whatsapp_helper.php");
        return false;
    }

    $mensaje = "⚠️ *ALERTA DE STOCK BAJO* ⚠️\n\n";
    $mensaje .= "El insumo *$nombreInsumo* se está agotando.\n";
    $mensaje .= "Stock restante: *$stockRestante*";

    // Codificar mensaje para URL
    $mensajeEncoded = urlencode($mensaje);
    
    // URL de la API
    $url = "https://api.callmebot.com/whatsapp.php?phone=$phone&text=$mensajeEncoded&apikey=$apikey";

    // Realizar la petición
    try {
        // Usar file_get_contents con timeout corto para no bloquear la venta si falla internet
        $ctx = stream_context_create(array(
            'http' => array(
                'timeout' => 2
            )
        ));
        $result = @file_get_contents($url, false, $ctx);
        
        if ($result === false) {
            error_log("Error enviando WhatsApp: Falló la conexión con CallMeBot");
            return false;
        }
        return true;
    } catch (Exception $e) {
        error_log("Error enviando WhatsApp: " . $e->getMessage());
        return false;
    }
}
?>

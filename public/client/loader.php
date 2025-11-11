<?php
/**
 * SCI-CHAT-SYSTEM - Widget Loader
 *
 * @file        loader.php
 * @description Este script se incrusta en los sitios de los clientes.
 *              Su única responsabilidad es cargar dinámicamente el HTML, CSS y JS
 *              necesarios para que el widget de chat funcione.
 *              Actúa como el punto de entrada para la aplicación del widget.
 */

// Establecer la cabecera para que el navegador lo trate como JavaScript ejecutable
header('Content-Type: application/javascript');
// Permitir que el script sea cacheado por el navegador para mejorar el rendimiento en visitas posteriores
header('Cache-Control: public, max-age=3600'); // Cachear por 1 hora

// --- Configuración ---
// La URL base donde se alojan tus assets (CSS/JS) y endpoints de la API.
// En una arquitectura distribuida, podrían ser dominios diferentes.
// Por ahora, asumimos que todo está bajo http://chat.test/

$base_url = 'http://' . $_SERVER['HTTP_HOST']; // Detecta el dominio automáticamente (ej. http://chat.test)

// --- Obtener el ID de la cuenta del cliente (de la URL del script) ---
$accountId = isset($_GET['account_id']) ? htmlspecialchars($_GET['account_id']) : null;

if (!$accountId) {
    // Si no hay ID de cuenta, no hacemos nada, solo logueamos un error en la consola del cliente.
    echo "console.error('SCI Chat Loader: Falta el ID de la cuenta (account_id).');";
    exit;
}

// --- Generar el HTML del Widget ---
// Usamos heredoc para crear un string multilínea con el HTML completo del widget.
// Toda esta estructura se inyectará en la página del cliente.
$widgetHTML = <<<HTML
<div class="chat-widget-container" id="chatWidgetContainer" style="display: none; position: fixed; bottom: 20px; right: 20px; z-index: 1000; width: 350px; height: 500px; background-color: white; border: 1px solid #ccc; box-shadow: 0 4px 12px rgba(0,0,0,0.15); border-radius: 8px; flex-direction: column;">
    <div class="chat-header">
        <span class="chat-title" id="chatWidgetTitle">Converse en línea</span>
        <button class="chat-close-btn" id="chatCloseBtn" aria-label="Cerrar chat">&times;</button>
    </div>
    <div class="chat-body">
        <div id="departmentSelectionView" style="display: flex; flex-direction: column; padding: 15px; width: 100%;">
            <p class="chat-intro">👋 ¡Hola! ¿Cómo podemos ayudarle? Por favor, seleccione un departamento:</p>
            <div id="departmentListContainer"><p>Cargando departamentos...</p></div>
            <p class="chat-privacy-notice-small" style="font-size: 0.75em; margin-top: auto; text-align: center;">Al continuar, acepta nuestra <a href="https://sci.com.ve/politica-privacidad.html" target="_blank">Política de Privacidad</a>.</p>
        </div>
        <div id="userInfoFormView" style="display: none; flex-direction: column; width: 100%;">
            <p class="chat-intro" id="userInfoFormIntro">Para participar en este chat con <strong>...</strong>, ingrese su informaci&oacute;n.</p>
            <form id="chatForm">
                <input type="hidden" id="selectedDepartmentId" name="department_id" value="">
                <div class="form-group"><label for="nombre">Nombre</label><input type="text" id="nombre" name="nombre" required></div>
                <div class="form-group"><label for="apellidos">Apellidos</label><input type="text" id="apellidos" name="apellidos" required></div>
                <div class="form-group"><label for="correo">Correo electrónico</label><input type="email" id="correo" name="correo" required></div>
                <div class="form-group"><label for="empresa">Empresa (Opcional)</label><input type="text" id="empresa" name="empresa"></div>
                <div class="chat-actions" style="margin-top: auto;">
                    <button type="button" class="btn btn-regresar" id="btnBackToDepartments">Regresar</button>
                    <button type="submit" class="btn btn-enviar" id="btnEnviarLeadForm" disabled>Iniciar Chat</button>
                </div>
            </form>
        </div>
        <div id="chatInterface" style="display: none; flex-direction: column; height: 100%;">
            <div class="chat-messages" id="chatMessages"></div>
            <div class="chat-input-area">
                <input type="text" id="chatMessageInput" placeholder="Escribe tu mensaje...">
                <button id="sendChatMessageBtn" class="btn btn-enviar-mensaje">Enviar</button>
            </div>
        </div>
    </div>
</div>
<button id="openChatButton" style="position: fixed; bottom: 20px; right: 20px; padding: 12px 20px; background-color: #007bff; color: white; border: none; border-radius: 25px; cursor: pointer; z-index: 999; font-size: 1em; box-shadow: 0 2px 10px rgba(0,0,0,0.2);">💬 Chatea con Nosotros</button>
HTML;

// Convertir el string HTML a un string JavaScript seguro para evitar problemas con comillas y saltos de línea.
$jsEscapedHTML = json_encode($widgetHTML);

// --- Generar el Script Final que se Envía al Navegador del Cliente ---
// Usamos una función autoejecutable (IIFE) para no contaminar el scope global de la página del cliente.
?>
(function() {
    if (document.getElementById('sci-chat-root-container')) return;

    const cssUrl = '<?php echo $base_url; ?>/public/client/assets/css/chat_widget.css';
    const scriptUrl = '<?php echo $base_url; ?>/public/client/assets/js/chat_widget.js';

    const cssLink = document.createElement('link');
    cssLink.rel = 'stylesheet';
    cssLink.href = cssUrl;
    document.head.appendChild(cssLink);

    const rootContainer = document.createElement('div');
    rootContainer.id = 'sci-chat-root-container';
    rootContainer.innerHTML = <?php echo $jsEscapedHTML; ?>;
    document.body.appendChild(rootContainer);

    const chatScript = document.createElement('script');
    chatScript.src = scriptUrl;
    chatScript.async = true;
    document.body.appendChild(chatScript);
})();
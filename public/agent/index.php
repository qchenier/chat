<!-- PRINCIPIO de agent_dashboard.php -->
<?php
session_start(); // PRIMERA LÍNEA ABSOLUTA

// Verificar si el agente está logueado
if (!isset($_SESSION['agent_id']) || empty($_SESSION['agent_id'])) {
    header("Location: agent_login"); // Ruta correcta a tu login
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Agente - SCI Chat</title>
    <link rel="stylesheet" href="css/agent_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="icon" href="images/x-icon/favicon.ico" type="image/x-icon">
</head>
<body>
    <div class="agent-panel-container">
        
        <!-- Barra Lateral de Navegación Principal (Extrema Izquierda) -->
        <nav class="main-navigation-sidebar collapsed" id="mainNavSidebar">
            <div class="logo-area">
                <!-- Puedes poner un logo pequeño o icono aquí -->
                <img src="images/logos/sci-logo-icono.png" alt="Logo" class="nav-logo"> <!-- Necesitarás un icono de logo -->
            </div>
            <ul class="nav-menu">
                <li class="nav-item active" data-section="chats" title="Chats">
                    <i class="fas fa-comments"></i> <span class="nav-text">Chats</span>
                </li>
                <li class="nav-item" data-section="analytics" title="Analíticas">
                    <i class="fas fa-chart-line"></i> <span class="nav-text">Analíticas</span>
                </li>
                <li class="nav-item" data-section="history" title="Historial">
                    <i class="fas fa-history"></i> <span class="nav-text">Historial</span>
                </li>
                <!-- Añadir más items según la referencia si es necesario -->
            </ul>
            <ul class="nav-menu bottom-menu">
                 <li class="nav-item" data-section="settings" title="Configuración">
                    <i class="fas fa-cog"></i> <span class="nav-text">Configuración</span>
                </li>
                <li class="nav-item" id="toggleNavButton" title="Expandir Menú">
                    <i class="fas fa-chevron-left"></i> <span class="nav-text">Minimizar</span>
                </li>
                <!-- NUEVO BOTÓN/ENLACE DE CERRAR SESIÓN -->
                <li class="nav-item" id="logoutButton" title="Cerrar Sesión">
                    <a href="agent_logout" style="color: inherit; text-decoration: none; display: flex; align-items: center; width: 100%;">
                        <i class="fas fa-sign-out-alt"></i> <span class="nav-text">Cerrar Sesión</span>
                    </a>
                </li>
            </ul>
        </nav>

        <!-- Contenido Principal del Dashboard (se ajustará si la barra lateral se minimiza) -->
        <div class="dashboard-content-area">
            <header class="main-header">
                <div class="header-left">
                    <h1 id="currentSectionTitle">Chats</h1> <!-- Cambiará dinámicamente -->
                </div>
                <div class="header-center">
                    <div class="search-bar">
                        <i class="fas fa-search"></i>
                        <input type="text" placeholder="Search or ask...">
                    </div>
                </div>
                <!-- En agent_dashboard.php, dentro de .main-header -->
                <div class="header-right">
                    <div class="header-icon-wrapper" id="notificationsIconWrapper" title="Notificaciones">
                        <i class="fas fa-bell header-icon"></i>
                        <!-- Aquí iría el menú desplegable de notificaciones más adelante -->
                    </div>
                    <div class="header-icon-wrapper profile-menu-wrapper" id="profileMenuIconWrapper" title="Mi Cuenta">
                        <!-- ======================================================= -->
                        <!-- INICIO DE LA MODIFICACIÓN                             -->
                        <!-- ======================================================= -->
                        <?php
                        // Verificar si hay una foto de perfil en la sesión y si el archivo existe
                        if (isset($_SESSION['agent_profile_picture']) && !empty($_SESSION['agent_profile_picture']) && file_exists($_SESSION['agent_profile_picture'])) {
                            // Si hay foto, mostrarla como una imagen
                            echo '<img src="' . htmlspecialchars($_SESSION['agent_profile_picture'], ENT_QUOTES, 'UTF-8') . '" alt="Foto de Perfil" class="header-icon profile-icon-img">';
                        } else {
                            // Si no hay foto, mostrar el icono por defecto
                            echo '<i class="fas fa-user-circle header-icon profile-icon"></i>';
                        }
                        ?>
                        <!-- ======================================================= -->
                        <!-- FIN DE LA MODIFICACIÓN                                -->
                        <!-- ======================================================= -->

                        <!--<i class="fas fa-user-circle header-icon profile-icon"></i>-->
                        <div class="dropdown-menu profile-dropdown" id="profileDropdownMenu" style="display: none;">
                            <div class="dropdown-header">
                                <strong><?php echo isset($_SESSION['agent_name']) ? htmlspecialchars($_SESSION['agent_name'], ENT_QUOTES, 'UTF-8') : 'Agente'; ?></strong>
                                <span class="subtle-text"><?php echo isset($_SESSION['agent_email']) ? htmlspecialchars($_SESSION['agent_email'], ENT_QUOTES, 'UTF-8') : ''; ?></span>
                            </div>
                            <a href="#" class="dropdown-item"><i class="fas fa-user-edit"></i> Mi Perfil</a>
                            <a href="#" class="dropdown-item"><i class="fas fa-cog"></i> Configuración</a>
                            <div class="dropdown-divider"></div>
                            <a href="agent_logout" class="dropdown-item"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a>
                        </div>
                    </div>
                </div>
            </header>

            <div class="main-layout-tres-columnas">
                <!-- Columna 1: Lista de Conversaciones (Sidebar Izquierdo Secundario) -->
                <aside class="conversations-list-sidebar">
                    <div class="conversations-header">
                        <h2>Conversaciones <span id="chatCount">(0)</span></h2>
                        <!-- SECCIÓN DE FILTROS ELIMINADA -->
                        <!--
                        <div class="conversation-filters">
                            <button class="filter-btn active" id="filterAll">Todos</button>
                            <button class="filter-btn" id="filterMyChats">Mis Chats</button>
                            <select id="sortFilter">
                                <option value="newest">Más Recientes</option>
                                <option value="oldest">Más Antiguos</option>
                            </select>
                        </div>
                        -->
                    </div>
                    <ul id="conversationsList">
                        <li class="loading-conversations">Cargando conversaciones...</li>
                    </ul>

                    <!-- ======================================================= -->
                    <!-- INICIO: Nueva Barra de Paginación de Conversaciones     -->
                    <!-- ======================================================= -->
                    <div class="conversations-pagination-bar" id="conversationsPagination">
                        <button class="pagination-btn" id="convoPageFirst" title="Primera Página"><i class="fas fa-angle-double-left"></i></button>
                        <button class="pagination-btn" id="convoPagePrev" title="Página Anterior"><i class="fas fa-angle-left"></i></button>
                        <span class="pagination-info" id="convoPageInfo">1 de 1</span>
                        <button class="pagination-btn" id="convoPageNext" title="Página Siguiente"><i class="fas fa-angle-right"></i></button>
                        <button class="pagination-btn" id="convoPageLast" title="Última Página"><i class="fas fa-angle-double-right"></i></button>
                        <select id="convoItemsPerPage" title="Items por página" style="margin-left: 10px;">
                            <option value="10" selected>10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                        </select>
                       <!-- <span id="convoTotalItems" style="margin-left: 10px; font-size: 0.85em; color: var(--subtle-text);">Total: 0</span>     -->
                    </div>
                    <!-- ======================================================= -->
                    <!-- FIN: Nueva Barra de Paginación de Conversaciones        -->
                    <!-- ======================================================= -->

                </aside>

                <!-- Columna 2: Área Principal del Chat -->
                <main class="chat-area-content"> <!-- Renombrado para claridad -->
                    <div id="chatInterfaceAgent" style="display: none;">
                        <div class="chat-area-header">
                            <h3 id="currentChatUserNameDisplay">Seleccione una conversación</h3>
                            <div class="chat-actions-menu">
                                <i class="fas fa-paperclip action-icon" title="Adjuntar"></i>
                                <i class="fas fa-random action-icon" id="initiateTransferBtn" title="Transferir Chat"></i>
                                <i class="fas fa-ellipsis-v action-icon" title="Más opciones"></i>
                            </div>
                        </div>
                        <div class="chat-messages-agent" id="chatMessagesAgent">
                            <!-- Mensajes del chat seleccionado -->
                        </div>
                        <div class="pre-chat-info" id="preChatInfoDisplay" style="display:none;">
                            <h4>Información Pre-Chat:</h4>
                            <p id="preChatName"></p>
                            <p id="preChatEmail"></p>
                            <p id="preChatCompany"></p>
                        </div>
                        <div class="chat-input-area-agent">
                            <textarea id="agentMessageInput" placeholder="Escribe tu respuesta..." rows="2"></textarea>
                            <div class="input-actions">
                                <!-- Iconos para respuestas guardadas, emojis, etc. -->
                                <i class="fas fa-hashtag action-icon-input" title="Respuestas guardadas"></i>
                                <i class="far fa-grin action-icon-input" title="Emojis"></i>
                                <button id="sendAgentMessageBtn"><i class="fas fa-paper-plane"></i> Enviar</button>
                            </div>
                        </div>
                    </div>
                    <div id="noChatSelected" class="placeholder-chat">
                        <i class="fas fa-comments placeholder-icon"></i>
                        <p>Seleccione o inicie una nueva conversación.</p>
                    </div>
                </main>

                <!-- Columna 3: Detalles del Cliente / Información Adicional -->
                <aside class="customer-details-sidebar" id="customerDetailsSidebar" style="display: none;">
                    <h4>Detalles del Cliente</h4>
                    <div class="customer-info-card">
                        <div class="customer-avatar-placeholder" id="customerAvatar">J</div>
                        <strong id="detailsCustomerName">Nombre Cliente</strong>
                        <span id="detailsCustomerEmail" class="subtle-text">email@cliente.com</span>
                        <div class="customer-location" id="customerLocationDisplay">
                            <i class="fas fa-map-marker-alt"></i> <span id="detailsCustomerLocationText">Ubicación desconocida</span>
                        </div>
                    </div>
                    <div class="details-section">
                        <h5>Descripción general</h5>
                        <p class="subtle-text" id="insightOverviewText">No hay información adicional.</p>
                    </div>
                    <div class="details-section">
                        <h5>Resumen de chat</h5>
                        <p class="subtle-text" id="chatSummaryText">Resumen no disponible.</p>
                    </div>
                    <!-- Más secciones de detalles si es necesario -->
                    <!-- En agent_dashboard.php, dentro de .customer-details-sidebar -->

                <!-- ... (secciones existentes: customer-info-card, customer-insight-overview, chat-summary-section) ... -->

                <div class="details-section transfer-chat-section" id="transferChatSection">
                    <h5>Transferir Chat</h5>
                    <div class="form-group-transfer">
                        <label for="transferDepartmentSelect">Transferir a Departamento:</label>
                        <select id="transferDepartmentSelect" name="transfer_department_id">
                            <option value="">Seleccione un departamento...</option>
                            <!-- Departamentos se cargarán aquí por JS -->
                        </select>
                    </div>
                    <div class="form-group-transfer" id="transferAgentSelectContainer" style="display: none;">
                        <label for="transferAgentSelect">Asignar a Agente Específico (Opcional):</label>
                        <select id="transferAgentSelect" name="transfer_agent_id">
                            <option value="">Cualquier agente disponible en el dpto.</option>
                            <!-- Agentes del departamento seleccionado se cargarán aquí -->
                        </select>
                    </div>
                    <!--
                    <div class="form-group-transfer" id="transferAgentSelectContainer" style="display: none;">
                        <label for="transferAgentSelect">Asignar a Agente Específico (Opcional):</label>
                        <select id="transferAgentSelect" name="transfer_agent_id">
                            <option value="">Cualquier agente disponible</option>                       
                        </select>
                    </div>
                    -->
                    <div class="form-group-transfer">
                        <label for="transferNote">Nota de Transferencia (Opcional):</label>
                        <textarea id="transferNote" name="transfer_note" rows="2" placeholder="Ej: Cliente necesita ayuda con impuestos..."></textarea>
                    </div>
                    <button id="confirmTransferBtn" class="btn-primary-action">Confirmar Transferencia</button>
                </div>
                </aside>
            </div> <!-- Fin de .main-layout-tres-columnas -->
        </div> <!-- Fin de .dashboard-content-area -->
    </div> <!-- Fin de .agent-panel-container -->

    <!-- En agent_dashboard.php -->
     
    <script>
        // Si $_SESSION['agent_id'] NO está seteado, imprimirá la palabra 'null' (sin comillas)
        // JavaScript interpretará esto como el valor null, no la cadena "null".
        const PHP_currentLoggedInAgentId = <?php echo isset($_SESSION['agent_id']) ? intval($_SESSION['agent_id']) : 'null'; ?>;
        const PHP_currentLoggedInAgentName = "<?php echo isset($_SESSION['agent_name']) ? htmlspecialchars($_SESSION['agent_name'], ENT_QUOTES, 'UTF-8') : 'Agente SCI'; ?>";
        console.log("PHP_TO_JS (dashboard): currentLoggedInAgentId =", PHP_currentLoggedInAgentId);
        console.log("PHP_TO_JS (dashboard): currentLoggedInAgentName =", PHP_currentLoggedInAgentName);
    </script>
    <!-- ... -->
    <script src="js/agent_script.js"></script> 
    
</body>
</html>
/**
 * SCI-CHAT-SYSTEM - Panel de Agente
 *
 * @file        agent_script.js
 * @author      [Tu Nombre/Empresa]
 * @copyright   Copyright (c) 2024, [Tu Nombre/Empresa]
 * @license     [Tu Licencia]
 * @version     2.2.0
 * @link        [URL de tu Proyecto]
 *
 * @description Lógica integral del lado del cliente para el dashboard de agentes.
 *              Este script es el corazón de la interfaz de agente y maneja:
 *              1.  Navegación principal y cambio dinámico entre secciones (Chats, Admin Agentes, etc.).
 *              2.  Carga de módulos de administración bajo demanda sin recargar la página.
 *              3.  Inicialización y gestión completa de la sección de CHATS:
 *                  - Carga y renderizado de conversaciones con paginación.
 *                  - Comunicación en tiempo real vía Server-Sent Events (SSE) para mensajes.
 *                  - Envío de mensajes y asignación/transferencia de chats.
 *                  - Gestión de la UI (paneles, notificaciones, etc.).
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // ===================================================================
    // --- CONFIGURACIÓN GLOBAL Y ESTADO DE LA APLICACIÓN ---
    // ===================================================================

    /**
     * @const {string} API_BASE_URL - URL base para los endpoints de la API.
     * Se asume que las rutas de la API son absolutas desde la raíz del sitio.
     */
    const API_BASE_URL = '/src/api'; 

    /**
     * @const {number} currentLoggedInAgentId - ID del agente logueado, obtenido de la variable JS global inyectada por PHP.
     */
    const currentLoggedInAgentId = typeof window.currentLoggedInAgentId !== 'undefined' ? window.currentLoggedInAgentId : null;
    
    /**
     * @const {string} agentName - Nombre del agente logueado, obtenido de la variable JS global inyectada por PHP.
     */
    const agentName = typeof window.currentLoggedInAgentName !== 'undefined' ? window.currentLoggedInAgentName : 'Agente';

    // --- Selectores del DOM Globales (elementos principales del layout) ---
    const mainNavSidebar = document.getElementById('mainNavSidebar');
    const toggleNavButton = document.getElementById('toggleNavButton');
    const currentSectionTitleH1 = document.getElementById('currentSectionTitle');
    const profileMenuIconWrapper = document.getElementById('profileMenuIconWrapper');
    const profileDropdownMenu = document.getElementById('profileDropdownMenu');

    // --- Selectores del DOM (Sección de CHATS) ---
    const conversationsListUl = document.getElementById('conversationsList');
    const chatCountSpan = document.getElementById('chatCount');
    const convoPaginationDiv = document.getElementById('conversationsPagination');
    const convoPageFirstBtn = document.getElementById('convoPageFirst');
    const convoPagePrevBtn = document.getElementById('convoPagePrev');
    const convoPageInfoSpan = document.getElementById('convoPageInfo');
    const convoPageNextBtn = document.getElementById('convoPageNext');
    const convoPageLastBtn = document.getElementById('convoPageLast');
    const convoItemsPerPageSelect = document.getElementById('convoItemsPerPage');
    const chatInterfaceAgentDiv = document.getElementById('chatInterfaceAgent');
    const noChatSelectedDiv = document.getElementById('noChatSelected');
    const currentChatUserNameDisplay = document.getElementById('currentChatUserNameDisplay');
    const chatMessagesAgentDiv = document.getElementById('chatMessagesAgent');
    const agentMessageInput = document.getElementById('agentMessageInput');
    const sendAgentMessageBtn = document.getElementById('sendAgentMessageBtn');
    const customerDetailsSidebar = document.getElementById('customerDetailsSidebar');
    const customerAvatarDiv = document.getElementById('customerAvatar');
    const detailsCustomerNameSpan = document.getElementById('detailsCustomerName');
    const detailsCustomerEmailSpan = document.getElementById('detailsCustomerEmail');
    const detailsCustomerLocationText = document.getElementById('detailsCustomerLocationText');
    const transferChatSectionDiv = document.getElementById('transferChatSection');
    const transferDepartmentSelect = document.getElementById('transferDepartmentSelect');
    const transferAgentSelectContainer = document.getElementById('transferAgentSelectContainer');
    const transferAgentSelect = document.getElementById('transferAgentSelect');
    const transferNoteInput = document.getElementById('transferNote');
    const confirmTransferBtn = document.getElementById('confirmTransferBtn');

    // --- Variables de Estado Globales ---
    const loadedScripts = {}; // Objeto para rastrear scripts ya cargados y evitar duplicados.
    const sectionTitles = {
        'chats': 'Chats',
        'analytics': 'Analíticas',
        'history': 'Historial',
        'settings': 'Configuración',
        'manage_agents': 'Gestionar Agentes',
        'manage_departments': 'Gestionar Departamentos'
    };

    // --- Variables de Estado (Sección de CHATS) ---
    let currentOpenConversationId = null; 
    let currentOpenUserEmail = null; 
    let currentOpenUserName = null; 
    let conversationsPollingInterval = null; // Intervalo para recargar la lista de chats.
    let agentEventSource = null; // Conexión SSE para mensajes en tiempo real.
    let isOpeningConversation = false; // Flag para evitar doble clic al abrir un chat.
    let currentPage = 1;
    let itemsPerPage = 10; // Valor inicial, se actualiza desde el select.
    let totalPages = 1;
    let agentOriginalTitle = document.title;
    let agentNotificationSound = null; 
    try { agentNotificationSound = new Audio('../sounds/agent_notification.mp3'); } catch (e) { console.warn("AGENTE: No se pudo cargar sonido de notificación.", e); }
    let agentUnreadMessages = 0;
    let isAgentWindowFocused = true;
    let agentIntervalIdTitle = null;
    window.onfocus = () => { isAgentWindowFocused = true; if (agentIntervalIdTitle) clearInterval(agentIntervalIdTitle); document.title = agentOriginalTitle; agentUnreadMessages = 0; };
    window.onblur = () => { isAgentWindowFocused = false; };

    // ===================================================================
    // --- FUNCIÓN DE ARRANQUE PRINCIPAL ---
    // ===================================================================

    /**
     * Orquesta la inicialización de todo el dashboard.
     * Verifica la autenticación y luego configura la navegación global y la sección de chats por defecto.
     */
    function initializeDashboard() {
        console.log("AGENT SCRIPT: DOMContentLoaded, iniciando dashboard para agent_id:", currentLoggedInAgentId);
        if (!currentLoggedInAgentId) {
            console.error("AGENT SCRIPT: ID de agente no válido. Deteniendo inicialización.");
            document.body.innerHTML = '<div style="text-align:center; padding:50px; font-size:1.2em; color:red;"><strong>Error de autenticación.</strong><br>No se ha podido identificar al agente. Por favor, <a href="agent_logout">cierre sesión</a> e intente de nuevo.</div>';
            return; 
        }

        initGlobalNav(); // Configura la barra de navegación principal y sus eventos.
        initProfileDropdown(); // Configura el menú desplegable del perfil.
        initializeChatSection(); // Prepara todos los listeners y la UI para la sección de chats.
    }

    // ===================================================================
    // --- SECCIÓN DE NAVEGACIÓN GLOBAL Y CARGA DINÁMICA DE MÓDULOS ---
    // ===================================================================

    /**
     * Inicializa la barra de navegación lateral, asignando los eventos de clic
     * para cambiar de sección o cargar módulos dinámicamente.
     */
    function initGlobalNav() {
        if (!mainNavSidebar || !toggleNavButton) return;
        const navItems = mainNavSidebar.querySelectorAll('.nav-item[data-section]');

        navItems.forEach(item => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                const sectionId = item.dataset.section;
                
                // Mapeo de acciones para cada sección
                const sectionMap = {
                    'chats': { handler: () => switchSection('chats') },
                    'manage_agents': { handler: () => loadSectionContent('manage_agents', '_partial_manage_agents.php', 'js/admin_manage_agents_logic.js') },
                    'manage_departments': { handler: () => loadSectionContent('manage_departments', '_partial_manage_departments.php', 'js/admin_manage_departments_logic.js') },
                    'analytics': { handler: () => switchSection('analytics') },
                    'history': { handler: () => switchSection('history') },
                    'settings': { handler: () => switchSection('settings') }
                };

                if (sectionMap[sectionId]) {
                    sectionMap[sectionId].handler();
                }
            });
        });

        // Lógica para expandir/colapsar el menú
        toggleNavButton.addEventListener('click', () => {
            mainNavSidebar.classList.toggle('collapsed');
            const icon = toggleNavButton.querySelector('i');
            if (icon) {
                const isCollapsed = mainNavSidebar.classList.contains('collapsed');
                icon.classList.toggle('fa-chevron-left', !isCollapsed);
                icon.classList.toggle('fa-chevron-right', isCollapsed);
                toggleNavButton.title = isCollapsed ? "Expandir Menú" : "Minimizar Menú";
            }
        });
    }

    /**
     * Muestra la sección de contenido solicitada y oculta las demás.
     * También gestiona el polling de chats para optimizar recursos.
     * @param {string} sectionId - El ID de la sección a mostrar (ej. 'chats', 'manage_agents').
     */
    function switchSection(sectionId) {
        // Detiene el polling de chats si nos movemos a otra sección
        if (sectionId !== 'chats' && conversationsPollingInterval) {
            clearInterval(conversationsPollingInterval);
            conversationsPollingInterval = null;
            console.log("Polling de conversaciones detenido (cambio de sección).");
        }

        // Reactiva el polling si volvemos a la sección de chats
        if (sectionId === 'chats' && !conversationsPollingInterval) {
            loadConversations(); // Carga inicial al volver a la sección
            conversationsPollingInterval = setInterval(loadConversations, 15000);
            console.log("Polling de conversaciones reactivado.");
        }
        
        // Muestra/oculta los contenedores de sección
        document.querySelectorAll('.content-section').forEach(section => {
            section.style.display = 'none';
        });

        const activeSection = document.getElementById(`section-${sectionId}`);
        if (activeSection) {
            activeSection.style.display = 'block';
            if(currentSectionTitleH1) currentSectionTitleH1.textContent = sectionTitles[sectionId] || 'Dashboard';
        }

        // Actualiza el estado 'active' en el menú de navegación
        document.querySelectorAll('.main-navigation-sidebar .nav-item').forEach(item => {
            item.classList.remove('active');
            if (item.dataset.section === sectionId) {
                item.classList.add('active');
            }
        });
    }

    /**
     * Carga el contenido HTML de un módulo desde un archivo parcial y su script de lógica asociado.
     * @param {string} sectionId - ID de la sección a cargar.
     * @param {string} partialUrl - Ruta al archivo PHP/HTML que contiene el fragmento de UI.
     * @param {string} scriptUrl - Ruta al archivo JS que contiene la lógica para ese fragmento.
     */
    async function loadSectionContent(sectionId, partialUrl, scriptUrl) {
        const targetContainer = document.getElementById(`section-${sectionId}`);
        
        // Si el contenido ya fue cargado, simplemente cambia de vista
        if (targetContainer.innerHTML.trim() !== '') {
            switchSection(sectionId);
            return;
        }

        try {
            targetContainer.innerHTML = '<div class="loading-placeholder"><i class="fas fa-spinner fa-spin"></i> Cargando módulo...</div>';
            switchSection(sectionId); // Cambia la vista inmediatamente para mostrar el "Cargando..."

            // Carga el fragmento HTML
            const response = await fetch(partialUrl);
            if (!response.ok) throw new Error(`Error HTTP ${response.status} al cargar ${partialUrl}`);
            const html = await response.text();
            targetContainer.innerHTML = html;

            // Carga y ejecuta el script asociado si no ha sido cargado antes
            if (scriptUrl && !loadedScripts[scriptUrl]) {
                const script = document.createElement('script');
                script.src = scriptUrl;
                script.defer = true;
                script.onload = () => {
                    console.log(`Script ${scriptUrl} cargado y listo.`);
                    loadedScripts[scriptUrl] = true;
                };
                document.body.appendChild(script);
            }
        } catch (error) {
            console.error(`Error cargando la sección ${sectionId}:`, error);
            targetContainer.innerHTML = `<div class="error-placeholder"><i class="fas fa-exclamation-triangle"></i> Error al cargar el módulo.</div>`;
        }
    }

    /**
     * Inicializa el menú desplegable del perfil de usuario.
     */
    function initProfileDropdown() {
        if (!profileMenuIconWrapper || !profileDropdownMenu) return;
        profileMenuIconWrapper.addEventListener('click', (event) => {
            event.stopPropagation();
            profileDropdownMenu.style.display = profileDropdownMenu.style.display === 'block' ? 'none' : 'block';
        });
        // Cierra el menú si se hace clic en cualquier otro lugar de la página
        window.addEventListener('click', () => {
            if (profileDropdownMenu.style.display === 'block') profileDropdownMenu.style.display = 'none';
        });
        profileDropdownMenu.addEventListener('click', (event) => event.stopPropagation());
    }

    // ===================================================================
    // --- SECCIÓN DE LÓGICA DE CHATS ---
    // ===================================================================

    /**
     * Prepara todos los listeners y la UI para la sección de chats.
     * Se llama una sola vez al cargar la página.
     */
    function initializeChatSection() {
        initializePagination();
        initializeTransferListeners();
        initializeMessageSending();
        
        if (document.querySelector('.content-section.active-section')?.id === 'section-chats') {
            loadConversations();
            if (!conversationsPollingInterval) {
                 conversationsPollingInterval = setInterval(loadConversations, 15000);
            }
        }
       
        if (customerDetailsSidebar) { 
            customerDetailsSidebar.style.display = 'block'; 
            resetCustomerDetails();
        }
        if (chatInterfaceAgentDiv) chatInterfaceAgentDiv.style.display = 'none'; 
        if (noChatSelectedDiv) noChatSelectedDiv.style.display = 'flex';
    }

    /**
     * Configura los listeners para los botones y el selector de la paginación.
     */
    function initializePagination() {
        if (!convoPaginationDiv) return;
        if (convoPageFirstBtn) convoPageFirstBtn.addEventListener('click', () => goToPage(1));
        if (convoPagePrevBtn) convoPagePrevBtn.addEventListener('click', () => goToPage(currentPage - 1));
        if (convoPageNextBtn) convoPageNextBtn.addEventListener('click', () => goToPage(currentPage + 1));
        if (convoPageLastBtn) convoPageLastBtn.addEventListener('click', () => goToPage(totalPages));
        if (convoItemsPerPageSelect) {
            itemsPerPage = parseInt(convoItemsPerPageSelect.value);
            convoItemsPerPageSelect.addEventListener('change', (event) => {
                itemsPerPage = parseInt(event.target.value);
                currentPage = 1; // Resetea a la primera página al cambiar el número de items
                loadConversations();
            });
        }
        updatePaginationControls();
    }

    /**
     * Configura los listeners para la funcionalidad de transferencia de chat.
     */
    function initializeTransferListeners() {
        if (confirmTransferBtn) confirmTransferBtn.addEventListener('click', handleConfirmTransfer);
        if (transferDepartmentSelect) {
            transferDepartmentSelect.addEventListener('change', function() {
                const selectedDeptId = this.value;
                if (selectedDeptId && transferAgentSelectContainer) {
                    populateTransferAgents(selectedDeptId);
                    transferAgentSelectContainer.style.display = 'block';
                } else if (transferAgentSelectContainer) {
                    transferAgentSelectContainer.style.display = 'none';
                    if (transferAgentSelect) transferAgentSelect.innerHTML = '<option value="">Cualquier agente disponible</option>';
                }
            });
        }
    }

    /**
     * Configura los listeners para el área de envío de mensajes.
     */
    function initializeMessageSending() {
        if (sendAgentMessageBtn && agentMessageInput) {
            sendAgentMessageBtn.addEventListener('click', sendAgentMessage);
            agentMessageInput.addEventListener('keypress', (e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendAgentMessage(); } });
            // Auto-resize del textarea
            agentMessageInput.addEventListener('input', function () { this.style.height = 'auto'; this.style.height = (this.scrollHeight) + 'px'; });
        }
    }

    /**
     * Obtiene la lista de conversaciones desde la API y las renderiza.
     */
    function loadConversations() {
        if (!currentLoggedInAgentId) return; 
        // Como eliminaste los filtros del HTML, la URL ya no los necesita.
        const fetchUrl = `${API_BASE_URL}/agent/get_agent_conversations.php?agent_id=${currentLoggedInAgentId}&page=${currentPage}&items_per_page=${itemsPerPage}`;
        fetch(fetchUrl) 
            .then(response => {
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                return response.json();
            })
            .then(data => {
                if (data.status === 'success') {
                    if(conversationsListUl) renderConversations(data.conversations);
                    if (data.pagination) {
                        currentPage = data.pagination.currentPage;
                        totalPages = data.pagination.totalPages;
                        if (chatCountSpan) chatCountSpan.textContent = `(${data.pagination.totalItems})`;
                        updatePaginationControls();
                    }
                } else {
                    if(conversationsListUl) conversationsListUl.innerHTML = `<li class="error-loading">${data.message}</li>`;
                    if (chatCountSpan) chatCountSpan.textContent = `(0)`;
                    totalPages = 1; currentPage = 1;
                    updatePaginationControls();
                }
            })
            .catch(error => {
                console.error('Error cargando conversaciones:', error);
                if(conversationsListUl) conversationsListUl.innerHTML = `<li class="error-loading">Error de red al cargar chats.</li>`;
            });
    }

    /**
     * Dibuja la lista de conversaciones en el panel izquierdo.
     * @param {Array} conversations - Array de objetos de conversación.
     */
    function renderConversations(conversations) {
        if (!conversationsListUl) return;
        conversationsListUl.innerHTML = ''; 
        if (conversations.length === 0) { 
            conversationsListUl.innerHTML = `<li class="no-conversations">No hay conversaciones activas.</li>`; 
            return; 
        }
        
        conversations.forEach(convo => {
            const li = document.createElement('li');
            li.dataset.conversationId = convo.id; // Usar un data- attribute específico
            li.classList.add('conversation-item');
            
            let statusText = convo.status ? (convo.status.charAt(0).toUpperCase() + convo.status.slice(1)).replace('_', ' ') : 'Desconocido';
            let lastMsgPreview = convo.last_message_preview ? convo.last_message_preview : '<i>Nuevo chat...</i>';
            if (lastMsgPreview.length > 25) lastMsgPreview = lastMsgPreview.substring(0, 22) + '...';
            let unreadIndicator = convo.unread_user_messages > 0 ? `<span class="unread-count">${convo.unread_user_messages}</span>` : '';
            
            let statusClass = '';
            if (convo.status === 'pending_agent') statusClass = 'status-pending';
            else if (convo.status === 'active') statusClass = 'status-active';

            li.innerHTML = `
                <div class="convo-main-info">
                    <span class="user-name">${convo.user_name || convo.user_email}</span>
                    ${unreadIndicator}
                </div>
                <div class="convo-secondary-info">
                    <span class="department-name">Dpto: ${convo.department_name || 'N/A'}</span>
                    <span class="convo-status ${statusClass}">${statusText}</span>
                </div>
                <span class="last-message-preview">${lastMsgPreview}</span>`;
            
            if (parseInt(convo.id) === currentOpenConversationId) {
                li.classList.add('active-conversation');
            }

            // Guardamos todo el objeto para fácil acceso
            li.onclick = () => { 
                // Almacenamos el objeto convo para no tener que parsear JSON en cada click
                handleConversationClick(convo); 
            }; 
            conversationsListUl.appendChild(li);
        });
    }

    /**
     * Manejador de clic para un item de la lista de conversaciones.
     * Decide si tomar un chat pendiente o abrir uno ya asignado.
     * @param {object} convo - El objeto de la conversación seleccionada.
     */
    function handleConversationClick(convo) {
        if (isOpeningConversation || currentOpenConversationId === parseInt(convo.id)) return;
        isOpeningConversation = true;
        
        document.querySelectorAll('#conversationsList li').forEach(item => item.classList.remove('active-conversation'));
        const liToActivate = conversationsListUl.querySelector(`[data-conversation-id="${convo.id}"]`);
        if (liToActivate) liToActivate.classList.add('active-conversation');

        const canTakeChat = convo.status === 'pending_agent' && convo.agent_id == null;
        const isMyChat = convo.agent_id != null && parseInt(convo.agent_id) === currentLoggedInAgentId;

        if (canTakeChat) {
            takeChat(convo);
        } else if (isMyChat) {
            setupConversationUI(convo);
        } else {
            alert(`Este chat está siendo atendido por ${convo.agent_name_assigned || 'otro agente'}.`);
            isOpeningConversation = false;
        }
    }
    
    /**
     * Llama a la API para asignarse un chat pendiente.
     * @param {object} convo - La conversación a tomar.
     */
    function takeChat(convo) { 
        console.log(`AGENT: Tomando chat ID: ${convo.id}`);
        const formData = new FormData();
        formData.append('conversation_id', convo.id); 
        formData.append('agent_id', currentLoggedInAgentId);
        fetch(`${API_BASE_URL}/agent/assign_agent_to_chat.php`, { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                setupConversationUI(convo); // Abre la UI del chat inmediatamente
                loadConversations(); // Refresca la lista de chats en segundo plano
            } else { 
                alert("Error al tomar chat: " + data.message); 
                loadConversations(); 
                isOpeningConversation = false;
            }
        })
        .catch(error => { 
            alert("Error de red al intentar tomar el chat."); 
            console.error('Error en fetch takeChat:', error); 
            isOpeningConversation = false;
        });
    }
        
    /**
     * Configura toda la interfaz (chat, detalles de cliente) para una conversación activa.
     * @param {object} convo - La conversación a mostrar.
     */
    function setupConversationUI(convo) {
        console.log(`AGENT: Configurando UI para conversación ID: ${convo.id}`);
        currentOpenConversationId = parseInt(convo.id); 
        currentOpenUserEmail = convo.user_email; 
        currentOpenUserName = convo.user_name || convo.user_email;

        chatInterfaceAgentDiv.style.display = 'flex';
        noChatSelectedDiv.style.display = 'none';
        
        // Panel de detalles del cliente
        customerDetailsSidebar.style.display = 'block';
        detailsCustomerNameSpan.textContent = currentOpenUserName;
        detailsCustomerEmailSpan.textContent = currentOpenUserEmail;
        customerAvatarDiv.textContent = currentOpenUserName ? currentOpenUserName.charAt(0).toUpperCase() : '?';
        detailsCustomerLocationText.textContent = "Ubicación desconocida"; 
        
        // Panel de transferencia
        transferChatSectionDiv.style.display = 'block';
        populateTransferDepartments();
        transferAgentSelectContainer.style.display = 'none';
        transferAgentSelect.innerHTML = '<option value="">Cualquier agente disponible</option>';
        transferNoteInput.value = '';
        
        currentChatUserNameDisplay.textContent = `Chat con ${currentOpenUserName}`;
        chatMessagesAgentDiv.innerHTML = '<div class="system-message"><i class="fas fa-spinner fa-spin"></i> Cargando historial...</div>';
        
        // Carga mensajes e inicia la comunicación en tiempo real
        loadInitialMessagesAndStartStream().finally(() => { isOpeningConversation = false; });
    }

    /**
     * Carga el historial de mensajes y luego inicia el stream SSE.
     * @returns {Promise}
     */
    function loadInitialMessagesAndStartStream() { 
        if (!currentOpenConversationId) return Promise.resolve();
        stopAgentMessageStream(); // Asegurarse de detener cualquier stream anterior

        return fetch(`${API_BASE_URL}/common/get_messages.php?conversation_id=${currentOpenConversationId}`)
            .then(response => response.json())
            .then(data => {
                chatMessagesAgentDiv.innerHTML = ''; 
                if (data.status === 'success') {
                    if (data.messages.length > 0) {
                        data.messages.forEach(msg => addMessageToAgentChat(msg.sender_type, msg.sender_name, msg.message, msg.timestamp));
                    } else { 
                        chatMessagesAgentDiv.innerHTML = '<div class="system-message">Aún no hay mensajes. ¡Saluda!</div>'; 
                    }
                } else { 
                    chatMessagesAgentDiv.innerHTML = `<div class="system-message error-loading">${data.message}</div>`; 
                }
                startAgentMessageStream(); 
            })
            .catch(error => { 
                chatMessagesAgentDiv.innerHTML = `<div class="system-message error-loading">Error de red al cargar mensajes.</div>`; 
                startAgentMessageStream(); // Intentar iniciar el stream de todas formas
                throw error;
            });
    }

    /**
     * Inicia la conexión Server-Sent Events (SSE) para recibir mensajes nuevos.
     */
    function startAgentMessageStream() { 
        if (!currentOpenConversationId) return; 
        if (agentEventSource) stopAgentMessageStream();

        const streamUrl = `${API_BASE_URL}/../streams/stream_messages.php?conversation_id=${currentOpenConversationId}`; 
        agentEventSource = new EventSource(streamUrl);
        agentEventSource.onopen = () => console.log("SSE AGENT: Conexión abierta para conversación", currentOpenConversationId);
        
        agentEventSource.addEventListener('new_message', (event) => {
            try {
                const msg = JSON.parse(event.data);
                addMessageToAgentChat(msg.sender_type, msg.sender_name, msg.message, msg.timestamp);
                
                // Notificación si la ventana no está en foco y el mensaje es del usuario
                if (!isAgentWindowFocused && msg.sender_type === 'user') { 
                    agentUnreadMessages++; 
                    showAgentTitleNotification(`(${agentUnreadMessages}) Nuevo Mensaje`);
                    if (agentNotificationSound && agentNotificationSound.play) { 
                        agentNotificationSound.play().catch(e => console.warn("El navegador bloqueó la reproducción automática de sonido.", e));
                    }
                }
            } catch (e) { console.error("SSE AGENT: Error parseando JSON del stream:", e); }
        });

        agentEventSource.onerror = () => { 
            console.error("SSE AGENT: Error de conexión con el stream."); 
            if (agentEventSource && agentEventSource.readyState === EventSource.CLOSED) {
                console.log("SSE AGENT: Conexión cerrada. Se podría intentar reconectar aquí."); 
            }
        };
    }
    
    /**
     * Cierra la conexión SSE actual.
     */
    function stopAgentMessageStream() { 
        if (agentEventSource) { 
            agentEventSource.close(); 
            agentEventSource = null; 
            console.log("SSE AGENT: Stream detenido."); 
        }
    }
    
    /**
     * Añade un nuevo mensaje a la ventana de chat.
     * @param {string} type - 'user', 'agent' o 'system'.
     * @param {string} senderName - Nombre del remitente.
     * @param {string} message - Contenido del mensaje.
     * @param {number} timestamp - Marca de tiempo UNIX del mensaje.
     */
    function addMessageToAgentChat(type, senderName, message, timestamp) { 
        if (!chatMessagesAgentDiv) return;
        const messageElement = document.createElement('div');
        messageElement.classList.add('chat-message', type); 

        if (type === 'system') {
            messageElement.innerHTML = `<i class="fas fa-info-circle"></i> ${message}`;
        } else {
            const senderNameElement = document.createElement('strong');
            senderNameElement.textContent = (type === 'agent') ? 'Tú' : (senderName || 'Usuario');
            
            const messageTextNode = document.createElement('span'); 
            messageTextNode.innerHTML = message.replace(/\n/g, '<br>'); // Soporte para saltos de línea
            
            const timeElement = document.createElement('span'); 
            timeElement.classList.add('message-time');
            if (timestamp) {
                const messageDate = new Date(timestamp * 1000); 
                const hours = messageDate.getHours(); 
                const minutes = messageDate.getMinutes();
                timeElement.textContent = `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`;
            }

            messageElement.appendChild(senderNameElement);
            messageElement.appendChild(messageTextNode);
            messageElement.appendChild(timeElement); 
        }
        
        chatMessagesAgentDiv.appendChild(messageElement);
        // Auto-scroll al final
        chatMessagesAgentDiv.scrollTop = chatMessagesAgentDiv.scrollHeight;
    }

    /**
     * Envía un mensaje a través de la API.
     */
    function sendAgentMessage() { 
        const messageText = agentMessageInput.value.trim();
        if (messageText === '' || !currentOpenConversationId) return;
        
        addMessageToAgentChat('agent', agentName, messageText, Math.floor(Date.now() / 1000)); 
        agentMessageInput.value = ''; 
        agentMessageInput.style.height = 'auto'; // Resetear altura
        agentMessageInput.focus();

        const messageData = new FormData();
        messageData.append('conversation_id', currentOpenConversationId); 
        messageData.append('sender_name', agentName); 
        messageData.append('sender_type', 'agent'); 
        messageData.append('message', messageText);
        fetch(`${API_BASE_URL}/common/send_message.php`, { method: 'POST', body: messageData })
            .then(response => response.json())
            .then(data => { 
                if (data.status !== 'success') { console.error('Error al enviar mensaje de agente:', data.message); } 
                else { loadConversations(); } // Refresca la lista para actualizar el "último mensaje"
            })
            .catch(error => { console.error('Error en fetch sendAgentMessage:', error); });
    }

    /**
     * Navega a una página específica de la lista de conversaciones.
     * @param {number} pageNumber - El número de página al que ir.
     */
    function goToPage(pageNumber) {
        if (pageNumber >= 1 && pageNumber <= totalPages && pageNumber !== currentPage) {
            currentPage = pageNumber;
            loadConversations();
        }
    }

    /**
     * Actualiza el estado (habilitado/deshabilitado) de los botones de paginación.
     */
    function updatePaginationControls() {
        if (!convoPaginationDiv) return;
        convoPageInfoSpan.textContent = `${currentPage} de ${totalPages}`;
        convoPageFirstBtn.disabled = (currentPage <= 1);
        convoPagePrevBtn.disabled = (currentPage <= 1);
        convoPageNextBtn.disabled = (currentPage >= totalPages);
        convoPageLastBtn.disabled = (currentPage >= totalPages);
    }

    /**
     * Obtiene y puebla la lista de departamentos para la transferencia.
     */
    function populateTransferDepartments() { 
        if (!transferDepartmentSelect) return;
        fetch(`${API_BASE_URL}/admin/get_departments.php?all=true`)
            .then(response => response.json())
            .then(data => {
                transferDepartmentSelect.innerHTML = '<option value="">Seleccione un departamento...</option>';
                if (data.status === 'success' && data.departments) {
                    data.departments.forEach(dept => {
                        const option = document.createElement('option'); 
                        option.value = dept.id; 
                        option.textContent = dept.name;
                        transferDepartmentSelect.appendChild(option);
                    });
                }
            })
            .catch(error => console.error("Error al cargar departamentos para transferencia:", error));
    }

    /**
     * Obtiene y puebla la lista de agentes de un departamento específico.
     * @param {number} departmentId - El ID del departamento.
     */
    function populateTransferAgents(departmentId) {
        if (!transferAgentSelect || !departmentId) return;
        transferAgentSelect.innerHTML = '<option value="">Cargando agentes...</option>'; 
        fetch(`${API_BASE_URL}/agent/get_department_agents.php?department_id=${departmentId}`)
            .then(response => response.json())
            .then(data => {
                transferAgentSelect.innerHTML = '<option value="">Cualquier agente disponible</option>'; 
                if (data.status === 'success' && data.agents) {
                    data.agents.forEach(agent => {
                        if (agent.id == currentLoggedInAgentId) return; // No mostrarse a sí mismo
                        const option = document.createElement('option');
                        option.value = agent.id; option.textContent = agent.name;
                        transferAgentSelect.appendChild(option);
                    });
                }
            })
            .catch(error => console.error("Error poblando agentes para transferencia:", error));
    }

    /**
     * Maneja la confirmación de la transferencia de un chat.
     */
    function handleConfirmTransfer() {
        if (!currentOpenConversationId) { alert("No hay chat activo para transferir."); return; }
        confirmTransferBtn.disabled = true; 
        confirmTransferBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Transfiriendo...';
        
        const targetDepartmentId = transferDepartmentSelect.value;
        const targetAgentId = transferAgentSelect ? transferAgentSelect.value : ""; 
        const note = transferNoteInput.value.trim();
        if (!targetDepartmentId) { 
            alert("Por favor, seleccione un departamento de destino."); 
            confirmTransferBtn.disabled = false; 
            confirmTransferBtn.textContent = "Confirmar Transferencia"; 
            return; 
        }

        const formData = new FormData();
        formData.append('conversation_id', currentOpenConversationId);
        formData.append('target_department_id', targetDepartmentId);
        if (targetAgentId) formData.append('target_agent_id', targetAgentId);
        if (note) formData.append('transfer_note', note);
        formData.append('transferring_agent_id', currentLoggedInAgentId);
        
        fetch(`${API_BASE_URL}/agent/transfer_chat.php`, { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                alert("Chat transferido exitosamente.");
                // Cierra la interfaz del chat actual
                chatInterfaceAgentDiv.style.display = 'none';
                noChatSelectedDiv.style.display = 'flex';
                resetCustomerDetails();
                currentOpenConversationId = null; 
                currentOpenUserEmail = null; 
                currentOpenUserName = null;
                stopAgentMessageStream(); 
                loadConversations(); 
            } else { 
                alert("Error al transferir: " + (data.message || "Error desconocido")); 
            }
        })
        .catch(error => { 
            alert("Error de red al intentar transferir."); 
            console.error('Error en fetch transferChat:', error); 
        })
        .finally(() => { 
            if(confirmTransferBtn) {
                confirmTransferBtn.disabled = false; 
                confirmTransferBtn.textContent = "Confirmar Transferencia";
            } 
        });
    }

    /**
     * Muestra una notificación parpadeante en el título de la página.
     * @param {string} newText - El texto a mostrar.
     */
    function showAgentTitleNotification(newText) { 
        if (agentIntervalIdTitle) clearInterval(agentIntervalIdTitle);
        let showNew = true; 
        document.title = newText; 
        agentIntervalIdTitle = setInterval(() => { 
            document.title = showNew ? agentOriginalTitle : newText; 
            showNew = !showNew; 
        }, 1200);
    }

    /**
     * Resetea el panel de detalles del cliente a su estado inicial.
     */
    function resetCustomerDetails() {
        detailsCustomerNameSpan.textContent = "Seleccione un Chat";
        detailsCustomerEmailSpan.textContent = " ";
        customerAvatarDiv.textContent = "-";
        detailsCustomerLocationText.textContent = "Información no disponible";
        transferChatSectionDiv.style.display = 'none';
    }

    // ===================================================================
    // --- PUNTO DE ENTRADA: INICIAR EL DASHBOARD ---
    // ===================================================================
    initializeDashboard();
});
/**
 * SCI-CHAT-SYSTEM - Panel de Agente
 *
 * @file        agent_script.js
 * @author      [Tu Nombre/Empresa]
 * @copyright   Copyright (c) 2024, [Tu Nombre/Empresa]
 * @license     [Tu Licencia]
 * @version     2.1.0
 * @link        [URL de tu Proyecto]
 *
 * @description Lógica del lado del cliente para el dashboard de agentes. Este script maneja
 *              la inicialización de la interfaz, la carga y renderizado de conversaciones,
 *              la comunicación en tiempo real para la recepción de mensajes vía Server-Sent Events (SSE),
 *              el envío de mensajes, y la gestión de la interacción del agente con la UI,
 *              incluyendo filtros, paginación y transferencia de chats.
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // ===================================================================
    // --- CONFIGURACIÓN INICIAL Y ESTADO DE LA APLICACIÓN ---
    // ===================================================================

    /**
     * @const {string} API_BASE_URL - La URL base para todos los endpoints de la API.
     * Facilita el cambio entre entornos de desarrollo (ruta relativa) y producción (dominio completo).
     * Se asume una estructura donde los scripts de la API están en subcarpetas relativas
     * a la ubicación de agent_dashboard.php.
     */
    const API_BASE_URL = '/src/api'; 

    /**
     * @const {URLSearchParams} urlParams - Objeto para leer los parámetros de la URL actual.
     */
    const urlParams = new URLSearchParams(window.location.search);

    /**
     * @const {number} currentLoggedInAgentId - El ID del agente actualmente logueado.
     * Se obtiene del parámetro 'agent_id' de la URL. Si no se proporciona, se usa 1 por defecto para pruebas.
     */
    const currentLoggedInAgentId = urlParams.get('agent_id') ? parseInt(urlParams.get('agent_id')) : 1;

    /**
     * @const {string} agentName - El nombre del agente que se usará al enviar mensajes.
     */
    const agentName = "Agente SCI"; // Placeholder, idealmente vendría de la sesión PHP.

    // --- Selectores del DOM ---
    const mainNavSidebar = document.getElementById('mainNavSidebar');
    const toggleNavButton = document.getElementById('toggleNavButton');
    const currentSectionTitleH1 = document.getElementById('currentSectionTitle');
    const profileMenuIconWrapper = document.getElementById('profileMenuIconWrapper');
    const profileDropdownMenu = document.getElementById('profileDropdownMenu');
    const conversationsListUl = document.getElementById('conversationsList');
    const chatCountSpan = document.getElementById('chatCount');
    const filterBtnAll = document.getElementById('filterAll');
    const filterBtnMyChats = document.getElementById('filterMyChats');
    const sortFilterSelect = document.getElementById('sortFilter');
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

    // --- Variables de Estado ---
    let currentOpenConversationId = null; 
    let currentOpenUserEmail = null; 
    let currentOpenUserName = null; 
    let conversationsPollingInterval;
    let agentEventSource = null; 
    let isOpeningConversation = false;
    let currentFilterType = 'all'; 
    let currentSortBy = 'newest';  
    let currentPage = 1;
    let itemsPerPage = 25; 
    let totalConversations = 0;
    let totalPages = 1;

    // --- Notificaciones ---
    let agentOriginalTitle = document.title;
    let agentNotificationSound = null; 
    const agentSoundFilePath = 'sounds/agent_notification.mp3'; // Ajustado a la estructura de assets
    try { agentNotificationSound = new Audio(agentSoundFilePath); } catch (e) { console.warn("AGENTE: No se pudo cargar sonido: " + agentSoundFilePath, e); }
    let agentUnreadMessages = 0;
    let isAgentWindowFocused = true;
    let agentIntervalIdTitle = null;
    window.onfocus = () => { isAgentWindowFocused = true; if (agentIntervalIdTitle) clearInterval(agentIntervalIdTitle); document.title = agentOriginalTitle; agentUnreadMessages = 0; };
    window.onblur = () => { isAgentWindowFocused = false; };

    // ===================================================================
    // --- FUNCIÓN DE ARRANQUE PRINCIPAL ---
    // ===================================================================

    /**
     * Inicializa todos los componentes y listeners del dashboard.
     */
    function initializeDashboard() {
        console.log("AGENT SCRIPT: DOMContentLoaded, iniciando dashboard para agent_id:", currentLoggedInAgentId);
        if (typeof currentLoggedInAgentId !== 'number' || isNaN(currentLoggedInAgentId)) {
            console.error("AGENT SCRIPT: ID de agente no válido. Deteniendo inicialización.");
            document.body.innerHTML = '<p style="text-align:center; padding:50px; font-size:1.2em;">Error de autenticación. Por favor, <a href="agent_login.php">inicie sesión</a>.</p>';
            return; 
        }
        initNavMenu();
        initProfileDropdown();
        initializeFilters(); 
        initializePagination();
        initializeTransferListeners();
        initializeMessageSending();
        loadConversations(); 
        conversationsPollingInterval = setInterval(loadConversations, 15000); 
        if (customerDetailsSidebar) { 
            customerDetailsSidebar.style.display = 'block'; 
            resetCustomerDetails();
        }
        if (chatInterfaceAgentDiv) chatInterfaceAgentDiv.style.display = 'none'; 
        if (noChatSelectedDiv) noChatSelectedDiv.style.display = 'flex'; 
    }

    // ===================================================================
    // --- SECCIÓN DE INICIALIZACIÓN DE COMPONENTES DE UI ---
    // ===================================================================
    
    function initNavMenu() { 
        if (!mainNavSidebar || !toggleNavButton) return;
        const navItems = mainNavSidebar.querySelectorAll('.nav-item[data-section]');
        navItems.forEach(item => {
            item.addEventListener('click', () => {
                navItems.forEach(i => i.classList.remove('active')); item.classList.add('active');
                if(currentSectionTitleH1) currentSectionTitleH1.textContent = item.title; 
            });
        });
        toggleNavButton.addEventListener('click', () => {
            mainNavSidebar.classList.toggle('collapsed'); const icon = toggleNavButton.querySelector('i');
            if (icon) { 
                if (mainNavSidebar.classList.contains('collapsed')) { icon.classList.remove('fa-chevron-left'); icon.classList.add('fa-chevron-right'); toggleNavButton.title = "Expandir Menú"; } 
                else { icon.classList.remove('fa-chevron-right'); icon.classList.add('fa-chevron-left'); toggleNavButton.title = "Minimizar Menú"; }
            }
        });
    }

    function initProfileDropdown() {
        if (!profileMenuIconWrapper || !profileDropdownMenu) return;
        profileMenuIconWrapper.addEventListener('click', (event) => {
            event.stopPropagation();
            profileDropdownMenu.style.display = profileDropdownMenu.style.display === 'block' ? 'none' : 'block';
        });
        window.addEventListener('click', () => {
            if (profileDropdownMenu.style.display === 'block') profileDropdownMenu.style.display = 'none';
        });
        profileDropdownMenu.addEventListener('click', (event) => event.stopPropagation());
    }

    function initializeFilters() {
        if (filterBtnAll) filterBtnAll.addEventListener('click', () => { currentPage = 1; currentFilterType = 'all'; setActiveFilterButton(filterBtnAll); loadConversations(); });
        if (filterBtnMyChats) filterBtnMyChats.addEventListener('click', () => { currentPage = 1; currentFilterType = 'my_chats'; setActiveFilterButton(filterBtnMyChats); loadConversations(); });
        if (sortFilterSelect) {
            sortFilterSelect.addEventListener('change', (event) => { currentPage = 1; currentSortBy = event.target.value; loadConversations(); });
            sortFilterSelect.value = currentSortBy;
        }
        setActiveFilterButton(filterBtnAll);
    }

    function setActiveFilterButton(activeButton) {
        if (filterBtnAll) filterBtnAll.classList.remove('active');
        if (filterBtnMyChats) filterBtnMyChats.classList.remove('active');
        if (activeButton) activeButton.classList.add('active');
    }

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
                currentPage = 1;
                loadConversations();
            });
        }
        updatePaginationControls();
    }

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

    function initializeMessageSending() {
        if (sendAgentMessageBtn && agentMessageInput) {
            sendAgentMessageBtn.addEventListener('click', sendAgentMessage);
            agentMessageInput.addEventListener('keypress', (e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendAgentMessage(); } });
            agentMessageInput.addEventListener('input', function () { this.style.height = 'auto'; this.style.height = (this.scrollHeight) + 'px'; });
        }
    }

    // ===================================================================
    // --- LÓGICA DE NEGOCIO Y MANEJO DE DATOS ---
    // ===================================================================

    function loadConversations() {
        if (!currentLoggedInAgentId) return; 
        const fetchUrl = `${API_BASE_URL}/agent/get_agent_conversations.php?agent_id=${currentLoggedInAgentId}&filter_type=${currentFilterType}&sort_by=${currentSortBy}&page=${currentPage}&items_per_page=${itemsPerPage}`;
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
                if(conversationsListUl) conversationsListUl.innerHTML = `<li class="error-loading">Error de red.</li>`;
            });
    }

    function renderConversations(conversations) {
        if (!conversationsListUl) return;
        const debouncedHandler = debounce(handleConversationClick, 300);
        conversationsListUl.innerHTML = ''; 
        if (conversations.length === 0) { conversationsListUl.innerHTML = `<li class="no-conversations">No hay conversaciones para atender.</li>`; return; }
        
        conversations.forEach(convo => {
            const li = document.createElement('li');
            li.dataset.convo = JSON.stringify(convo); // Guardar todo el objeto convo
            
            let statusText = convo.status ? (convo.status.charAt(0).toUpperCase() + convo.status.slice(1)) : 'Desconocido';
            if (statusText === 'Pending_agent') statusText = 'Pendiente';
            let lastMsgPreview = convo.last_message_preview ? convo.last_message_preview : '<i>Sin mensajes</i>';
            if (lastMsgPreview.length > 25) lastMsgPreview = lastMsgPreview.substring(0, 22) + '...';
            let unreadIndicator = convo.unread_user_messages > 0 ? `<span class="unread-count">${convo.unread_user_messages}</span>` : '';
            let pendingActionText = '';
            if (convo.status === 'pending_agent' && convo.agent_id == null) { 
                pendingActionText = ' <strong style="color: orange;">(¡Nuevo!)</strong>';
            } else if (convo.agent_id && parseInt(convo.agent_id) !== currentLoggedInAgentId && convo.status !== 'closed') { 
                pendingActionText = ` <em style="font-size:0.8em;">(Atendido por: ${convo.agent_name_assigned || 'Otro'})</em>`; 
            }

            li.innerHTML = `<span class="user-email">${convo.user_name || convo.user_email} ${unreadIndicator} ${pendingActionText}</span><span class="status-preview">Dpto: ${convo.department_name || 'N/A'} - Estado: ${statusText}</span><span class="last-message-preview">${lastMsgPreview}</span>`;
            
            if (parseInt(convo.id) === currentOpenConversationId) {
                li.classList.add('active-conversation');
            }

            li.addEventListener('click', () => debouncedHandler(convo));
            conversationsListUl.appendChild(li);
        });
    }

    function debounce(func, delay) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), delay);
        };
    }
    
    function handleConversationClick(convo) {
        if (isOpeningConversation || currentOpenConversationId === parseInt(convo.id)) {
            return;
        }
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
    
    function takeChat(convo) { 
        console.log(`AGENT: Tomando chat ID: ${convo.id}`);
        const formData = new FormData();
        formData.append('conversation_id', convo.id); 
        formData.append('agent_id', currentLoggedInAgentId);
        fetch(`${API_BASE_URL}/agent/assign_agent_to_chat.php`, { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                setupConversationUI(convo); 
                loadConversations(); 
            } else { 
                alert("Error al tomar chat: " + data.message); 
                loadConversations(); 
                isOpeningConversation = false;
            }
        })
        .catch(error => { 
            alert("Error de red al tomar chat."); 
            console.error('Error en fetch takeChat:', error); 
            isOpeningConversation = false;
        });
    }
        
    function setupConversationUI(convo) {
        console.log(`AGENT: Configurando UI para conversación ID: ${convo.id}`);
        currentOpenConversationId = parseInt(convo.id); 
        currentOpenUserEmail = convo.user_email; 
        currentOpenUserName = convo.user_name || convo.user_email;

        if (chatInterfaceAgentDiv) chatInterfaceAgentDiv.style.display = 'flex';
        if (noChatSelectedDiv) noChatSelectedDiv.style.display = 'none';
        
        if (customerDetailsSidebar) customerDetailsSidebar.style.display = 'block';
        if (detailsCustomerNameSpan) detailsCustomerNameSpan.textContent = currentOpenUserName;
        if (detailsCustomerEmailSpan) detailsCustomerEmailSpan.textContent = currentOpenUserEmail;
        if (customerAvatarDiv) { customerAvatarDiv.textContent = currentOpenUserName ? currentOpenUserName.charAt(0).toUpperCase() : '?'; }
        if (detailsCustomerLocationText) detailsCustomerLocationText.textContent = "Ubicación desconocida"; 
        
        if (transferChatSectionDiv) transferChatSectionDiv.style.display = 'block';
        if (transferDepartmentSelect) populateTransferDepartments();
        if (transferAgentSelectContainer) transferAgentSelectContainer.style.display = 'none';
        if (transferAgentSelect) transferAgentSelect.innerHTML = '<option value="">Cualquier agente disponible</option>';
        if (transferNoteInput) transferNoteInput.value = '';
        
        if (currentChatUserNameDisplay) currentChatUserNameDisplay.textContent = currentOpenUserName;
        if (chatMessagesAgentDiv) chatMessagesAgentDiv.innerHTML = '<p class="system-message">Cargando historial...</p>';
        
        stopAgentMessageStream(); 
        loadInitialMessagesAndStartStream()
            .finally(() => {
                isOpeningConversation = false;
            });
    }

    function loadInitialMessagesAndStartStream() { 
        if (!currentOpenUserEmail) return Promise.resolve();
        let initialLoadLastTimestamp = 0; 
        return fetch(`${API_BASE_URL}/common/get_messages.php?chat_id=${encodeURIComponent(currentOpenUserEmail)}&last_timestamp=0`)
            .then(response => response.json())
            .then(data => {
                if (chatMessagesAgentDiv) chatMessagesAgentDiv.innerHTML = ''; 
                if (data.status === 'success') {
                    if (data.messages.length > 0) {
                        data.messages.forEach(msg => { 
                            addMessageToAgentChat(msg.sender_type, msg.sender_name, msg.message, msg.timestamp); 
                            if (msg.timestamp > initialLoadLastTimestamp) { initialLoadLastTimestamp = msg.timestamp; } 
                        });
                    } else { if (chatMessagesAgentDiv) chatMessagesAgentDiv.innerHTML = '<p class="system-message">No hay mensajes.</p>'; }
                } else { if (chatMessagesAgentDiv) chatMessagesAgentDiv.innerHTML = `<p class="system-message error-loading">${data.message}</p>`; }
                startAgentMessageStream(initialLoadLastTimestamp); 
            })
            .catch(error => { if (chatMessagesAgentDiv) chatMessagesAgentDiv.innerHTML = `<p class="system-message error-loading">Error de red.</p>`; startAgentMessageStream(0); throw error;});
    }

    function startAgentMessageStream(initialLastTimestamp = 0) { 
        if (!currentOpenUserEmail) return; 
        if (agentEventSource) stopAgentMessageStream();
        const streamUrl = `${API_BASE_URL}../../streams/stream_messages.php?chat_id=${encodeURIComponent(currentOpenUserEmail)}&initial_ts=${initialLastTimestamp}`; 
        agentEventSource = new EventSource(streamUrl);
        agentEventSource.onopen = () => console.log("SSE AGENT: Conexión abierta para", currentOpenUserEmail);
        agentEventSource.addEventListener('new_message', (event) => {
            try {
                const msg = JSON.parse(event.data);
                if (msg.sender_type === 'user' || msg.sender_type === 'system') { // Mostrar mensajes de usuario y sistema
                    addMessageToAgentChat(msg.sender_type, msg.sender_name, msg.message, msg.timestamp);
                    if (!isAgentWindowFocused && msg.sender_type === 'user') { 
                        agentUnreadMessages++; 
                        showAgentTitleNotification(`(${agentUnreadMessages}) Nuevo Mensaje`);
                        if (agentNotificationSound && agentNotificationSound.play) { agentNotificationSound.play().catch(e => console.warn("Error sonido agente:", e));}
                    }
                }
            } catch (e) { console.error("SSE AGENT: Error parseando JSON:", e); }
        });
        agentEventSource.onerror = () => { console.error("SSE AGENT: Error conexión."); if (agentEventSource && agentEventSource.readyState === EventSource.CLOSED) console.log("SSE AGENT: Conexión cerrada."); };
    }
    
    function stopAgentMessageStream() { 
        if (agentEventSource) { agentEventSource.close(); agentEventSource = null; console.log("SSE AGENT: Stream detenido."); }
    }
    
    function addMessageToAgentChat(type, senderName, message, timestamp) { 
        if (!chatMessagesAgentDiv) return;
        const messageElement = document.createElement('div');
        messageElement.classList.add('chat-message', type); 

        if (type === 'system') {
            messageElement.innerHTML = `<i class="fas fa-info-circle"></i> ${message}`;
        } else {
            const senderNameElement = document.createElement('strong');
            senderNameElement.textContent = (type === 'agent' && senderName === agentName) ? 'Tú (Agente)' : (senderName || 'Usuario');
            messageElement.appendChild(senderNameElement);
            
            const messageTextNode = document.createElement('span'); 
            messageTextNode.textContent = message;
            messageElement.appendChild(messageTextNode);

            if (timestamp) {
                const timeElement = document.createElement('span'); 
                timeElement.classList.add('message-time');
                const messageDate = new Date(timestamp * 1000); 
                const hours = messageDate.getHours(); const minutes = messageDate.getMinutes(); const ampm = hours >= 12 ? 'p. m.' : 'a. m.';
                const formattedHours = hours % 12 || 12; const formattedMinutes = minutes < 10 ? '0' + minutes : minutes;
                timeElement.textContent = ` (${formattedHours}:${formattedMinutes} ${ampm})`; 
                messageElement.appendChild(timeElement); 
            }
        }
        
        chatMessagesAgentDiv.appendChild(messageElement);
        if (chatMessagesAgentDiv.scrollHeight > chatMessagesAgentDiv.clientHeight) { chatMessagesAgentDiv.scrollTop = chatMessagesAgentDiv.scrollHeight; }
    }

    function sendAgentMessage() { 
        const messageText = agentMessageInput.value.trim();
        if (messageText === '' || !currentOpenUserEmail) return;
        const currentMessageTimestamp = Math.floor(Date.now() / 1000); 
        addMessageToAgentChat('agent', agentName, messageText, currentMessageTimestamp); 
        agentMessageInput.value = ''; agentMessageInput.style.height = 'auto'; 
        const messageData = new FormData();
        messageData.append('chat_id', currentOpenUserEmail); 
        messageData.append('sender_name', agentName); 
        messageData.append('sender_type', 'agent'); messageData.append('message', messageText);
        fetch(`${API_BASE_URL}/common/send_message.php`, { method: 'POST', body: messageData })
        .then(response => response.json())
        .then(data => { if (data.status !== 'success') { console.error('Error al enviar mensaje de agente:', data.message); } else { loadConversations(); } })
        .catch(error => { console.error('Error en fetch sendAgentMessage:', error); });
    }

    function goToPage(pageNumber) {
        if (pageNumber >= 1 && pageNumber <= totalPages && pageNumber !== currentPage) {
            currentPage = pageNumber;
            loadConversations();
        }
    }

    function updatePaginationControls() {
        if (!convoPaginationDiv) return;
        if (convoPageInfoSpan) convoPageInfoSpan.textContent = `${currentPage} de ${totalPages}`;
        if (convoPageFirstBtn) convoPageFirstBtn.disabled = (currentPage <= 1);
        if (convoPagePrevBtn) convoPagePrevBtn.disabled = (currentPage <= 1);
        if (convoPageNextBtn) convoPageNextBtn.disabled = (currentPage >= totalPages);
        if (convoPageLastBtn) convoPageLastBtn.disabled = (currentPage >= totalPages);
    }

    function populateTransferDepartments() { 
        if (!transferDepartmentSelect) return;
        fetch(`${API_BASE_URL}/admin/get_departments.php?all=true`)
            .then(response => response.json())
            .then(data => {
                transferDepartmentSelect.innerHTML = '<option value="">Seleccione un departamento...</option>';
                if (data.status === 'success' && data.departments) {
                    data.departments.forEach(dept => {
                        const option = document.createElement('option'); option.value = dept.id; option.textContent = dept.name;
                        transferDepartmentSelect.appendChild(option);
                    });
                }
            })
            .catch(error => console.error("Error al cargar deptos para transfer:", error));
    }

    function populateTransferAgents(departmentId) {
        if (!transferAgentSelect || !departmentId) return;
        transferAgentSelect.innerHTML = '<option value="">Cargando agentes...</option>'; 
        fetch(`${API_BASE_URL}/agent/get_department_agents.php?department_id=${departmentId}`)
            .then(response => response.json())
            .then(data => {
                transferAgentSelect.innerHTML = '<option value="">Cualquier agente disponible</option>'; 
                if (data.status === 'success' && data.agents) {
                    data.agents.forEach(agent => {
                        if (agent.id == currentLoggedInAgentId) return; 
                        const option = document.createElement('option');
                        option.value = agent.id; option.textContent = agent.name;
                        transferAgentSelect.appendChild(option);
                    });
                }
            })
            .catch(error => console.error("Error poblando agentes de transfer:", error));
    }

    function handleConfirmTransfer() {
        if (!currentOpenConversationId) { alert("No hay chat activo para transferir."); return; }
        if (!transferDepartmentSelect || !transferNoteInput || !confirmTransferBtn) return;
        confirmTransferBtn.disabled = true; confirmTransferBtn.textContent = "Transfiriendo...";
        const targetDepartmentId = transferDepartmentSelect.value;
        const targetAgentId = transferAgentSelect ? transferAgentSelect.value : ""; 
        const note = transferNoteInput.value.trim();
        if (!targetDepartmentId) { alert("Seleccione un departamento destino."); confirmTransferBtn.disabled = false; confirmTransferBtn.textContent = "Confirmar Transferencia"; return; }
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
                if (chatInterfaceAgentDiv) chatInterfaceAgentDiv.style.display = 'none';
                if (noChatSelectedDiv) noChatSelectedDiv.style.display = 'flex';
                resetCustomerDetails();
                currentOpenConversationId = null; currentOpenUserEmail = null; currentOpenUserName = null;
                stopAgentMessageStream(); 
                loadConversations(); 
            } else { alert("Error al transferir: " + (data.message || "Error")); }
        })
        .catch(error => { alert("Error de red al transferir."); console.error('Error en fetch transferChat:', error); })
        .finally(() => { if(confirmTransferBtn) {confirmTransferBtn.disabled = false; confirmTransferBtn.textContent = "Confirmar Transferencia";} });
    }

    function showAgentTitleNotification(newText) { 
        if (agentIntervalIdTitle) clearInterval(agentIntervalIdTitle);
        let pageTitle = agentOriginalTitle; let showNew = true; document.title = newText; 
        agentIntervalIdTitle = setInterval(() => { document.title = showNew ? pageTitle : newText; showNew = !showNew; }, 1200);
    }

    function resetCustomerDetails() {
        if(detailsCustomerNameSpan) detailsCustomerNameSpan.textContent = "Seleccione un Chat";
        if(detailsCustomerEmailSpan) detailsCustomerEmailSpan.textContent = "-";
        if(customerAvatarDiv) customerAvatarDiv.textContent = "-";
        if(detailsCustomerLocationText) detailsCustomerLocationText.textContent = "Información no disponible";
        if (transferChatSectionDiv) transferChatSectionDiv.style.display = 'none';
    }

    // --- INICIAR EL DASHBOARD ---
    initializeDashboard();
});
/**
 * SCI-CHAT-SYSTEM - Widget de Chat para Clientes
 *
 * @file        chat_widget.js
 * @author      [Tu Nombre/Empresa]
 * @version     2.4.2
 * @description Lógica del widget de chat que se carga dinámicamente y se comunica
 *              con una API backend a través de URLs públicas y limpias.
 */

(function() {
    
    if (window.sciChatInitialized) {
        return;
    }
    window.sciChatInitialized = true;

    console.log("CHAT_WIDGET_JS: Script ejecutándose.");

    const API_BASE_URL = 'http://chat.test';

    const chatWidgetContainer = document.getElementById('chatWidgetContainer');
    const chatWidgetTitle = document.getElementById('chatWidgetTitle');
    const chatCloseBtn = document.getElementById('chatCloseBtn');
    const openChatButton = document.getElementById('openChatButton');
    const departmentSelectionView = document.getElementById('departmentSelectionView');
    const departmentListContainer = document.getElementById('departmentListContainer');
    const userInfoFormView = document.getElementById('userInfoFormView');
    const chatInterface = document.getElementById('chatInterface');
    const chatForm = document.getElementById('chatForm'); 
    const btnEnviarLeadForm = document.getElementById('btnEnviarLeadForm'); 
    const btnBackToDepartments = document.getElementById('btnBackToDepartments');
    const selectedDepartmentIdInput = document.getElementById('selectedDepartmentId'); 
    const userInfoFormIntroP = document.getElementById('userInfoFormIntro'); 
    const chatMessagesDiv = document.getElementById('chatMessages');
    const chatMessageInput = document.getElementById('chatMessageInput');
    const sendChatMessageBtn = document.getElementById('sendChatMessageBtn');

    if (!chatWidgetContainer || !openChatButton) {
        console.error("CHAT_WIDGET_JS: Elementos base no encontrados.");
        return;
    }

    let currentUserName = '', currentChatId = '', currentSelectedDepartmentId = null, currentSelectedDepartmentName = '', eventSource = null;
    let originalTitle = document.title;
    let notificationSound = null;
    const soundFilePath = `${API_BASE_URL}/public/client/assets/sounds/notification.mp3`; 
    try { notificationSound = new Audio(soundFilePath); } catch (e) { console.warn("CHAT_SCRIPT: No se pudo cargar sonido.", e); }
    let unreadMessages = 0, isWindowFocused = true, intervalIdTitle = null;
    window.onfocus = () => { isWindowFocused = true; if (intervalIdTitle) clearInterval(intervalIdTitle); document.title = originalTitle; unreadMessages = 0; };
    window.onblur = () => { isWindowFocused = false; };

    function showView(viewId) {
        const views = { departmentSelection: departmentSelectionView, userInfoForm: userInfoFormView, chatInterface: chatInterface };
        Object.values(views).forEach(view => view && (view.style.display = 'none'));
        if (views[viewId]) views[viewId].style.display = 'flex';
    }

    function resetToDepartmentSelection() {
        if (chatWidgetTitle) chatWidgetTitle.textContent = "Converse en línea";
        if(chatForm) chatForm.reset(); 
        if(departmentListContainer) loadDepartments(); 
        showView('departmentSelection');
    }

    function loadDepartments() {
        if (!departmentListContainer) return;
        departmentListContainer.innerHTML = '<p>Cargando departamentos...</p>';
        fetch(`${API_BASE_URL}/src/api/client/get_departments.php`)
            .then(response => { if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`); return response.json(); })
            .then(data => {
                departmentListContainer.innerHTML = ''; 
                if (data.status === 'success' && data.departments && data.departments.length > 0) {
                    data.departments.forEach(dept => {
                        const button = document.createElement('button');
                        button.className = 'department-button'; button.textContent = dept.name;
                        button.dataset.departmentId = dept.id; button.dataset.departmentName = dept.name; 
                        button.addEventListener('click', handleDepartmentSelection);
                        departmentListContainer.appendChild(button);
                    });
                } else { departmentListContainer.innerHTML = `<p>${data.message || 'No hay departamentos disponibles.'}</p>`; }
            })
            .catch(error => { console.error('Error cargando departamentos:', error); departmentListContainer.innerHTML = '<p>Error al cargar departamentos.</p>'; });
    }

    function handleDepartmentSelection(event) {
        const button = event.currentTarget; 
        currentSelectedDepartmentId = button.dataset.departmentId;
        currentSelectedDepartmentName = button.dataset.departmentName;
        if (selectedDepartmentIdInput) selectedDepartmentIdInput.value = currentSelectedDepartmentId;
        if (chatWidgetTitle) chatWidgetTitle.textContent = `Chat con ${currentSelectedDepartmentName}`;
        if (userInfoFormIntroP) userInfoFormIntroP.innerHTML = `Para participar en este chat con <strong>${currentSelectedDepartmentName}</strong>, ingrese su información de contacto:`;
        showView('userInfoForm');
        checkLeadFormValidity(); 
    }
    
    function checkLeadFormValidity() {
        if (!chatForm || !btnEnviarLeadForm) return;
        const requiredInputs = chatForm.querySelectorAll('input[required]:not([type="hidden"])');
        let allValid = true;
        requiredInputs.forEach(input => { if (input.value.trim() === '') { allValid = false; } });
        btnEnviarLeadForm.disabled = !allValid;
    }

    function handleLeadFormSubmit(event) {
        event.preventDefault();
        btnEnviarLeadForm.disabled = true; btnEnviarLeadForm.textContent = 'Enviando...';
        const formData = new FormData(chatForm); 
        currentUserName = ((formData.get('nombre') || '') + ' ' + (formData.get('apellidos') || '')).trim();
        currentChatId = formData.get('correo'); 
        fetch(`${API_BASE_URL}/src/api/client/process_chat_form.php`, { method: 'POST', body: formData })
        .then(response => { if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`); return response.json(); })
        .then(data => {
            if (data.status === 'success') {
                initializeChatInterface(currentUserName, currentSelectedDepartmentName);
            } else {
                alert('Error del servidor: ' + data.message);
                btnEnviarLeadForm.disabled = false; btnEnviarLeadForm.textContent = 'Iniciar Chat';
            }
        })
        .catch(error => {
            console.error('Error en fetch process_chat_form:', error);
            alert('Ocurrió un error al enviar su información.');
            btnEnviarLeadForm.disabled = false; btnEnviarLeadForm.textContent = 'Iniciar Chat';
        });
    }

    function initializeChatInterface(userName, departmentName) {
        if(chatWidgetTitle) chatWidgetTitle.textContent = `Chat: ${departmentName || 'Dpto.'} - ${userName || 'Usuario'}`;
        const systemMessageHTML = `<p class="chat-system-message">Conectado como <strong>${userName || 'Usuario'}</strong>. Esperando mensajes...</p>`;
        if (chatMessagesDiv) chatMessagesDiv.innerHTML = systemMessageHTML;
        showView('chatInterface');
        startMessageStream(); 
    }

    function sendChatMessage() {
        if (!chatMessageInput) return;
        const messageText = chatMessageInput.value.trim();
        if (messageText === '' || !currentChatId || !currentUserName) return;
        addMessageToChat('user', currentUserName, messageText);
        chatMessageInput.value = '';
        const messageData = new FormData();
        messageData.append('chat_id', currentChatId); 
        messageData.append('sender_name', currentUserName);
        messageData.append('sender_type', 'user');
        messageData.append('message', messageText);
        fetch(`${API_BASE_URL}/src/api/common/send_message.php`, { method: 'POST', body: messageData })
        .then(response => { if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`); return response.json(); })
        .then(data => { if (data.status !== 'success') console.error('CHAT_SCRIPT: Error al enviar (backend):', data.message); })
        .catch(error => console.error('CHAT_SCRIPT: Error en fetch sendChatMessage:', error));
    }

    function addMessageToChat(type, senderName, message) {
        if (!chatMessagesDiv) return;
        const systemMessage = chatMessagesDiv.querySelector('.chat-system-message');
        if (systemMessage) systemMessage.remove();
        const messageElement = document.createElement('div'); 
        messageElement.classList.add('chat-message', type); 
        if (type === 'user' || type === 'agent') {
            const senderNameElement = document.createElement('strong');
            senderNameElement.textContent = (type === 'user') ? 'Tú' : senderName; 
            messageElement.appendChild(senderNameElement);
        }
        const messageTextNode = document.createElement('span'); 
        messageTextNode.textContent = message;
        messageElement.appendChild(messageTextNode);
        chatMessagesDiv.appendChild(messageElement);
        if (chatMessagesDiv.scrollHeight > chatMessagesDiv.clientHeight) { chatMessagesDiv.scrollTop = chatMessagesDiv.scrollHeight; }
    }

    function startMessageStream() {
        if (!currentChatId) return;
        stopMessageStream();
        const streamUrl = `${API_BASE_URL}/src/streams/stream_messages.php?chat_id=${encodeURIComponent(currentChatId)}`;
        eventSource = new EventSource(streamUrl);
        eventSource.onopen = () => console.log("SSE: Conexión abierta para", currentChatId);
        eventSource.addEventListener('new_message', (event) => {
            try {
                const msg = JSON.parse(event.data);
                if (msg.sender_type === 'agent' || msg.sender_type === 'system') {
                    addMessageToChat(msg.sender_type, msg.sender_name, msg.message, msg.timestamp);
                    if (!isWindowFocused && msg.sender_type === 'agent') {
                        unreadMessages++; showTitleNotification(`(${unreadMessages}) Nuevo Mensaje`);
                        if (notificationSound && notificationSound.play) { notificationSound.play().catch(e => console.warn("Error sonido:", e));}
                    }
                }
            } catch (e) { console.error("SSE: Error parseando JSON:", e); }
        });
        eventSource.onerror = () => { console.error("SSE: Error en la conexión."); };
    }
    
    function stopMessageStream() {
        if (eventSource) { eventSource.close(); console.log("SSE: Stream detenido."); eventSource = null; }
    }

    function showTitleNotification(newText) {
        if (intervalIdTitle) clearInterval(intervalIdTitle);
        let pageTitle = originalTitle; let showNew = true; document.title = newText; 
        intervalIdTitle = setInterval(() => { document.title = showNew ? pageTitle : newText; showNew = !showNew; }, 1200);
    }
    
    openChatButton.addEventListener('click', () => {
        if (chatWidgetContainer) chatWidgetContainer.style.display = 'flex';
        openChatButton.style.display = 'none';
        resetToDepartmentSelection();
    });

    if (chatCloseBtn) {
        chatCloseBtn.addEventListener('click', () => {
            if(chatWidgetContainer) chatWidgetContainer.style.display = 'none';
            if (openChatButton) openChatButton.style.display = 'block';
            stopMessageStream();
            if (intervalIdTitle) clearInterval(intervalIdTitle);
            document.title = originalTitle;
            unreadMessages = 0;
            resetToDepartmentSelection();
        });
    }

    if (btnBackToDepartments) {
        btnBackToDepartments.addEventListener('click', resetToDepartmentSelection);
    }
    
    if (chatForm) {
        chatForm.addEventListener('submit', handleLeadFormSubmit);
        const inputsToValidate = chatForm.querySelectorAll('input[required]:not([type="hidden"])');
        inputsToValidate.forEach(input => input.addEventListener('input', checkLeadFormValidity));
        checkLeadFormValidity();
    }
    
    if (sendChatMessageBtn && chatMessageInput) {
        sendChatMessageBtn.addEventListener('click', sendChatMessage);
        chatMessageInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendChatMessage(); }
        });
    }

})();
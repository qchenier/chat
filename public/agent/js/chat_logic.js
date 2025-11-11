/**
 * SCI-CHAT-SYSTEM - Lógica del Módulo de Chats del Agente
 *
 * @file        chat_logic.js
 * @version     1.0.0
 * @description Contiene toda la lógica específica para la sección de "Chats".
 *              Es cargado dinámicamente por el script orquestador (agent_script.js).
 */

(function(window) {

    if (window.chatModuleInitialized) {
        console.log("CHAT_LOGIC: Módulo ya inicializado.");
        return;
    }
    
    const API_BASE_URL = ''; // Rutas relativas al dominio actual (ej. /api/...)
    const currentLoggedInAgentId = window.currentLoggedInAgentId;
    const agentName = window.currentLoggedInAgentName;

    // --- Selectores del DOM (específicos de este módulo) ---
    const chatModuleContainer = document.getElementById('section-chats');
    if (!chatModuleContainer) {
        console.error("CHAT_LOGIC: Contenedor principal #section-chats no encontrado. Abortando inicialización del módulo.");
        return;
    }
    const conversationsListUl = chatModuleContainer.querySelector('#conversationsList');
    // ... (El resto de tus selectores usando chatModuleContainer.querySelector)
    const chatCountSpan = chatModuleContainer.querySelector('#chatCount');
    const filterBtnAll = chatModuleContainer.querySelector('#filterAll');
    const filterBtnMyChats = chatModuleContainer.querySelector('#filterMyChats');
    const sortFilterSelect = chatModuleContainer.querySelector('#sortFilter');
    const convoPaginationDiv = chatModuleContainer.querySelector('#conversationsPagination');
    const convoPageFirstBtn = chatModuleContainer.querySelector('#convoPageFirst');
    const convoPagePrevBtn = chatModuleContainer.querySelector('#convoPagePrev');
    const convoPageInfoSpan = chatModuleContainer.querySelector('#convoPageInfo');
    const convoPageNextBtn = chatModuleContainer.querySelector('#convoPageNext');
    const convoPageLastBtn = chatModuleContainer.querySelector('#convoPageLast');
    const convoItemsPerPageSelect = chatModuleContainer.querySelector('#convoItemsPerPage');
    const chatInterfaceAgentDiv = chatModuleContainer.querySelector('#chatInterfaceAgent');
    const noChatSelectedDiv = chatModuleContainer.querySelector('#noChatSelected');
    const currentChatUserNameDisplay = chatModuleContainer.querySelector('#currentChatUserNameDisplay');
    const chatMessagesAgentDiv = chatModuleContainer.querySelector('#chatMessagesAgent');
    const agentMessageInput = chatModuleContainer.querySelector('#agentMessageInput');
    const sendAgentMessageBtn = chatModuleContainer.querySelector('#sendAgentMessageBtn');
    const customerDetailsSidebar = chatModuleContainer.querySelector('#customerDetailsSidebar');
    const detailsPlaceholder = chatModuleContainer.querySelector('#detailsPlaceholder');
    const detailsContent = chatModuleContainer.querySelector('#detailsContent');
    const customerAvatarDiv = chatModuleContainer.querySelector('#customerAvatar');
    const detailsCustomerNameSpan = chatModuleContainer.querySelector('#detailsCustomerName');
    const detailsCustomerEmailSpan = chatModuleContainer.querySelector('#detailsCustomerEmail');
    const detailsCustomerLocationText = chatModuleContainer.querySelector('#detailsCustomerLocationText');
    const transferChatSectionDiv = chatModuleContainer.querySelector('#transferChatSection');
    const transferDepartmentSelect = chatModuleContainer.querySelector('#transferDepartmentSelect');
    const transferAgentSelectContainer = chatModuleContainer.querySelector('#transferAgentSelectContainer');
    const transferAgentSelect = chatModuleContainer.querySelector('#transferAgentSelect');
    const transferNoteInput = chatModuleContainer.querySelector('#transferNote');
    const confirmTransferBtn = chatModuleContainer.querySelector('#confirmTransferBtn');

    // --- Estado del Módulo de Chats ---
    let state = {
        currentOpenConversationId: null, 
        currentOpenUserEmail: null,
        isOpeningConversation: false, 
        currentPage: 1,
        itemsPerPage: 10,
        totalPages: 1,
        totalConversations: 0,
        currentFilterType: 'all', 
        currentSortBy: 'newest',
        agentEventSource: null
    };

    /**
     * Función de inicialización para este módulo.
     */
    function init() {
        console.log("CHAT_LOGIC: Inicializando listeners y cargando datos...");
        initializeFilters();
        initializePagination();
        initializeTransferListeners();
        initializeMessageSending();
        loadConversations();
    }

    // El resto del archivo contiene todas las funciones de lógica de chat
    // que antes estaban en agent_script.js.
    // ...
    // Pega aquí el contenido COMPLETO de TODAS las funciones que te pasé
    // en la respuesta anterior (la que se titulaba agent_script.js (COMPLETO Y REESTRUCTURADO))
    // desde `function initializeFilters() {` hasta el final `resetCustomerDetails()}`
    
    // Al final, exporta la función de inicialización y marca el módulo como cargado.
    window.chatModule = { init };
    window.chatModuleInitialized = true;
    init(); // Auto-iniciar al cargar el script

})();
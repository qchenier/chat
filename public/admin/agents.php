<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <link rel="icon" href="images/favicon.ico" type="image/x-icon">
    <title>Gestionar Agentes</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; margin: 20px; background-color: #f4f7f9; }
        .container { max-width: 900px; margin: auto; background-color: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1, h2 { color: #333; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        label { display: block; margin-top: 15px; font-weight: bold; color: #555; }
        input[type="text"], input[type="email"], input[type="password"], input[type="file"], textarea { width: 100%; padding: 10px; margin-top: 5px; border-radius: 4px; border: 1px solid #ccc; box-sizing: border-box; }
        input[type="submit"] { background-color: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin-top: 20px; font-size: 1em; }
        input[type="submit"]:hover { background-color: #218838; }
        .message { padding: 15px; margin-bottom: 20px; border-radius: 4px; font-size: 1.1em; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; vertical-align: middle; }
        th { background-color: #e9ecef; }
        .department-checkboxes label { display: inline-block; margin-right: 15px; font-weight: normal;}
        .department-checkboxes input[type="checkbox"] { margin-right: 5px; }
        .profile-pic { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
        .actions-cell { text-align: center; white-space: nowrap; }
        .actions-cell button { padding: 5px 10px; font-size: 0.9em; margin: 0 5px; cursor: pointer; border-radius: 4px; border: 1px solid transparent; }
        .edit-btn { background-color: #ffc107; color: #212529; border-color: #ffc107; }
        .delete-btn { background-color: #dc3545; color: white; border-color: #dc3545; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Gestionar Agentes</h1>

        <h2 id="form-title">Crear Nuevo Agente</h2>
        <form action="/src/api/admin/create_agent.php" method="post" id="agentForm" enctype="multipart/form-data">
            <input type="hidden" name="agent_id" id="agent_id">

            <label for="agent_name">Nombre del Agente:</label>
            <input type="text" id="agent_name" name="agent_name" required>

            <label for="agent_email">Email del Agente:</label>
            <input type="email" id="agent_email" name="agent_email" required>
            
            <label for="agent_password">Contraseña:</label>
            <input type="password" id="agent_password" name="agent_password" placeholder="Dejar en blanco para no cambiar">
            <small>Al crear un nuevo agente, la contraseña es obligatoria.</small>

            <label for="agent_photo">Foto de Perfil (Opcional):</label>
            <input type="file" id="agent_photo" name="agent_photo" accept="image/png, image/jpeg, image/gif">

            <label>Asignar a Departamentos:</label>
            <div id="departmentsCheckboxes" class="department-checkboxes">
                Cargando departamentos...
            </div>

            <label class="form-checkbox" style="margin-top: 15px;">
                <input type="checkbox" id="is_active" name="is_active" value="1" checked>
                ¿Agente Activo?
            </label>

            <input type="submit" id="submitButton" value="Crear Agente">
            <button type="button" id="cancelEditButton" style="display:none; background-color:#6c757d; color:white; padding:10px 15px; border:none; border-radius:4px; cursor:pointer;">Cancelar Edición</button>
        </form>
        <hr style="margin: 30px 0;">

        <h2>Agentes Existentes</h2>
        <table id="agentsTable">
            <thead>
                <tr>
                    <th>Foto</th>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>Activo</th>
                    <th>Departamentos</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                </tbody>
        </table>
    </div>

    <script>
        const agentForm = document.getElementById('agentForm');
        const formTitle = document.getElementById('form-title');
        const agentIdInput = document.getElementById('agent_id');
        const agentNameInput = document.getElementById('agent_name');
        const agentEmailInput = document.getElementById('agent_email');
        const agentPasswordInput = document.getElementById('agent_password');
        const agentPhotoInput = document.getElementById('agent_photo');
        const departmentsCheckboxesDiv = document.getElementById('departmentsCheckboxes');
        const isActiveCheckbox = document.getElementById('is_active');
        const submitButton = document.getElementById('submitButton');
        const cancelEditButton = document.getElementById('cancelEditButton');
        const agentsTbody = document.getElementById('agentsTable').querySelector('tbody');

        let allDepartments = [];
        let allAgentsData = [];

        // Función para cargar departamentos y poblar los checkboxes
        function populateDepartmentsCheckboxes() {
            // Adjusted fetch URL for get_departments.php (relative to sci.test/public/)
            fetch('/src/api/admin/get_departments.php?all=true') // Obtener todos, no solo los públicos
                .then(response => response.json())
                .then(data => {
                    departmentsCheckboxesDiv.innerHTML = ''; 
                    if (data.status === 'success' && data.departments) {
                        allDepartments = data.departments;
                        allDepartments.forEach(dept => {
                            const label = document.createElement('label');
                            const checkbox = document.createElement('input');
                            checkbox.type = 'checkbox';
                            checkbox.name = 'agent_departments[]'; 
                            checkbox.value = dept.id;
                            label.appendChild(checkbox);
                            label.appendChild(document.createTextNode(' ' + dept.name));
                            departmentsCheckboxesDiv.appendChild(label);
                        });
                    } else {
                        departmentsCheckboxesDiv.textContent = 'No hay departamentos disponibles.';
                    }
                })
                .catch(error => console.error('Error cargando departamentos:', error));
        }

        // Función para cargar agentes y mostrarlos en la tabla
        function fetchAgents() {
            // Adjusted fetch URL for get_agents_with_departments.php (relative to sci.test/public/)
            fetch('/src/api/admin/get_agents_with_departments.php')
                .then(response => response.json())
                .then(data => {
                    agentsTbody.innerHTML = '';
                    if (data.status === 'success' && data.agents) {
                        allAgentsData = data.agents;
                        allAgentsData.forEach(agent => {
                            const row = agentsTbody.insertRow();
                            const photoCell = row.insertCell();
                            const profilePic = document.createElement('img');
                            // Adjusted profile picture path: assuming /public/assets/agent_photos/
                            profilePic.src = agent.profile_picture_path ? '/' + agent.profile_picture_path : 'images/agent_photos/default_avatar.png'; // Updated default avatar path
                            profilePic.alt = agent.name;
                            profilePic.className = 'profile-pic';
                            photoCell.appendChild(profilePic);

                            row.insertCell().textContent = agent.id;
                            row.insertCell().textContent = agent.name;
                            row.insertCell().textContent = agent.email;
                            row.insertCell().innerHTML = agent.is_active ? '✔️ Sí' : '❌ No';
                            row.insertCell().textContent = agent.department_names || 'Ninguno';
                            
                            const actionsCell = row.insertCell();
                            actionsCell.className = 'actions-cell';
                            
                            const editButton = document.createElement('button');
                            editButton.textContent = 'Editar';
                            editButton.className = 'edit-btn';
                            editButton.onclick = () => populateEditForm(agent.id);
                            
                            const deleteButton = document.createElement('button');
                            deleteButton.textContent = 'Eliminar';
                            deleteButton.className = 'delete-btn';
                            deleteButton.onclick = () => deleteAgent(agent.id, agent.name);
                            
                            actionsCell.appendChild(editButton);
                            actionsCell.appendChild(deleteButton);
                        });
                    } else {
                        agentsTbody.innerHTML = `<tr><td colspan="7" style="text-align:center;">${data.message || 'Error al cargar agentes.'}</td></tr>`;
                    }
                })
                .catch(error => console.error('Error en fetchAgents:', error));
        }

        // Poblar el formulario para editar un agente
        function populateEditForm(id) {
            const agent = allAgentsData.find(a => a.id == id);
            if (!agent) return;

            formTitle.textContent = 'Editar Agente';
            // Adjusted form action for update_agent.php (relative to sci.test/public/)
            agentForm.action = '/src/api/admin/update_agent.php';
            agentIdInput.value = agent.id;
            agentNameInput.value = agent.name;
            agentEmailInput.value = agent.email;
            agentPasswordInput.required = false; // La contraseña no es obligatoria al editar
            agentPasswordInput.placeholder = 'Dejar en blanco para no cambiar';
            isActiveCheckbox.checked = agent.is_active;

            // Desmarcar todos los checkboxes de departamento
            departmentsCheckboxesDiv.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
            
            // Marcar los departamentos a los que pertenece el agente
            /*if (agent.department_ids) {
                const agentDeptIds = agent.department_ids.split(',').map(Number);
                agentDeptIds.forEach(deptId => {
                    const checkbox = departmentsCheckboxesDiv.querySelector(`input[value="${deptId}"]`);
                    if (checkbox) checkbox.checked = true;
                });
            }*/

            // Marcar los departamentos a los que pertenece el agente
            // El backend ya envía 'department_ids' como un array de números.
            if (agent.department_ids && Array.isArray(agent.department_ids)) {
                agent.department_ids.forEach(deptId => {
                    const checkbox = departmentsCheckboxesDiv.querySelector(`input[type="checkbox"][value="${deptId}"]`);
                    if (checkbox) checkbox.checked = true;
                });
            }

            submitButton.value = 'Actualizar Agente';
            cancelEditButton.style.display = 'inline-block';
            window.scrollTo(0, 0);
        }

        // Resetear el formulario a su estado de "Crear"
        function resetForm() {
            formTitle.textContent = 'Crear Nuevo Agente';
            // Reset form action back to create_agent.php (relative to sci.test/public/)
            agentForm.action = '/src/api/admin/create_agent.php';
            agentForm.reset();
            agentIdInput.value = '';
            agentPasswordInput.required = true; // La contraseña es obligatoria al crear
            agentPasswordInput.placeholder = 'Contraseña (temporal o inicial)';
            isActiveCheckbox.checked = true; // Default to active when creating
            submitButton.value = 'Crear Agente';
            cancelEditButton.style.display = 'none';

            // Ensure checkboxes are reset for new agent creation
            departmentsCheckboxesDiv.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
        }

        cancelEditButton.addEventListener('click', resetForm);

        function deleteAgent(id, name) {
            if (confirm(`¿Estás seguro de que quieres eliminar al agente "${name}"?`)) {
                // Adjusted fetch URL for delete_agent.php (relative to sci.test/public/)
                fetch('/src/api/admin/delete_agent.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `agent_id=${id}`
                })
                .then(response => response.json())
                .then(data => {
                    alert(data.message);
                    if (data.status === 'success') {
                        fetchAgents();
                    }
                })
                .catch(error => {
                    console.error('Error al eliminar:', error);
                    alert('Error de red al intentar eliminar el agente.');
                });
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            populateDepartmentsCheckboxes();
            fetchAgents();
            // Code to display messages from URL parameters (already present and correct)
            const urlParams = new URLSearchParams(window.location.search);
            const message = urlParams.get('message');
            const messageType = urlParams.get('type');

            if (message) {
                const messageDiv = document.createElement('div');
                messageDiv.className = `message ${messageType}`;
                messageDiv.textContent = decodeURIComponent(message);
                document.querySelector('.container').insertBefore(messageDiv, document.querySelectorAll('h2')[0]);
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        });
    </script>
</body>
</html>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <link rel="icon" href="images/favicon.ico" type="image/x-icon">
    <title>Gestionar Departamentos</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; margin: 20px; background-color: #f4f7f9; }
        .container { max-width: 800px; margin: auto; background-color: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1, h2 { color: #333; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        label { display: block; margin-top: 15px; font-weight: bold; color: #555; }
        input[type="text"], textarea { width: 100%; padding: 10px; margin-top: 5px; border-radius: 4px; border: 1px solid #ccc; box-sizing: border-box; }
        input[type="submit"] { background-color: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin-top: 20px; font-size: 1em; }
        input[type="submit"]:hover { background-color: #0056b3; }
        .message { padding: 15px; margin-bottom: 20px; border-radius: 4px; font-size: 1.1em; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; vertical-align: middle; }
        th { background-color: #e9ecef; }
        .form-checkbox label { display: inline-block; margin-left: 10px; font-weight: normal; }
        .form-checkbox input { width: auto; margin-top: 0; vertical-align: middle; }
        .actions-cell { text-align: center; }
        .actions-cell button { padding: 5px 10px; font-size: 0.9em; margin: 0 5px; cursor: pointer; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Gestionar Departamentos</h1>

        <h2 id="form-title">Crear Nuevo Departamento</h2>
        <form action="/src/api/admin/create_department.php" method="post" id="departmentForm">
            <input type="hidden" name="dept_id" id="dept_id">
            
            <label for="dept_name">Nombre del Departamento:</label>
            <input type="text" id="dept_name" name="dept_name" required>

            <label for="dept_description">Descripción (Opcional):</label>
            <textarea id="dept_description" name="dept_description" rows="3"></textarea>

            <div class="form-checkbox" style="margin-top: 15px;">
                <input type="checkbox" id="is_public" name="is_public" checked>
                <label for="is_public">¿Es un departamento público? (Visible en el widget de chat)</label>
            </div>

            <input type="submit" id="submitButton" value="Crear Departamento">
            <button type="button" id="cancelEditButton" style="display:none; background-color:#6c757d; color:white; padding:10px 15px; border:none; border-radius:4px; cursor:pointer;">Cancelar Edición</button>
        </form>
        <hr style="margin: 30px 0;">

        <h2>Departamentos Existentes</h2>
        <table id="departmentsTable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>¿Público?</th>
                    <th>Creado en</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                </tbody>
        </table>
    </div>

    <script>
        const departmentForm = document.getElementById('departmentForm');
        const formTitle = document.getElementById('form-title');
        const deptIdInput = document.getElementById('dept_id');
        const deptNameInput = document.getElementById('dept_name');
        const deptDescriptionInput = document.getElementById('dept_description');
        const isPublicCheckbox = document.getElementById('is_public');
        const submitButton = document.getElementById('submitButton');
        const cancelEditButton = document.getElementById('cancelEditButton');
        const departmentsTbody = document.getElementById('departmentsTable').querySelector('tbody');

        let allDepartmentsData = []; // Caché de datos para editar

        function fetchDepartments() {
            // Pasamos ?all=true para obtener TODOS los departamentos (públicos e internos)
            // Adjust fetch URL for get_departments.php
            fetch('/src/api/admin/get_departments.php?all=true')
                .then(response => response.json())
                .then(data => {
                    departmentsTbody.innerHTML = '';
                    if (data.status === 'success' && data.departments) {
                        allDepartmentsData = data.departments; // Guardar datos en caché
                        if (allDepartmentsData.length > 0) {
                            allDepartmentsData.forEach(dept => {
                                const row = departmentsTbody.insertRow();
                                row.insertCell().textContent = dept.id;
                                row.insertCell().textContent = dept.name;
                                row.insertCell().innerHTML = dept.is_public ? '✔️ Sí' : '❌ No';
                                row.insertCell().textContent = new Date(dept.created_at).toLocaleString();
                                
                                const actionsCell = row.insertCell();
                                actionsCell.className = 'actions-cell';
                                
                                const editButton = document.createElement('button');
                                editButton.textContent = 'Editar';
                                editButton.onclick = () => populateEditForm(dept.id);
                                
                                const deleteButton = document.createElement('button');
                                deleteButton.textContent = 'Eliminar';
                                deleteButton.style.backgroundColor = '#dc3545';
                                deleteButton.style.color = 'white';
                                deleteButton.onclick = () => deleteDepartment(dept.id, dept.name);
                                
                                actionsCell.appendChild(editButton);
                                actionsCell.appendChild(deleteButton);
                            });
                        } else {
                            departmentsTbody.innerHTML = '<tr><td colspan="5" style="text-align:center;">No hay departamentos registrados.</td></tr>';
                        }
                    } else {
                        console.error('Error al cargar departamentos:', data.message);
                        departmentsTbody.innerHTML = '<tr><td colspan="5" style="text-align:center;">Error al cargar departamentos.</td></tr>';
                    }
                })
                .catch(error => {
                    console.error('Error en fetchDepartments:', error);
                    departmentsTbody.innerHTML = '<tr><td colspan="5" style="text-align:center;">Error de red al cargar departamentos.</td></tr>';
                });
        }

        function populateEditForm(id) {
            const dept = allDepartmentsData.find(d => d.id === id);
            if (!dept) return;

            formTitle.textContent = 'Editar Departamento';
            // Adjust form action for update_department.php
            departmentForm.action = '/src/api/admin/update_department.php';
            deptIdInput.value = dept.id;
            deptNameInput.value = dept.name;
            deptDescriptionInput.value = dept.description || '';
            isPublicCheckbox.checked = dept.is_public;
            submitButton.value = 'Actualizar Departamento';
            cancelEditButton.style.display = 'inline-block';
            window.scrollTo(0, 0); // Ir al inicio de la página para ver el formulario
        }

        function resetForm() {
            formTitle.textContent = 'Crear Nuevo Departamento';
            // Reset form action back to create_department.php
            departmentForm.action = '/src/api/admin/create_department.php';
            departmentForm.reset(); // Limpia los campos
            deptIdInput.value = ''; // Asegurar que el ID oculto esté vacío
            submitButton.value = 'Crear Departamento';
            cancelEditButton.style.display = 'none';
        }

        cancelEditButton.addEventListener('click', resetForm);

        function deleteDepartment(id, name) {
            if (confirm(`¿Estás seguro de que quieres eliminar el departamento "${name}"? Esta acción no se puede deshacer.`)) {
                // Adjust fetch URL for delete_department.php
                fetch('/src/api/admin/delete_department.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `dept_id=${id}`
                })
                .then(response => response.json())
                .then(data => {
                    alert(data.message);
                    if (data.status === 'success') {
                        fetchDepartments(); // Recargar la lista
                    }
                })
                .catch(error => {
                    console.error('Error al eliminar:', error);
                    alert('Error de red al intentar eliminar el departamento.');
                });
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            fetchDepartments();
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
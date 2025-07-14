<?php
$pageTitle = "Gestión de Usuarios";
require_once '../includes/header.php';
checkRole(['Administrador']);

// Manejo de creación, edición y eliminación de usuarios
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $username = trim($_POST['username'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $group_role = $_POST['group_role'] ?? '';
    $role_detail = $_POST['role_detail'] ?? '';
    $password = $_POST['password'] ?? '';
    $user_id = $_POST['user_id'] ?? null;

    try {
        if ($action === 'create') {
            if (empty($username) || empty($name) || empty($group_role) || empty($role_detail) || empty($password)) {
                throw new Exception('Por favor, complete todos los campos obligatorios.');
            }
            // Verificar si el usuario ya existe
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                throw new Exception('El nombre de usuario ya existe.');
            }
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password, name, email, group_role, role_detail) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$username, $hashedPassword, $name, $email, $group_role, $role_detail]);
            $success = 'Usuario creado exitosamente.';
        } elseif ($action === 'edit' && $user_id) {
            if (empty($username) || empty($name) || empty($group_role) || empty($role_detail)) {
                throw new Exception('Por favor, complete todos los campos obligatorios.');
            }
            // Actualizar usuario
            if (!empty($password)) {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET username = ?, name = ?, email = ?, group_role = ?, role_detail = ?, password = ? WHERE id = ?");
                $stmt->execute([$username, $name, $email, $group_role, $role_detail, $hashedPassword, $user_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET username = ?, name = ?, email = ?, group_role = ?, role_detail = ? WHERE id = ?");
                $stmt->execute([$username, $name, $email, $group_role, $role_detail, $user_id]);
            }
            $success = 'Usuario actualizado exitosamente.';
        } elseif ($action === 'delete' && $user_id) {
            // Eliminar usuario
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $success = 'Usuario eliminado exitosamente.';
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Obtener lista de usuarios
$stmt = $pdo->query("SELECT * FROM users WHERE active = 1 ORDER BY created_at DESC");
$users = $stmt->fetchAll();

$groups = ['Administrador', 'Will', 'Spare Parts', 'Global'];
$roles = [
    'Administrador' => ['Admin'],
    'Will' => ['Técnico', 'Supervisor', 'Almacenero'],
    'Spare Parts' => ['Vendedor'],
    'Global' => ['Motorizado']
];
?>

<div class="row">
    <div class="col-12">
        <div class="page-header">
            <h1 class="page-title">Gestión de Usuarios</h1>
            <p class="page-subtitle">Crear, editar y eliminar usuarios del sistema</p>
        </div>
    </div>
</div>

<?php if ($error): ?>
<div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>
<?php if ($success): ?>
<div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<!-- Formulario de creación/edición -->
<div class="card mb-4">
    <div class="card-body">
        <form method="POST" id="userForm">
            <input type="hidden" name="action" id="formAction" value="create">
            <input type="hidden" name="user_id" id="userId" value="">
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="username" class="form-label">Usuario *</label>
                    <input type="text" class="form-control" id="username" name="username" required>
                </div>
                <div class="col-md-4">
                    <label for="name" class="form-label">Nombre Completo *</label>
                    <input type="text" class="form-control" id="name" name="name" required>
                </div>
                <div class="col-md-4">
                    <label for="email" class="form-label">Correo Electrónico</label>
                    <input type="email" class="form-control" id="email" name="email">
                </div>
                <div class="col-md-4">
                    <label for="group_role" class="form-label">Grupo *</label>
                    <select class="form-select" id="group_role" name="group_role" required>
                        <option value="" disabled selected>Seleccione un grupo</option>
                        <?php foreach ($groups as $group): ?>
                        <option value="<?php echo $group; ?>"><?php echo $group; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="role_detail" class="form-label">Rol *</label>
                    <select class="form-select" id="role_detail" name="role_detail" required>
                        <option value="" disabled selected>Seleccione un rol</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="password" class="form-label">Contraseña <small>(Solo para crear o cambiar)</small></label>
                    <input type="password" class="form-control" id="password" name="password" autocomplete="new-password">
                </div>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-primary">Guardar Usuario</button>
                <button type="button" class="btn btn-secondary" id="cancelEdit" style="display:none;">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<!-- Tabla de usuarios -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Usuario</th>
                        <th>Nombre</th>
                        <th>Correo</th>
                        <th>Grupo</th>
                        <th>Rol</th>
                        <th>Creado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo $user['id']; ?></td>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td><?php echo htmlspecialchars($user['name']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><?php echo htmlspecialchars($user['group_role']); ?></td>
                        <td><?php echo htmlspecialchars($user['role_detail']); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                        <td>
                            <button class="btn btn-sm btn-warning editUserBtn" data-user='<?php echo json_encode($user); ?>'>Editar</button>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar usuario?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    const roles = <?php echo json_encode($roles); ?>;
    const groupSelect = document.getElementById('group_role');
    const roleSelect = document.getElementById('role_detail');
    const formAction = document.getElementById('formAction');
    const userIdInput = document.getElementById('userId');
    const userForm = document.getElementById('userForm');
    const cancelEditBtn = document.getElementById('cancelEdit');

    function populateRoles(group) {
        roleSelect.innerHTML = '<option value="" disabled selected>Seleccione un rol</option>';
        if (roles[group]) {
            roles[group].forEach(role => {
                const option = document.createElement('option');
                option.value = role;
                option.textContent = role;
                roleSelect.appendChild(option);
            });
        }
    }

    groupSelect.addEventListener('change', () => {
        populateRoles(groupSelect.value);
    });

    document.querySelectorAll('.editUserBtn').forEach(button => {
        button.addEventListener('click', () => {
            const user = JSON.parse(button.getAttribute('data-user'));
            formAction.value = 'edit';
            userIdInput.value = user.id;
            userForm.username.value = user.username;
            userForm.name.value = user.name;
            userForm.email.value = user.email;
            groupSelect.value = user.group_role;
            populateRoles(user.group_role);
            roleSelect.value = user.role_detail;
            userForm.password.value = '';
            cancelEditBtn.style.display = 'inline-block';
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    });

    cancelEditBtn.addEventListener('click', () => {
        formAction.value = 'create';
        userIdInput.value = '';
        userForm.reset();
        roleSelect.innerHTML = '<option value="" disabled selected>Seleccione un rol</option>';
        cancelEditBtn.style.display = 'none';
    });
</script>

<?php require_once '../includes/footer.php'; ?>

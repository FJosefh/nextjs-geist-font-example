<?php
$pageTitle = "Órdenes de Servicio (RS)";
require_once '../includes/header.php';
checkRole(['Will']);

$error = '';
$success = '';

// Manejo de creación y actualización de órdenes RS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $rsId = $_POST['rs_id'] ?? null;
    $rsNumber = $_POST['rs_number'] ?? '';
    $type = $_POST['type'] ?? '';
    $technicianId = $_POST['technician_id'] ?? '';
    $status = $_POST['status'] ?? 'Pendiente';
    $description = $_POST['description'] ?? '';

    try {
        if ($action === 'create') {
            if (empty($rsNumber) || empty($type) || empty($technicianId)) {
                throw new Exception('Complete todos los campos obligatorios.');
            }
            $stmt = $pdo->prepare("INSERT INTO rs_orders (rs_number, type, technician_id, status, description) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$rsNumber, $type, $technicianId, $status, $description]);
            $success = 'Orden RS creada exitosamente.';
        } elseif ($action === 'update' && $rsId) {
            $stmt = $pdo->prepare("UPDATE rs_orders SET type = ?, technician_id = ?, status = ?, description = ? WHERE id = ?");
            $stmt->execute([$type, $technicianId, $status, $description, $rsId]);
            $success = 'Orden RS actualizada exitosamente.';
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Obtener lista de órdenes RS
$stmt = $pdo->query("SELECT rs.*, u.name as technician_name FROM rs_orders rs LEFT JOIN users u ON rs.technician_id = u.id ORDER BY rs.created_at DESC");
$rsOrders = $stmt->fetchAll();

// Obtener técnicos para asignación
$stmt = $pdo->prepare("SELECT id, name FROM users WHERE group_role = 'Will' AND role_detail IN ('Técnico', 'Supervisor', 'Almacenero') AND active = 1 ORDER BY name ASC");
$stmt->execute();
$technicians = $stmt->fetchAll();

?>

<div class="row">
    <div class="col-12">
        <div class="page-header">
            <h1 class="page-title">Órdenes de Servicio (RS)</h1>
            <p class="page-subtitle">Crear y gestionar órdenes de servicio</p>
        </div>
    </div>
</div>

<?php if ($error): ?>
<div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>
<?php if ($success): ?>
<div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<!-- Formulario de creación -->
<div class="card mb-4">
    <div class="card-body">
        <form method="POST" id="rsForm">
            <input type="hidden" name="action" value="create">
            <div class="row g-3">
                <div class="col-md-3">
                    <label for="rs_number" class="form-label">Número RS *</label>
                    <input type="text" class="form-control" id="rs_number" name="rs_number" required>
                </div>
                <div class="col-md-3">
                    <label for="type" class="form-label">Tipo *</label>
                    <select class="form-select" id="type" name="type" required>
                        <option value="" disabled selected>Seleccione tipo</option>
                        <option value="Instalación">Instalación</option>
                        <option value="Prueba">Prueba</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="technician_id" class="form-label">Técnico *</label>
                    <select class="form-select" id="technician_id" name="technician_id" required>
                        <option value="" disabled selected>Seleccione técnico</option>
                        <?php foreach ($technicians as $tech): ?>
                        <option value="<?php echo $tech['id']; ?>"><?php echo htmlspecialchars($tech['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label">Estado</label>
                    <select class="form-select" id="status" name="status">
                        <option value="Pendiente" selected>Pendiente</option>
                        <option value="En Proceso">En Proceso</option>
                        <option value="Completada">Completada</option>
                        <option value="Cancelada">Cancelada</option>
                    </select>
                </div>
                <div class="col-12">
                    <label for="description" class="form-label">Descripción</label>
                    <textarea class="form-control" id="description" name="description" rows="2"></textarea>
                </div>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-primary">Crear Orden RS</button>
            </div>
        </form>
    </div>
</div>

<!-- Tabla de órdenes RS -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead>
                    <tr>
                        <th>Número RS</th>
                        <th>Tipo</th>
                        <th>Técnico</th>
                        <th>Estado</th>
                        <th>Descripción</th>
                        <th>Creado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rsOrders as $order): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($order['rs_number']); ?></td>
                        <td><?php echo htmlspecialchars($order['type']); ?></td>
                        <td><?php echo htmlspecialchars($order['technician_name']); ?></td>
                        <td>
                            <span class="badge 
                                <?php 
                                switch ($order['status']) {
                                    case 'Pendiente': echo 'bg-warning'; break;
                                    case 'En Proceso': echo 'bg-info'; break;
                                    case 'Completada': echo 'bg-success'; break;
                                    case 'Cancelada': echo 'bg-danger'; break;
                                    default: echo 'bg-secondary';
                                }
                                ?>">
                                <?php echo htmlspecialchars($order['status']); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($order['description']); ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

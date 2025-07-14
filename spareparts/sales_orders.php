<?php
$pageTitle = "Órdenes de Venta";
require_once '../includes/header.php';
checkRole(['Spare Parts']);

$error = '';
$success = '';

// Manejo de creación y actualización de órdenes de venta
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $orderId = $_POST['order_id'] ?? null;
    $orderNumber = $_POST['order_number'] ?? '';
    $customerName = $_POST['customer_name'] ?? '';
    $status = $_POST['status'] ?? 'Pendiente';
    $sellerId = $currentUser['id'];

    try {
        if ($action === 'create') {
            if (empty($orderNumber)) {
                throw new Exception('El número de orden es obligatorio.');
            }
            $stmt = $pdo->prepare("INSERT INTO sales_orders (order_number, seller_id, customer_name, status) VALUES (?, ?, ?, ?)");
            $stmt->execute([$orderNumber, $sellerId, $customerName, $status]);
            $success = 'Orden de venta creada exitosamente.';
        } elseif ($action === 'update' && $orderId) {
            $stmt = $pdo->prepare("UPDATE sales_orders SET customer_name = ?, status = ? WHERE id = ?");
            $stmt->execute([$customerName, $status, $orderId]);
            $success = 'Orden de venta actualizada exitosamente.';
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Obtener órdenes de venta del vendedor actual
$stmt = $pdo->prepare("SELECT * FROM sales_orders WHERE seller_id = ? ORDER BY created_at DESC");
$stmt->execute([$currentUser['id']]);
$salesOrders = $stmt->fetchAll();

?>

<div class="row">
    <div class="col-12">
        <div class="page-header">
            <h1 class="page-title">Órdenes de Venta</h1>
            <p class="page-subtitle">Crear y gestionar órdenes de venta</p>
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
        <form method="POST" id="salesOrderForm">
            <input type="hidden" name="action" value="create">
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="order_number" class="form-label">Número de Orden *</label>
                    <input type="text" class="form-control" id="order_number" name="order_number" required>
                </div>
                <div class="col-md-4">
                    <label for="customer_name" class="form-label">Nombre del Cliente</label>
                    <input type="text" class="form-control" id="customer_name" name="customer_name">
                </div>
                <div class="col-md-4">
                    <label for="status" class="form-label">Estado</label>
                    <select class="form-select" id="status" name="status">
                        <option value="Pendiente" selected>Pendiente</option>
                        <option value="Completada">Completada</option>
                        <option value="Cancelada">Cancelada</option>
                    </select>
                </div>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-primary">Crear Orden de Venta</button>
            </div>
        </form>
    </div>
</div>

<!-- Tabla de órdenes de venta -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead>
                    <tr>
                        <th>Número de Orden</th>
                        <th>Cliente</th>
                        <th>Estado</th>
                        <th>Fecha</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($salesOrders as $order): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($order['order_number']); ?></td>
                        <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                        <td>
                            <span class="badge 
                                <?php 
                                switch ($order['status']) {
                                    case 'Pendiente': echo 'bg-warning'; break;
                                    case 'Completada': echo 'bg-success'; break;
                                    case 'Cancelada': echo 'bg-danger'; break;
                                    default: echo 'bg-secondary';
                                }
                                ?>">
                                <?php echo htmlspecialchars($order['status']); ?>
                            </span>
                        </td>
                        <td><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

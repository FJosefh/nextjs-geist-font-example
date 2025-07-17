<?php
$pageTitle = "Órdenes de Servicio (RS) - WillAQP";
require_once '../includes/header.php';
checkRole(['WillAQP']);

$error = '';
$success = '';

// Manejo de asignación de repuestos a RS y otras acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $productId = $_POST['product_id'] ?? null;
    $quantity = intval($_POST['quantity'] ?? 0);
    $rsNumber = trim($_POST['rs_number'] ?? '');
    $technicianId = $_POST['technician_id'] ?? '';
    $type = $_POST['type'] ?? '';
    $observations = trim($_POST['observations'] ?? '');
    $rsOrderId = $_POST['rs_order_id'] ?? null;
    $detailId = $_POST['detail_id'] ?? null;

    try {
        if ($action === 'assign_to_rs') {
            if (empty($productId) || empty($quantity) || empty($rsNumber) || empty($technicianId) || empty($type)) {
                throw new Exception('Complete todos los campos obligatorios.');
            }

            // Obtener producto actual
            $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND active = 1");
            $stmt->execute([$productId]);
            $product = $stmt->fetch();
            if (!$product) {
                throw new Exception('Producto no encontrado.');
            }

            // Verificar stock disponible en WillAQP
            if ($product['stock_will_aqp'] < $quantity) {
                throw new Exception('Stock insuficiente en almacén WillAQP.');
            }

            // Crear o buscar la orden RS
            $stmt = $pdo->prepare("SELECT id FROM rs_orders WHERE rs_number = ?");
            $stmt->execute([$rsNumber]);
            $rsOrder = $stmt->fetch();

            if (!$rsOrder) {
                // Crear nueva orden RS
                $stmt = $pdo->prepare("INSERT INTO rs_orders (rs_number, type, technician_id, status, description) VALUES (?, ?, ?, 'En Proceso', ?)");
                $stmt->execute([$rsNumber, $type, $technicianId, $observations]);
                $rsOrderId = $pdo->lastInsertId();
            } else {
                $rsOrderId = $rsOrder['id'];
            }

            // Reducir stock del almacén WillAQP
            $newStock = $product['stock_will_aqp'] - $quantity;
            $stmt = $pdo->prepare("UPDATE products SET stock_will_aqp = ?, total_used = total_used + ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$newStock, $quantity, $productId]);

            // Registrar detalle de RS
            $stmt = $pdo->prepare("INSERT INTO rs_order_details (rs_order_id, product_id, quantity, assigned_by) VALUES (?, ?, ?, ?)");
            $stmt->execute([$rsOrderId, $productId, $quantity, $currentUser['id']]);

            // Registrar movimiento de inventario
            $stmt = $pdo->prepare("INSERT INTO inventory_movements (product_id, user_id, action, quantity, warehouse_origin, warehouse_destination, motive, reference_type, reference_id) VALUES (?, ?, 'salida', ?, 'will_aqp', NULL, ?, 'rs_order', ?)");
            $stmt->execute([$productId, $currentUser['id'], $quantity, "Asignado a RS: $rsNumber", $rsOrderId]);

            $success = 'Repuesto asignado exitosamente a la orden RS.';
        } elseif ($action === 'return_part' && $detailId) {
            // Devolver repuesto (solo para pruebas)
            $stmt = $pdo->prepare("
                SELECT rsd.*, rs.type, rs.rs_number, p.name as product_name 
                FROM rs_order_details rsd 
                JOIN rs_orders rs ON rsd.rs_order_id = rs.id 
                JOIN products p ON rsd.product_id = p.id 
                WHERE rsd.id = ?
            ");
            $stmt->execute([$detailId]);
            $detail = $stmt->fetch();
            
            if (!$detail) {
                throw new Exception('Detalle de RS no encontrado.');
            }
            
            if ($detail['type'] !== 'Prueba') {
                throw new Exception('Solo se pueden devolver repuestos de tipo Prueba.');
            }

            // Devolver stock al almacén WillAQP
            $stmt = $pdo->prepare("UPDATE products SET stock_will_aqp = stock_will_aqp + ?, total_used = total_used - ? WHERE id = ?");
            $stmt->execute([$detail['quantity'], $detail['quantity'], $detail['product_id']]);

            // Eliminar el detalle de RS
            $stmt = $pdo->prepare("DELETE FROM rs_order_details WHERE id = ?");
            $stmt->execute([$detailId]);

            // Registrar movimiento de devolución
            $stmt = $pdo->prepare("INSERT INTO inventory_movements (product_id, user_id, action, quantity, warehouse_origin, warehouse_destination, motive, reference_type, reference_id) VALUES (?, ?, 'entrada', ?, NULL, 'will_aqp', ?, 'rs_order', ?)");
            $stmt->execute([$detail['product_id'], $currentUser['id'], $detail['quantity'], "Devolución de prueba RS: " . $detail['rs_number'], $detail['rs_order_id']]);

            $success = 'Repuesto devuelto exitosamente al almacén.';
        } elseif ($action === 'complete_rs' && $rsOrderId) {
            // Marcar RS como completada
            $stmt = $pdo->prepare("UPDATE rs_orders SET status = 'Completada' WHERE id = ?");
            $stmt->execute([$rsOrderId]);

            $success = 'Orden RS marcada como completada.';
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Obtener productos disponibles con búsqueda
$searchProduct = trim($_GET['search_product'] ?? '');
$whereProduct = "active = 1 AND stock_will_aqp > 0";
$paramsProduct = [];

if (!empty($searchProduct)) {
    $whereProduct .= " AND (name LIKE ? OR sku LIKE ? OR description LIKE ?)";
    $searchParam = "%$searchProduct%";
    $paramsProduct[] = $searchParam;
    $paramsProduct[] = $searchParam;
    $paramsProduct[] = $searchParam;
}

$stmt = $pdo->prepare("SELECT * FROM products WHERE $whereProduct ORDER BY name ASC");
$stmt->execute($paramsProduct);
$products = $stmt->fetchAll();

// Obtener técnicos para asignación
$stmt = $pdo->prepare("SELECT id, name FROM users WHERE group_role = 'WillAQP' AND role_detail IN ('Técnico', 'Supervisor', 'Almacenero') AND active = 1 ORDER BY name ASC");
$stmt->execute();
$technicians = $stmt->fetchAll();

// Obtener órdenes RS recientes con detalles
$stmt = $pdo->query("
    SELECT rs.*, u.name as technician_name,
           COUNT(rsd.id) as total_items,
           SUM(rsd.quantity) as total_quantity
    FROM rs_orders rs 
    LEFT JOIN users u ON rs.technician_id = u.id 
    LEFT JOIN rs_order_details rsd ON rs.id = rsd.rs_order_id
    GROUP BY rs.id
    ORDER BY rs.created_at DESC 
    LIMIT 10
");
$recentOrders = $stmt->fetchAll();

// Obtener detalles de RS para mostrar productos asignados
$stmt = $pdo->query("
    SELECT rsd.*, rs.rs_number, rs.type, rs.status, p.name as product_name, p.sku,
           u.name as technician_name, ua.name as assigned_by_name
    FROM rs_order_details rsd
    JOIN rs_orders rs ON rsd.rs_order_id = rs.id
    JOIN products p ON rsd.product_id = p.id
    JOIN users u ON rs.technician_id = u.id
    JOIN users ua ON rsd.assigned_by = ua.id
    WHERE rs.status IN ('Pendiente', 'En Proceso')
    ORDER BY rsd.assigned_at DESC
    LIMIT 20
");
$rsDetails = $stmt->fetchAll();

?>

<div class="row">
    <div class="col-12">
        <div class="page-header">
            <h1 class="page-title">Órdenes de Servicio (RS) - WillAQP</h1>
            <p class="page-subtitle">Asignar repuestos a órdenes de servicio existentes</p>
        </div>
    </div>
</div>

<?php if ($error): ?>
<div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>
<?php if ($success): ?>
<div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<!-- Formulario de asignación -->
<div class="card mb-4">
    <div class="card-body">
        <h5 class="card-title">Asignar Repuesto a Orden RS</h5>
        <form method="POST" id="rsForm">
            <input type="hidden" name="action" value="assign_to_rs">
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="product_search" class="form-label">Buscar Producto *</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="product_search" placeholder="Buscar por nombre o SKU..." value="<?php echo htmlspecialchars($searchProduct); ?>">
                        <button type="button" class="btn btn-outline-secondary" onclick="searchProducts()">Buscar</button>
                    </div>
                    <select class="form-select mt-2" id="product_id" name="product_id" required>
                        <option value="" disabled selected>Seleccione un producto</option>
                        <?php foreach ($products as $product): ?>
                        <option value="<?php echo $product['id']; ?>" data-stock="<?php echo $product['stock_will_aqp']; ?>" data-name="<?php echo htmlspecialchars($product['name']); ?>" data-sku="<?php echo htmlspecialchars($product['sku']); ?>">
                            <?php echo htmlspecialchars($product['sku'] . ' - ' . $product['name'] . ' (Stock: ' . $product['stock_will_aqp'] . ')'); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="quantity" class="form-label">Cantidad *</label>
                    <input type="number" class="form-control" id="quantity" name="quantity" min="1" required>
                    <small class="text-muted">Stock disponible: <span id="available_stock">0</span></small>
                </div>
                <div class="col-md-3">
                    <label for="rs_number" class="form-label">Número RS *</label>
                    <input type="text" class="form-control" id="rs_number" name="rs_number" placeholder="Ej: RS-2024-001" required>
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
                    <label for="type" class="form-label">Tipo *</label>
                    <select class="form-select" id="type" name="type" required>
                        <option value="" disabled selected>Seleccione tipo</option>
                        <option value="Instalación">Instalación</option>
                        <option value="Prueba">Prueba</option>
                    </select>
                </div>
                <div class="col-md-9">
                    <label for="observations" class="form-label">Observaciones</label>
                    <textarea class="form-control" id="observations" name="observations" rows="2" placeholder="Observaciones adicionales..."></textarea>
                </div>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-primary">Asignar a RS</button>
            </div>
        </form>
    </div>
</div>

<!-- Productos asignados a RS activas -->
<div class="card mb-4">
    <div class="card-body">
        <h5 class="card-title">Productos Asignados a RS Activas</h5>
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead>
                    <tr>
                        <th>RS</th>
                        <th>Producto</th>
                        <th>Cantidad</th>
                        <th>Tipo</th>
                        <th>Técnico</th>
                        <th>Estado</th>
                        <th>Asignado por</th>
                        <th>Fecha</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rsDetails as $detail): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($detail['rs_number']); ?></strong></td>
                        <td>
                            <strong><?php echo htmlspecialchars($detail['product_name']); ?></strong><br>
                            <small class="text-muted"><?php echo htmlspecialchars($detail['sku']); ?></small>
                        </td>
                        <td><?php echo $detail['quantity']; ?></td>
                        <td>
                            <span class="badge <?php echo $detail['type'] === 'Prueba' ? 'bg-warning' : 'bg-info'; ?>">
                                <?php echo htmlspecialchars($detail['type']); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($detail['technician_name']); ?></td>
                        <td>
                            <span class="badge 
                                <?php 
                                switch ($detail['status']) {
                                    case 'Pendiente': echo 'bg-warning'; break;
                                    case 'En Proceso': echo 'bg-info'; break;
                                    case 'Completada': echo 'bg-success'; break;
                                    case 'Cancelada': echo 'bg-danger'; break;
                                    default: echo 'bg-secondary';
                                }
                                ?>">
                                <?php echo htmlspecialchars($detail['status']); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($detail['assigned_by_name']); ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($detail['assigned_at'])); ?></td>
                        <td>
                            <?php if ($detail['type'] === 'Prueba'): ?>
                            <form method="POST" class="d-inline" onsubmit="return confirm('¿Devolver este repuesto al almacén?');">
                                <input type="hidden" name="action" value="return_part">
                                <input type="hidden" name="detail_id" value="<?php echo $detail['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-warning">Devolver</button>
                            </form>
                            <?php endif; ?>
                            <form method="POST" class="d-inline" onsubmit="return confirm('¿Marcar RS como completada?');">
                                <input type="hidden" name="action" value="complete_rs">
                                <input type="hidden" name="rs_order_id" value="<?php echo $detail['rs_order_id']; ?>">
                                <button type="submit" class="btn btn-sm btn-success">Completar</button>
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
// Función para buscar productos
function searchProducts() {
    const searchTerm = document.getElementById('product_search').value;
    window.location.href = '?search_product=' + encodeURIComponent(searchTerm);
}

// Búsqueda de productos en tiempo real
document.getElementById('product_search').addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const productSelect = document.getElementById('product_id');
    const options = productSelect.querySelectorAll('option');
    
    options.forEach(option => {
        if (option.value === '') return;
        const text = option.textContent.toLowerCase();
        option.style.display = text.includes(searchTerm) ? 'block' : 'none';
    });
});

// Actualizar stock disponible
document.getElementById('product_id').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const stock = selectedOption.getAttribute('data-stock') || 0;
    document.getElementById('available_stock').textContent = stock;
    document.getElementById('quantity').max = stock;
});

// Validar cantidad
document.getElementById('quantity').addEventListener('input', function() {
    const maxStock = parseInt(this.max) || 0;
    const quantity = parseInt(this.value) || 0;
    
    if (quantity > maxStock) {
        this.setCustomValidity('La cantidad no puede ser mayor al stock disponible');
    } else {
        this.setCustomValidity('');
    }
});

// Permitir búsqueda con Enter
document.getElementById('product_search').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        searchProducts();
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>

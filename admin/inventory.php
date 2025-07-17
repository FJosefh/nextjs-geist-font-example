<?php
$pageTitle = "Gestión de Inventario";
require_once '../includes/header.php';
checkRole(['Administrador', 'Will', 'WillAQP', 'Spare Parts']);

$userWarehouse = getUserWarehouse();
$canEdit = canEditInventory();
$canReduce = canReduceStock();

$error = '';
$success = '';

// Obtener parámetros de búsqueda y filtros
$search = trim($_GET['search'] ?? '');
$warehouseFilter = $_GET['warehouse_filter'] ?? '';
$stockFilter = $_GET['stock_filter'] ?? '';

// Manejo de actualización de stock y edición de nombre
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $productId = $_POST['product_id'] ?? null;
    $newName = trim($_POST['new_name'] ?? '');
    $quantityChange = intval($_POST['quantity_change'] ?? 0);
    $motive = trim($_POST['motive'] ?? '');
    $warehouseOrigin = $_POST['warehouse_origin'] ?? '';
    $warehouseDestination = $_POST['warehouse_destination'] ?? '';

    try {
        if (!$productId) {
            throw new Exception('Producto no especificado.');
        }

        // Obtener producto actual
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        if (!$product) {
            throw new Exception('Producto no encontrado.');
        }

        if ($action === 'edit_name' && $canEdit) {
            if (empty($newName)) {
                throw new Exception('El nombre no puede estar vacío.');
            }
            $stmt = $pdo->prepare("UPDATE products SET name = ? WHERE id = ?");
            $stmt->execute([$newName, $productId]);
            $success = 'Nombre del producto actualizado.';
        } elseif ($action === 'change_stock' && $canEdit) {
            if (empty($motive)) {
                throw new Exception('Debe especificar un motivo para el cambio de stock.');
            }
            $validWarehouses = ['callao', 'spare_parts', 'will', 'will_aqp'];
            if (!in_array($warehouseOrigin, $validWarehouses) && $warehouseOrigin !== '') {
                throw new Exception('Almacén origen inválido.');
            }
            if (!in_array($warehouseDestination, $validWarehouses)) {
                throw new Exception('Almacén destino inválido.');
            }

            // Validar permisos para reducir stock
            if ($quantityChange < 0 && !$canReduce) {
                throw new Exception('No tiene permiso para reducir stock.');
            }

            // Actualizar stock según almacenes
            $stockFieldOrigin = '';
            $stockFieldDestination = '';

            switch ($warehouseOrigin) {
                case 'callao':
                    $stockFieldOrigin = 'stock_callao';
                    break;
                case 'spare_parts':
                    $stockFieldOrigin = 'stock_spare_parts';
                    break;
                case 'will':
                    $stockFieldOrigin = 'stock_will';
                    break;
                case 'will_aqp':
                    $stockFieldOrigin = 'stock_will_aqp';
                    break;
            }

            switch ($warehouseDestination) {
                case 'callao':
                    $stockFieldDestination = 'stock_callao';
                    break;
                case 'spare_parts':
                    $stockFieldDestination = 'stock_spare_parts';
                    break;
                case 'will':
                    $stockFieldDestination = 'stock_will';
                    break;
                case 'will_aqp':
                    $stockFieldDestination = 'stock_will_aqp';
                    break;
            }

            // Si origen es vacío (solo permitido para Callao entrada)
            if ($warehouseOrigin === '') {
                if ($warehouseDestination !== 'callao') {
                    throw new Exception('Solo Callao puede recibir stock sin almacén origen.');
                }
                $newStockDest = $product[$stockFieldDestination] + $quantityChange;
                if ($newStockDest < 0) {
                    throw new Exception('Stock insuficiente para esta operación.');
                }
                $stmt = $pdo->prepare("UPDATE products SET $stockFieldDestination = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$newStockDest, $productId]);
            } else {
                // Movimiento entre almacenes
                $newStockOrigin = $product[$stockFieldOrigin] - $quantityChange;
                $newStockDest = $product[$stockFieldDestination] + $quantityChange;
                if ($newStockOrigin < 0) {
                    throw new Exception('Stock insuficiente en almacén origen.');
                }
                if ($newStockDest < 0) {
                    throw new Exception('Stock insuficiente en almacén destino.');
                }
                $stmt = $pdo->prepare("UPDATE products SET $stockFieldOrigin = ?, $stockFieldDestination = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$newStockOrigin, $newStockDest, $productId]);
            }

            // Registrar movimiento
            $userId = $currentUser['id'];
            $actionType = 'traslado';
            $stmt = $pdo->prepare("INSERT INTO inventory_movements (product_id, user_id, action, quantity, warehouse_origin, warehouse_destination, motive, reference_type, reference_id) VALUES (?, ?, ?, ?, ?, ?, ?, 'manual', NULL)");
            $stmt->execute([$productId, $userId, $actionType, abs($quantityChange), $warehouseOrigin, $warehouseDestination, $motive]);

            $success = 'Stock actualizado correctamente.';
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Construir consulta con filtros
$whereConditions = ['active = 1'];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(name LIKE ? OR sku LIKE ? OR description LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

if (!empty($warehouseFilter)) {
    switch ($warehouseFilter) {
        case 'callao':
            $whereConditions[] = "stock_callao > 0";
            break;
        case 'spare_parts':
            $whereConditions[] = "stock_spare_parts > 0";
            break;
        case 'will':
            $whereConditions[] = "stock_will > 0";
            break;
        case 'will_aqp':
            $whereConditions[] = "stock_will_aqp > 0";
            break;
    }
}

if (!empty($stockFilter)) {
    switch ($stockFilter) {
        case 'low_stock':
            $whereConditions[] = "(stock_callao + stock_spare_parts + stock_will + stock_will_aqp) < 5";
            break;
        case 'no_stock':
            $whereConditions[] = "(stock_callao + stock_spare_parts + stock_will + stock_will_aqp) = 0";
            break;
        case 'high_stock':
            $whereConditions[] = "(stock_callao + stock_spare_parts + stock_will + stock_will_aqp) > 20";
            break;
    }
}

$whereClause = implode(' AND ', $whereConditions);
$sql = "SELECT * FROM products WHERE $whereClause ORDER BY name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

?>

<div class="row">
    <div class="col-12">
        <div class="page-header">
            <h1 class="page-title">Gestión de Inventario</h1>
            <p class="page-subtitle">Editar productos y administrar stock</p>
        </div>
    </div>
</div>

<?php if ($error): ?>
<div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>
<?php if ($success): ?>
<div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<!-- Filtros y búsqueda -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label for="search" class="form-label">Buscar</label>
                <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Buscar por nombre, SKU o descripción...">
            </div>
            <div class="col-md-3">
                <label for="warehouse_filter" class="form-label">Filtrar por Almacén</label>
                <select class="form-select" id="warehouse_filter" name="warehouse_filter">
                    <option value="">Todos los almacenes</option>
                    <option value="callao" <?php echo $warehouseFilter === 'callao' ? 'selected' : ''; ?>>Callao</option>
                    <option value="spare_parts" <?php echo $warehouseFilter === 'spare_parts' ? 'selected' : ''; ?>>Spare Parts</option>
                    <option value="will" <?php echo $warehouseFilter === 'will' ? 'selected' : ''; ?>>Will</option>
                    <option value="will_aqp" <?php echo $warehouseFilter === 'will_aqp' ? 'selected' : ''; ?>>WillAQP</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="stock_filter" class="form-label">Filtrar por Stock</label>
                <select class="form-select" id="stock_filter" name="stock_filter">
                    <option value="">Todos los niveles</option>
                    <option value="low_stock" <?php echo $stockFilter === 'low_stock' ? 'selected' : ''; ?>>Stock Bajo (< 5)</option>
                    <option value="no_stock" <?php echo $stockFilter === 'no_stock' ? 'selected' : ''; ?>>Sin Stock</option>
                    <option value="high_stock" <?php echo $stockFilter === 'high_stock' ? 'selected' : ''; ?>>Stock Alto (> 20)</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                </div>
            </div>
        </form>
        <?php if (!empty($search) || !empty($warehouseFilter) || !empty($stockFilter)): ?>
        <div class="mt-3">
            <a href="inventory.php" class="btn btn-outline-secondary btn-sm">Limpiar Filtros</a>
            <span class="text-muted ms-2">Mostrando <?php echo count($products); ?> productos</span>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="table-responsive table-container">
    <table class="table table-striped table-hover align-middle" id="inventoryTable">
        <thead>
            <tr>
                <th>SKU</th>
                <th>Nombre</th>
                <th>Descripción</th>
                <th>Precio</th>
                <th class="<?php echo $userWarehouse === 'callao' ? 'warehouse-highlight' : ''; ?>">Stock Callao</th>
                <th class="<?php echo $userWarehouse === 'spare_parts' ? 'warehouse-highlight' : ''; ?>">Stock Spare Parts</th>
                <th class="<?php echo $userWarehouse === 'will' ? 'warehouse-highlight' : ''; ?>">Stock Will</th>
                <th class="<?php echo $userWarehouse === 'will_aqp' ? 'warehouse-highlight' : ''; ?>">Stock WillAQP</th>
                <th>Total</th>
                <th>Total Usado</th>
                <th>Técnico Asignado</th>
                <?php if ($canEdit): ?>
                <th>Acciones</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($products as $product): ?>
            <?php 
            $totalStock = $product['stock_callao'] + $product['stock_spare_parts'] + $product['stock_will'] + $product['stock_will_aqp'];
            $rowClass = '';
            if ($totalStock == 0) {
                $rowClass = 'table-danger';
            } elseif ($totalStock < 5) {
                $rowClass = 'table-warning';
            }
            ?>
            <tr class="<?php echo $rowClass; ?>">
                <td><strong><?php echo htmlspecialchars($product['sku']); ?></strong></td>
                <td>
                    <?php if ($canEdit): ?>
                    <form method="POST" class="d-inline" style="display:inline;">
                        <input type="hidden" name="action" value="edit_name">
                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                        <input type="text" name="new_name" value="<?php echo htmlspecialchars($product['name']); ?>" class="form-control form-control-sm" style="max-width: 200px; display:inline-block;">
                        <button type="submit" class="btn btn-sm btn-primary ms-1">Guardar</button>
                    </form>
                    <?php else: ?>
                    <?php echo htmlspecialchars($product['name']); ?>
                    <?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars($product['description']); ?></td>
                <td><?php echo formatPrice($product['price']); ?></td>
                <td class="<?php echo $userWarehouse === 'callao' ? 'warehouse-highlight' : ''; ?>"><?php echo $product['stock_callao']; ?></td>
                <td class="<?php echo $userWarehouse === 'spare_parts' ? 'warehouse-highlight' : ''; ?>"><?php echo $product['stock_spare_parts']; ?></td>
                <td class="<?php echo $userWarehouse === 'will' ? 'warehouse-highlight' : ''; ?>"><?php echo $product['stock_will']; ?></td>
                <td class="<?php echo $userWarehouse === 'will_aqp' ? 'warehouse-highlight' : ''; ?>"><?php echo $product['stock_will_aqp']; ?></td>
                <td><strong><?php echo $totalStock; ?></strong></td>
                <td><?php echo $product['total_used']; ?></td>
                <td>
                    <?php
                    if ($product['technician_assigned']) {
                        $stmtTech = $pdo->prepare("SELECT name FROM users WHERE id = ?");
                        $stmtTech->execute([$product['technician_assigned']]);
                        $techName = $stmtTech->fetchColumn();
                        echo htmlspecialchars($techName);
                    } else {
                        echo '-';
                    }
                    ?>
                </td>
                <?php if ($canEdit): ?>
                <td>
                    <form method="POST" class="d-inline" style="display:inline;">
                        <input type="hidden" name="action" value="change_stock">
                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                        <select name="warehouse_origin" class="form-select form-select-sm d-inline-block" style="width: 120px;">
                            <option value="" <?php echo $userWarehouse === 'callao' ? 'selected' : ''; ?>>Sin Origen (Solo Callao)</option>
                            <option value="callao" <?php echo $userWarehouse === 'callao' ? 'selected' : ''; ?>>Callao</option>
                            <option value="spare_parts" <?php echo $userWarehouse === 'spare_parts' ? 'selected' : ''; ?>>Spare Parts</option>
                            <option value="will" <?php echo $userWarehouse === 'will' ? 'selected' : ''; ?>>Will</option>
                            <option value="will_aqp" <?php echo $userWarehouse === 'will_aqp' ? 'selected' : ''; ?>>WillAQP</option>
                        </select>
                        <select name="warehouse_destination" class="form-select form-select-sm d-inline-block ms-1" style="width: 120px;">
                            <option value="callao" <?php echo $userWarehouse === 'callao' ? 'selected' : ''; ?>>Callao</option>
                            <option value="spare_parts" <?php echo $userWarehouse === 'spare_parts' ? 'selected' : ''; ?>>Spare Parts</option>
                            <option value="will" <?php echo $userWarehouse === 'will' ? 'selected' : ''; ?>>Will</option>
                            <option value="will_aqp" <?php echo $userWarehouse === 'will_aqp' ? 'selected' : ''; ?>>WillAQP</option>
                        </select>
                        <input type="number" name="quantity_change" class="form-control form-control-sm d-inline-block ms-1" style="width: 80px;" placeholder="Cantidad" required>
                        <input type="text" name="motive" class="form-control form-control-sm d-inline-block ms-1" style="width: 150px;" placeholder="Motivo" required>
                        <button type="submit" class="btn btn-sm btn-success ms-1">Actualizar</button>
                    </form>
                </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if (empty($products)): ?>
<div class="text-center py-4">
    <p class="text-muted">No se encontraron productos con los filtros aplicados.</p>
</div>
<?php endif; ?>

<script>
// Búsqueda en tiempo real
document.getElementById('search').addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const table = document.getElementById('inventoryTable');
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    
    for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        const cells = row.getElementsByTagName('td');
        let found = false;
        
        // Buscar en SKU, nombre y descripción
        for (let j = 0; j < 3; j++) {
            if (cells[j] && cells[j].textContent.toLowerCase().includes(searchTerm)) {
                found = true;
                break;
            }
        }
        
        row.style.display = found ? '' : 'none';
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>

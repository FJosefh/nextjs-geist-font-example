<?php
$pageTitle = "Gestión de Inventario";
require_once '../includes/header.php';
checkRole(['Administrador', 'Will', 'Spare Parts']);

$userWarehouse = getUserWarehouse();
$canEdit = canEditInventory();
$canReduce = canReduceStock();

$error = '';
$success = '';

// Manejo de actualización de stock y edición de nombre
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $productId = $_POST['product_id'] ?? null;
    $newName = trim($_POST['new_name'] ?? '');
    $quantityChange = intval($_POST['quantity_change'] ?? 0);
    $motive = trim($_POST['motive'] ?? '');
    $warehouse = $_POST['warehouse'] ?? '';

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
            if (!in_array($warehouse, ['callao', 'spare_parts', 'will'])) {
                throw new Exception('Almacén inválido.');
            }

            // Validar permisos para reducir stock
            if ($quantityChange < 0 && !$canReduce) {
                throw new Exception('No tiene permiso para reducir stock.');
            }

            // Actualizar stock según almacén
            $stockField = '';
            switch ($warehouse) {
                case 'callao':
                    $stockField = 'stock_callao';
                    break;
                case 'spare_parts':
                    $stockField = 'stock_spare_parts';
                    break;
                case 'will':
                    $stockField = 'stock_will';
                    break;
            }

            $newStock = $product[$stockField] + $quantityChange;
            if ($newStock < 0) {
                throw new Exception('Stock insuficiente para esta operación.');
            }

            $stmt = $pdo->prepare("UPDATE products SET $stockField = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$newStock, $productId]);

            // Registrar movimiento
            $userId = $currentUser['id'];
            $actionType = $quantityChange > 0 ? 'entrada' : 'salida';
            $stmt = $pdo->prepare("INSERT INTO inventory_movements (product_id, user_id, action, quantity, warehouse_origin, warehouse_destination, motive, reference_type, reference_id) VALUES (?, ?, ?, ?, ?, ?, ?, 'manual', NULL)");
            $stmt->execute([$productId, $userId, $actionType, abs($quantityChange), $warehouse, $warehouse, $motive]);

            $success = 'Stock actualizado correctamente.';
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Obtener productos activos
$stmt = $pdo->query("SELECT * FROM products WHERE active = 1 ORDER BY name ASC");
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

<div class="table-responsive table-container">
    <table class="table table-striped table-hover align-middle">
        <thead>
            <tr>
                <th>SKU</th>
                <th>Nombre</th>
                <th>Descripción</th>
                <th>Precio</th>
                <th class="<?php echo $userWarehouse === 'callao' ? 'warehouse-highlight' : ''; ?>">Stock Callao</th>
                <th class="<?php echo $userWarehouse === 'spare_parts' ? 'warehouse-highlight' : ''; ?>">Stock Spare Parts</th>
                <th class="<?php echo $userWarehouse === 'will' ? 'warehouse-highlight' : ''; ?>">Stock Will</th>
                <th>Total Usado</th>
                <th>Técnico Asignado</th>
                <?php if ($canEdit): ?>
                <th>Acciones</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($products as $product): ?>
            <tr>
                <td><?php echo htmlspecialchars($product['sku']); ?></td>
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
                        <select name="warehouse" class="form-select form-select-sm d-inline-block" style="width: 120px;">
                            <option value="callao" <?php echo $userWarehouse === 'callao' ? 'selected' : ''; ?>>Callao</option>
                            <option value="spare_parts" <?php echo $userWarehouse === 'spare_parts' ? 'selected' : ''; ?>>Spare Parts</option>
                            <option value="will" <?php echo $userWarehouse === 'will' ? 'selected' : ''; ?>>Will</option>
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

<?php require_once '../includes/footer.php'; ?>

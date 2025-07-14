<?php
require_once '../config/config.php';
require_once '../auth/session.php';

// Verificar que el usuario tenga permisos para ver inventario
checkLogin();

$currentUser = getCurrentUser();
$userWarehouse = getUserWarehouse();

// Procesar acciones
$message = '';
$error = '';

if ($_POST) {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'edit_product':
                    if (canEditInventory()) {
                        $productId = (int)$_POST['product_id'];
                        $name = trim($_POST['name']);
                        $description = trim($_POST['description']);
                        $price = (float)$_POST['price'];
                        
                        $stmt = $pdo->prepare("UPDATE products SET name = ?, description = ?, price = ? WHERE id = ?");
                        $stmt->execute([$name, $description, $price, $productId]);
                        
                        // Registrar movimiento
                        $stmt = $pdo->prepare("INSERT INTO inventory_movements (product_id, user_id, action, quantity, motive, reference_type) VALUES (?, ?, 'ajuste', 0, 'Edición de producto', 'manual')");
                        $stmt->execute([$productId, $currentUser['id']]);
                        
                        $message = "Producto actualizado correctamente";
                    } else {
                        $error = "No tiene permisos para editar productos";
                    }
                    break;
                    
                case 'adjust_stock':
                    $productId = (int)$_POST['product_id'];
                    $warehouse = $_POST['warehouse'];
                    $quantity = (int)$_POST['quantity'];
                    $action = $_POST['stock_action']; // 'increase' o 'decrease'
                    
                    if ($action === 'decrease' && !canReduceStock()) {
                        $error = "Solo el administrador puede reducir stock";
                        break;
                    }
                    
                    if (!canEditInventory()) {
                        $error = "No tiene permisos para ajustar stock";
                        break;
                    }
                    
                    $column = "stock_" . $warehouse;
                    $operator = ($action === 'increase') ? '+' : '-';
                    
                    // Verificar que no quede stock negativo
                    if ($action === 'decrease') {
                        $stmt = $pdo->prepare("SELECT $column FROM products WHERE id = ?");
                        $stmt->execute([$productId]);
                        $currentStock = $stmt->fetchColumn();
                        
                        if ($currentStock < $quantity) {
                            $error = "No hay suficiente stock para reducir";
                            break;
                        }
                    }
                    
                    $stmt = $pdo->prepare("UPDATE products SET $column = $column $operator ? WHERE id = ?");
                    $stmt->execute([$quantity, $productId]);
                    
                    // Registrar movimiento
                    $movementAction = ($action === 'increase') ? 'entrada' : 'salida';
                    $motive = ($action === 'increase') ? 'Aumento de stock manual' : 'Reducción de stock manual';
                    
                    $stmt = $pdo->prepare("INSERT INTO inventory_movements (product_id, user_id, action, quantity, warehouse_destination, motive, reference_type) VALUES (?, ?, ?, ?, ?, ?, 'manual')");
                    $stmt->execute([$productId, $currentUser['id'], $movementAction, $quantity, $warehouse, $motive]);
                    
                    $message = "Stock ajustado correctamente";
                    break;
            }
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Obtener filtros
$search = $_GET['search'] ?? '';
$filterWarehouse = $_GET['warehouse'] ?? '';
$filterLowStock = isset($_GET['filter']) && $_GET['filter'] === 'low_stock';

// Construir consulta
$whereConditions = ["p.active = 1"];
$params = [];

if ($search) {
    $whereConditions[] = "(p.name LIKE ? OR p.sku LIKE ? OR p.description LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
}

if ($filterWarehouse) {
    $whereConditions[] = "p.stock_$filterWarehouse > 0";
}

if ($filterLowStock) {
    $whereConditions[] = "(p.stock_callao + p.stock_spare_parts + p.stock_will) < 5";
}

$whereClause = implode(' AND ', $whereConditions);

$sql = "
    SELECT p.*, 
           u.name as technician_name,
           (p.stock_callao + p.stock_spare_parts + p.stock_will) as stock_total
    FROM products p
    LEFT JOIN users u ON p.technician_assigned = u.id
    WHERE $whereClause
    ORDER BY p.name ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Obtener técnicos para el dropdown
$stmt = $pdo->query("SELECT id, name FROM users WHERE group_role = 'Will' AND role_detail = 'Técnico' AND active = 1 ORDER BY name");
$technicians = $stmt->fetchAll();

$pageTitle = "Gestión de Inventario";
require_once '../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="page-header">
            <h1 class="page-title">Gestión de Inventario</h1>
            <p class="page-subtitle">Administrar productos y stock por almacén</p>
        </div>
    </div>
</div>

<?php if ($message): ?>
<div class="alert alert-success-modern alert-dismissible fade show">
    <?php echo htmlspecialchars($message); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger-modern alert-dismissible fade show">
    <?php echo htmlspecialchars($error); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Filtros y búsqueda -->
<div class="filter-container">
    <form method="GET" class="filter-group">
        <div class="col-md-4">
            <label class="form-label-modern">Buscar producto</label>
            <div class="search-container">
                <span class="search-icon">🔍</span>
                <input type="text" name="search" class="form-control search-input" 
                       placeholder="Nombre, SKU o descripción..." 
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
        </div>
        
        <div class="col-md-3">
            <label class="form-label-modern">Filtrar por almacén</label>
            <select name="warehouse" class="form-control form-control-modern">
                <option value="">Todos los almacenes</option>
                <option value="callao" <?php echo $filterWarehouse === 'callao' ? 'selected' : ''; ?>>Callao</option>
                <option value="spare_parts" <?php echo $filterWarehouse === 'spare_parts' ? 'selected' : ''; ?>>Spare Parts</option>
                <option value="will" <?php echo $filterWarehouse === 'will' ? 'selected' : ''; ?>>Will</option>
            </select>
        </div>
        
        <div class="col-md-2">
            <label class="form-label-modern">&nbsp;</label>
            <div>
                <button type="submit" class="btn btn-primary-modern">
                    Filtrar
                </button>
            </div>
        </div>
        
        <div class="col-md-3">
            <label class="form-label-modern">&nbsp;</label>
            <div>
                <a href="?filter=low_stock" class="btn btn-warning-modern">
                    Stock Bajo
                </a>
                <?php if (canEditInventory()): ?>
                <button type="button" class="btn btn-success-modern" data-bs-toggle="modal" data-bs-target="#uploadModal">
                    Subir Excel
                </button>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<!-- Tabla de productos -->
<div class="card-modern">
    <div class="card-header-modern">
        <h5 class="mb-0">Productos en Inventario (<?php echo count($products); ?>)</h5>
    </div>
    <div class="card-body-modern p-0">
        <div class="table-responsive">
            <table class="table table-modern mb-0" id="inventoryTable">
                <thead>
                    <tr>
                        <th>SKU</th>
                        <th>Producto</th>
                        <th>Precio</th>
                        <th class="<?php echo $userWarehouse === 'callao' ? 'warehouse-highlight' : ''; ?>">Callao</th>
                        <th class="<?php echo $userWarehouse === 'spare_parts' ? 'warehouse-highlight' : ''; ?>">Spare Parts</th>
                        <th class="<?php echo $userWarehouse === 'will' ? 'warehouse-highlight' : ''; ?>">Will</th>
                        <th>Total</th>
                        <th>Usado</th>
                        <th>Técnico</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                    <tr>
                        <td><code><?php echo htmlspecialchars($product['sku']); ?></code></td>
                        <td>
                            <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                            <?php if ($product['description']): ?>
                            <br><small class="text-muted"><?php echo htmlspecialchars($product['description']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo formatPrice($product['price']); ?></td>
                        <td class="<?php echo $userWarehouse === 'callao' ? 'warehouse-highlight' : ''; ?>">
                            <span class="badge badge-modern bg-primary"><?php echo $product['stock_callao']; ?></span>
                        </td>
                        <td class="<?php echo $userWarehouse === 'spare_parts' ? 'warehouse-highlight' : ''; ?>">
                            <span class="badge badge-modern bg-success"><?php echo $product['stock_spare_parts']; ?></span>
                        </td>
                        <td class="<?php echo $userWarehouse === 'will' ? 'warehouse-highlight' : ''; ?>">
                            <span class="badge badge-modern bg-info"><?php echo $product['stock_will']; ?></span>
                        </td>
                        <td>
                            <strong><?php echo $product['stock_total']; ?></strong>
                            <?php if ($product['stock_total'] < 5): ?>
                            <span class="badge bg-danger ms-1">Bajo</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge bg-secondary"><?php echo $product['total_used']; ?></span></td>
                        <td>
                            <?php if ($product['technician_name']): ?>
                            <small><?php echo htmlspecialchars($product['technician_name']); ?></small>
                            <?php else: ?>
                            <small class="text-muted">Sin asignar</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (canEditInventory()): ?>
                            <button class="btn btn-sm btn-outline-modern" 
                                    onclick="editProduct(<?php echo $product['id']; ?>)"
                                    data-bs-toggle="modal" data-bs-target="#editModal">
                                Editar
                            </button>
                            <button class="btn btn-sm btn-primary-modern" 
                                    onclick="adjustStock(<?php echo $product['id']; ?>)"
                                    data-bs-toggle="modal" data-bs-target="#stockModal">
                                Stock
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal para editar producto -->
<div class="modal modal-modern fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editar Producto</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_product">
                    <input type="hidden" name="product_id" id="edit_product_id">
                    
                    <div class="mb-3">
                        <label class="form-label-modern">Nombre del producto</label>
                        <input type="text" name="name" id="edit_name" class="form-control form-control-modern" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label-modern">Descripción</label>
                        <textarea name="description" id="edit_description" class="form-control form-control-modern" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label-modern">Precio</label>
                        <input type="number" name="price" id="edit_price" class="form-control form-control-modern" step="0.01" min="0">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-modern" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary-modern">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para ajustar stock -->
<div class="modal modal-modern fade" id="stockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajustar Stock</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="stockForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="adjust_stock">
                    <input type="hidden" name="product_id" id="stock_product_id">
                    
                    <div class="mb-3">
                        <label class="form-label-modern">Almacén</label>
                        <select name="warehouse" class="form-control form-control-modern" required>
                            <option value="callao">Callao</option>
                            <option value="spare_parts">Spare Parts</option>
                            <option value="will">Will</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label-modern">Acción</label>
                        <select name="stock_action" class="form-control form-control-modern" required>
                            <option value="increase">Aumentar stock</option>
                            <?php if (canReduceStock()): ?>
                            <option value="decrease">Reducir stock</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label-modern">Cantidad</label>
                        <input type="number" name="quantity" class="form-control form-control-modern" min="1" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-modern" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary-modern">Ajustar Stock</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para subir Excel -->
<?php if (canEditInventory()): ?>
<div class="modal modal-modern fade" id="uploadModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Subir Inventario por Excel</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info-modern">
                    <strong>Formato requerido:</strong><br>
                    Columnas: SKU, Nombre, Descripción, Precio, Stock_Callao, Stock_Spare_Parts, Stock_Will
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload_excel">
                    <div class="mb-3">
                        <label class="form-label-modern">Archivo Excel/CSV</label>
                        <input type="file" name="excel_file" class="form-control form-control-modern" accept=".xlsx,.xls,.csv" required>
                    </div>
                    <button type="submit" class="btn btn-success-modern">Subir Archivo</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// Datos de productos para JavaScript
const products = <?php echo json_encode($products); ?>;

function editProduct(productId) {
    const product = products.find(p => p.id == productId);
    if (product) {
        document.getElementById('edit_product_id').value = product.id;
        document.getElementById('edit_name').value = product.name;
        document.getElementById('edit_description').value = product.description || '';
        document.getElementById('edit_price').value = product.price;
    }
}

function adjustStock(productId) {
    document.getElementById('stock_product_id').value = productId;
}

// Búsqueda en tiempo real
document.addEventListener('DOMContentLoaded', function() {
    setupTableSearch('search', 'inventoryTable');
    highlightUserWarehouse();
});
</script>

<?php require_once '../includes/footer.php'; ?>

<?php
$pageTitle = "Dashboard Administrador";
require_once '../includes/header.php';
checkRole(['Administrador']);

// Obtener estadísticas del sistema
try {
    // Total de productos
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM products WHERE active = 1");
    $totalProducts = $stmt->fetch()['total'];
    
    // Total de usuarios
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE active = 1");
    $totalUsers = $stmt->fetch()['total'];
    
    // Órdenes RS pendientes
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM rs_orders WHERE status = 'Pendiente'");
    $pendingRS = $stmt->fetch()['total'];
    
    // Órdenes de venta del mes
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM sales_orders WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
    $monthlySales = $stmt->fetch()['total'];
    
    // Productos con stock bajo (menos de 5 unidades en total)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM products WHERE (stock_callao + stock_spare_parts + stock_will) < 5 AND active = 1");
    $lowStock = $stmt->fetch()['total'];
    
    // Solicitudes pendientes
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM repuesto_requests WHERE status = 'Pendiente'");
    $pendingRequests = $stmt->fetch()['total'];
    
    // Movimientos recientes
    $stmt = $pdo->prepare("
        SELECT im.*, p.name as product_name, u.name as user_name 
        FROM inventory_movements im 
        LEFT JOIN products p ON im.product_id = p.id 
        LEFT JOIN users u ON im.user_id = u.id 
        WHERE im.product_id > 0
        ORDER BY im.created_at DESC 
        LIMIT 10
    ");
    $stmt->execute();
    $recentMovements = $stmt->fetchAll();
    
    // Productos más utilizados
    $stmt = $pdo->query("
        SELECT p.name, p.sku, p.total_used, 
               (p.stock_callao + p.stock_spare_parts + p.stock_will) as stock_total
        FROM products p 
        WHERE p.active = 1 
        ORDER BY p.total_used DESC 
        LIMIT 5
    ");
    $topProducts = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = "Error al cargar estadísticas: " . $e->getMessage();
}
?>

<div class="row">
    <div class="col-12">
        <div class="page-header">
            <h1 class="page-title">Dashboard Administrador</h1>
            <p class="page-subtitle">Panel de control y estadísticas del sistema</p>
        </div>
    </div>
</div>

<?php if (isset($error)): ?>
<div class="row">
    <div class="col-12">
        <div class="alert alert-danger"><?php echo $error; ?></div>
    </div>
</div>
<?php endif; ?>

<!-- Estadísticas principales -->
<div class="row mb-4">
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="stats-card">
            <div class="stats-number" id="counter-products"><?php echo $totalProducts ?? 0; ?></div>
            <div class="stats-label">Productos Activos</div>
        </div>
    </div>
    
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="stats-card" style="background: linear-gradient(135deg, #27ae60 0%, #229954 100%);">
            <div class="stats-number" id="counter-users"><?php echo $totalUsers ?? 0; ?></div>
            <div class="stats-label">Usuarios</div>
        </div>
    </div>
    
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="stats-card" style="background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);">
            <div class="stats-number" id="counter-pending-rs"><?php echo $pendingRS ?? 0; ?></div>
            <div class="stats-label">RS Pendientes</div>
        </div>
    </div>
    
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="stats-card" style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);">
            <div class="stats-number" id="counter-monthly-sales"><?php echo $monthlySales ?? 0; ?></div>
            <div class="stats-label">Ventas del Mes</div>
        </div>
    </div>
    
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="stats-card" style="background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);">
            <div class="stats-number" id="counter-low-stock"><?php echo $lowStock ?? 0; ?></div>
            <div class="stats-label">Stock Bajo</div>
        </div>
    </div>
    
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="stats-card" style="background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);">
            <div class="stats-number" id="counter-pending-requests"><?php echo $pendingRequests ?? 0; ?></div>
            <div class="stats-label">Solicitudes</div>
        </div>
    </div>
</div>

<!-- Accesos rápidos -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card-modern">
            <div class="card-header-modern">
                <h5 class="mb-0">Accesos Rápidos</h5>
            </div>
            <div class="card-body-modern">
                <div class="row">
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="inventory.php" class="btn btn-primary-modern w-100">
                            <span>📦</span> Gestionar Inventario
                        </a>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="users.php" class="btn btn-success-modern w-100">
                            <span>👥</span> Gestionar Usuarios
                        </a>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="orders.php" class="btn btn-warning-modern w-100">
                            <span>📋</span> Ver Órdenes
                        </a>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="../global/search.php" class="btn btn-outline-modern w-100">
                            <span>🔍</span> Búsqueda Universal
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Movimientos recientes -->
    <div class="col-lg-8 mb-4">
        <div class="card-modern">
            <div class="card-header-modern">
                <h5 class="mb-0">Movimientos Recientes de Inventario</h5>
            </div>
            <div class="card-body-modern">
                <?php if (!empty($recentMovements)): ?>
                <div class="table-responsive">
                    <table class="table table-modern">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Producto</th>
                                <th>Usuario</th>
                                <th>Acción</th>
                                <th>Cantidad</th>
                                <th>Motivo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentMovements as $movement): ?>
                            <tr>
                                <td><?php echo formatDate($movement['created_at']); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($movement['product_name'] ?? 'N/A'); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($movement['user_name']); ?></td>
                                <td>
                                    <span class="badge badge-modern 
                                        <?php 
                                        switch($movement['action']) {
                                            case 'entrada': echo 'bg-success'; break;
                                            case 'salida': echo 'bg-danger'; break;
                                            case 'traslado': echo 'bg-warning'; break;
                                            default: echo 'bg-secondary';
                                        }
                                        ?>">
                                        <?php echo ucfirst($movement['action']); ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?php echo $movement['quantity']; ?></strong>
                                </td>
                                <td>
                                    <small><?php echo htmlspecialchars($movement['motive']); ?></small>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="text-center mt-3">
                    <a href="inventory.php" class="btn btn-outline-modern">Ver Todos los Movimientos</a>
                </div>
                <?php else: ?>
                <div class="text-center py-4">
                    <p class="text-muted">No hay movimientos recientes</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Productos más utilizados -->
    <div class="col-lg-4 mb-4">
        <div class="card-modern">
            <div class="card-header-modern">
                <h5 class="mb-0">Productos Más Utilizados</h5>
            </div>
            <div class="card-body-modern">
                <?php if (!empty($topProducts)): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($topProducts as $product): ?>
                    <div class="list-group-item border-0 px-0">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <h6 class="mb-1"><?php echo htmlspecialchars($product['name']); ?></h6>
                                <small class="text-muted"><?php echo htmlspecialchars($product['sku']); ?></small>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-primary"><?php echo $product['total_used']; ?> usados</span>
                                <br>
                                <small class="text-muted"><?php echo $product['stock_total']; ?> en stock</small>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="text-center py-4">
                    <p class="text-muted">No hay datos de productos utilizados</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Alertas del sistema -->
<?php if ($lowStock > 0): ?>
<div class="row">
    <div class="col-12">
        <div class="alert alert-warning-modern">
            <h6 class="mb-2">⚠️ Alerta de Stock Bajo</h6>
            <p class="mb-2">Hay <strong><?php echo $lowStock; ?></strong> productos con stock bajo (menos de 5 unidades).</p>
            <a href="inventory.php?filter=low_stock" class="btn btn-warning btn-sm">Ver Productos</a>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($pendingRS > 0): ?>
<div class="row">
    <div class="col-12">
        <div class="alert alert-info-modern">
            <h6 class="mb-2">📋 Órdenes RS Pendientes</h6>
            <p class="mb-2">Hay <strong><?php echo $pendingRS; ?></strong> órdenes de servicio pendientes de atención.</p>
            <a href="orders.php?type=rs&status=pending" class="btn btn-info btn-sm">Ver Órdenes</a>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// Actualizar estadísticas cada 30 segundos
setInterval(function() {
    fetch('../api/dashboard-stats.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('counter-products').textContent = data.totalProducts || 0;
                document.getElementById('counter-users').textContent = data.totalUsers || 0;
                document.getElementById('counter-pending-rs').textContent = data.pendingRS || 0;
                document.getElementById('counter-monthly-sales').textContent = data.monthlySales || 0;
                document.getElementById('counter-low-stock').textContent = data.lowStock || 0;
                document.getElementById('counter-pending-requests').textContent = data.pendingRequests || 0;
            }
        })
        .catch(error => console.error('Error updating stats:', error));
}, 30000);
</script>

<?php require_once '../includes/footer.php'; ?>

<?php
$pageTitle = "Dashboard Spare Parts - Ventas";
require_once '../includes/header.php';
checkRole(['Spare Parts']);

// Obtener estadísticas específicas para Spare Parts
try {
    // Ventas del mes
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM sales_orders WHERE seller_id = ? AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
    $stmt->execute([$currentUser['id']]);
    $myMonthlySales = $stmt->fetch()['total'];
    
    // Total ventas del mes (todos los vendedores)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM sales_orders WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
    $totalMonthlySales = $stmt->fetch()['total'];
    
    // Solicitudes pendientes de Spare Parts
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM repuesto_requests WHERE group_origin = 'Spare Parts' AND status = 'Pendiente'");
    $pendingRequests = $stmt->fetch()['total'];
    
    // Stock en almacén Spare Parts
    $stmt = $pdo->query("SELECT SUM(stock_spare_parts) as total FROM products WHERE active = 1");
    $sparePartsStock = $stmt->fetch()['total'] ?? 0;
    
    // Productos con stock bajo en Spare Parts
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM products WHERE stock_spare_parts < 3 AND active = 1");
    $lowStockSpareParts = $stmt->fetch()['total'];
    
    // Devoluciones por garantía del mes
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM warranty_returns WHERE group_origin = 'Spare Parts' AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
    $monthlyReturns = $stmt->fetch()['total'];
    
    // Mis ventas recientes
    $stmt = $pdo->prepare("
        SELECT so.*, 
               COUNT(sod.id) as total_products,
               SUM(sod.total_price) as total_amount
        FROM sales_orders so 
        LEFT JOIN sales_order_details sod ON so.id = sod.sales_order_id
        WHERE so.seller_id = ?
        GROUP BY so.id
        ORDER BY so.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$currentUser['id']]);
    $myRecentSales = $stmt->fetchAll();
    
    // Productos más vendidos
    $stmt = $pdo->query("
        SELECT p.name, p.sku, SUM(sod.quantity) as total_sold
        FROM products p
        INNER JOIN sales_order_details sod ON p.id = sod.product_id
        INNER JOIN sales_orders so ON sod.sales_order_id = so.id
        WHERE MONTH(so.created_at) = MONTH(CURRENT_DATE()) AND YEAR(so.created_at) = YEAR(CURRENT_DATE())
        GROUP BY p.id
        ORDER BY total_sold DESC
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
            <h1 class="page-title">Dashboard Spare Parts - Ventas</h1>
            <p class="page-subtitle">Panel de control para vendedores y gestión de ventas</p>
        </div>
    </div>
</div>

<?php if (isset($error)): ?>
<div class="row">
    <div class="col-12">
        <div class="alert alert-danger-modern"><?php echo $error; ?></div>
    </div>
</div>
<?php endif; ?>

<!-- Estadísticas principales -->
<div class="row mb-4">
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="stats-card">
            <div class="stats-number"><?php echo $myMonthlySales ?? 0; ?></div>
            <div class="stats-label">Mis Ventas del Mes</div>
        </div>
    </div>
    
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="stats-card" style="background: linear-gradient(135deg, #27ae60 0%, #229954 100%);">
            <div class="stats-number"><?php echo $totalMonthlySales ?? 0; ?></div>
            <div class="stats-label">Total Ventas del Mes</div>
        </div>
    </div>
    
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="stats-card" style="background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);">
            <div class="stats-number"><?php echo $pendingRequests ?? 0; ?></div>
            <div class="stats-label">Solicitudes Pendientes</div>
        </div>
    </div>
    
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="stats-card" style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);">
            <div class="stats-number"><?php echo $sparePartsStock; ?></div>
            <div class="stats-label">Stock Spare Parts</div>
        </div>
    </div>
    
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="stats-card" style="background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);">
            <div class="stats-number"><?php echo $lowStockSpareParts ?? 0; ?></div>
            <div class="stats-label">Stock Bajo SP</div>
        </div>
    </div>
    
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="stats-card" style="background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);">
            <div class="stats-number"><?php echo $monthlyReturns ?? 0; ?></div>
            <div class="stats-label">Devoluciones</div>
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
                        <a href="../admin/inventory.php" class="btn btn-primary-modern w-100">
                            <span>📦</span> Ver Inventario
                        </a>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="sales_orders.php" class="btn btn-success-modern w-100">
                            <span>💰</span> Órdenes de Venta
                        </a>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="returns.php" class="btn btn-warning-modern w-100">
                            <span>↩️</span> Devoluciones
                        </a>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="repuesto_requests.php" class="btn btn-outline-modern w-100">
                            <span>🔧</span> Solicitar Repuestos
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Mis ventas recientes -->
    <div class="col-lg-8 mb-4">
        <div class="card-modern">
            <div class="card-header-modern">
                <h5 class="mb-0">Mis Ventas Recientes</h5>
            </div>
            <div class="card-body-modern">
                <?php if (!empty($myRecentSales)): ?>
                <div class="table-responsive">
                    <table class="table table-modern">
                        <thead>
                            <tr>
                                <th>Número Orden</th>
                                <th>Cliente</th>
                                <th>Estado</th>
                                <th>Productos</th>
                                <th>Total</th>
                                <th>Fecha</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($myRecentSales as $sale): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($sale['order_number']); ?></strong></td>
                                <td><?php echo htmlspecialchars($sale['customer_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <span class="badge badge-modern 
                                        <?php 
                                        switch($sale['status']) {
                                            case 'Pendiente': echo 'bg-warning'; break;
                                            case 'Completada': echo 'bg-success'; break;
                                            case 'Cancelada': echo 'bg-danger'; break;
                                            default: echo 'bg-secondary';
                                        }
                                        ?>">
                                        <?php echo htmlspecialchars($sale['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo $sale['total_products']; ?> productos</td>
                                <td><strong><?php echo formatPrice($sale['total_amount'] ?? 0); ?></strong></td>
                                <td><?php echo formatDate($sale['created_at']); ?></td>
                                <td>
                                    <a href="sales_orders.php?view=<?php echo $sale['id']; ?>" class="btn btn-sm btn-outline-modern">
                                        Ver
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="text-center mt-3">
                    <a href="sales_orders.php" class="btn btn-outline-modern">Ver Todas las Ventas</a>
                </div>
                <?php else: ?>
                <div class="text-center py-4">
                    <p class="text-muted">No tienes ventas recientes</p>
                    <a href="sales_orders.php" class="btn btn-primary-modern">Crear Nueva Venta</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Productos más vendidos -->
    <div class="col-lg-4 mb-4">
        <div class="card-modern">
            <div class="card-header-modern">
                <h5 class="mb-0">Productos Más Vendidos</h5>
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
                                <span class="badge bg-success"><?php echo $product['total_sold']; ?> vendidos</span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="text-center py-4">
                    <p class="text-muted">No hay datos de ventas este mes</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Alertas específicas para Spare Parts -->
<?php if ($lowStockSpareParts > 0): ?>
<div class="row">
    <div class="col-12">
        <div class="alert alert-warning-modern">
            <h6 class="mb-2">⚠️ Alerta de Stock Bajo en Spare Parts</h6>
            <p class="mb-2">Hay <strong><?php echo $lowStockSpareParts; ?></strong> productos con stock bajo en el almacén Spare Parts.</p>
            <a href="../admin/inventory.php?warehouse=spare_parts&filter=low_stock" class="btn btn-warning btn-sm">Ver Productos</a>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($pendingRequests > 0): ?>
<div class="row">
    <div class="col-12">
        <div class="alert alert-info-modern">
            <h6 class="mb-2">📋 Solicitudes Pendientes</h6>
            <p class="mb-2">Hay <strong><?php echo $pendingRequests; ?></strong> solicitudes de repuestos pendientes de atención.</p>
            <a href="repuesto_requests.php?status=pending" class="btn btn-info btn-sm">Ver Solicitudes</a>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>

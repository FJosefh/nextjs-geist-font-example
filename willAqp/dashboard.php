<?php
$pageTitle = "Dashboard WillAQP";
require_once '../includes/header.php';
checkRole(['WillAQP']);

// Obtener estadísticas específicas para WillAQP (similar a Will)
try {
    // Total de órdenes RS pendientes
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM rs_orders WHERE status = 'Pendiente'");
    $pendingRS = $stmt->fetch()['total'];

    // Total de solicitudes pendientes para WillAQP
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM repuesto_requests WHERE group_origin = 'WillAQP' AND status = 'Pendiente'");
    $pendingRequests = $stmt->fetch()['total'];

    // Stock en almacén WillAQP (asumiendo almacén 'will_aqp')
    $stmt = $pdo->query("SELECT SUM(stock_will) as total FROM products WHERE active = 1");
    $willAqpStock = $stmt->fetch()['total'] ?? 0;

    // Productos con stock bajo en WillAQP
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM products WHERE stock_will < 3 AND active = 1");
    $lowStockWillAqp = $stmt->fetch()['total'];

} catch (PDOException $e) {
    $error = "Error al cargar estadísticas: " . $e->getMessage();
}
?>

<div class="row">
    <div class="col-12">
        <div class="page-header">
            <h1 class="page-title">Dashboard WillAQP</h1>
            <p class="page-subtitle">Panel de control para WillAQP</p>
        </div>
    </div>
</div>

<?php if (isset($error)): ?>
<div class="alert alert-danger-modern"><?php echo $error; ?></div>
<?php endif; ?>

<!-- Estadísticas principales -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="stats-card">
            <div class="stats-number"><?php echo $pendingRS ?? 0; ?></div>
            <div class="stats-label">Órdenes RS Pendientes</div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="stats-card" style="background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);">
            <div class="stats-number"><?php echo $pendingRequests ?? 0; ?></div>
            <div class="stats-label">Solicitudes Pendientes</div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="stats-card" style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);">
            <div class="stats-number"><?php echo $willAqpStock; ?></div>
            <div class="stats-label">Stock WillAQP</div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="stats-card" style="background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);">
            <div class="stats-number"><?php echo $lowStockWillAqp ?? 0; ?></div>
            <div class="stats-label">Stock Bajo WillAQP</div>
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
                        <a href="../will/rs_orders.php" class="btn btn-success-modern w-100">
                            <span>📋</span> Órdenes RS
                        </a>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="../will/repuesto_requests.php" class="btn btn-warning-modern w-100">
                            <span>🔧</span> Solicitudes
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

<?php require_once '../includes/footer.php'; ?>

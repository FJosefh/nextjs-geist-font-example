<?php
$pageTitle = "Dashboard Will - Soporte Técnico";
require_once '../includes/header.php';
checkRole(['Will']);

// Obtener estadísticas específicas para Will
try {
    // RS pendientes asignadas al usuario actual
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM rs_orders WHERE technician_id = ? AND status = 'Pendiente'");
    $stmt->execute([$currentUser['id']]);
    $myPendingRS = $stmt->fetch()['total'];
    
    // Total RS del mes
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM rs_orders WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
    $monthlyRS = $stmt->fetch()['total'];
    
    // Solicitudes pendientes
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM repuesto_requests WHERE group_origin = 'Will' AND status = 'Pendiente'");
    $pendingRequests = $stmt->fetch()['total'];
    
    // Stock en almacén Will
    $stmt = $pdo->query("SELECT SUM(stock_will) as total FROM products WHERE active = 1");
    $willStock = $stmt->fetch()['total'] ?? 0;
    
    // Productos con stock bajo en Will
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM products WHERE stock_will < 3 AND active = 1");
    $lowStockWill = $stmt->fetch()['total'];
    
    // Mis RS recientes
    $stmt = $pdo->prepare("
        SELECT rs.*, 
               COUNT(rsd.id) as total_products
        FROM rs_orders rs 
        LEFT JOIN rs_order_details rsd ON rs.id = rsd.rs_order_id
        WHERE rs.technician_id = ?
        GROUP BY rs.id
        ORDER BY rs.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$currentUser['id']]);
    $myRecentRS = $stmt->fetchAll();
    
    // Solicitudes recientes de repuestos
    $stmt = $pdo->prepare("
        SELECT rr.*, u.name as requester_name,
               COUNT(rrd.id) as total_products
        FROM repuesto_requests rr
        LEFT JOIN users u ON rr.requester_id = u.id
        LEFT JOIN repuesto_request_details rrd ON rr.id = rrd.request_id
        WHERE rr.group_origin = 'Will'
        GROUP BY rr.id
        ORDER BY rr.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $recentRequests = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = "Error al cargar estadísticas: " . $e->getMessage();
}
?>

<div class="row">
    <div class="col-12">
        <div class="page-header">
            <h1 class="page-title">Dashboard Will - Soporte Técnico</h1>
            <p class="page-subtitle">Panel de control para técnicos, supervisores y almaceneros</p>
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
            <div class="stats-number"><?php echo $myPendingRS ?? 0; ?></div>
            <div class="stats-label">Mis RS Pendientes</div>
        </div>
    </div>
    
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="stats-card" style="background: linear-gradient(135deg, #27ae60 0%, #229954 100%);">
            <div class="stats-number"><?php echo $monthlyRS ?? 0; ?></div>
            <div class="stats-label">RS del Mes</div>
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
            <div class="stats-number"><?php echo $willStock; ?></div>
            <div class="stats-label">Stock Will</div>
        </div>
    </div>
    
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="stats-card" style="background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);">
            <div class="stats-number"><?php echo $lowStockWill ?? 0; ?></div>
            <div class="stats-label">Stock Bajo Will</div>
        </div>
    </div>
    
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="stats-card" style="background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);">
            <div class="stats-number"><?php echo $currentUser['role_detail']; ?></div>
            <div class="stats-label">Mi Rol</div>
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
                    <?php if (hasPermission('create_rs')): ?>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="rs_orders.php" class="btn btn-success-modern w-100">
                            <span>📋</span> Órdenes RS
                        </a>
                    </div>
                    <?php endif; ?>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="repuesto_requests.php" class="btn btn-warning-modern w-100">
                            <span>🔧</span> Solicitar Repuestos
                        </a>
                    </div>
                    <?php if (hasPermission('universal_search')): ?>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="../global/search.php" class="btn btn-outline-modern w-100">
                            <span>🔍</span> Búsqueda Universal
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Mis RS recientes -->
    <div class="col-lg-8 mb-4">
        <div class="card-modern">
            <div class="card-header-modern">
                <h5 class="mb-0">Mis Órdenes RS Recientes</h5>
            </div>
            <div class="card-body-modern">
                <?php if (!empty($myRecentRS)): ?>
                <div class="table-responsive">
                    <table class="table table-modern">
                        <thead>
                            <tr>
                                <th>Número RS</th>
                                <th>Tipo</th>
                                <th>Estado</th>
                                <th>Productos</th>
                                <th>Fecha</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($myRecentRS as $rs): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($rs['rs_number']); ?></strong></td>
                                <td>
                                    <span class="badge badge-modern bg-info">
                                        <?php echo htmlspecialchars($rs['type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-modern 
                                        <?php 
                                        switch($rs['status']) {
                                            case 'Pendiente': echo 'bg-warning'; break;
                                            case 'En Proceso': echo 'bg-primary'; break;
                                            case 'Completada': echo 'bg-success'; break;
                                            case 'Cancelada': echo 'bg-danger'; break;
                                            default: echo 'bg-secondary';
                                        }
                                        ?>">
                                        <?php echo htmlspecialchars($rs['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo $rs['total_products']; ?> productos</td>
                                <td><?php echo formatDate($rs['created_at']); ?></td>
                                <td>
                                    <a href="rs_orders.php?view=<?php echo $rs['id']; ?>" class="btn btn-sm btn-outline-modern">
                                        Ver
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="text-center mt-3">
                    <a href="rs_orders.php" class="btn btn-outline-modern">Ver Todas las RS</a>
                </div>
                <?php else: ?>
                <div class="text-center py-4">
                    <p class="text-muted">No tienes órdenes RS recientes</p>
                    <?php if (hasPermission('create_rs')): ?>
                    <a href="rs_orders.php" class="btn btn-primary-modern">Crear Nueva RS</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Solicitudes recientes -->
    <div class="col-lg-4 mb-4">
        <div class="card-modern">
            <div class="card-header-modern">
                <h5 class="mb-0">Solicitudes Recientes</h5>
            </div>
            <div class="card-body-modern">
                <?php if (!empty($recentRequests)): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($recentRequests as $request): ?>
                    <div class="list-group-item border-0 px-0">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <h6 class="mb-1"><?php echo htmlspecialchars($request['request_number']); ?></h6>
                                <small class="text-muted">
                                    Por: <?php echo htmlspecialchars($request['requester_name']); ?>
                                </small>
                                <br>
                                <small class="text-muted">
                                    <?php echo $request['total_products']; ?> productos
                                </small>
                            </div>
                            <div class="text-end">
                                <span class="badge 
                                    <?php 
                                    switch($request['status']) {
                                        case 'Pendiente': echo 'bg-warning'; break;
                                        case 'Listo para recojo': echo 'bg-success'; break;
                                        case 'Completada': echo 'bg-primary'; break;
                                        case 'Cancelada': echo 'bg-danger'; break;
                                        default: echo 'bg-secondary';
                                    }
                                    ?>">
                                    <?php echo htmlspecialchars($request['status']); ?>
                                </span>
                                <br>
                                <small class="text-muted"><?php echo formatDate($request['created_at']); ?></small>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="text-center mt-3">
                    <a href="repuesto_requests.php" class="btn btn-outline-modern">Ver Todas</a>
                </div>
                <?php else: ?>
                <div class="text-center py-4">
                    <p class="text-muted">No hay solicitudes recientes</p>
                    <a href="repuesto_requests.php" class="btn btn-primary-modern">Nueva Solicitud</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Alertas específicas para Will -->
<?php if ($lowStockWill > 0): ?>
<div class="row">
    <div class="col-12">
        <div class="alert alert-warning-modern">
            <h6 class="mb-2">⚠️ Alerta de Stock Bajo en Will</h6>
            <p class="mb-2">Hay <strong><?php echo $lowStockWill; ?></strong> productos con stock bajo en el almacén Will.</p>
            <a href="../admin/inventory.php?warehouse=will&filter=low_stock" class="btn btn-warning btn-sm">Ver Productos</a>
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

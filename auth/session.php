<?php
// Gestión de sesiones y control de acceso
session_start();

// Función para verificar si el usuario está logueado
function checkLogin() {
    if (!isset($_SESSION['user'])) {
        header("Location: " . dirname($_SERVER['PHP_SELF']) . "/../index.php");
        exit();
    }
}

// Función para verificar roles específicos
function checkRole($allowedRoles) {
    checkLogin();
    
    $userRole = $_SESSION['user']['group_role'];
    $userRoleDetail = $_SESSION['user']['role_detail'];
    
    // El administrador siempre tiene acceso
    if ($userRole === 'Administrador') {
        return true;
    }
    
    // Verificar si el rol está en los permitidos
    if (is_array($allowedRoles)) {
        foreach ($allowedRoles as $role) {
            if ($userRole === $role || $userRoleDetail === $role) {
                return true;
            }
        }
    } else {
        if ($userRole === $allowedRoles || $userRoleDetail === $allowedRoles) {
            return true;
        }
    }
    
    // Si no tiene permisos, mostrar error
    http_response_code(403);
    die("
    <div style='text-align: center; margin-top: 50px; font-family: Arial, sans-serif;'>
        <h2>Acceso Denegado</h2>
        <p>No tienes permisos para acceder a esta página.</p>
        <a href='../index.php' style='color: #007bff; text-decoration: none;'>Volver al inicio</a>
    </div>
    ");
}

// Función para obtener información del usuario actual
function getCurrentUser() {
    checkLogin();
    return $_SESSION['user'];
}

// Función para verificar si el usuario puede editar inventario
function canEditInventory() {
    $user = getCurrentUser();
    $allowedRoles = ['Administrador', 'Supervisor', 'Almacenero', 'Vendedor'];
    
    return in_array($user['group_role'], $allowedRoles) || 
           in_array($user['role_detail'], $allowedRoles);
}

// Función para verificar si el usuario puede reducir stock
function canReduceStock() {
    $user = getCurrentUser();
    return $user['group_role'] === 'Administrador';
}

// Función para obtener el almacén principal del usuario
function getUserWarehouse() {
    $user = getCurrentUser();
    
    switch ($user['group_role']) {
        case 'Will':
        case 'WillAQP':
            return 'will';
        case 'Spare Parts':
            return 'spare_parts';
        case 'Administrador':
            return 'callao'; // Por defecto
        default:
            return null;
    }
}

// Función para verificar permisos específicos por acción
function hasPermission($action) {
    $user = getCurrentUser();
    $role = $user['group_role'];
    $roleDetail = $user['role_detail'];
    
    $permissions = [
        'view_inventory' => ['Administrador', 'Will', 'Spare Parts', 'Global'],
        'edit_product_name' => ['Administrador', 'Supervisor', 'Almacenero', 'Vendedor'],
        'increase_stock' => ['Administrador', 'Supervisor', 'Almacenero', 'Vendedor'],
        'reduce_stock' => ['Administrador'],
        'upload_excel' => ['Administrador', 'Supervisor', 'Almacenero', 'Vendedor'],
        'create_rs' => ['Técnico', 'Supervisor', 'Almacenero'],
        'assign_repuestos' => ['Almacenero'],
        'create_sales_order' => ['Vendedor'],
        'manage_users' => ['Administrador'],
        'universal_search' => ['Administrador', 'Supervisor', 'Motorizado'],
        'view_traceability' => ['Administrador'],
        'request_repuestos' => ['Técnico', 'Supervisor', 'Almacenero', 'Vendedor']
    ];
    
    if (!isset($permissions[$action])) {
        return false;
    }
    
    return in_array($role, $permissions[$action]) || 
           in_array($roleDetail, $permissions[$action]);
}

// Función para generar números únicos para órdenes
function generateOrderNumber($prefix = '') {
    return $prefix . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

// Función para formatear fecha
function formatDate($date) {
    return date('d/m/Y H:i', strtotime($date));
}

// Función para formatear precio
function formatPrice($price) {
    return 'S/ ' . number_format($price, 2);
}
?>

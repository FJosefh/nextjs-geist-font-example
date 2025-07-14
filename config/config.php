<?php
// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'inventory_db');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error de conexión a la base de datos: " . $e->getMessage());
}

// Configuraciones generales
define('SITE_URL', 'http://localhost:8000');
define('SITE_NAME', 'Sistema de Inventario');

// Grupos y roles
define('GROUPS', [
    'Administrador' => ['Admin'],
    'Will' => ['Técnico', 'Supervisor', 'Almacenero'],
    'Spare Parts' => ['Vendedor'],
    'Global' => ['Motorizado']
]);

// Almacenes
define('WAREHOUSES', [
    'Callao' => 'callao',
    'Spare Parts' => 'spare_parts', 
    'Will' => 'will'
]);
?>

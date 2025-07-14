<?php
require_once '../config/config.php';
session_start();

// Si ya está logueado, redirigir al dashboard correspondiente
if (isset($_SESSION['user'])) {
    $group = $_SESSION['user']['group_role'];
    switch ($group) {
        case 'Administrador':
            header("Location: ../admin/dashboard.php");
            break;
        case 'Will':
            header("Location: ../will/dashboard.php");
            break;
        case 'Spare Parts':
            header("Location: ../spareparts/dashboard.php");
            break;
        case 'Global':
            header("Location: ../global/search.php");
            break;
        default:
            header("Location: ../index.php");
    }
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Por favor, ingrese usuario y contraseña.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND active = 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // Login exitoso
                $_SESSION['user'] = [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'group_role' => $user['group_role'],
                    'role_detail' => $user['role_detail']
                ];
                
                // Registrar el login en los movimientos (opcional)
                $stmt = $pdo->prepare("INSERT INTO inventory_movements (product_id, user_id, action, quantity, motive, reference_type) VALUES (0, ?, 'entrada', 0, 'Login al sistema', 'manual')");
                $stmt->execute([$user['id']]);
                
                // Redirigir según el grupo
                switch ($user['group_role']) {
                    case 'Administrador':
                        header("Location: ../admin/dashboard.php");
                        break;
                    case 'Will':
                        header("Location: ../will/dashboard.php");
                        break;
                    case 'Spare Parts':
                        header("Location: ../spareparts/dashboard.php");
                        break;
                    case 'Global':
                        header("Location: ../global/search.php");
                        break;
                    default:
                        header("Location: ../index.php");
                }
                exit();
            } else {
                $error = 'Usuario o contraseña incorrectos.';
            }
        } catch (PDOException $e) {
            $error = 'Error en el sistema. Intente nuevamente.';
            error_log("Login error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            width: 100%;
            max-width: 400px;
        }
        
        .login-header {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .login-header h1 {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
        }
        
        .login-header p {
            margin: 0.5rem 0 0 0;
            opacity: 0.8;
            font-size: 0.9rem;
        }
        
        .login-body {
            padding: 2rem;
        }
        
        .form-floating {
            margin-bottom: 1rem;
        }
        
        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 0.75rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 0.75rem;
            font-weight: 500;
            width: 100%;
            transition: transform 0.2s ease;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .alert {
            border-radius: 10px;
            border: none;
        }
        
        .group-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            margin-top: 1rem;
            font-size: 0.85rem;
        }
        
        .group-info h6 {
            color: #495057;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .group-info ul {
            margin: 0;
            padding-left: 1rem;
        }
        
        .group-info li {
            color: #6c757d;
            margin-bottom: 0.25rem;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1><?php echo SITE_NAME; ?></h1>
            <p>Sistema de Gestión de Inventario</p>
        </div>
        
        <div class="login-body">
            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-floating">
                    <input type="text" class="form-control" id="username" name="username" placeholder="Usuario" required>
                    <label for="username">Usuario</label>
                </div>
                
                <div class="form-floating">
                    <input type="password" class="form-control" id="password" name="password" placeholder="Contraseña" required>
                    <label for="password">Contraseña</label>
                </div>
                
                <button type="submit" class="btn btn-primary btn-login">
                    Iniciar Sesión
                </button>
            </form>
            
            <div class="group-info">
                <h6>Grupos de Acceso:</h6>
                <ul>
                    <li><strong>Administrador:</strong> Acceso completo</li>
                    <li><strong>Will:</strong> Técnico, Supervisor, Almacenero</li>
                    <li><strong>Spare Parts:</strong> Vendedor</li>
                    <li><strong>Global:</strong> Motorizado (solo búsqueda)</li>
                </ul>
                <small class="text-muted">
                    <strong>Usuario de prueba:</strong> admin / password
                </small>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

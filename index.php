<?php
require_once 'config/config.php';
session_start();

// Si ya está logueado, redirigir al dashboard correspondiente
if (isset($_SESSION['user'])) {
    $group = $_SESSION['user']['group_role'];
    switch ($group) {
        case 'Administrador':
            header("Location: admin/dashboard.php");
            break;
        case 'Will':
            header("Location: will/dashboard.php");
            break;
        case 'Spare Parts':
            header("Location: spareparts/dashboard.php");
            break;
        case 'Global':
            header("Location: global/search.php");
            break;
        default:
            // Limpiar sesión si hay un grupo inválido
            session_destroy();
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Iniciar Sesión</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }
        
        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
            overflow: hidden;
            width: 100%;
            max-width: 450px;
            animation: fadeInUp 0.6s ease-out;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .login-header {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            padding: 2.5rem 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .login-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }
        
        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .login-header h1 {
            font-size: 1.75rem;
            font-weight: 700;
            margin: 0;
            position: relative;
            z-index: 1;
        }
        
        .login-header p {
            margin: 0.75rem 0 0 0;
            opacity: 0.9;
            font-size: 1rem;
            font-weight: 400;
            position: relative;
            z-index: 1;
        }
        
        .login-body {
            padding: 2.5rem 2rem;
        }
        
        .form-floating {
            margin-bottom: 1.5rem;
        }
        
        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 1rem 0.75rem;
            font-size: 1rem;
            transition: all 0.3s ease;
            background-color: #f8f9fa;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.25rem rgba(102, 126, 234, 0.15);
            background-color: white;
        }
        
        .form-floating > label {
            color: #6c757d;
            font-weight: 500;
        }
        
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 12px;
            padding: 1rem;
            font-weight: 600;
            font-size: 1.1rem;
            width: 100%;
            transition: all 0.3s ease;
            color: white;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
            color: white;
        }
        
        .btn-login:active {
            transform: translateY(-1px);
        }
        
        .alert {
            border-radius: 12px;
            border: none;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
            color: white;
        }
        
        .system-info {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 15px;
            padding: 1.5rem;
            margin-top: 2rem;
            border: 1px solid #dee2e6;
        }
        
        .system-info h6 {
            color: #495057;
            font-weight: 600;
            margin-bottom: 1rem;
            font-size: 1rem;
        }
        
        .group-list {
            display: grid;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }
        
        .group-item {
            display: flex;
            align-items: center;
            padding: 0.75rem;
            background: white;
            border-radius: 10px;
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
        }
        
        .group-item:hover {
            border-color: #667eea;
            transform: translateX(5px);
        }
        
        .group-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-weight: bold;
            color: white;
            font-size: 0.9rem;
        }
        
        .group-admin { background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); }
        .group-will { background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); }
        .group-spare { background: linear-gradient(135deg, #27ae60 0%, #229954 100%); }
        .group-global { background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%); }
        
        .group-details h6 {
            margin: 0;
            font-size: 0.9rem;
            color: #2c3e50;
            font-weight: 600;
        }
        
        .group-details p {
            margin: 0;
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        .demo-credentials {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem;
            border-radius: 10px;
            text-align: center;
            margin-top: 1rem;
        }
        
        .demo-credentials strong {
            font-size: 1.1rem;
        }
        
        .demo-credentials code {
            background: rgba(255,255,255,0.2);
            padding: 0.25rem 0.5rem;
            border-radius: 5px;
            color: white;
        }
        
        @media (max-width: 576px) {
            .login-container {
                margin: 1rem;
                max-width: none;
            }
            
            .login-header {
                padding: 2rem 1.5rem;
            }
            
            .login-body {
                padding: 2rem 1.5rem;
            }
            
            .login-header h1 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1><?php echo SITE_NAME; ?></h1>
            <p>Sistema de Gestión de Inventario Empresarial</p>
        </div>
        
        <div class="login-body">
            <form method="POST" action="auth/login.php" id="loginForm">
                <div class="form-floating">
                    <input type="text" class="form-control" id="username" name="username" placeholder="Usuario" required autocomplete="username">
                    <label for="username">Usuario</label>
                </div>
                
                <div class="form-floating">
                    <input type="password" class="form-control" id="password" name="password" placeholder="Contraseña" required autocomplete="current-password">
                    <label for="password">Contraseña</label>
                </div>
                
                <button type="submit" class="btn btn-login">
                    Iniciar Sesión
                </button>
            </form>
            
            <div class="system-info">
                <h6>Grupos de Acceso del Sistema</h6>
                
                <div class="group-list">
                    <div class="group-item">
                        <div class="group-icon group-admin">ADM</div>
                        <div class="group-details">
                            <h6>Administrador</h6>
                            <p>Acceso completo a todas las funciones del sistema</p>
                        </div>
                    </div>
                    
                    <div class="group-item">
                        <div class="group-icon group-will">WILL</div>
                        <div class="group-details">
                            <h6>Will (Soporte Técnico)</h6>
                            <p>Técnico, Supervisor, Almacenero - Gestión de RS y repuestos</p>
                        </div>
                    </div>
                    
                    <div class="group-item">
                        <div class="group-icon group-spare">SP</div>
                        <div class="group-details">
                            <h6>Spare Parts (Ventas)</h6>
                            <p>Vendedor - Órdenes de venta y devoluciones</p>
                        </div>
                    </div>
                    
                    <div class="group-item">
                        <div class="group-icon group-global">GLB</div>
                        <div class="group-details">
                            <h6>Global (Motorizado)</h6>
                            <p>Búsqueda universal y consulta de stock</p>
                        </div>
                    </div>
                </div>
                
                <div class="demo-credentials">
                    <strong>Credenciales de Prueba</strong><br>
                    Usuario: <code>admin</code> | Contraseña: <code>password</code>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Auto-focus en el campo de usuario
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('username').focus();
            
            // Animación de entrada para los elementos
            const groupItems = document.querySelectorAll('.group-item');
            groupItems.forEach((item, index) => {
                item.style.opacity = '0';
                item.style.transform = 'translateX(-20px)';
                setTimeout(() => {
                    item.style.transition = 'all 0.5s ease';
                    item.style.opacity = '1';
                    item.style.transform = 'translateX(0)';
                }, 200 + (index * 100));
            });
        });
        
        // Validación del formulario
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            
            if (!username || !password) {
                e.preventDefault();
                alert('Por favor, ingrese usuario y contraseña.');
                return false;
            }
            
            // Mostrar indicador de carga
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Iniciando sesión...';
            submitBtn.disabled = true;
            
            // Si hay error, restaurar el botón después de un tiempo
            setTimeout(() => {
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            }, 5000);
        });
        
        // Efecto de teclas para mejorar UX
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                const form = document.getElementById('loginForm');
                if (form) {
                    form.dispatchEvent(new Event('submit'));
                }
            }
        });
    </script>
</body>
</html>

<?php
require_once 'config.php';

// Verificar si el usuario ya está logueado
if (isLoggedIn()) {
    redirectByRole();
}

$error = '';

// Procesar el formulario cuando se envía
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = cleanInput($_POST['email']);
    $password = $_POST['password'];
    
    // Validar campos
    if (empty($email) || empty($password)) {
        $error = "Por favor, complete todos los campos.";
    } else {
        // Verificar credenciales
        $sql = "SELECT id, nombre, apellido, email, password, rol FROM usuarios WHERE email = ?";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $email);
            
            if ($stmt->execute()) {
                $stmt->store_result();
                
                if ($stmt->num_rows == 1) {
                    $stmt->bind_result($id, $nombre, $apellido, $email, $hashed_password, $rol);
                    
                    if ($stmt->fetch()) {
                        if (password_verify($password, $hashed_password)) {
                            // Iniciar sesión
                            $_SESSION['user_id'] = $id;
                            $_SESSION['user_name'] = $nombre . ' ' . $apellido;
                            $_SESSION['user_email'] = $email;
                            $_SESSION['user_role'] = $rol;
                            
                            // Redirigir según el rol
                            redirectByRole();
                        } else {
                            $error = "La contraseña ingresada es incorrecta.";
                        }
                    }
                } else {
                    $error = "No existe una cuenta con ese correo electrónico.";
                }
            } else {
                $error = "Ocurrió un error. Por favor intente nuevamente.";
            }
            
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Sistema de Autoevaluación</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .login-container {
            max-width: 400px;
            margin: 100px auto;
        }
        .card {
            border-radius: 1rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .card-header {
            background-color: #343a40;
            color: white;
            border-radius: 1rem 1rem 0 0 !important;
            padding: 1.5rem;
        }
        .btn-primary {
            background-color: #343a40;
            border-color: #343a40;
        }
        .btn-primary:hover {
            background-color: #23272b;
            border-color: #23272b;
        }
    </style>
</head>
<body>
    <div class="container login-container">
        <div class="card">
            <div class="card-header text-center">
                <h3 class="mb-0">Sistema de Autoevaluación</h3>
            </div>
            <div class="card-body p-4">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <div class="mb-3">
                        <label for="email" class="form-label">Correo Electrónico</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Contraseña</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">Iniciar Sesión</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
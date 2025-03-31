<?php
// Este archivo permite crear usuarios de prueba para diferentes roles
// IMPORTANTE: Por seguridad, deberías eliminar este archivo después de usarlo

// Incluir archivo de configuración
require_once 'config.php';

// Inicializar variables
$mensaje = '';
$nombre = '';
$apellido = '';
$email = '';
$password = '';
$rol = 'empleado'; // Valor por defecto

// Procesar el formulario cuando se envía
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['crear_usuario'])) {
    $nombre = cleanInput($_POST['nombre']);
    $apellido = cleanInput($_POST['apellido']);
    $email = cleanInput($_POST['email']);
    $password = $_POST['password'];
    $rol = $_POST['rol'];
    
    // Validar que los campos no estén vacíos
    if (empty($nombre) || empty($apellido) || empty($email) || empty($password)) {
        $mensaje = '<div class="alert alert-danger">Por favor, complete todos los campos obligatorios.</div>';
    } else {
        // Verificar si ya existe un usuario con ese email
        $sql = "SELECT id FROM usuarios WHERE email = ?";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $email);
            
            if ($stmt->execute()) {
                $stmt->store_result();
                
                if ($stmt->num_rows > 0) {
                    $mensaje = '<div class="alert alert-danger">Ya existe un usuario con ese correo electrónico.</div>';
                } else {
                    // Generar el hash de la contraseña
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Insertar el nuevo usuario
                    $insert_sql = "INSERT INTO usuarios (nombre, apellido, email, password, rol) VALUES (?, ?, ?, ?, ?)";
                    
                    if ($insert_stmt = $conn->prepare($insert_sql)) {
                        $insert_stmt->bind_param("sssss", $nombre, $apellido, $email, $hashed_password, $rol);
                        
                        if ($insert_stmt->execute()) {
                            $nuevo_usuario_id = $insert_stmt->insert_id;
                            $mensaje = '<div class="alert alert-success">Usuario creado con éxito! ID: ' . $nuevo_usuario_id . '</div>';
                            
                            // Limpiar el formulario después de una creación exitosa
                            $nombre = '';
                            $apellido = '';
                            $email = '';
                            $password = '';
                            $rol = 'empleado';
                            
                            // Si es necesario, crear asignaciones para el nuevo usuario
                            if ($rol === 'empleado' && isset($_POST['lider_id']) && !empty($_POST['lider_id'])) {
                                $lider_id = intval($_POST['lider_id']);
                                
                                $asignacion_sql = "INSERT INTO lider_empleado (id_lider, id_empleado) VALUES (?, ?)";
                                
                                if ($asignacion_stmt = $conn->prepare($asignacion_sql)) {
                                    $asignacion_stmt->bind_param("ii", $lider_id, $nuevo_usuario_id);
                                    
                                    if ($asignacion_stmt->execute()) {
                                        $mensaje .= '<div class="alert alert-success">Empleado asignado correctamente al líder seleccionado.</div>';
                                    } else {
                                        $mensaje .= '<div class="alert alert-warning">No se pudo asignar el empleado al líder: ' . $asignacion_stmt->error . '</div>';
                                    }
                                    
                                    $asignacion_stmt->close();
                                }
                            } else if ($rol === 'lider' && isset($_POST['gerente_id']) && !empty($_POST['gerente_id'])) {
                                $gerente_id = intval($_POST['gerente_id']);
                                
                                $asignacion_sql = "INSERT INTO gerente_lider (id_gerente, id_lider) VALUES (?, ?)";
                                
                                if ($asignacion_stmt = $conn->prepare($asignacion_sql)) {
                                    $asignacion_stmt->bind_param("ii", $gerente_id, $nuevo_usuario_id);
                                    
                                    if ($asignacion_stmt->execute()) {
                                        $mensaje .= '<div class="alert alert-success">Líder asignado correctamente al gerente seleccionado.</div>';
                                    } else {
                                        $mensaje .= '<div class="alert alert-warning">No se pudo asignar el líder al gerente: ' . $asignacion_stmt->error . '</div>';
                                    }
                                    
                                    $asignacion_stmt->close();
                                }
                            }
                        } else {
                            $mensaje = '<div class="alert alert-danger">Error al crear el usuario: ' . $insert_stmt->error . '</div>';
                        }
                        
                        $insert_stmt->close();
                    }
                }
            } else {
                $mensaje = '<div class="alert alert-danger">Ocurrió un error al verificar el email: ' . $stmt->error . '</div>';
            }
            
            $stmt->close();
        }
    }
}

// Crear usuarios predeterminados para cada rol
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['crear_predeterminados'])) {
    $usuarios_predeterminados = [
        [
            'nombre' => 'Juan',
            'apellido' => 'Empleado',
            'email' => 'empleado@sistema.com',
            'password' => 'empleado123',
            'rol' => 'empleado'
        ],
        [
            'nombre' => 'María',
            'apellido' => 'Líder',
            'email' => 'lider@sistema.com',
            'password' => 'lider123',
            'rol' => 'lider'
        ],
        [
            'nombre' => 'Pedro',
            'apellido' => 'Gerente',
            'email' => 'gerente@sistema.com',
            'password' => 'gerente123',
            'rol' => 'gerente'
        ]
    ];
    
    $creados = 0;
    $errores = 0;
    $mensaje_detalles = '';
    
    foreach ($usuarios_predeterminados as $usuario) {
        // Verificar si ya existe
        $sql = "SELECT id FROM usuarios WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $usuario['email']);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $stmt->bind_result($user_id);
            $stmt->fetch();
            
            // Actualizar contraseña
            $hashed_password = password_hash($usuario['password'], PASSWORD_DEFAULT);
            $update_sql = "UPDATE usuarios SET nombre = ?, apellido = ?, password = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("sssi", $usuario['nombre'], $usuario['apellido'], $hashed_password, $user_id);
            
            if ($update_stmt->execute()) {
                $creados++;
                $mensaje_detalles .= "<li>Usuario {$usuario['rol']} actualizado: {$usuario['email']} (contraseña: {$usuario['password']})</li>";
            } else {
                $errores++;
                $mensaje_detalles .= "<li class='text-danger'>Error al actualizar {$usuario['rol']}: {$update_stmt->error}</li>";
            }
            
            $update_stmt->close();
        } else {
            // Crear nuevo usuario
            $hashed_password = password_hash($usuario['password'], PASSWORD_DEFAULT);
            $insert_sql = "INSERT INTO usuarios (nombre, apellido, email, password, rol) VALUES (?, ?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("sssss", $usuario['nombre'], $usuario['apellido'], $usuario['email'], $hashed_password, $usuario['rol']);
            
            if ($insert_stmt->execute()) {
                $nuevo_id = $insert_stmt->insert_id;
                $creados++;
                $mensaje_detalles .= "<li>Usuario {$usuario['rol']} creado: {$usuario['email']} (contraseña: {$usuario['password']})</li>";
                
                // Si creamos un líder y un empleado, vamos a asignarlos
                if ($usuario['rol'] === 'lider') {
                    $lider_id = $nuevo_id;
                }
                
                if ($usuario['rol'] === 'empleado' && isset($lider_id)) {
                    $asignacion_sql = "INSERT INTO lider_empleado (id_lider, id_empleado) VALUES (?, ?)";
                    $asignacion_stmt = $conn->prepare($asignacion_sql);
                    $asignacion_stmt->bind_param("ii", $lider_id, $nuevo_id);
                    
                    if ($asignacion_stmt->execute()) {
                        $mensaje_detalles .= "<li>Empleado asignado al líder automáticamente</li>";
                    }
                    
                    $asignacion_stmt->close();
                }
                
                if ($usuario['rol'] === 'gerente') {
                    $gerente_id = $nuevo_id;
                }
                
                if ($usuario['rol'] === 'lider' && isset($gerente_id)) {
                    $asignacion_sql = "INSERT INTO gerente_lider (id_gerente, id_lider) VALUES (?, ?)";
                    $asignacion_stmt = $conn->prepare($asignacion_sql);
                    $asignacion_stmt->bind_param("ii", $gerente_id, $nuevo_id);
                    
                    if ($asignacion_stmt->execute()) {
                        $mensaje_detalles .= "<li>Líder asignado al gerente automáticamente</li>";
                    }
                    
                    $asignacion_stmt->close();
                }
            } else {
                $errores++;
                $mensaje_detalles .= "<li class='text-danger'>Error al crear {$usuario['rol']}: {$insert_stmt->error}</li>";
            }
            
            $insert_stmt->close();
        }
        
        $stmt->close();
    }
    
    // Crear un período activo si no existe ninguno
    $sql = "SELECT COUNT(*) as total FROM periodos WHERE estado = 'activo'";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    
    if ($row['total'] == 0) {
        $nombre_periodo = 'Evaluación ' . date('Y') . ' - Semestre ' . (date('n') <= 6 ? '1' : '2');
        $fecha_inicio = date('Y-m-d');
        $fecha_fin = date('Y-m-d', strtotime('+3 months'));
        
        $sql = "INSERT INTO periodos (nombre, fecha_inicio, fecha_fin, estado) VALUES (?, ?, ?, 'activo')";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("sss", $nombre_periodo, $fecha_inicio, $fecha_fin);
            
            if ($stmt->execute()) {
                $mensaje_detalles .= "<li>Período activo creado: {$nombre_periodo}</li>";
            } else {
                $mensaje_detalles .= "<li class='text-danger'>Error al crear período: {$stmt->error}</li>";
            }
            
            $stmt->close();
        }
    }
    
    if ($creados > 0) {
        $mensaje = '<div class="alert alert-success">Se han creado/actualizado ' . $creados . ' usuarios predeterminados.</div>';
        if ($errores > 0) {
            $mensaje .= '<div class="alert alert-warning">Hubo ' . $errores . ' errores durante el proceso.</div>';
        }
        $mensaje .= '<div class="alert alert-info"><strong>Detalles:</strong><ul>' . $mensaje_detalles . '</ul></div>';
    } else if ($errores > 0) {
        $mensaje = '<div class="alert alert-danger">No se pudo crear ningún usuario. Hubo ' . $errores . ' errores.</div>';
        $mensaje .= '<div><ul>' . $mensaje_detalles . '</ul></div>';
    } else {
        $mensaje = '<div class="alert alert-warning">No se realizó ninguna acción.</div>';
    }
}

// Obtener líderes para asignación
$lideres = [];
$sql = "SELECT id, nombre, apellido, email FROM usuarios WHERE rol = 'lider'";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $lideres[] = $row;
    }
}

// Obtener gerentes para asignación
$gerentes = [];
$sql = "SELECT id, nombre, apellido, email FROM usuarios WHERE rol = 'gerente'";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $gerentes[] = $row;
    }
}

// Cerrar la conexión
$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Usuario de Prueba - Sistema de Autoevaluación</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            padding-top: 50px;
            padding-bottom: 50px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        .card {
            border-radius: 1rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            margin-bottom: 30px;
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
        .btn-success {
            background-color: #28a745;
            border-color: #28a745;
        }
        .btn-success:hover {
            background-color: #218838;
            border-color: #1e7e34;
        }
        .security-notice {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 10px 15px;
            border-radius: 5px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="text-center mb-4">Crear Usuario de Prueba</h1>
        
        <?php if (!empty($mensaje)) echo $mensaje; ?>
        
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0">Crear Usuario Individual</h3>
            </div>
            <div class="card-body p-4">
                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="nombre" class="form-label">Nombre *</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" value="<?php echo $nombre; ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="apellido" class="form-label">Apellido *</label>
                            <input type="text" class="form-control" id="apellido" name="apellido" value="<?php echo $apellido; ?>" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Correo Electrónico *</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo $email; ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Contraseña *</label>
                        <input type="text" class="form-control" id="password" name="password" value="<?php echo $password; ?>" required>
                        <div class="form-text">La contraseña se guardará de forma segura utilizando password_hash().</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="rol" class="form-label">Rol *</label>
                        <select class="form-select" id="rol" name="rol" required onchange="mostrarAsignaciones()">
                            <option value="empleado" <?php if ($rol === 'empleado') echo 'selected'; ?>>Empleado</option>
                            <option value="lider" <?php if ($rol === 'lider') echo 'selected'; ?>>Líder</option>
                            <option value="gerente" <?php if ($rol === 'gerente') echo 'selected'; ?>>Gerente</option>
                            <option value="administrador" <?php if ($rol === 'administrador') echo 'selected'; ?>>Administrador</option>
                        </select>
                    </div>
                    
                    <div id="asignacion_lider" class="mb-3" style="display: <?php echo ($rol === 'empleado') ? 'block' : 'none'; ?>">
                        <label for="lider_id" class="form-label">Asignar a Líder</label>
                        <select class="form-select" id="lider_id" name="lider_id">
                            <option value="">Seleccione un líder (opcional)</option>
                            <?php foreach ($lideres as $lider): ?>
                                <option value="<?php echo $lider['id']; ?>">
                                    <?php echo $lider['nombre'] . ' ' . $lider['apellido'] . ' (' . $lider['email'] . ')'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Si no selecciona ningún líder, el empleado quedará sin asignar.</div>
                    </div>
                    
                    <div id="asignacion_gerente" class="mb-3" style="display: <?php echo ($rol === 'lider') ? 'block' : 'none'; ?>">
                        <label for="gerente_id" class="form-label">Asignar a Gerente</label>
                        <select class="form-select" id="gerente_id" name="gerente_id">
                            <option value="">Seleccione un gerente (opcional)</option>
                            <?php foreach ($gerentes as $gerente): ?>
                                <option value="<?php echo $gerente['id']; ?>">
                                    <?php echo $gerente['nombre'] . ' ' . $gerente['apellido'] . ' (' . $gerente['email'] . ')'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Si no selecciona ningún gerente, el líder quedará sin asignar.</div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" name="crear_usuario" class="btn btn-primary btn-lg">Crear Usuario</button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0">Crear Usuarios Predeterminados</h3>
            </div>
            <div class="card-body p-4">
                <p>Este botón creará o actualizará usuarios de prueba para cada rol (empleado, líder y gerente) con credenciales predeterminadas. También creará un período de evaluación activo si no existe ninguno.</p>
                
                <div class="mb-4">
                    <h5>Credenciales que se crearán:</h5>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Rol</th>
                                    <th>Email</th>
                                    <th>Contraseña</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Empleado</td>
                                    <td>empleado@sistema.com</td>
                                    <td>empleado123</td>
                                </tr>
                                <tr>
                                    <td>Líder</td>
                                    <td>lider@sistema.com</td>
                                    <td>lider123</td>
                                </tr>
                                <tr>
                                    <td>Gerente</td>
                                    <td>gerente@sistema.com</td>
                                    <td>gerente123</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="small text-muted">Nota: Si estos usuarios ya existen, se actualizarán sus contraseñas a los valores indicados.</p>
                </div>
                
                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <div class="d-grid gap-2">
                        <button type="submit" name="crear_predeterminados" class="btn btn-success btn-lg">Crear Usuarios Predeterminados</button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="security-notice">
            <h5><i class="bi bi-exclamation-triangle"></i> Advertencia de seguridad</h5>
            <p>Este archivo está destinado únicamente para la creación inicial de usuarios de prueba. Por razones de seguridad, elimínelo después de usarlo o protéjalo adecuadamente.</p>
        </div>
        
        <div class="text-center mt-4">
            <a href="login.php" class="btn btn-outline-secondary">Volver a la página de inicio de sesión</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function mostrarAsignaciones() {
            const rol = document.getElementById('rol').value;
            const asignacionLider = document.getElementById('asignacion_lider');
            const asignacionGerente = document.getElementById('asignacion_gerente');
            
            if (rol === 'empleado') {
                asignacionLider.style.display = 'block';
                asignacionGerente.style.display = 'none';
            } else if (rol === 'lider') {
                asignacionLider.style.display = 'none';
                asignacionGerente.style.display = 'block';
            } else {
                asignacionLider.style.display = 'none';
                asignacionGerente.style.display = 'none';
            }
        }
    </script>
</body>
</html>
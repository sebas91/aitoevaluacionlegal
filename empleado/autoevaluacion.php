<?php
require_once '../config.php';

// Verificar si el usuario está logueado y tiene el rol de empleado
if (!isLoggedIn() || !hasRole('empleado')) {
    header("Location: ../login.php");
    exit;
}

$userId = $_SESSION['user_id'];
$mensaje = '';
$periodoId = isset($_GET['periodo']) ? intval($_GET['periodo']) : 0;

// Verificar si existe el período seleccionado
if ($periodoId > 0) {
    $sql = "SELECT * FROM periodos WHERE id = ? AND estado = 'activo'";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $periodoId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            header("Location: dashboard.php");
            exit;
        }
        $periodo = $result->fetch_assoc();
        $stmt->close();
    }
} else {
    // Obtener el período activo más reciente
    $sql = "SELECT * FROM periodos WHERE estado = 'activo' ORDER BY fecha_inicio DESC LIMIT 1";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $periodo = $result->fetch_assoc();
        $periodoId = $periodo['id'];
    } else {
        $mensaje = "No hay períodos de evaluación activos.";
    }
}

// Verificar si ya existe una autoevaluación para este período
$autoevaluacionExistente = false;
if ($periodoId > 0) {
    $sql = "SELECT * FROM autoevaluacion_empleado WHERE id_usuario = ? AND id_periodo = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ii", $userId, $periodoId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $autoevaluacionExistente = true;
            $autoevaluacion = $result->fetch_assoc();
        }
        $stmt->close();
    }
}

// Procesar el formulario de autoevaluación
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_autoevaluacion'])) {
    // Obtener y validar datos del formulario
    $logro_objetivos = intval($_POST['logro_objetivos']);
    $trabajo_equipo = intval($_POST['trabajo_equipo']);
    $comunicacion_efectiva = intval($_POST['comunicacion_efectiva']);
    $resolucion_problemas = intval($_POST['resolucion_problemas']);
    $areas_mejora = cleanInput($_POST['areas_mejora']);
    $logros_destacados = cleanInput($_POST['logros_destacados']);
    
    // Validar rangos
    if ($logro_objetivos < 1 || $logro_objetivos > 5 || 
        $trabajo_equipo < 1 || $trabajo_equipo > 5 || 
        $comunicacion_efectiva < 1 || $comunicacion_efectiva > 5 || 
        $resolucion_problemas < 1 || $resolucion_problemas > 5) {
        $mensaje = "Los valores de la evaluación deben estar entre 1 y 5.";
    } else {
        // Guardar o actualizar la autoevaluación
        if ($autoevaluacionExistente) {
            $sql = "UPDATE autoevaluacion_empleado SET 
                    logro_objetivos = ?, 
                    trabajo_equipo = ?, 
                    comunicacion_efectiva = ?, 
                    resolucion_problemas = ?, 
                    areas_mejora = ?, 
                    logros_destacados = ? 
                    WHERE id_usuario = ? AND id_periodo = ?";
            
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("iiiiisii", 
                    $logro_objetivos, 
                    $trabajo_equipo, 
                    $comunicacion_efectiva, 
                    $resolucion_problemas, 
                    $areas_mejora, 
                    $logros_destacados, 
                    $userId, 
                    $periodoId);
                
                if ($stmt->execute()) {
                    $mensaje = "¡Tu autoevaluación ha sido actualizada con éxito!";
                } else {
                    $mensaje = "Error al actualizar la autoevaluación: " . $stmt->error;
                }
                
                $stmt->close();
            }
        } else {
            $sql = "INSERT INTO autoevaluacion_empleado 
                    (id_usuario, id_periodo, logro_objetivos, trabajo_equipo, comunicacion_efectiva, 
                    resolucion_problemas, areas_mejora, logros_destacados) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("iiiiiiss", 
                    $userId, 
                    $periodoId, 
                    $logro_objetivos, 
                    $trabajo_equipo, 
                    $comunicacion_efectiva, 
                    $resolucion_problemas, 
                    $areas_mejora, 
                    $logros_destacados);
                
                if ($stmt->execute()) {
                    $mensaje = "¡Tu autoevaluación ha sido guardada con éxito!";
                    $autoevaluacionExistente = true;
                    
                    // Actualizar datos de la autoevaluación
                    $sql = "SELECT * FROM autoevaluacion_empleado WHERE id_usuario = ? AND id_periodo = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ii", $userId, $periodoId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $autoevaluacion = $result->fetch_assoc();
                } else {
                    $mensaje = "Error al guardar la autoevaluación: " . $stmt->error;
                }
                
                $stmt->close();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Autoevaluación - Empleado</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .sidebar {
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
            padding: 48px 0 0;
            box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
            background-color: #343a40;
        }
        .sidebar-sticky {
            position: relative;
            top: 0;
            height: calc(100vh - 48px);
            overflow-x: hidden;
            overflow-y: auto;
        }
        .sidebar .nav-link {
            font-weight: 500;
            color: #fff;
            padding: 0.5rem 1rem;
        }
        .sidebar .nav-link:hover {
            color: #ccc;
        }
        .sidebar .nav-link.active {
            color: #007bff;
        }
        .content {
            margin-left: 240px;
            padding: 2rem;
        }
        .navbar {
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
        }
        body {
            padding-top: 56px;
        }
        .form-check-input[type="radio"] {
            margin-right: 5px;
        }
        .form-check-label {
            margin-right: 15px;
        }
    </style>
</head>
<body>
    <!-- Barra de navegación superior -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">Sistema de Autoevaluación</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <?php echo $_SESSION['user_name']; ?>
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="../perfil.php">Mi Perfil</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php">Cerrar Sesión</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Sidebar -->
    <nav class="sidebar" style="width: 240px;">
        <div class="sidebar-sticky">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">
                        <i class="bi bi-speedometer2 me-2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="autoevaluacion.php">
                        <i class="bi bi-clipboard-check me-2"></i> Nueva Autoevaluación
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="historial.php">
                        <i class="bi bi-clock-history me-2"></i> Historial
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <!-- Contenido principal -->
    <main class="content">
        <div class="container-fluid">
            <h1 class="mb-4">Autoevaluación</h1>
            
            <?php if (!empty($mensaje)): ?>
                <div class="alert <?php echo (strpos($mensaje, 'éxito') !== false) ? 'alert-success' : 'alert-danger'; ?> alert-dismissible fade show" role="alert">
                    <?php echo $mensaje; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($periodoId > 0): ?>
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">Período de Evaluación: <?php echo $periodo['nombre']; ?></h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Fecha de inicio:</strong> <?php echo date('d/m/Y', strtotime($periodo['fecha_inicio'])); ?></p>
                        <p><strong>Fecha de fin:</strong> <?php echo date('d/m/Y', strtotime($periodo['fecha_fin'])); ?></p>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="card-title mb-0">Formulario de Autoevaluación</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . "?periodo=" . $periodoId; ?>">
                            <div class="mb-4">
                                <h4>1. Logro de objetivos: ¿Qué tanto lograste los objetivos establecidos para este periodo?</h4>
                                <div class="d-flex flex-wrap">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <div class="form-check form-check-inline mb-2">
                                            <input class="form-check-input" type="radio" name="logro_objetivos" id="logro_<?php echo $i; ?>" value="<?php echo $i; ?>" 
                                                <?php if ($autoevaluacionExistente && $autoevaluacion['logro_objetivos'] == $i) echo 'checked'; ?> required>
                                            <label class="form-check-label" for="logro_<?php echo $i; ?>">
                                                <?php 
                                                switch ($i) {
                                                    case 1: echo "1 (Muy bajo)"; break;
                                                    case 2: echo "2 (Bajo)"; break;
                                                    case 3: echo "3 (Aceptable)"; break;
                                                    case 4: echo "4 (Bueno)"; break;
                                                    case 5: echo "5 (Excelente)"; break;
                                                }
                                                ?>
                                            </label>
                                        </div>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            
                            <h4>2. Competencias clave: Evalúa tu desempeño en las siguientes competencias:</h4>
                            
                            <div class="mb-3">
                                <h5>Trabajo en equipo:</h5>
                                <div class="d-flex flex-wrap">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <div class="form-check form-check-inline mb-2">
                                            <input class="form-check-input" type="radio" name="trabajo_equipo" id="trabajo_equipo_<?php echo $i; ?>" value="<?php echo $i; ?>" 
                                                <?php if ($autoevaluacionExistente && $autoevaluacion['trabajo_equipo'] == $i) echo 'checked'; ?> required>
                                            <label class="form-check-label" for="trabajo_equipo_<?php echo $i; ?>">
                                                <?php 
                                                switch ($i) {
                                                    case 1: echo "1 (Muy bajo)"; break;
                                                    case 2: echo "2 (Bajo)"; break;
                                                    case 3: echo "3 (Aceptable)"; break;
                                                    case 4: echo "4 (Bueno)"; break;
                                                    case 5: echo "5 (Excelente)"; break;
                                                }
                                                ?>
                                            </label>
                                        </div>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <h5>Comunicación efectiva:</h5>
                                <div class="d-flex flex-wrap">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <div class="form-check form-check-inline mb-2">
                                            <input class="form-check-input" type="radio" name="comunicacion_efectiva" id="comunicacion_efectiva_<?php echo $i; ?>" value="<?php echo $i; ?>" 
                                                <?php if ($autoevaluacionExistente && $autoevaluacion['comunicacion_efectiva'] == $i) echo 'checked'; ?> required>
                                            <label class="form-check-label" for="comunicacion_efectiva_<?php echo $i; ?>">
                                                <?php 
                                                switch ($i) {
                                                    case 1: echo "1 (Muy bajo)"; break;
                                                    case 2: echo "2 (Bajo)"; break;
                                                    case 3: echo "3 (Aceptable)"; break;
                                                    case 4: echo "4 (Bueno)"; break;
                                                    case 5: echo "5 (Excelente)"; break;
                                                }
                                                ?>
                                            </label>
                                        </div>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <h5>Resolución de problemas:</h5>
                                <div class="d-flex flex-wrap">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <div class="form-check form-check-inline mb-2">
                                            <input class="form-check-input" type="radio" name="resolucion_problemas" id="resolucion_problemas_<?php echo $i; ?>" value="<?php echo $i; ?>" 
                                                <?php if ($autoevaluacionExistente && $autoevaluacion['resolucion_problemas'] == $i) echo 'checked'; ?> required>
                                            <label class="form-check-label" for="resolucion_problemas_<?php echo $i; ?>">
                                                <?php 
                                                switch ($i) {
                                                    case 1: echo "1 (Muy bajo)"; break;
                                                    case 2: echo "2 (Bajo)"; break;
                                                    case 3: echo "3 (Aceptable)"; break;
                                                    case 4: echo "4 (Bueno)"; break;
                                                    case 5: echo "5 (Excelente)"; break;
                                                }
                                                ?>
                                            </label>
                                        </div>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <h4>3. Áreas de mejora: ¿Qué aspectos consideras que necesitas mejorar?</h4>
                                <textarea class="form-control" name="areas_mejora" rows="3" required><?php if ($autoevaluacionExistente) echo htmlspecialchars($autoevaluacion['areas_mejora']); ?></textarea>
                            </div>
                            
                            <div class="mb-4">
                                <h4>4. Logros destacados: ¿Cuáles fueron tus mayores logros durante este periodo?</h4>
                                <textarea class="form-control" name="logros_destacados" rows="3" required><?php if ($autoevaluacionExistente) echo htmlspecialchars($autoevaluacion['logros_destacados']); ?></textarea>
                            </div>
                            
                            <div class="text-end">
                                <button type="submit" name="submit_autoevaluacion" class="btn btn-primary btn-lg">
                                    <?php echo $autoevaluacionExistente ? 'Actualizar Autoevaluación' : 'Guardar Autoevaluación'; ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-warning" role="alert">
                    No hay períodos de evaluación activos en este momento.
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
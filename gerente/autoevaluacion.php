<?php
require_once '../config.php';

// Verificar si el usuario está logueado y tiene el rol de gerente
if (!isLoggedIn() || !hasRole('gerente')) {
    header("Location: ../login.php");
    exit;
}

// Obtener ID del gerente y del líder
$gerenteId = $_SESSION['user_id'];
$liderId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$periodoId = isset($_GET['periodo']) ? intval($_GET['periodo']) : 0;

$mensaje = '';
$lider = null;
$periodo = null;

// Verificar si el líder existe y está asignado a este gerente
if ($liderId > 0) {
    $sql = "SELECT u.* FROM usuarios u 
            JOIN gerente_lider gl ON u.id = gl.id_lider 
            WHERE gl.id_gerente = ? AND u.id = ? AND u.rol = 'lider'";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ii", $gerenteId, $liderId);
        
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $lider = $result->fetch_assoc();
            } else {
                header("Location: dashboard.php");
                exit;
            }
        }
        
        $stmt->close();
    }
} else {
    header("Location: lideres.php");
    exit;
}

// Verificar si el período existe
if ($periodoId > 0) {
    $sql = "SELECT * FROM periodos WHERE id = ? AND estado = 'activo'";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $periodoId);
        
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $periodo = $result->fetch_assoc();
            } else {
                // Si no se especificó un período válido, obtener el período activo más reciente
                $sql = "SELECT * FROM periodos WHERE estado = 'activo' ORDER BY fecha_inicio DESC LIMIT 1";
                $result = $conn->query($sql);
                
                if ($result->num_rows > 0) {
                    $periodo = $result->fetch_assoc();
                    $periodoId = $periodo['id'];
                } else {
                    $mensaje = "No hay períodos de evaluación activos.";
                    $periodoId = 0;
                }
            }
        }
        
        $stmt->close();
    }
} else {
    // Si no se especificó un período, obtener el período activo más reciente
    $sql = "SELECT * FROM periodos WHERE estado = 'activo' ORDER BY fecha_inicio DESC LIMIT 1";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $periodo = $result->fetch_assoc();
        $periodoId = $periodo['id'];
    } else {
        $mensaje = "No hay períodos de evaluación activos.";
        $periodoId = 0;
    }
}

// Verificar si ya existe una evaluación para este líder y período
$evaluacionExistente = false;
$evaluacion = [];

if ($periodoId > 0) {
    $sql = "SELECT * FROM evaluacion_gerente WHERE id_gerente = ? AND id_lider = ? AND id_periodo = ?";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("iii", $gerenteId, $liderId, $periodoId);
        
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $evaluacionExistente = true;
                $evaluacion = $result->fetch_assoc();
            }
        }
        
        $stmt->close();
    }
}

// Procesar el formulario cuando se envía
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_evaluacion']) && $periodoId > 0) {
    // Obtener los datos del formulario
    $campos = [
        'vision_estrategia_escala1', 'vision_estrategia_escala2', 'vision_estrategia_ejemplo',
        'planificacion_escala1', 'planificacion_escala2', 'planificacion_comentario',
        'cumplimiento_objetivos_escala1', 'cumplimiento_objetivos_escala2', 'cumplimiento_objetivos_comentario',
        'innovacion_escala1', 'innovacion_escala2', 'innovacion_comentario',
        'relaciones_escala1', 'relaciones_escala2', 'relaciones_ejemplo',
        'motivacion_escala1', 'motivacion_escala2', 'motivacion_acciones',
        'valores_escala1', 'valores_escala2', 'valores_ejemplo',
        'responsabilidad_escala1', 'responsabilidad_escala2', 'responsabilidad_contribucion',
        'desarrollo_escala1', 'desarrollo_escala2', 'desarrollo_ejemplo',
        'adaptabilidad_escala1', 'adaptabilidad_escala2', 'adaptabilidad_ejemplo',
        'puntos_fuertes', 'areas_mejora', 'comentarios_adicionales'
    ];
    
    $valores = [];
    $tipos = '';
    
    foreach ($campos as $campo) {
        if (strpos($campo, 'escala') !== false) {
            $valores[$campo] = intval($_POST[$campo] ?? 1);
            $tipos .= 'i';
        } else {
            $valores[$campo] = cleanInput($_POST[$campo] ?? '');
            $tipos .= 's';
        }
    }
    
    // Validar rangos para escalas
    $esValido = true;
    foreach ($valores as $campo => $valor) {
        if (strpos($campo, 'escala') !== false && ($valor < 1 || $valor > 5)) {
            $esValido = false;
            $mensaje = "Los valores de escala deben estar entre 1 y 5.";
            break;
        }
    }
    
    if ($esValido) {
        if ($evaluacionExistente) {
            // Actualizar evaluación existente
            $sql = "UPDATE evaluacion_gerente SET ";
            $setStatements = [];
            
            foreach ($campos as $campo) {
                $setStatements[] = "$campo = ?";
            }
            
            $sql .= implode(', ', $setStatements);
            $sql .= " WHERE id_gerente = ? AND id_lider = ? AND id_periodo = ?";
            
            if ($stmt = $conn->prepare($sql)) {
                $bindValues = array_values($valores);
                $bindValues[] = $gerenteId;
                $bindValues[] = $liderId;
                $bindValues[] = $periodoId;
                
                $stmt->bind_param($tipos . "iii", ...$bindValues);
                
                if ($stmt->execute()) {
                    $mensaje = "La evaluación ha sido actualizada con éxito.";
                } else {
                    $mensaje = "Error al actualizar la evaluación: " . $stmt->error;
                }
                
                $stmt->close();
            }
        } else {
            // Insertar nueva evaluación
            $sql = "INSERT INTO evaluacion_gerente (id_gerente, id_lider, id_periodo, ";
            $sql .= implode(', ', $campos);
            $sql .= ") VALUES (?, ?, ?, ";
            $sql .= implode(', ', array_fill(0, count($campos), '?'));
            $sql .= ")";
            
            if ($stmt = $conn->prepare($sql)) {
                $bindValues = [$gerenteId, $liderId, $periodoId];
                $bindValues = array_merge($bindValues, array_values($valores));
                
                $stmt->bind_param("iii" . $tipos, ...$bindValues);
                
                if ($stmt->execute()) {
                    $mensaje = "La evaluación ha sido guardada con éxito.";
                    $evaluacionExistente = true;
                    
                    // Obtener la evaluación recién creada
                    $sql = "SELECT * FROM evaluacion_gerente WHERE id_gerente = ? AND id_lider = ? AND id_periodo = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("iii", $gerenteId, $liderId, $periodoId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $evaluacion = $result->fetch_assoc();
                } else {
                    $mensaje = "Error al guardar la evaluación: " . $stmt->error;
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
    <title>Evaluar Líder - Gerente</title>
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
        .form-label {
            font-weight: 500;
        }
        .section-title {
            background-color: #f8f9fa;
            padding: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #007bff;
        }
        .rating-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .rating-option {
            display: none;
        }
        .rating-label {
            cursor: pointer;
            width: 50px;
            height: 50px;
            display: flex;
            justify-content: center;
            align-items: center;
            border: 2px solid #dee2e6;
            border-radius: 5px;
            font-weight: bold;
            transition: all 0.2s;
        }
        .rating-option:checked + .rating-label {
            background-color: #007bff;
            color: white;
            border-color: #007bff;
        }
        .rating-label:hover {
            background-color: #e9ecef;
        }
        .rating-option:checked + .rating-label:hover {
            background-color: #0069d9;
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
                    <a class="nav-link" href="lideres.php">
                        <i class="bi bi-people me-2"></i> Mis Líderes
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="evaluaciones.php">
                        <i class="bi bi-clipboard-check me-2"></i> Evaluaciones
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="reportes.php">
                        <i class="bi bi-bar-chart me-2"></i> Reportes
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <!-- Contenido principal -->
    <main class="content">
        <div class="container-fluid">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="lideres.php">Líderes</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Evaluar Líder</li>
                </ol>
            </nav>
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Evaluar a <?php echo $lider ? $lider['nombre'] . ' ' . $lider['apellido'] : ''; ?></h1>
                
                <?php if ($periodo): ?>
                <div>
                    <span class="badge bg-info p-2">Período: <?php echo $periodo['nombre']; ?></span>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($mensaje)): ?>
                <div class="alert <?php echo (strpos($mensaje, 'éxito') !== false) ? 'alert-success' : 'alert-danger'; ?> alert-dismissible fade show" role="alert">
                    <?php echo $mensaje; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($periodoId > 0): ?>
                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . "?id=" . $liderId . "&periodo=" . $periodoId; ?>">
                    <!-- Liderazgo y Gestión Estratégica -->
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h3 class="card-title mb-0">1. Liderazgo y Gestión Estratégica</h3>
                        </div>
                        <div class="card-body">
                            <!-- Visión y Estrategia -->
                            <div class="section-title">
                                <h4>1.1 Visión y Estrategia</h4>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Comunica claramente la visión y objetivos estratégicos:</label>
                                <div class="rating-container">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <input type="radio" class="rating-option" id="vision_estrategia_escala1_<?php echo $i; ?>" name="vision_estrategia_escala1" value="<?php echo $i; ?>" <?php if ($evaluacionExistente && $evaluacion['vision_estrategia_escala1'] == $i) echo 'checked'; ?> required>
                                        <label class="rating-label" for="vision_estrategia_escala1_<?php echo $i; ?>"><?php echo $i; ?></label>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Adapta las estrategias de su área a los cambios del entorno:</label>
                                <div class="rating-container">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <input type="radio" class="rating-option" id="vision_estrategia_escala2_<?php echo $i; ?>" name="vision_estrategia_escala2" value="<?php echo $i; ?>" <?php if ($evaluacionExistente && $evaluacion['vision_estrategia_escala2'] == $i) echo 'checked'; ?> required>
                                        <label class="rating-label" for="vision_estrategia_escala2_<?php echo $i; ?>"><?php echo $i; ?></label>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="vision_estrategia_ejemplo" class="form-label">Proporciona un ejemplo de cómo este líder ha implementado estrategias efectivas o innovadoras en su área:</label>
                                <textarea class="form-control" id="vision_estrategia_ejemplo" name="vision_estrategia_ejemplo" rows="3" required><?php echo $evaluacionExistente ? htmlspecialchars($evaluacion['vision_estrategia_ejemplo']) : ''; ?></textarea>
                            </div>
                            
                            <!-- Planificación y Organización -->
                            <div class="section-title">
                                <h4>1.2 Planificación y Organización</h4>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Gestiona eficazmente los recursos disponibles:</label>
                                <div class="rating-container">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <input type="radio" class="rating-option" id="planificacion_escala1_<?php echo $i; ?>" name="planificacion_escala1" value="<?php echo $i; ?>" <?php if ($evaluacionExistente && $evaluacion['planificacion_escala1'] == $i) echo 'checked'; ?> required>
                                        <label class="rating-label" for="planificacion_escala1_<?php echo $i; ?>"><?php echo $i; ?></label>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Prioriza adecuadamente las tareas y proyectos:</label>
                                <div class="rating-container">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <input type="radio" class="rating-option" id="planificacion_escala2_<?php echo $i; ?>" name="planificacion_escala2" value="<?php echo $i; ?>" <?php if ($evaluacionExistente && $evaluacion['planificacion_escala2'] == $i) echo 'checked'; ?> required>
                                        <label class="rating-label" for="planificacion_escala2_<?php echo $i; ?>"><?php echo $i; ?></label>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="planificacion_comentario" class="form-label">¿Qué aspectos destacarías sobre la forma en que este líder organiza y ejecuta sus planes?</label>
                                <textarea class="form-control" id="planificacion_comentario" name="planificacion_comentario" rows="3" required><?php echo $evaluacionExistente ? htmlspecialchars($evaluacion['planificacion_comentario']) : ''; ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Desempeño en Resultados y Metas -->
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h3 class="card-title mb-0">2. Desempeño en Resultados y Metas</h3>
                        </div>
                        <div class="card-body">
                            <!-- Cumplimiento de Objetivos -->
                            <div class="section-title">
                                <h4>2.1 Cumplimiento de Objetivos</h4>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Alcanza consistentemente los objetivos de su área:</label>
                                <div class="rating-container">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <input type="radio" class="rating-option" id="cumplimiento_objetivos_escala1_<?php echo $i; ?>" name="cumplimiento_objetivos_escala1" value="<?php echo $i; ?>" <?php if ($evaluacionExistente && $evaluacion['cumplimiento_objetivos_escala1'] == $i) echo 'checked'; ?> required>
                                        <label class="rating-label" for="cumplimiento_objetivos_escala1_<?php echo $i; ?>"><?php echo $i; ?></label>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Gestiona eficazmente los desafíos para cumplir con las metas:</label>
                                <div class="rating-container">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <input type="radio" class="rating-option" id="cumplimiento_objetivos_escala2_<?php echo $i; ?>" name="cumplimiento_objetivos_escala2" value="<?php echo $i; ?>" <?php if ($evaluacionExistente && $evaluacion['cumplimiento_objetivos_escala2'] == $i) echo 'checked'; ?> required>
                                        <label class="rating-label" for="cumplimiento_objetivos_escala2_<?php echo $i; ?>"><?php echo $i; ?></label>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="cumplimiento_objetivos_comentario" class="form-label">¿Puedes describir un logro reciente de este líder que consideres destacable?</label>
                                <textarea class="form-control" id="cumplimiento_objetivos_comentario" name="cumplimiento_objetivos_comentario" rows="3" required><?php echo $evaluacionExistente ? htmlspecialchars($evaluacion['cumplimiento_objetivos_comentario']) : ''; ?></textarea>
                            </div>
                            
                            <!-- Innovación y Mejora Continua -->
                            <div class="section-title">
                                <h4>2.2 Innovación y Mejora Continua</h4>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Fomenta la innovación en procesos y prácticas:</label>
                                <div class="rating-container">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <input type="radio" class="rating-option" id="innovacion_escala1_<?php echo $i; ?>" name="innovacion_escala1" value="<?php echo $i; ?>" <?php if ($evaluacionExistente && $evaluacion['innovacion_escala1'] == $i) echo 'checked'; ?> required>
                                        <label class="rating-label" for="innovacion_escala1_<?php echo $i; ?>"><?php echo $i; ?></label>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Está comprometido con la mejora continua de su equipo:</label>
                                <div class="rating-container">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <input type="radio" class="rating-option" id="innovacion_escala2_<?php echo $i; ?>" name="innovacion_escala2" value="<?php echo $i; ?>" <?php if ($evaluacionExistente && $evaluacion['innovacion_escala2'] == $i) echo 'checked'; ?> required>
                                        <label class="rating-label" for="innovacion_escala2_<?php echo $i; ?>"><?php echo $i; ?></label>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="innovacion_comentario" class="form-label">¿Cómo ha demostrado este líder su disposición para innovar o mejorar los resultados?</label>
                                <textarea class="form-control" id="innovacion_comentario" name="innovacion_comentario" rows="3" required><?php echo $evaluacionExistente ? htmlspecialchars($evaluacion['innovacion_comentario']) : ''; ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Competencias Interpersonales y Trabajo en Equipo -->
                    <div class="card mb-4">
                        <div class="card-header bg-info text-white">
                            <h3 class="card-title mb-0">3. Competencias Interpersonales y Trabajo en Equipo</h3>
                        </div>
                        <div class="card-body">
                            <!-- Relaciones Interpersonales -->
                            <div class="section-title">
                                <h4>3.1 Relaciones Interpersonales</h4>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Mantiene una comunicación efectiva con el equipo y otros líderes:</label>
                                <div class="rating-container">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <input type="radio" class="rating-option" id="relaciones_escala1_<?php echo $i; ?>" name="relaciones_escala1" value="<?php echo $i; ?>" <?php if ($evaluacionExistente && $evaluacion['relaciones_escala1'] == $i) echo 'checked'; ?> required>
                                        <label class="rating-label" for="relaciones_escala1_<?php echo $i; ?>"><?php echo $i; ?></label>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Maneja conflictos de manera constructiva:</label>
                                <div class="rating-container">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <input type="radio" class="rating-option" id="relaciones_escala2_<?php echo $i; ?>" name="relaciones_escala2" value="<?php echo $i; ?>" <?php if ($evaluacionExistente && $evaluacion['relaciones_escala2'] == $i) echo 'checked'; ?> required>
                                        <label class="rating-label" for="relaciones_escala2_<?php echo $i; ?>"><?php echo $i; ?></label>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="relaciones_ejemplo" class="form-label">Describe un ejemplo de cómo este líder ha manejado una situación de conflicto en su equipo:</label>
                                <textarea class="form-control" id="relaciones_ejemplo" name="relaciones_ejemplo" rows="3" required><?php echo $evaluacionExistente ? htmlspecialchars($evaluacion['relaciones_ejemplo']) : ''; ?></textarea>
                            </div>
                            
                            <!-- Motivación y Desarrollo del Equipo -->
                            <div class="section-title">
                                <h4>3.2 Motivación y Desarrollo del Equipo</h4>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Apoya el desarrollo profesional de los miembros de su equipo:</label>
                                <div class="rating-container">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <input type="radio" class="rating-option" id="motivacion_escala1_<?php echo $i; ?>" name="motivacion_escala1" value="<?php echo $i; ?>" <?php if ($evaluacionExistente && $evaluacion['motivacion_escala1'] == $i) echo <?php
require_once '../config.php';

// Verificar si el usuario está logueado y tiene el rol de gerente
if (!isLoggedIn() || !hasRole('gerente')) {
    header("Location: ../login.php");
    exit;
}

// Obtener ID del gerente y del líder
$gerenteId = $_SESSION['user_id'];
$liderId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$periodoId = isset($_GET['periodo']) ? intval($_GET['periodo']) : 0;

$mensaje = '';
$lider = null;
$periodo = null;

// Verificar si el líder existe y está asignado a este gerente
if ($liderId > 0) {
    $sql = "SELECT u.* FROM usuarios u 
            JOIN gerente_lider gl ON u.id = gl.id_lider 
            WHERE gl.id_gerente = ? AND u.id = ? AND u.rol = 'lider'";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ii", $gerenteId, $liderId);
        
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $lider = $result->fetch_assoc();
            } else {
                header("Location: dashboard.php");
                exit;
            }
        }
        
        $stmt->close();
    }
} else {
    header("Location: lideres.php");
    exit;
}

// Verificar si el período existe
if ($periodoId > 0) {
    $sql = "SELECT * FROM periodos WHERE id = ? AND estado = 'activo
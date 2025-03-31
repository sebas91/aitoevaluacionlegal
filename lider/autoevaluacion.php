<?php
require_once '../config.php';

// Verificar si el usuario está logueado y tiene el rol de líder
if (!isLoggedIn() || !hasRole('lider')) {
    header("Location: ../login.php");
    exit;
}

// Obtener ID del líder y del empleado
$liderId = $_SESSION['user_id'];
$empleadoId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$periodoId = isset($_GET['periodo']) ? intval($_GET['periodo']) : 0;

$mensaje = '';
$empleado = null;
$periodo = null;

// Verificar si el empleado existe y está asignado a este líder
if ($empleadoId > 0) {
    $sql = "SELECT u.* FROM usuarios u 
            JOIN lider_empleado le ON u.id = le.id_empleado 
            WHERE le.id_lider = ? AND u.id = ? AND u.rol = 'empleado'";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ii", $liderId, $empleadoId);
        
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $empleado = $result->fetch_assoc();
            } else {
                header("Location: dashboard.php");
                exit;
            }
        }
        
        $stmt->close();
    }
} else {
    header("Location: empleados.php");
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

// Verificar si ya existe una evaluación para este empleado y período
$evaluacionExistente = false;
$evaluacion = [];

if ($periodoId > 0) {
    $sql = "SELECT * FROM evaluacion_lider WHERE id_lider = ? AND id_empleado = ? AND id_periodo = ?";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("iii", $liderId, $empleadoId, $periodoId);
        
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
        'actitud_valores', 'valores_empresa', 'integridad_responsabilidad',
        'trabajo_equipo', 'receptividad_feedback', 'contribucion_ambiente',
        'manejo_estres', 'empatia', 'compromiso_crecimiento',
        'cumplimiento_objetivos', 'priorizacion_tareas', 'eficiencia_recursos',
        'calidad_trabajo', 'innovacion_procesos', 'atencion_detalle',
        'resolucion_problemas', 'toma_decisiones', 'proactividad',
        'respuesta_cambios', 'aprendizaje', 'adaptabilidad'
    ];
    
    $valores = [];
    $tipos = '';
    
    foreach ($campos as $campo) {
        $valores[$campo] = cleanInput($_POST[$campo] ?? '');
        $tipos .= 's';
    }
    
    if ($evaluacionExistente) {
        // Actualizar evaluación existente
        $sql = "UPDATE evaluacion_lider SET ";
        $setStatements = [];
        
        foreach ($campos as $campo) {
            $setStatements[] = "$campo = ?";
        }
        
        $sql .= implode(', ', $setStatements);
        $sql .= " WHERE id_lider = ? AND id_empleado = ? AND id_periodo = ?";
        
        if ($stmt = $conn->prepare($sql)) {
            $bindValues = array_values($valores);
            $bindValues[] = $liderId;
            $bindValues[] = $empleadoId;
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
        $sql = "INSERT INTO evaluacion_lider (id_lider, id_empleado, id_periodo, ";
        $sql .= implode(', ', $campos);
        $sql .= ") VALUES (?, ?, ?, ";
        $sql .= implode(', ', array_fill(0, count($campos), '?'));
        $sql .= ")";
        
        if ($stmt = $conn->prepare($sql)) {
            $bindValues = [$liderId, $empleadoId, $periodoId];
            $bindValues = array_merge($bindValues, array_values($valores));
            
            $stmt->bind_param("iii" . $tipos, ...$bindValues);
            
            if ($stmt->execute()) {
                $mensaje = "La evaluación ha sido guardada con éxito.";
                $evaluacionExistente = true;
                
                // Obtener la evaluación recién creada
                $sql = "SELECT * FROM evaluacion_lider WHERE id_lider = ? AND id_empleado = ? AND id_periodo = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iii", $liderId, $empleadoId, $periodoId);
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
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evaluar Empleado - Líder</title>
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
                    <a class="nav-link" href="empleados.php">
                        <i class="bi bi-people me-2"></i> Mis Empleados
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
                    <li class="breadcrumb-item"><a href="empleados.php">Empleados</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Evaluar Empleado</li>
                </ol>
            </nav>
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Evaluar a <?php echo $empleado ? $empleado['nombre'] . ' ' . $empleado['apellido'] : ''; ?></h1>
                
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
                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . "?id=" . $empleadoId . "&periodo=" . $periodoId; ?>">
                    <!-- El Ser (Competencias personales y valores) -->
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h3 class="card-title mb-0">1. El Ser (Competencias personales y valores)</h3>
                        </div>
                        <div class="card-body">
                            <!-- Actitud y Valores -->
                            <div class="section-title">
                                <h4>1.1 Actitud y Valores</h4>
                            </div>
                            
                            <div class="mb-3">
                                <label for="actitud_valores" class="form-label">¿Cómo describirías la actitud de <?php echo $empleado['nombre']; ?> hacia el trabajo y los demás compañeros?</label>
                                <textarea class="form-control" id="actitud_valores" name="actitud_valores" rows="3" required><?php echo $evaluacionExistente ? htmlspecialchars($evaluacion['actitud_valores']) : ''; ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="valores_empresa" class="form-label">¿De qué manera <?php echo $empleado['nombre']; ?> demuestra los valores de la empresa en su día a día?</label>
                                <textarea class="form-control" id="valores_empresa" name="valores_empresa" rows="3" required><?php echo $evaluacionExistente ? htmlspecialchars($evaluacion['valores_empresa']) : ''; ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="integridad_responsabilidad" class="form-label">¿Crees que <?php echo $empleado['nombre']; ?> actúa con integridad y responsabilidad en su rol?</label>
                                <textarea class="form-control" id="integridad_responsabilidad" name="integridad_responsabilidad" rows="3" required><?php echo $evaluacionExistente ? htmlspecialchars($evaluacion['integridad_responsabilidad']) : ''; ?></textarea>
                            </div>
                            
                            <!-- Relaciones Interpersonales -->
                            <div class="section-title">
                                <h4>1.2 Relaciones Interpersonales</h4>
                            </div>
                            
                            <div class="mb-3">
                                <label for="trabajo_equipo" class="form-label">¿Cómo evalúas la capacidad de <?php echo $empleado['nombre']; ?> para trabajar en equipo y construir relaciones positivas?</label>
                                <textarea class="form-control" id="trabajo_equipo" name="trabajo_equipo" rows="3" required><?php echo $evaluacionExistente ? htmlspecialchars($evaluacion['trabajo_equipo']) : ''; ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="receptividad_feedback" class="form-label">¿Qué tan receptivo es <?php echo $empleado['nombre']; ?> al recibir retroalimentación constructiva?</label>
                                <textarea class="form-control" id="receptividad_feedback" name="receptividad_feedback" rows="3" required><?php echo $evaluacionExistente ? htmlspecialchars($evaluacion['receptividad_feedback']) : ''; ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="contribucion_ambiente" class="form-label">¿Consideras que <?php echo $empleado['nombre']; ?> contribuye a un ambiente laboral de respeto y colaboración?</label>
                                <textarea class="form-control" id="contribucion_ambiente" name="contribucion_ambiente" rows="3" required><?php echo $evaluacionExistente ? htmlspecialchars($evaluacion['contribucion_ambiente']) : ''; ?></textarea>
                            </div>
                            
                            <!-- Autogestión y Emociones -->
                            <div class="section-title">
                                <h4>1.3 Autogestión y Emociones</h4>
                            </div>
                            
                            <div class="mb-3">
                                <label for="manejo_estres" class="form-label">¿Cómo maneja <?php echo $empleado['nombre']; ?> situaciones de estrés o conflicto?</label>
                                <textarea class="form-control" id="manejo_estres" name="manejo_estres" rows="3" required><?php echo $evaluacionExistente ? htmlspecialchars($evaluacion['manejo_estres']) : ''; ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="empatia" class="form-label">¿De qué manera <?php echo $empleado['nombre']; ?> demuestra empatía y sensibilidad hacia los demás?</label>
                                <textarea class="form-control" id="empatia" name="empatia" rows="3" required><?php echo $evaluacionExistente ? htmlspecialchars($evaluacion['empatia']) : ''; ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="compromiso_crecimiento" class="form-label">¿Qué tan comprometido está <?php echo $empleado['nombre']; ?> con su crecimiento personal y profesional?</label>
                                <textarea class="form-control" id="compromiso_crecimiento" name="compromiso_crecimiento" rows="3" required><?php echo $evaluacionExistente ? htmlspecialchars($evaluacion['compromiso_crecimiento']) : ''; ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- El Hacer (Desempeño y competencias técnicas) -->
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h3 class="card-title mb-0">2. El Hacer (Desempeño y competencias técnicas)</h3>
                        </div>
                        <div class="card-body">
                            <!-- Cumplimiento de Objetivos -->
                            <div class="section-title">
                                <h4>2.1 Cumplimiento de Objetivos</h4>
                            </div>
                            
                            <div class="mb-3">
                                <label for="cumplimiento_objetivos" class="form-label">¿Cómo evalúas el nivel de cumplimiento de <?php echo $empleado['nombre']; ?> respecto a los objetivos asignados?</label>
                                <textarea class="form-control" id="cumplimiento_objetivos" name="cumplimiento_objetivos" rows="3" required><?php echo $evaluacionExistente ? htmlspecialchars($evaluacion['cumplimiento_objetivos']) : ''; ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="priorizacion_tareas" class="form-label">¿Crees que <?php echo $empleado['nombre']; ?> prioriza adecuadamente sus tareas para maximizar los resultados?</label>
                                <textarea class="form-control" id="priorizacion_tareas" name="priorizacion_tareas" rows="3" required><?php echo $evaluacionExistente ? htmlspecialchars($evaluacion['priorizacion_tareas']) : ''; ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="eficiencia_recursos" class="form-label">¿Qué tan eficiente es <?php echo $empleado['nombre']; ?> al manejar los recursos disponibles para realizar su trabajo?</label>
                                <textarea class="form-control" id="eficiencia_recursos" name="eficiencia_recursos" rows="3" required><?php echo $evaluacionExistente ? htmlspecialchars($evaluacion['eficiencia_recursos']) : ''; ?></textarea>
                            </div>
                            
                            <!-- Calidad del Trabajo -->
                            <div class="section-title">
                                <h4>2.2 Calidad del Trabajo</h4>
                            </div>
                            
                            <div class="mb-3">
                                <label for="calidad_trabajo" class="form-label">¿Qué tan consistentemente <?php echo $empleado['nombre']; ?> entrega un trabajo de alta calidad?</label>
                                <textarea class="form-control" id="calidad_trabajo" name="calidad_trabajo" rows="3" required><?php echo $evaluacionExistente ? htmlspecialchars($evaluacion['calidad_trabajo']) : ''; ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="innovacion_procesos" class="form-label">¿De qué manera <?php echo $empleado['nombre']; ?> busca innovar o mejorar los procesos en su área?</label>
                                <textarea class="form-control" id="innovacion_procesos" name="innovacion_procesos" rows="3" required><?php echo $evaluacionExistente ? htmlspecialchars($evaluacion['innovacion_procesos']) : ''; ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="atencion_detalle" class="form-label">¿Cómo evalúas la atención al detalle y el cuidado que <?php echo $empleado['nombre']; ?> pone en sus tareas?</label>
                                <textarea class="form-control" id="atencion_detalle" name="atencion_detalle" rows="3" required><?php echo $evaluacionExistente ? htmlspecialchars($evaluacion['atencion_detalle']) : ''; ?></textarea>
                            </div>
                            
                            <!-- Resolución de Problemas y Toma de Decisiones -->
                            <div class="section-title">
                                <h4>2.3 Resolución de Problemas y Toma de Decisiones</h4>
                            </div>
                            
                            <div class="mb-3">
                                <label for="resolucion_problemas" class="form-label">¿Qué tan efectivo es <?php echo $empleado['nombre']; ?> al identificar y resolver problemas en su trabajo?</label>
                                <textarea class="form-control" id="resolucion_problemas" name="resolucion_problemas" rows="3" required><?php echo $evaluacionExistente ? htmlspecialchars($evaluacion['resolucion_problemas']) : ''; ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="toma_decisiones" class="form-label">¿Cómo evalúas la capacidad de <?php echo $empleado['nombre']; ?> para tomar decisiones informadas y oportunas?</label>
                                <textarea class="form-control" id="toma_decisiones" name="toma_decisiones" rows="3" required><?php echo $evaluacionExistente ? htmlspecialchars($evaluacion['toma_decisiones']) : ''; ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="proactividad" class="form-label">¿Crees que <?php echo $empleado['nombre']; ?> muestra proactividad al enfrentar desafíos o buscar soluciones?</label>
                                <textarea class="form-control" id="proactividad" name="proactividad" rows="3" required><?php echo $evaluacionExistente ? htmlspecialchars($evaluacion['proactividad']) : ''; ?></textarea>
                            </div>
                            
                            <!-- Adaptabilidad y Aprendizaje -->
                            <div class="section-title">
                                <h4>2.4 Adaptabilidad y Aprendizaje</h4>
                            </div>
                            
                            <div class="mb-3">
                                <label for="respuesta_cambios" class="form-label">¿Cómo responde <?php echo $empleado['nombre']; ?> ante cambios o nuevas exigencias en su rol?</label>
                                <textarea class="form-control" id="respuesta_cambios" name="respuesta_cambios" rows="3" required><?php echo $evaluacionExistente ? htmlspecialchars($evaluacion['respuesta_cambios']) : ''; ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="aprendizaje" class="form-label">¿Qué tan dispuesto está <?php echo $empleado['nombre']; ?> a aprender nuevas habilidades o conocimientos?</label>
                                <textarea class="form-control" id="aprendizaje" name="aprendizaje" rows="3" required><?php echo $evaluacionExistente ? htmlspecialchars($evaluacion['aprendizaje']) : ''; ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="adaptabilidad" class="form-label">¿De qué manera <?php echo $empleado['nombre']; ?> se adapta a trabajar en diferentes entornos o equipos?</label>
                                <textarea class="form-control" id="adaptabilidad" name="adaptabilidad" rows="3" required><?php echo $evaluacionExistente ? htmlspecialchars($evaluacion['adaptabilidad']) : ''; ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-end mb-4">
                        <button type="submit" name="submit_evaluacion" class="btn btn-primary btn-lg">
                            <?php echo $evaluacionExistente ? 'Actualizar Evaluación' : 'Guardar Evaluación'; ?>
                        </button>
                    </div>
                </form>
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
<?php
require_once '../config.php';

// Verificar si el usuario está logueado y tiene el rol de empleado
if (!isLoggedIn() || !hasRole('empleado')) {
    header("Location: ../login.php");
    exit;
}

// Obtener períodos activos
$periodos = getPeriodosActivos();

// Obtener las autoevaluaciones realizadas por el empleado
$userId = $_SESSION['user_id'];
$autoevaluaciones = [];

$sql = "SELECT a.*, p.nombre as periodo_nombre, p.fecha_inicio, p.fecha_fin 
        FROM autoevaluacion_empleado a 
        JOIN periodos p ON a.id_periodo = p.id 
        WHERE a.id_usuario = ? 
        ORDER BY p.fecha_inicio DESC";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $autoevaluaciones[] = $row;
        }
    }
    
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Empleado</title>
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
        .dropdown-menu {
            right: 0;
            left: auto;
        }
        body {
            padding-top: 56px;
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
                    <a class="nav-link active" href="dashboard.php">
                        <i class="bi bi-speedometer2 me-2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="autoevaluacion.php">
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
            <h1 class="mb-4">Bienvenido, <?php echo $_SESSION['user_name']; ?></h1>
            
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="card-title mb-0">Períodos de Evaluación Activos</h5>
                        </div>
                        <div class="card-body">
                            <?php if (count($periodos) > 0): ?>
                                <div class="list-group">
                                    <?php foreach ($periodos as $periodo): ?>
                                        <a href="autoevaluacion.php?periodo=<?php echo $periodo['id']; ?>" class="list-group-item list-group-item-action">
                                            <div class="d-flex w-100 justify-content-between">
                                                <h5 class="mb-1"><?php echo $periodo['nombre']; ?></h5>
                                                <small>
                                                    <?php
                                                    // Verificar si ya se ha realizado esta autoevaluación
                                                    $realizada = false;
                                                    foreach ($autoevaluaciones as $eval) {
                                                        if ($eval['id_periodo'] == $periodo['id']) {
                                                            $realizada = true;
                                                            break;
                                                        }
                                                    }
                                                    
                                                    if ($realizada) {
                                                        echo '<span class="badge bg-success">Completada</span>';
                                                    } else {
                                                        echo '<span class="badge bg-warning">Pendiente</span>';
                                                    }
                                                    ?>
                                                </small>
                                            </div>
                                            <p class="mb-1">Período: <?php echo date('d/m/Y', strtotime($periodo['fecha_inicio'])); ?> - <?php echo date('d/m/Y', strtotime($periodo['fecha_fin'])); ?></p>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info" role="alert">
                                    No hay períodos de evaluación activos en este momento.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h5 class="card-title mb-0">Mis Últimas Autoevaluaciones</h5>
                        </div>
                        <div class="card-body">
                            <?php if (count($autoevaluaciones) > 0): ?>
                                <div class="list-group">
                                    <?php foreach (array_slice($autoevaluaciones, 0, 3) as $eval): ?>
                                        <a href="ver_autoevaluacion.php?id=<?php echo $eval['id']; ?>" class="list-group-item list-group-item-action">
                                            <div class="d-flex w-100 justify-content-between">
                                                <h5 class="mb-1"><?php echo $eval['periodo_nombre']; ?></h5>
                                                <small><?php echo date('d/m/Y', strtotime($eval['fecha_creacion'])); ?></small>
                                            </div>
                                            <p class="mb-1">
                                                Puntaje promedio: 
                                                <?php
                                                $promedio = ($eval['logro_objetivos'] + $eval['trabajo_equipo'] + $eval['comunicacion_efectiva'] + $eval['resolucion_problemas']) / 4;
                                                echo number_format($promedio, 1);
                                                ?>
                                            </p>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                                
                                <?php if (count($autoevaluaciones) > 3): ?>
                                    <div class="text-center mt-3">
                                        <a href="historial.php" class="btn btn-outline-primary">Ver todas mis autoevaluaciones</a>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="alert alert-info" role="alert">
                                    No has realizado ninguna autoevaluación aún.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
require_once '../config.php';

// Verificar si el usuario está logueado y tiene el rol de administrador
if (!isLoggedIn() || !hasRole('administrador')) {
    header("Location: ../login.php");
    exit;
}

// Obtener estadísticas generales del sistema
$stats = [];

// Contar usuarios por rol
$sql = "SELECT rol, COUNT(*) as total FROM usuarios GROUP BY rol";
$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $stats['usuarios'][$row['rol']] = $row['total'];
    }
}

// Contar períodos activos e inactivos
$sql = "SELECT estado, COUNT(*) as total FROM periodos GROUP BY estado";
$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $stats['periodos'][$row['estado']] = $row['total'];
    }
}

// Contar autoevaluaciones por período
$sql = "SELECT p.nombre, COUNT(a.id) as total 
        FROM periodos p 
        LEFT JOIN autoevaluacion_empleado a ON p.id = a.id_periodo 
        GROUP BY p.id 
        ORDER BY p.fecha_inicio DESC 
        LIMIT 5";
$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $stats['autoevaluaciones'][$row['nombre']] = $row['total'];
    }
}

// Contar evaluaciones de líderes por período
$sql = "SELECT p.nombre, COUNT(e.id) as total 
        FROM periodos p 
        LEFT JOIN evaluacion_lider e ON p.id = e.id_periodo 
        GROUP BY p.id 
        ORDER BY p.fecha_inicio DESC 
        LIMIT 5";
$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $stats['evaluaciones_lider'][$row['nombre']] = $row['total'];
    }
}

// Contar evaluaciones de gerentes por período
$sql = "SELECT p.nombre, COUNT(e.id) as total 
        FROM periodos p 
        LEFT JOIN evaluacion_gerente e ON p.id = e.id_periodo 
        GROUP BY p.id 
        ORDER BY p.fecha_inicio DESC 
        LIMIT 5";
$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $stats['evaluaciones_gerente'][$row['nombre']] = $row['total'];
    }
}

// Obtener períodos activos
$periodos = getPeriodosActivos();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Administrador</title>
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
        .stats-card {
            transition: all 0.3s;
        }
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
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
                    <a class="nav-link" href="usuarios.php">
                        <i class="bi bi-people me-2"></i> Usuarios
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="periodos.php">
                        <i class="bi bi-calendar-range me-2"></i> Períodos
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="asignaciones.php">
                        <i class="bi bi-diagram-3 me-2"></i> Asignaciones
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="reportes.php">
                        <i class="bi bi-file-earmark-text me-2"></i> Reportes
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="configuracion.php">
                        <i class="bi bi-gear me-2"></i> Configuración
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <!-- Contenido principal -->
    <main class="content">
        <div class="container-fluid">
            <h1 class="mb-4">Panel de Administración</h1>
            
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2 stats-card">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        Total Empleados</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['usuarios']['empleado'] ?? 0; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-person fs-2 text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-success shadow h-100 py-2 stats-card">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                        Total Líderes</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['usuarios']['lider'] ?? 0; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-person-check fs-2 text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-info shadow h-100 py-2 stats-card">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                        Total Gerentes</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['usuarios']['gerente'] ?? 0; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-person-badge fs-2 text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-warning shadow h-100 py-2 stats-card">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                        Períodos Activos</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['periodos']['activo'] ?? 0; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-calendar-check fs-2 text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-lg-6">
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="bi bi-clipboard-data me-2"></i> Resumen de Evaluaciones</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Período</th>
                                            <th>Autoevaluaciones</th>
                                            <th>Evaluaciones de Líderes</th>
                                            <th>Evaluaciones de Gerentes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($periodos as $periodo): ?>
                                            <tr>
                                                <td><?php echo $periodo['nombre']; ?></td>
                                                <td><?php echo $stats['autoevaluaciones'][$periodo['nombre']] ?? 0; ?></td>
                                                <td><?php echo $stats['evaluaciones_lider'][$periodo['nombre']] ?? 0; ?></td>
                                                <td><?php echo $stats['evaluaciones_gerente'][$periodo['nombre']] ?? 0; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="text-center mt-3">
                                <a href="reportes.php" class="btn btn-outline-primary">Ver Reportes Detallados</a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mb-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="bi bi-calendar-range me-2"></i> Períodos de Evaluación</h5>
                        </div>
                        <div class="card-body">
                            <?php if (count($periodos) > 0): ?>
                                <div class="list-group">
                                    <?php foreach ($periodos as $periodo): ?>
                                        <div class="list-group-item">
                                            <div class="d-flex w-100 justify-content-between">
                                                <h5 class="mb-1"><?php echo $periodo['nombre']; ?></h5>
                                                <small class="text-success">Activo</small>
                                            </div>
                                            <p class="mb-1">
                                                Período: <?php echo date('d/m/Y', strtotime($periodo['fecha_inicio'])); ?> - 
                                                <?php echo date('d/m/Y', strtotime($periodo['fecha_fin'])); ?>
                                            </p>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div class="text-center mt-3">
                                    <a href="periodos.php" class="btn btn-outline-info">Gestionar Períodos</a>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    No hay períodos de evaluación activos.
                                </div>
                                <div class="text-center">
                                    <a href="periodos.php" class="btn btn-info">Crear Período</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6">
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="bi bi-people me-2"></i> Gestión de Usuarios</h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-md-4 mb-3">
                                    <div class="p-3 bg-light rounded">
                                        <h3><?php echo $stats['usuarios']['empleado'] ?? 0; ?></h3>
                                        <p class="mb-0">Empleados</p>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="p-3 bg-light rounded">
                                        <h3><?php echo $stats['usuarios']['lider'] ?? 0; ?></h3>
                                        <p class="mb-0">Líderes</p>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="p-3 bg-light rounded">
                                        <h3><?php echo $stats['usuarios']['gerente'] ?? 0; ?></h3>
                                        <p class="mb-0">Gerentes</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-center mt-3">
                                <a href="usuarios.php" class="btn btn-outline-success me-2">Gestionar Usuarios</a>
                                <a href="nuevo_usuario.php" class="btn btn-success">Nuevo Usuario</a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mb-4">
                        <div class="card-header bg-warning text-white">
                            <h5 class="mb-0"><i class="bi bi-diagram-3 me-2"></i> Asignaciones</h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-md-6 mb-3">
                                    <div class="p-3 bg-light rounded">
                                        <h3><i class="bi bi-people"></i></h3>
                                        <p class="mb-0">Asignar Empleados a Líderes</p>
                                        <a href="asignar_empleados.php" class="btn btn-sm btn-outline-warning mt-2">Gestionar</a>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="p-3 bg-light rounded">
                                        <h3><i class="bi bi-people-fill"></i></h3>
                                        <p class="mb-0">Asignar Líderes a Gerentes</p>
                                        <a href="asignar_lideres.php" class="btn btn-sm btn-outline-warning mt-2">Gestionar</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mb-4">
                        <div class="card-header bg-secondary text-white">
                            <h5 class="mb-0"><i class="bi bi-gear me-2"></i> Acciones Rápidas</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="reportes.php" class="btn btn-outline-primary">
                                    <i class="bi bi-file-earmark-text me-2"></i> Generar Reportes
                                </a>
                                <a href="exportar_datos.php" class="btn btn-outline-success">
                                    <i class="bi bi-file-earmark-excel me-2"></i> Exportar Datos
                                </a>
                                <a href="configuracion.php" class="btn btn-outline-dark">
                                    <i class="bi bi-gear me-2"></i> Configuración del Sistema
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
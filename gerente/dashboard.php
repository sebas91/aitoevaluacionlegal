<?php
require_once '../config.php';

// Verificar si el usuario está logueado y tiene el rol de gerente
if (!isLoggedIn() || !hasRole('gerente')) {
    header("Location: ../login.php");
    exit;
}

// Obtener ID del gerente
$gerenteId = $_SESSION['user_id'];

// Obtener líderes asignados al gerente
$lideres = [];
$sql = "SELECT u.* FROM usuarios u 
        JOIN gerente_lider gl ON u.id = gl.id_lider 
        WHERE gl.id_gerente = ? AND u.rol = 'lider'";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $gerenteId);
    
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $lideres[] = $row;
        }
    }
    
    $stmt->close();
}

// Obtener períodos activos
$periodos = getPeriodosActivos();

// Contar evaluaciones pendientes
$evaluacionesPendientes = 0;
if (count($lideres) > 0 && count($periodos) > 0) {
    $periodoIds = array_column($periodos, 'id');
    $liderIds = array_column($lideres, 'id');
    
    $placeholders = implode(',', array_fill(0, count($liderIds), '?'));
    $periodoPlaceholders = implode(',', array_fill(0, count($periodoIds), '?'));
    
    $sql = "SELECT COUNT(*) as total FROM usuarios u 
            JOIN gerente_lider gl ON u.id = gl.id_lider 
            WHERE gl.id_gerente = ? AND u.id IN ($placeholders) 
            AND u.id NOT IN (
                SELECT id_lider FROM evaluacion_gerente 
                WHERE id_gerente = ? AND id_periodo IN ($periodoPlaceholders)
            )";
    
    if ($stmt = $conn->prepare($sql)) {
        $types = "i" . str_repeat("i", count($liderIds)) . "i" . str_repeat("i", count($periodoIds));
        $params = array_merge([$gerenteId], $liderIds, [$gerenteId], $periodoIds);
        
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $evaluacionesPendientes = $row['total'];
        }
        
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Gerente</title>
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
                    <a class="nav-link" href="lideres.php">
                        <i class="bi bi-people me-2"></i> Mis Líderes
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="evaluaciones.php">
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
            <h1 class="mb-4">Bienvenido, <?php echo $_SESSION['user_name']; ?></h1>
            
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        Líderes a Cargo</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo count($lideres); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-people fs-2 text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                        Períodos Activos</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo count($periodos); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-calendar-check fs-2 text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                        Evaluaciones Pendientes</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $evaluacionesPendientes; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-exclamation-triangle fs-2 text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                        Evaluaciones Completadas</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php 
                                        $completadas = count($lideres) * count($periodos) - $evaluacionesPendientes;
                                        echo $completadas > 0 ? $completadas : 0; 
                                        ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-check-circle fs-2 text-gray-300"></i>
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
                            <h5 class="mb-0">Mis Líderes</h5>
                        </div>
                        <div class="card-body">
                            <?php if (count($lideres) > 0): ?>
                                <div class="list-group">
                                    <?php foreach (array_slice($lideres, 0, 5) as $lider): ?>
                                        <a href="evaluar_lider.php?id=<?php echo $lider['id']; ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                            <?php echo $lider['nombre'] . ' ' . $lider['apellido']; ?>
                                            <span class="badge bg-primary rounded-pill">Evaluar</span>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                                
                                <?php if (count($lideres) > 5): ?>
                                    <div class="text-center mt-3">
                                        <a href="lideres.php" class="btn btn-outline-primary">Ver todos mis líderes</a>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    No tienes líderes asignados. Contacta al administrador.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6">
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">Períodos de Evaluación Activos</h5>
                        </div>
                        <div class="card-body">
                            <?php if (count($periodos) > 0): ?>
                                <div class="list-group">
                                    <?php foreach ($periodos as $periodo): ?>
                                        <div class="list-group-item">
                                            <div class="d-flex w-100 justify-content-between">
                                                <h5 class="mb-1"><?php echo $periodo['nombre']; ?></h5>
                                            </div>
                                            <p class="mb-1">
                                                Período: <?php echo date('d/m/Y', strtotime($periodo['fecha_inicio'])); ?> - 
                                                <?php echo date('d/m/Y', strtotime($periodo['fecha_fin'])); ?>
                                            </p>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    No hay períodos de evaluación activos.
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
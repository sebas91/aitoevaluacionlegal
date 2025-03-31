<?php
// Configuración de la base de datos
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'legalcarteraa');
define('DB_PASSWORD', 'sebas123');
define('DB_NAME', 'sistema_autoevaluacion');

// Intentar conexión con la base de datos
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Verificar conexión
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

// Configurar caracteres UTF-8
$conn->set_charset("utf8");

// Iniciar sesión
session_start();

// Función para verificar si el usuario está logueado
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Función para verificar el rol del usuario
function hasRole($role) {
    if (!isLoggedIn()) {
        return false;
    }
    
    if (is_array($role)) {
        return in_array($_SESSION['user_role'], $role);
    }
    
    return $_SESSION['user_role'] === $role;
}

// Función para redirigir según el rol
function redirectByRole() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit;
    }
    
    switch ($_SESSION['user_role']) {
        case 'empleado':
            header("Location: empleado/dashboard.php");
            break;
        case 'lider':
            header("Location: lider/dashboard.php");
            break;
        case 'gerente':
            header("Location: gerente/dashboard.php");
            break;
        case 'administrador':
            header("Location: admin/dashboard.php");
            break;
        default:
            header("Location: login.php");
            break;
    }
    exit;
}

// Obtener períodos activos
function getPeriodosActivos() {
    global $conn;
    
    $sql = "SELECT * FROM periodos WHERE estado = 'activo' ORDER BY fecha_inicio DESC";
    $result = $conn->query($sql);
    
    $periodos = [];
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $periodos[] = $row;
        }
    }
    
    return $periodos;
}

// Función para limpiar entradas de datos
function cleanInput($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $conn->real_escape_string($data);
}
?>
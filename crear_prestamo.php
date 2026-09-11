<?php
require_once 'config.php';
require_once 'includes/auth_check.php';

// Solo docentes y estudiantes pueden solicitar equipos
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['rol']) || !in_array($_SESSION['rol'], ['docente', 'estudiante'])) {
    header('Location: index.html');
    exit;
}

$rol = $_SESSION['rol'];
$panel = 'dashboard_' . $rol . '.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: $panel");
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$fecha_devolucion_prevista = $_POST['fecha_devolucion_prevista'] ?? '';

// Validar fecha: obligatoria y no puede ser hoy ni anterior
$hoy = date('Y-m-d');
if ($fecha_devolucion_prevista === '' || $fecha_devolucion_prevista <= $hoy) {
    header("Location: $panel?error=" . urlencode('Elegí una fecha de devolución posterior a hoy.'));
    exit;
}

// --- Determinar qué equipo puntual se va a prestar ---
$equipo_id = null;

if ($rol === 'docente') {
    // El docente elige un equipo específico del listado
    $equipo_id = (int)($_POST['equipo_id'] ?? 0);

    $stmt = $conn->prepare("SELECT id FROM equipos WHERE id = ? AND estado = 'Disponible'");
    $stmt->bind_param('i', $equipo_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows !== 1) {
        header("Location: $panel?error=" . urlencode('Ese equipo ya no está disponible.'));
        exit;
    }
} else {
    // El estudiante elige un TIPO; el sistema asigna la primera unidad disponible de ese tipo
    $tipo = trim($_POST['tipo'] ?? '');

    $stmt = $conn->prepare("SELECT id FROM equipos WHERE tipo = ? AND estado = 'Disponible' ORDER BY id LIMIT 1");
    $stmt->bind_param('s', $tipo);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows !== 1) {
        header("Location: $panel?error=" . urlencode('Ya no queda ninguna unidad disponible de ese tipo.'));
        exit;
    }
    $equipo_id = $res->fetch_assoc()['id'];
}

// --- Crear el préstamo y marcar el equipo como Prestado (transacción) ---
$conn->begin_transaction();
try {
    $stmt = $conn->prepare("
        INSERT INTO prestamos (usuario_id, equipo_id, fecha_prestamo, fecha_devolucion_prevista, estado)
        VALUES (?, ?, ?, ?, 'Activo')
    ");
    $stmt->bind_param('iiss', $usuario_id, $equipo_id, $hoy, $fecha_devolucion_prevista);
    $stmt->execute();

    $stmt = $conn->prepare("UPDATE equipos SET estado = 'Prestado' WHERE id = ? AND estado = 'Disponible'");
    $stmt->bind_param('i', $equipo_id);
    $stmt->execute();

    if ($stmt->affected_rows !== 1) {
        // Alguien más se lo llevó justo antes (condición de carrera) -> deshacemos todo
        throw new Exception('sin_stock');
    }

    $conn->commit();
    header("Location: $panel?ok=" . urlencode('¡Listo! Equipo reservado correctamente.'));
    exit;

} catch (Exception $e) {
    $conn->rollback();
    header("Location: $panel?error=" . urlencode('Ese equipo se acaba de reservar, elegí otro.'));
    exit;
}

<?php
require_once 'config.php';
require_once 'includes/auth_check.php';
require_once 'includes/helpers.php';
requerir_rol('admin');

$id_admin = (int)$_SESSION['id_usuario'];
$errores = [];

$id_reserva = isset($_GET['id_reserva']) ? (int)$_GET['id_reserva'] : (isset($_POST['id_reserva']) ? (int)$_POST['id_reserva'] : 0);
$reserva_origen = null;

if ($id_reserva) {
    $stmt = $conn->prepare("
        SELECT r.id_reserva, r.id_usuario, r.id_equipo, r.fecha_fin, r.estado,
               u.nombre, u.apellido, u.rol AS rol_usuario,
               e.numero_serie, e.tipo, e.disponibilidad
        FROM reserva r
        JOIN usuario u ON u.id_usuario = r.id_usuario
        JOIN equipo e ON e.id_equipo = r.id_equipo
        WHERE r.id_reserva = ?
    ");
    $stmt->bind_param('i', $id_reserva);
    $stmt->execute();
    $reserva_origen = $stmt->get_result()->fetch_assoc();

    if (!$reserva_origen || $reserva_origen['estado'] !== 'pendiente') {
        header('Location: dashboard_administrador.php?error=' . urlencode('Esa reserva ya no está pendiente de conversión.'));
        exit;
    }
}

// Listas para los selectores (solo cuando NO viene de una reserva)
$usuarios = $conn->query("SELECT id_usuario, nombre, apellido, rol FROM usuario WHERE activo = 1 AND rol IN ('docente','estudiante') ORDER BY apellido");
$lista_usuarios = $usuarios->fetch_all(MYSQLI_ASSOC);

$equipos = $conn->query("SELECT id_equipo, numero_serie, tipo FROM equipo WHERE disponibilidad = 'disponible' ORDER BY tipo, numero_serie");
$lista_equipos = $equipos->fetch_all(MYSQLI_ASSOC);

$prestamo = [
    'id_usuario' => $reserva_origen['id_usuario'] ?? '',
    'id_equipo'  => $reserva_origen['id_equipo'] ?? '',
    'fecha_devolucion_esperada' => $reserva_origen ? date('Y-m-d', max(strtotime($reserva_origen['fecha_fin']), strtotime('today'))) : date('Y-m-d', strtotime('+7 days')),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $prestamo['id_usuario'] = (int)($_POST['id_usuario'] ?? 0);
    $prestamo['id_equipo']  = (int)($_POST['id_equipo'] ?? 0);
    $prestamo['fecha_devolucion_esperada'] = $_POST['fecha_devolucion_esperada'] ?? '';

    $stmt = $conn->prepare("SELECT id_usuario, nombre, rol, activo FROM usuario WHERE id_usuario = ?");
    $stmt->bind_param('i', $prestamo['id_usuario']);
    $stmt->execute();
    $usuario_dest = $stmt->get_result()->fetch_assoc();

    $stmt = $conn->prepare("SELECT id_equipo, numero_serie, tipo, disponibilidad FROM equipo WHERE id_equipo = ? FOR UPDATE");
    $stmt->bind_param('i', $prestamo['id_equipo']);
    $stmt->execute();
    $equipo_dest = $stmt->get_result()->fetch_assoc();

    if (!$usuario_dest || !$usuario_dest['activo'] || !in_array($usuario_dest['rol'], ['docente', 'estudiante'], true)) {
        $errores[] = 'Elegí un usuario (docente o estudiante) activo.';
    } elseif ($sancion = sancion_vigente($conn, $usuario_dest['id_usuario'])) {
        $errores[] = 'Ese usuario tiene una sanción vigente hasta el ' . date('d/m/Y', strtotime($sancion['fecha_fin'])) . ' y no puede recibir préstamos.';
    }
    if (!$equipo_dest || ($equipo_dest['disponibilidad'] !== 'disponible' && $equipo_dest['id_equipo'] != ($reserva_origen['id_equipo'] ?? -1))) {
        $errores[] = 'Ese equipo ya no está disponible.';
    }
    if (isset($usuario_dest['rol']) && $usuario_dest['rol'] === 'estudiante' && isset($equipo_dest['tipo']) && $equipo_dest['tipo'] !== 'Ceibalita') {
        $errores[] = 'Los estudiantes solo pueden llevar ceibalitas en préstamo.';
    }
    if ($prestamo['fecha_devolucion_esperada'] === '' || strtotime($prestamo['fecha_devolucion_esperada']) < strtotime('today')) {
        $errores[] = 'La fecha de devolución esperada debe ser hoy o una fecha futura.';
    }

    if (empty($errores)) {
        $stmt = $conn->prepare("
            INSERT INTO prestamo (id_usuario, id_equipo, id_admin, id_reserva, fecha_devolucion_esperada, estado)
            VALUES (?,?,?,?,?,'activo')
        ");
        $id_reserva_param = $id_reserva ?: null;
        $stmt->bind_param('iiiis', $prestamo['id_usuario'], $prestamo['id_equipo'], $id_admin, $id_reserva_param, $prestamo['fecha_devolucion_esperada']);

        if ($stmt->execute()) {
            $id_prestamo = $conn->insert_id;
            $conn->query("UPDATE equipo SET disponibilidad = 'prestado' WHERE id_equipo = " . (int)$prestamo['id_equipo']);
            if ($id_reserva) {
                $conn->query("UPDATE reserva SET estado = 'convertida' WHERE id_reserva = " . (int)$id_reserva);
            }
            $msg = "Se registró tu préstamo de {$equipo_dest['tipo']} ({$equipo_dest['numero_serie']}). Devolución esperada: " . date('d/m/Y', strtotime($prestamo['fecha_devolucion_esperada'])) . '.';
            crear_notificacion($conn, $prestamo['id_usuario'], 'confirmacion_prestamo', $msg);
            registrar_log($conn, $id_admin, 'Registró préstamo', 'prestamo', $id_prestamo);

            header('Location: dashboard_administrador.php?ok=' . urlencode('Préstamo registrado correctamente.'));
            exit;
        }
        $errores[] = 'No se pudo registrar el préstamo: ' . $conn->error;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>SGPET - Registrar préstamo</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="form-page">
        <h2><?php echo $reserva_origen ? 'Convertir reserva en préstamo' : 'Registrar préstamo'; ?></h2>

        <?php foreach ($errores as $err): ?>
            <div class="error-msg"><?php echo htmlspecialchars($err); ?></div>
        <?php endforeach; ?>

        <form method="POST">
            <?php if ($reserva_origen): ?>
                <input type="hidden" name="id_reserva" value="<?php echo $id_reserva; ?>">
                <input type="hidden" name="id_usuario" value="<?php echo $reserva_origen['id_usuario']; ?>">
                <input type="hidden" name="id_equipo" value="<?php echo $reserva_origen['id_equipo']; ?>">
                <div class="input-group">
                    <label>Usuario</label>
                    <p><?php echo htmlspecialchars($reserva_origen['nombre'] . ' ' . $reserva_origen['apellido'] . ' (' . $reserva_origen['rol_usuario'] . ')'); ?></p>
                </div>
                <div class="input-group">
                    <label>Equipo</label>
                    <p><?php echo htmlspecialchars($reserva_origen['tipo'] . ' — ' . $reserva_origen['numero_serie']); ?></p>
                </div>
            <?php else: ?>
                <div class="input-group">
                    <label>Usuario (docente o estudiante)</label>
                    <select name="id_usuario" required>
                        <option value="">-- Elegí un usuario --</option>
                        <?php foreach ($lista_usuarios as $u): ?>
                            <option value="<?php echo $u['id_usuario']; ?>" <?php echo (string)$prestamo['id_usuario'] === (string)$u['id_usuario'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($u['nombre'] . ' ' . $u['apellido'] . ' (' . $u['rol'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="input-group">
                    <label>Equipo disponible</label>
                    <select name="id_equipo" required>
                        <option value="">-- Elegí un equipo --</option>
                        <?php foreach ($lista_equipos as $e): ?>
                            <option value="<?php echo $e['id_equipo']; ?>" <?php echo (string)$prestamo['id_equipo'] === (string)$e['id_equipo'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($e['tipo'] . ' — ' . $e['numero_serie']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="nota">Recordá: si el usuario es estudiante, solo puede llevarse ceibalitas.</p>
                </div>
            <?php endif; ?>

            <div class="input-group">
                <label>Fecha de devolución esperada</label>
                <input type="date" name="fecha_devolucion_esperada" required min="<?php echo date('Y-m-d'); ?>"
                       value="<?php echo htmlspecialchars($prestamo['fecha_devolucion_esperada']); ?>">
            </div>

            <div class="acciones">
                <button type="submit" class="btn btn-azul">Registrar préstamo</button>
                <a href="dashboard_administrador.php" class="btn btn-editar">Cancelar</a>
            </div>
        </form>
    </div>
</body>
</html>

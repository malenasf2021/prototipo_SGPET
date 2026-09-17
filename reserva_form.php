<?php
require_once 'config.php';
require_once 'includes/auth_check.php';
require_once 'includes/helpers.php';
requerir_rol_alguno(['docente', 'estudiante']);

$rol = $_SESSION['rol'];
$id_usuario = (int)$_SESSION['id_usuario'];
$errores = [];

// Un usuario sancionado no puede reservar equipos.
$sancion = sancion_vigente($conn, $id_usuario);
if ($sancion) {
    require_once 'includes/vista_sancion_bloqueo.php';
    exit;
}

// Los estudiantes solo pueden reservar ceibalitas (regla de negocio del CU).
if ($rol === 'estudiante') {
    $equipos = $conn->query("SELECT id_equipo, numero_serie, tipo FROM equipo WHERE disponibilidad = 'disponible' AND tipo = 'Ceibalita' ORDER BY numero_serie");
} else {
    $equipos = $conn->query("SELECT id_equipo, numero_serie, tipo FROM equipo WHERE disponibilidad = 'disponible' ORDER BY tipo, numero_serie");
}
$lista_equipos = $equipos->fetch_all(MYSQLI_ASSOC);
$ids_permitidos = array_column($lista_equipos, 'id_equipo');

$reserva = [
    'id_equipo'   => (int)($_GET['id_equipo'] ?? 0),
    'fecha_inicio'=> date('Y-m-d\TH:i'),
    'fecha_fin'   => date('Y-m-d\TH:i', strtotime('+2 days')),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reserva['id_equipo']    = (int)($_POST['id_equipo'] ?? 0);
    $reserva['fecha_inicio'] = $_POST['fecha_inicio'] ?? '';
    $reserva['fecha_fin']    = $_POST['fecha_fin'] ?? '';

    if (!in_array($reserva['id_equipo'], $ids_permitidos, true)) {
        $errores[] = $rol === 'estudiante'
            ? 'Como estudiante solo podés reservar ceibalitas, y deben estar disponibles.'
            : 'Elegí un equipo disponible de la lista.';
    }
    if ($reserva['fecha_inicio'] === '' || $reserva['fecha_fin'] === '') {
        $errores[] = 'Completá la fecha de inicio y de fin de la reserva.';
    } elseif (strtotime($reserva['fecha_fin']) <= strtotime($reserva['fecha_inicio'])) {
        $errores[] = 'La fecha de fin debe ser posterior a la fecha de inicio.';
    } elseif (strtotime($reserva['fecha_inicio']) < strtotime('-1 minute')) {
        $errores[] = 'La fecha de inicio no puede estar en el pasado.';
    }

    if (empty($errores)) {
        // Revalidamos disponibilidad justo antes de guardar (por si cambió).
        $stmt = $conn->prepare("SELECT numero_serie, tipo, disponibilidad FROM equipo WHERE id_equipo = ? FOR UPDATE");
        $stmt->bind_param('i', $reserva['id_equipo']);
        $stmt->execute();
        $eq = $stmt->get_result()->fetch_assoc();

        if (!$eq || $eq['disponibilidad'] !== 'disponible') {
            $errores[] = 'Ese equipo ya no está disponible; elegí otro.';
        } else {
            $stmt = $conn->prepare("INSERT INTO reserva (id_usuario, id_equipo, fecha_inicio, fecha_fin, estado) VALUES (?,?,?,?,'pendiente')");
            $fi = str_replace('T', ' ', $reserva['fecha_inicio']);
            $ff = str_replace('T', ' ', $reserva['fecha_fin']);
            $stmt->bind_param('iiss', $id_usuario, $reserva['id_equipo'], $fi, $ff);

            if ($stmt->execute()) {
                $id_reserva = $conn->insert_id;
                $conn->query("UPDATE equipo SET disponibilidad = 'reservado' WHERE id_equipo = " . (int)$reserva['id_equipo']);
                crear_notificacion($conn, $id_usuario, 'reserva', "Reservaste el equipo {$eq['tipo']} ({$eq['numero_serie']}) para el " . date('d/m/Y H:i', strtotime($fi)) . '.');
                registrar_log($conn, $id_usuario, 'Creó reserva', 'reserva', $id_reserva);

                $panel = panel_de_rol($rol);
                header('Location: ' . $panel . '?ok=' . urlencode('Reserva creada. Un administrador puede convertirla en préstamo cuando retires el equipo.'));
                exit;
            }
            $errores[] = 'No se pudo crear la reserva: ' . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>SGPET - Nueva reserva</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="form-page">
        <h2>Reservar equipo</h2>

        <?php foreach ($errores as $err): ?>
            <div class="error-msg"><?php echo htmlspecialchars($err); ?></div>
        <?php endforeach; ?>

        <?php if (empty($lista_equipos)): ?>
            <p class="nota">No hay equipos disponibles para reservar en este momento<?php echo $rol === 'estudiante' ? ' (ceibalitas).' : '.'; ?></p>
            <div class="acciones">
                <a href="<?php echo panel_de_rol($rol); ?>" class="btn btn-editar">Volver al panel</a>
            </div>
        <?php else: ?>
        <form method="POST">
            <div class="input-group">
                <label>Equipo</label>
                <select name="id_equipo" required>
                    <option value="">-- Elegí un equipo --</option>
                    <?php foreach ($lista_equipos as $e): ?>
                        <option value="<?php echo $e['id_equipo']; ?>" <?php echo $reserva['id_equipo'] == $e['id_equipo'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($e['tipo'] . ' — ' . $e['numero_serie']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($rol === 'estudiante'): ?>
                    <p class="nota">Como estudiante, solo podés reservar ceibalitas.</p>
                <?php endif; ?>
            </div>
            <div class="input-group">
                <label>Fecha y hora de inicio</label>
                <input type="datetime-local" name="fecha_inicio" required value="<?php echo htmlspecialchars($reserva['fecha_inicio']); ?>">
            </div>
            <div class="input-group">
                <label>Fecha y hora de fin</label>
                <input type="datetime-local" name="fecha_fin" required value="<?php echo htmlspecialchars($reserva['fecha_fin']); ?>">
            </div>

            <div class="acciones">
                <button type="submit" class="btn btn-azul">Reservar</button>
                <a href="<?php echo panel_de_rol($rol); ?>" class="btn btn-editar">Cancelar</a>
            </div>
        </form>
        <?php endif; ?>
    </div>
</body>
</html>

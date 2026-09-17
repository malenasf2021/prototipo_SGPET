<?php
require_once 'config.php';
require_once 'includes/auth_check.php';
require_once 'includes/helpers.php';
requerir_rol('estudiante');

$id_usuario = (int)$_SESSION['id_usuario'];

$resumen = $conn->query("
    SELECT tipo, COUNT(*) AS disponibles
    FROM equipo
    WHERE disponibilidad = 'disponible'
    GROUP BY tipo
    ORDER BY tipo
");

$mis_reservas = $conn->query("
    SELECT r.id_reserva, r.fecha_inicio, r.fecha_fin, r.estado, e.tipo, e.numero_serie
    FROM reserva r JOIN equipo e ON e.id_equipo = r.id_equipo
    WHERE r.id_usuario = $id_usuario
    ORDER BY r.fecha_inicio DESC
");

$mis_prestamos = $conn->query("
    SELECT p.id_prestamo, p.fecha_solicitud, p.fecha_devolucion_esperada, p.fecha_devolucion_real, p.estado, e.tipo, e.numero_serie
    FROM prestamo p JOIN equipo e ON e.id_equipo = p.id_equipo
    WHERE p.id_usuario = $id_usuario
    ORDER BY p.fecha_solicitud DESC
");

$mis_notificaciones = $conn->query("
    SELECT id_notificacion, tipo, mensaje, fecha_envio, leida
    FROM notificacion WHERE id_usuario = $id_usuario
    ORDER BY leida ASC, fecha_envio DESC LIMIT 15
");

$sancion_actual = sancion_vigente($conn, $id_usuario);

$mensaje_ok = $_GET['ok'] ?? '';
$mensaje_error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>SGPET - Panel Estudiante</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body class="panel">
    <div class="topbar">
        <h2>SGPET · Panel Estudiante
            <span class="badge estudiante">ESTUDIANTE</span>
        </h2>
        <div>
            Hola, <?php echo htmlspecialchars($_SESSION['nombre'] . ' ' . $_SESSION['apellido']); ?> —
            <a href="logout.php">Cerrar sesión</a>
        </div>
    </div>

    <div class="container">

        <?php if ($mensaje_ok): ?><div class="card mensaje-ok"><?php echo htmlspecialchars($mensaje_ok); ?></div><?php endif; ?>
        <?php if ($mensaje_error): ?><div class="card mensaje-error"><?php echo htmlspecialchars($mensaje_error); ?></div><?php endif; ?>

        <?php if ($sancion_actual): ?>
        <div class="sancion-aviso">
            Tenés una sanción vigente hasta el <?php echo date('d/m/Y', strtotime($sancion_actual['fecha_fin'])); ?>
            (<?php echo htmlspecialchars($sancion_actual['motivo']); ?>). No podés generar nuevas reservas mientras esté vigente.
        </div>
        <?php endif; ?>

        <div class="card">
            <h3>
                Disponibilidad general por tipo de equipo
                <?php if (!$sancion_actual): ?><a href="reserva_form.php" class="btn btn-verde btn-sm">+ Reservar ceibalita</a><?php endif; ?>
            </h3>
            <table>
                <tr><th>Tipo de equipo</th><th>Unidades disponibles</th></tr>
                <?php while ($r = $resumen->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($r['tipo']); ?></td>
                    <td><?php echo $r['disponibles']; ?></td>
                </tr>
                <?php endwhile; ?>
            </table>
            <p class="nota">
                Como estudiante ves solo la disponibilidad general por tipo, no el detalle unidad por unidad
                (ese detalle completo lo ve el docente). Además, solo podés reservar ceibalitas.
            </p>
        </div>

        <div class="card">
            <h3>Mis reservas</h3>
            <table>
                <tr><th>Equipo</th><th>Desde</th><th>Hasta</th><th>Estado</th><th>Acciones</th></tr>
                <?php while ($r = $mis_reservas->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($r['tipo'] . ' — ' . $r['numero_serie']); ?></td>
                    <td><?php echo date('d/m/Y H:i', strtotime($r['fecha_inicio'])); ?></td>
                    <td><?php echo date('d/m/Y H:i', strtotime($r['fecha_fin'])); ?></td>
                    <td><?php echo badge_reserva($r['estado']); ?></td>
                    <td>
                        <?php if ($r['estado'] === 'pendiente'): ?>
                        <form action="reserva_cancelar.php" method="POST" style="display:inline;"
                              onsubmit="return confirm('¿Cancelar esta reserva?');">
                            <input type="hidden" name="id" value="<?php echo $r['id_reserva']; ?>">
                            <button type="submit" class="btn btn-eliminar btn-sm">Cancelar</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php if ($mis_reservas->num_rows === 0): ?>
                <tr><td colspan="5" class="nota">Todavía no hiciste reservas.</td></tr>
                <?php endif; ?>
            </table>
        </div>

        <div class="card">
            <h3>Mis préstamos</h3>
            <table>
                <tr><th>Equipo</th><th>Solicitado</th><th>Devolución esperada</th><th>Devolución real</th><th>Estado</th></tr>
                <?php while ($p = $mis_prestamos->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($p['tipo'] . ' — ' . $p['numero_serie']); ?></td>
                    <td><?php echo date('d/m/Y', strtotime($p['fecha_solicitud'])); ?></td>
                    <td><?php echo date('d/m/Y', strtotime($p['fecha_devolucion_esperada'])); ?></td>
                    <td><?php echo $p['fecha_devolucion_real'] ? date('d/m/Y', strtotime($p['fecha_devolucion_real'])) : '-'; ?></td>
                    <td><?php echo badge_prestamo($p['estado']); ?></td>
                </tr>
                <?php endwhile; ?>
                <?php if ($mis_prestamos->num_rows === 0): ?>
                <tr><td colspan="5" class="nota">Todavía no tenés préstamos registrados.</td></tr>
                <?php endif; ?>
            </table>
        </div>

        <div class="card">
            <h3>Notificaciones</h3>
            <?php if ($mis_notificaciones->num_rows === 0): ?>
                <p class="nota">No tenés notificaciones.</p>
            <?php endif; ?>
            <?php while ($n = $mis_notificaciones->fetch_assoc()): ?>
            <div class="notif <?php echo $n['leida'] ? '' : 'no-leida'; ?>">
                <?php echo htmlspecialchars($n['mensaje']); ?>
                <small>
                    <?php echo label_notif_tipo($n['tipo']); ?> · <?php echo date('d/m/Y H:i', strtotime($n['fecha_envio'])); ?>
                    <?php if (!$n['leida']): ?>
                    · <form action="notificacion_marcar_leida.php" method="POST" style="display:inline;">
                        <input type="hidden" name="id" value="<?php echo $n['id_notificacion']; ?>">
                        <button type="submit" class="btn btn-editar btn-sm" style="padding:2px 8px;">Marcar leída</button>
                      </form>
                    <?php endif; ?>
                </small>
            </div>
            <?php endwhile; ?>
        </div>

    </div>
</body>
</html>

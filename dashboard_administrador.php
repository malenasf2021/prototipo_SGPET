<?php
require_once 'config.php';
require_once 'includes/auth_check.php';
require_once 'includes/helpers.php';
requerir_rol('admin');

$usuarios = $conn->query("
    SELECT id_usuario, nombre, apellido, cedula, email, telefono, rol, departamento, grupo, activo
    FROM usuario
    ORDER BY rol, apellido
");

$equipos = $conn->query("
    SELECT id_equipo, numero_serie, tipo, condicion_tecnica, disponibilidad, restriccion_uso
    FROM equipo
    ORDER BY tipo, numero_serie
");

$reservas_pendientes = $conn->query("
    SELECT r.id_reserva, r.fecha_inicio, r.fecha_fin, u.nombre, u.apellido, u.rol AS rol_usuario, e.tipo, e.numero_serie
    FROM reserva r
    JOIN usuario u ON u.id_usuario = r.id_usuario
    JOIN equipo e ON e.id_equipo = r.id_equipo
    WHERE r.estado = 'pendiente'
    ORDER BY r.fecha_inicio
");

$prestamos_vigentes = $conn->query("
    SELECT p.id_prestamo, p.fecha_devolucion_esperada, p.estado, u.nombre, u.apellido, e.tipo, e.numero_serie
    FROM prestamo p
    JOIN usuario u ON u.id_usuario = p.id_usuario
    JOIN equipo e ON e.id_equipo = p.id_equipo
    WHERE p.estado IN ('activo','atrasado')
    ORDER BY p.fecha_devolucion_esperada
");

$fallas_recientes = $conn->query("
    SELECT f.id_falla, f.descripcion, f.gravedad, f.fecha_registro, e.tipo, e.numero_serie
    FROM falla f
    JOIN equipo e ON e.id_equipo = f.id_equipo
    ORDER BY f.fecha_registro DESC
    LIMIT 10
");

$sanciones_vigentes = $conn->query("
    SELECT s.id_sancion, s.motivo, s.fecha_inicio, s.fecha_fin, u.nombre, u.apellido
    FROM sancion s
    JOIN usuario u ON u.id_usuario = s.id_usuario
    WHERE s.fecha_fin >= CURDATE()
    ORDER BY s.fecha_fin
");

$log_reciente = $conn->query("
    SELECT l.accion, l.entidad, l.id_entidad, l.fecha_hora, u.nombre, u.apellido
    FROM log_auditoria l
    JOIN usuario u ON u.id_usuario = l.id_usuario
    ORDER BY l.fecha_hora DESC
    LIMIT 15
");

$mensaje_ok = $_GET['ok'] ?? '';
$mensaje_error = $_GET['error'] ?? '';

function badge_disp($valor) {
    $labels = [
        'disponible' => 'Disponible', 'reservado' => 'Reservado', 'prestado' => 'Prestado',
        'en_mantenimiento' => 'En mantenimiento', 'dado_de_baja' => 'Dado de baja',
    ];
    return '<span class="badge disp-' . htmlspecialchars($valor) . '">' . htmlspecialchars($labels[$valor] ?? $valor) . '</span>';
}
function badge_cond($valor) {
    $labels = [
        'funcional' => 'Funcional', 'dañado' => 'Dañado',
        'en_reparacion' => 'En reparación', 'kit_incompleto' => 'Kit incompleto',
    ];
    return '<span class="badge cond-' . htmlspecialchars($valor) . '">' . htmlspecialchars($labels[$valor] ?? $valor) . '</span>';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>SGPET - Panel Administrador</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body class="panel">
    <div class="topbar">
        <h2>SGPET · Panel de Administración
            <span class="badge admin">ADMIN</span>
        </h2>
        <div>
            Hola, <?php echo htmlspecialchars($_SESSION['nombre']); ?> —
            <a href="logout.php">Cerrar sesión</a>
        </div>
    </div>

    <div class="container">

        <?php if ($mensaje_ok): ?><div class="card mensaje-ok"><?php echo htmlspecialchars($mensaje_ok); ?></div><?php endif; ?>
        <?php if ($mensaje_error): ?><div class="card mensaje-error"><?php echo htmlspecialchars($mensaje_error); ?></div><?php endif; ?>

        <div class="card">
            <h3>
                Usuarios registrados (<?php echo $usuarios->num_rows; ?>)
                <a href="usuario_form.php" class="btn btn-verde btn-sm">+ Nuevo usuario</a>
            </h3>
            <table>
                <tr>
                    <th>ID</th><th>Nombre</th><th>Apellido</th><th>Cédula</th><th>Email</th>
                    <th>Rol</th><th>Depto. / Grupo</th><th>Activo</th><th>Acciones</th>
                </tr>
                <?php while ($u = $usuarios->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $u['id_usuario']; ?></td>
                    <td><?php echo htmlspecialchars($u['nombre']); ?></td>
                    <td><?php echo htmlspecialchars($u['apellido']); ?></td>
                    <td><?php echo htmlspecialchars($u['cedula']); ?></td>
                    <td><?php echo htmlspecialchars($u['email']); ?></td>
                    <td><?php echo htmlspecialchars($u['rol']); ?></td>
                    <td><?php echo htmlspecialchars($u['departamento'] ?? $u['grupo'] ?? '-'); ?></td>
                    <td><span class="badge activo-<?php echo $u['activo'] ? 'si' : 'no'; ?>"><?php echo $u['activo'] ? 'Sí' : 'No'; ?></span></td>
                    <td style="white-space:nowrap;">
                        <a href="usuario_form.php?id=<?php echo $u['id_usuario']; ?>" class="btn btn-editar btn-sm">Editar</a>

                        <form action="usuario_resetear_password.php" method="POST" style="display:inline;"
                              onsubmit="return confirm('¿Generar una contraseña temporal para este usuario?');">
                            <input type="hidden" name="id" value="<?php echo $u['id_usuario']; ?>">
                            <button type="submit" class="btn btn-editar btn-sm">Resetear clave</button>
                        </form>

                        <form action="usuario_eliminar.php" method="POST" style="display:inline;"
                              onsubmit="return confirm('¿<?php echo $u['activo'] ? 'Desactivar' : 'Reactivar'; ?> este usuario?');">
                            <input type="hidden" name="id" value="<?php echo $u['id_usuario']; ?>">
                            <button type="submit" class="btn btn-eliminar btn-sm"><?php echo $u['activo'] ? 'Desactivar' : 'Reactivar'; ?></button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
            </table>
            <p class="nota">
                "Desactivar" no borra el registro (queda como historial futuro para préstamos/sanciones); solo bloquea el login.
            </p>
        </div>

        <div class="card">
            <h3>
                Inventario de equipos (<?php echo $equipos->num_rows; ?>)
                <a href="equipo_form.php" class="btn btn-verde btn-sm">+ Nuevo equipo</a>
            </h3>
            <table>
                <tr>
                    <th>N° de serie</th><th>Tipo</th><th>Condición técnica</th><th>Disponibilidad</th>
                    <th>Restricción de uso</th><th>Acciones</th>
                </tr>
                <?php while ($e = $equipos->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($e['numero_serie']); ?></td>
                    <td><?php echo htmlspecialchars($e['tipo']); ?></td>
                    <td><?php echo badge_cond($e['condicion_tecnica']); ?></td>
                    <td><?php echo badge_disp($e['disponibilidad']); ?></td>
                    <td><?php echo htmlspecialchars($e['restriccion_uso'] ?? '-'); ?></td>
                    <td style="white-space:nowrap;">
                        <a href="equipo_form.php?id=<?php echo $e['id_equipo']; ?>" class="btn btn-editar btn-sm">Editar</a>
                        <form action="equipo_eliminar.php" method="POST" style="display:inline;"
                              onsubmit="return confirm('¿Eliminar este equipo del inventario?');">
                            <input type="hidden" name="id" value="<?php echo $e['id_equipo']; ?>">
                            <button type="submit" class="btn btn-eliminar btn-sm">Eliminar</button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
            </table>
        </div>

        <div class="card">
            <h3>
                Reservas pendientes (<?php echo $reservas_pendientes->num_rows; ?>)
                <form action="generar_recordatorios.php" method="POST" style="display:inline;">
                    <button type="submit" class="btn btn-editar btn-sm">Generar recordatorios de vencimiento</button>
                </form>
            </h3>
            <table>
                <tr><th>Usuario</th><th>Equipo</th><th>Desde</th><th>Hasta</th><th>Acciones</th></tr>
                <?php while ($r = $reservas_pendientes->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($r['nombre'] . ' ' . $r['apellido'] . ' (' . $r['rol_usuario'] . ')'); ?></td>
                    <td><?php echo htmlspecialchars($r['tipo'] . ' — ' . $r['numero_serie']); ?></td>
                    <td><?php echo date('d/m/Y H:i', strtotime($r['fecha_inicio'])); ?></td>
                    <td><?php echo date('d/m/Y H:i', strtotime($r['fecha_fin'])); ?></td>
                    <td style="white-space:nowrap;">
                        <a href="prestamo_form.php?id_reserva=<?php echo $r['id_reserva']; ?>" class="btn btn-verde btn-sm">Convertir en préstamo</a>
                        <form action="reserva_cancelar.php" method="POST" style="display:inline;"
                              onsubmit="return confirm('¿Cancelar esta reserva?');">
                            <input type="hidden" name="id" value="<?php echo $r['id_reserva']; ?>">
                            <button type="submit" class="btn btn-eliminar btn-sm">Cancelar</button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php if ($reservas_pendientes->num_rows === 0): ?>
                <tr><td colspan="5" class="nota">No hay reservas pendientes.</td></tr>
                <?php endif; ?>
            </table>
        </div>

        <div class="card">
            <h3>
                Préstamos vigentes (<?php echo $prestamos_vigentes->num_rows; ?>)
                <a href="prestamo_form.php" class="btn btn-verde btn-sm">+ Registrar préstamo</a>
            </h3>
            <table>
                <tr><th>Usuario</th><th>Equipo</th><th>Devolución esperada</th><th>Estado</th><th>Acciones</th></tr>
                <?php while ($p = $prestamos_vigentes->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($p['nombre'] . ' ' . $p['apellido']); ?></td>
                    <td><?php echo htmlspecialchars($p['tipo'] . ' — ' . $p['numero_serie']); ?></td>
                    <td><?php echo date('d/m/Y', strtotime($p['fecha_devolucion_esperada'])); ?></td>
                    <td><?php echo badge_prestamo($p['estado']); ?></td>
                    <td>
                        <a href="prestamo_devolucion.php?id=<?php echo $p['id_prestamo']; ?>" class="btn btn-editar btn-sm">Registrar devolución</a>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php if ($prestamos_vigentes->num_rows === 0): ?>
                <tr><td colspan="5" class="nota">No hay préstamos activos.</td></tr>
                <?php endif; ?>
            </table>
        </div>

        <div class="card">
            <h3>Sanciones vigentes (<?php echo $sanciones_vigentes->num_rows; ?>)
                <a href="sancion_form.php" class="btn btn-verde btn-sm">+ Aplicar sanción</a>
            </h3>
            <table>
                <tr><th>Usuario</th><th>Motivo</th><th>Desde</th><th>Hasta</th><th>Acciones</th></tr>
                <?php while ($s = $sanciones_vigentes->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($s['nombre'] . ' ' . $s['apellido']); ?></td>
                    <td><?php echo htmlspecialchars($s['motivo']); ?></td>
                    <td><?php echo date('d/m/Y', strtotime($s['fecha_inicio'])); ?></td>
                    <td><?php echo date('d/m/Y', strtotime($s['fecha_fin'])); ?></td>
                    <td>
                        <form action="sancion_levantar.php" method="POST" style="display:inline;"
                              onsubmit="return confirm('¿Levantar esta sanción ahora?');">
                            <input type="hidden" name="id" value="<?php echo $s['id_sancion']; ?>">
                            <button type="submit" class="btn btn-eliminar btn-sm">Levantar</button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php if ($sanciones_vigentes->num_rows === 0): ?>
                <tr><td colspan="5" class="nota">No hay sanciones vigentes.</td></tr>
                <?php endif; ?>
            </table>
        </div>

        <div class="card">
            <h3>Fallas registradas recientemente</h3>
            <table>
                <tr><th>Equipo</th><th>Descripción</th><th>Gravedad</th><th>Fecha</th></tr>
                <?php while ($f = $fallas_recientes->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($f['tipo'] . ' — ' . $f['numero_serie']); ?></td>
                    <td><?php echo htmlspecialchars($f['descripcion']); ?></td>
                    <td><?php echo badge_gravedad($f['gravedad']); ?></td>
                    <td><?php echo date('d/m/Y H:i', strtotime($f['fecha_registro'])); ?></td>
                </tr>
                <?php endwhile; ?>
                <?php if ($fallas_recientes->num_rows === 0): ?>
                <tr><td colspan="4" class="nota">Todavía no se registraron fallas.</td></tr>
                <?php endif; ?>
            </table>
            <p class="nota">Las fallas se registran automáticamente al procesar una devolución con inspección técnica.</p>
        </div>

        <div class="card">
            <h3>Auditoría — últimas acciones</h3>
            <table>
                <tr><th>Fecha y hora</th><th>Usuario</th><th>Acción</th><th>Entidad</th></tr>
                <?php while ($l = $log_reciente->fetch_assoc()): ?>
                <tr>
                    <td><?php echo date('d/m/Y H:i', strtotime($l['fecha_hora'])); ?></td>
                    <td><?php echo htmlspecialchars($l['nombre'] . ' ' . $l['apellido']); ?></td>
                    <td><?php echo htmlspecialchars($l['accion']); ?></td>
                    <td><?php echo htmlspecialchars($l['entidad'] . ($l['id_entidad'] ? ' #' . $l['id_entidad'] : '')); ?></td>
                </tr>
                <?php endwhile; ?>
            </table>
        </div>

    </div>
</body>
</html>

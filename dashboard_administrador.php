<?php
require_once 'config.php';
require_once 'includes/auth_check.php';
requerir_rol('administrador');

// Listado completo de usuarios
$usuarios = $conn->query("SELECT id, nombre, apellido, email, rol, grupo FROM usuarios ORDER BY rol, apellido");

// Listado completo de equipos
$equipos = $conn->query("SELECT id, nombre, tipo, estado FROM equipos ORDER BY tipo, nombre");

// Préstamos con datos de usuario y equipo
$prestamos = $conn->query("
    SELECT p.id, u.nombre, u.apellido, e.nombre AS equipo,
           p.fecha_prestamo, p.fecha_devolucion_prevista, p.fecha_devolucion, p.estado
    FROM prestamos p
    JOIN usuarios u ON u.id = p.usuario_id
    JOIN equipos  e ON e.id = p.equipo_id
    ORDER BY (p.estado = 'Activo' OR p.estado = 'Atrasado') DESC, p.fecha_prestamo DESC
");

$mensaje_ok = $_GET['ok'] ?? '';
$mensaje_error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>SGPET - Panel Administrador</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
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

        <?php if ($mensaje_ok): ?>
            <div class="card mensaje-ok"><?php echo htmlspecialchars($mensaje_ok); ?></div>
        <?php endif; ?>
        <?php if ($mensaje_error): ?>
            <div class="card mensaje-error"><?php echo htmlspecialchars($mensaje_error); ?></div>
        <?php endif; ?>

        <div class="card">
            <h3>
                Usuarios registrados (<?php echo $usuarios->num_rows; ?>)
                <a href="usuario_form.php" class="btn btn-verde btn-sm">+ Nuevo usuario</a>
            </h3>
            <table>
                <tr>
                    <th>ID</th><th>Nombre</th><th>Apellido</th><th>Email</th><th>Rol</th><th>Grupo</th><th>Acciones</th>
                </tr>
                <?php while ($u = $usuarios->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $u['id']; ?></td>
                    <td><?php echo htmlspecialchars($u['nombre']); ?></td>
                    <td><?php echo htmlspecialchars($u['apellido']); ?></td>
                    <td><?php echo htmlspecialchars($u['email']); ?></td>
                    <td><?php echo htmlspecialchars($u['rol']); ?></td>
                    <td><?php echo htmlspecialchars($u['grupo'] ?? '-'); ?></td>
                    <td style="white-space:nowrap;">
                        <a href="usuario_form.php?id=<?php echo $u['id']; ?>" class="btn btn-editar btn-sm">Editar</a>

                        <form action="usuario_resetear_password.php" method="POST" style="display:inline;"
                              onsubmit="return confirm('¿Generar una contraseña temporal para este usuario?');">
                            <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                            <button type="submit" class="btn btn-editar btn-sm">Resetear clave</button>
                        </form>

                        <form action="usuario_eliminar.php" method="POST" style="display:inline;"
                              onsubmit="return confirm('¿Eliminar este usuario? Esta acción no se puede deshacer.');">
                            <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                            <button type="submit" class="btn btn-eliminar btn-sm">Eliminar</button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
            </table>
        </div>

        <div class="card">
            <h3>
                Inventario de equipos (<?php echo $equipos->num_rows; ?>)
                <a href="equipo_form.php" class="btn btn-verde btn-sm">+ Nuevo equipo</a>
            </h3>
            <table>
                <tr><th>ID</th><th>Nombre</th><th>Tipo</th><th>Estado</th><th>Acciones</th></tr>
                <?php while ($e = $equipos->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $e['id']; ?></td>
                    <td><?php echo htmlspecialchars($e['nombre']); ?></td>
                    <td><?php echo htmlspecialchars($e['tipo']); ?></td>
                    <td><?php echo htmlspecialchars($e['estado']); ?></td>
                    <td style="white-space:nowrap;">
                        <a href="equipo_form.php?id=<?php echo $e['id']; ?>" class="btn btn-editar btn-sm">Editar</a>
                        <form action="equipo_eliminar.php" method="POST" style="display:inline;"
                              onsubmit="return confirm('¿Eliminar este equipo?');">
                            <input type="hidden" name="id" value="<?php echo $e['id']; ?>">
                            <button type="submit" class="btn btn-eliminar btn-sm">Eliminar</button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
            </table>
        </div>

        <div class="card">
            <h3>Préstamos registrados (<?php echo $prestamos->num_rows; ?>)</h3>
            <table>
                <tr>
                    <th>Usuario</th><th>Equipo</th><th>F. préstamo</th><th>Devol. prevista</th>
                    <th>Devol. real</th><th>Estado</th><th>Acciones</th>
                </tr>
                <?php while ($p = $prestamos->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($p['nombre'] . ' ' . $p['apellido']); ?></td>
                    <td><?php echo htmlspecialchars($p['equipo']); ?></td>
                    <td><?php echo htmlspecialchars($p['fecha_prestamo']); ?></td>
                    <td><?php echo htmlspecialchars($p['fecha_devolucion_prevista']); ?></td>
                    <td><?php echo htmlspecialchars($p['fecha_devolucion'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($p['estado']); ?></td>
                    <td>
                        <?php if (in_array($p['estado'], ['Activo', 'Atrasado'])): ?>
                        <form action="prestamo_devolver.php" method="POST"
                              onsubmit="return confirm('¿Registrar la devolución de este equipo?');">
                            <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                            <button type="submit" class="btn btn-azul btn-sm">Registrar devolución</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </table>
        </div>

    </div>
</body>
</html>

<?php
require_once 'config.php';
require_once 'includes/auth_check.php';
requerir_rol('docente');

$id = $_SESSION['usuario_id'];

// Equipos disponibles para reservar
$disponibles = $conn->query("SELECT id, nombre, tipo, estado FROM equipos WHERE estado = 'Disponible' ORDER BY tipo, nombre");

// Préstamos propios del docente
$stmt = $conn->prepare("
    SELECT p.id, e.nombre AS equipo, p.fecha_prestamo, p.fecha_devolucion_prevista, p.fecha_devolucion, p.estado
    FROM prestamos p
    JOIN equipos e ON e.id = p.equipo_id
    WHERE p.usuario_id = ?
    ORDER BY p.fecha_prestamo DESC
");
$stmt->bind_param('i', $id);
$stmt->execute();
$mis_prestamos = $stmt->get_result();

$mensaje_ok = $_GET['ok'] ?? '';
$mensaje_error = $_GET['error'] ?? '';
$fecha_minima = date('Y-m-d', strtotime('+1 day'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>SGPET - Panel Docente</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="topbar">
        <h2>SGPET · Panel Docente
            <span class="badge docente">DOCENTE</span>
        </h2>
        <div>
            Hola, <?php echo htmlspecialchars($_SESSION['nombre'] . ' ' . $_SESSION['apellido']); ?> —
            <a href="logout.php">Cerrar sesión</a>
        </div>
    </div>

    <div class="container">

        <?php if ($mensaje_ok): ?>
            <div class="card" style="background:#eaf7ec; border:1px solid #b7e0bd; color:#256029;">
                <?php echo htmlspecialchars($mensaje_ok); ?>
            </div>
        <?php endif; ?>
        <?php if ($mensaje_error): ?>
            <div class="card" style="background:#fdecea; border:1px solid #f3b7b0; color:#b3261e;">
                <?php echo htmlspecialchars($mensaje_error); ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <h3>Equipamiento disponible para reservar (<?php echo $disponibles->num_rows; ?>)</h3>
            <form action="crear_prestamo.php" method="POST" style="margin-top:12px; display:flex; gap:12px; align-items:end; flex-wrap:wrap;">
                <div>
                    <label style="display:block; font-size:13px; margin-bottom:4px;">Equipo</label>
                    <select name="equipo_id" required style="padding:8px; border-radius:6px; border:1px solid #ccc; min-width:220px;">
                        <option value="">Elegí un equipo…</option>
                        <?php
                        $disponibles->data_seek(0);
                        while ($e = $disponibles->fetch_assoc()):
                        ?>
                        <option value="<?php echo $e['id']; ?>">
                            <?php echo htmlspecialchars($e['nombre'] . ' (' . $e['tipo'] . ')'); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div>
                    <label style="display:block; font-size:13px; margin-bottom:4px;">Devuelvo el</label>
                    <input type="date" name="fecha_devolucion_prevista" min="<?php echo $fecha_minima; ?>" required
                           style="padding:8px; border-radius:6px; border:1px solid #ccc;">
                </div>
                <button type="submit" style="padding:9px 20px; background:#1e3a5f; color:#fff; border:none; border-radius:6px; cursor:pointer;">
                    Solicitar equipo
                </button>
            </form>

            <table style="margin-top:18px;">
                <tr><th>Equipo</th><th>Tipo</th><th>Estado</th></tr>
                <?php
                $disponibles->data_seek(0);
                while ($e = $disponibles->fetch_assoc()):
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($e['nombre']); ?></td>
                    <td><?php echo htmlspecialchars($e['tipo']); ?></td>
                    <td><?php echo htmlspecialchars($e['estado']); ?></td>
                </tr>
                <?php endwhile; ?>
            </table>
        </div>

        <div class="card">
            <h3>Mis préstamos (<?php echo $mis_prestamos->num_rows; ?>)</h3>
            <table>
                <tr><th>Equipo</th><th>Fecha préstamo</th><th>Devolución prevista</th><th>Devolución real</th><th>Estado</th></tr>
                <?php while ($p = $mis_prestamos->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($p['equipo']); ?></td>
                    <td><?php echo htmlspecialchars($p['fecha_prestamo']); ?></td>
                    <td><?php echo htmlspecialchars($p['fecha_devolucion_prevista']); ?></td>
                    <td><?php echo htmlspecialchars($p['fecha_devolucion'] ?? 'Pendiente'); ?></td>
                    <td><?php echo htmlspecialchars($p['estado']); ?></td>
                </tr>
                <?php endwhile; ?>
            </table>
        </div>

    </div>
</body>
</html>

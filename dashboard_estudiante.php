<?php
require_once 'config.php';
require_once 'includes/auth_check.php';
requerir_rol('estudiante');

$id = $_SESSION['usuario_id'];

// Solo cantidad disponible por tipo (vista simplificada, sin el detalle completo del inventario)
$resumen = $conn->query("
    SELECT tipo, COUNT(*) AS disponibles
    FROM equipos
    WHERE estado = 'Disponible'
    GROUP BY tipo
    ORDER BY tipo
");

// Préstamos propios del estudiante
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
<title>SGPET - Panel Estudiante</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
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
            <h3>Disponibilidad general por tipo de equipo</h3>
            <form action="crear_prestamo.php" method="POST" style="margin-top:12px; display:flex; gap:12px; align-items:end; flex-wrap:wrap;">
                <div>
                    <label style="display:block; font-size:13px; margin-bottom:4px;">Tipo de equipo</label>
                    <select name="tipo" required style="padding:8px; border-radius:6px; border:1px solid #ccc; min-width:220px;">
                        <option value="">Elegí un tipo…</option>
                        <?php
                        $resumen->data_seek(0);
                        while ($r = $resumen->fetch_assoc()):
                        ?>
                        <option value="<?php echo htmlspecialchars($r['tipo']); ?>">
                            <?php echo htmlspecialchars($r['tipo'] . ' (' . $r['disponibles'] . ' disponibles)'); ?>
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
            <p style="margin-top:10px; font-size:12px; color:#666;">
                El sistema te asigna automáticamente la primera unidad disponible del tipo elegido.
            </p>

            <table style="margin-top:18px;">
                <tr><th>Tipo de equipo</th><th>Unidades disponibles</th></tr>
                <?php
                $resumen->data_seek(0);
                while ($r = $resumen->fetch_assoc()):
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($r['tipo']); ?></td>
                    <td><?php echo $r['disponibles']; ?></td>
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

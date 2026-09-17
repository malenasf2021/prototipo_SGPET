<?php
require_once 'config.php';
require_once 'includes/auth_check.php';
require_once 'includes/helpers.php';
requerir_rol('admin');

$id_admin = (int)$_SESSION['id_usuario'];
$errores = [];

$id_prestamo = (int)($_GET['id'] ?? $_POST['id_prestamo'] ?? 0);

$stmt = $conn->prepare("
    SELECT p.id_prestamo, p.id_usuario, p.id_equipo, p.fecha_devolucion_esperada, p.estado,
           u.nombre, u.apellido,
           e.numero_serie, e.tipo, e.condicion_tecnica
    FROM prestamo p
    JOIN usuario u ON u.id_usuario = p.id_usuario
    JOIN equipo e ON e.id_equipo = p.id_equipo
    WHERE p.id_prestamo = ?
");
$stmt->bind_param('i', $id_prestamo);
$stmt->execute();
$prestamo = $stmt->get_result()->fetch_assoc();

if (!$prestamo) {
    header('Location: dashboard_administrador.php?error=' . urlencode('Préstamo no encontrado.'));
    exit;
}
if ($prestamo['estado'] === 'devuelto') {
    header('Location: dashboard_administrador.php?error=' . urlencode('Ese préstamo ya fue devuelto.'));
    exit;
}

$condiciones = ['funcional' => 'Funcional', 'dañado' => 'Dañado', 'en_reparacion' => 'En reparación', 'kit_incompleto' => 'Kit incompleto'];
$condicion_final = $prestamo['condicion_tecnica'];
$hay_falla = false;
$falla_descripcion = '';
$falla_gravedad = 'leve';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $condicion_final = $_POST['condicion_tecnica'] ?? 'funcional';
    $hay_falla = isset($_POST['hay_falla']);
    $falla_descripcion = trim($_POST['falla_descripcion'] ?? '');
    $falla_gravedad = $_POST['falla_gravedad'] ?? 'leve';

    if (!array_key_exists($condicion_final, $condiciones)) {
        $errores[] = 'Condición técnica inválida.';
    }
    if ($hay_falla && $falla_descripcion === '') {
        $errores[] = 'Describí la falla detectada.';
    }
    if ($hay_falla && !array_key_exists($falla_gravedad, ['leve' => 1, 'media' => 1, 'grave' => 1])) {
        $errores[] = 'Gravedad de falla inválida.';
    }

    if (empty($errores)) {
        $conn->begin_transaction();
        $ok = true;

        $stmt = $conn->prepare("UPDATE prestamo SET fecha_devolucion_real = NOW(), estado = 'devuelto' WHERE id_prestamo = ?");
        $stmt->bind_param('i', $id_prestamo);
        $ok = $ok && $stmt->execute();

        // Un equipo dañado, en reparación o con kit incompleto vuelve a mantenimiento;
        // si vuelve funcional, queda disponible de inmediato.
        $nueva_disp = in_array($condicion_final, ['dañado', 'en_reparacion', 'kit_incompleto'], true) ? 'en_mantenimiento' : 'disponible';
        $stmt = $conn->prepare("UPDATE equipo SET condicion_tecnica = ?, disponibilidad = ? WHERE id_equipo = ?");
        $stmt->bind_param('ssi', $condicion_final, $nueva_disp, $prestamo['id_equipo']);
        $ok = $ok && $stmt->execute();

        if ($ok && $hay_falla) {
            $stmt = $conn->prepare("INSERT INTO falla (id_equipo, id_prestamo, descripcion, gravedad) VALUES (?,?,?,?)");
            $stmt->bind_param('iiss', $prestamo['id_equipo'], $id_prestamo, $falla_descripcion, $falla_gravedad);
            $ok = $ok && $stmt->execute();
            if ($ok) registrar_log($conn, $id_admin, 'Registró falla en devolución', 'falla', $conn->insert_id);
        }

        if ($ok) {
            $atraso = dias_atraso($prestamo['fecha_devolucion_esperada']);
            $motivos_sancion = [];
            if ($atraso > 0) $motivos_sancion[] = "devolución con $atraso día(s) de atraso";
            if ($hay_falla && $falla_gravedad === 'grave') $motivos_sancion[] = 'daño grave detectado en el equipo';

            if (!empty($motivos_sancion)) {
                $dias_sancion = ($atraso > 0 ? min(14, 3 + $atraso) : 0) + ($hay_falla && $falla_gravedad === 'grave' ? 14 : 0);
                $dias_sancion = max($dias_sancion, 7);
                $motivo = 'Sanción automática: ' . implode('; ', $motivos_sancion) . '.';
                $fecha_fin_sancion = date('Y-m-d', strtotime("+$dias_sancion days"));

                $stmt = $conn->prepare("INSERT INTO sancion (id_usuario, id_prestamo, motivo, fecha_inicio, fecha_fin) VALUES (?,?,?,CURDATE(),?)");
                $stmt->bind_param('iiss', $prestamo['id_usuario'], $id_prestamo, $motivo, $fecha_fin_sancion);
                $ok = $ok && $stmt->execute();
                if ($ok) {
                    registrar_log($conn, $id_admin, 'Aplicó sanción automática', 'sancion', $conn->insert_id);
                    crear_notificacion($conn, $prestamo['id_usuario'], 'sancion', "$motivo Vigente hasta el " . date('d/m/Y', strtotime($fecha_fin_sancion)) . '.');
                }
            }
        }

        if ($ok) {
            crear_notificacion($conn, $prestamo['id_usuario'], 'confirmacion_prestamo', "Se registró la devolución de {$prestamo['tipo']} ({$prestamo['numero_serie']}).");
            registrar_log($conn, $id_admin, 'Registró devolución', 'prestamo', $id_prestamo);
            $conn->commit();
            header('Location: dashboard_administrador.php?ok=' . urlencode('Devolución registrada correctamente.'));
            exit;
        }

        $conn->rollback();
        $errores[] = 'No se pudo registrar la devolución: ' . $conn->error;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>SGPET - Registrar devolución</title>
<link rel="stylesheet" href="css/style.css">
<script>
function toggleFalla() {
    document.getElementById('falla-detalle').style.display =
        document.getElementById('hay_falla').checked ? 'block' : 'none';
}
</script>
</head>
<body>
    <div class="form-page">
        <h2>Registrar devolución</h2>
        <p class="nota" style="margin-bottom:16px;">
            <?php echo htmlspecialchars($prestamo['nombre'] . ' ' . $prestamo['apellido']); ?> —
            <?php echo htmlspecialchars($prestamo['tipo'] . ' (' . $prestamo['numero_serie'] . ')'); ?><br>
            Devolución esperada: <?php echo date('d/m/Y', strtotime($prestamo['fecha_devolucion_esperada'])); ?>
        </p>

        <?php foreach ($errores as $err): ?>
            <div class="error-msg"><?php echo htmlspecialchars($err); ?></div>
        <?php endforeach; ?>

        <form method="POST">
            <input type="hidden" name="id_prestamo" value="<?php echo $id_prestamo; ?>">

            <div class="input-group">
                <label>Condición técnica al devolver (inspección)</label>
                <select name="condicion_tecnica" required>
                    <?php foreach ($condiciones as $val => $lbl): ?>
                        <option value="<?php echo $val; ?>" <?php echo $condicion_final === $val ? 'selected' : ''; ?>><?php echo $lbl; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="checkbox-row">
                <input type="checkbox" id="hay_falla" name="hay_falla" onclick="toggleFalla()" <?php echo $hay_falla ? 'checked' : ''; ?>>
                <label for="hay_falla" style="margin:0;">Se detectó una falla (daño o pieza faltante)</label>
            </div>

            <div id="falla-detalle" style="display: <?php echo $hay_falla ? 'block' : 'none'; ?>;">
                <div class="input-group">
                    <label>Descripción de la falla</label>
                    <textarea name="falla_descripcion" rows="3" placeholder="Ej: pantalla rajada, falta el cargador..."><?php echo htmlspecialchars($falla_descripcion); ?></textarea>
                </div>
                <div class="input-group">
                    <label>Gravedad</label>
                    <select name="falla_gravedad">
                        <option value="leve" <?php echo $falla_gravedad === 'leve' ? 'selected' : ''; ?>>Leve</option>
                        <option value="media" <?php echo $falla_gravedad === 'media' ? 'selected' : ''; ?>>Media</option>
                        <option value="grave" <?php echo $falla_gravedad === 'grave' ? 'selected' : ''; ?>>Grave</option>
                    </select>
                </div>
            </div>

            <p class="nota">
                Si hay atraso en la devolución o se registra una falla grave, el sistema aplica automáticamente
                una sanción temporal al usuario.
            </p>

            <div class="acciones">
                <button type="submit" class="btn btn-azul">Registrar devolución</button>
                <a href="dashboard_administrador.php" class="btn btn-editar">Cancelar</a>
            </div>
        </form>
    </div>
</body>
</html>

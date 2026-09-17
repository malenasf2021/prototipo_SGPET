<?php
require_once 'config.php';
require_once 'includes/auth_check.php';
require_once 'includes/helpers.php';
requerir_rol('admin');

$id_admin = (int)$_SESSION['id_usuario'];
$errores = [];

$usuarios = $conn->query("SELECT id_usuario, nombre, apellido, rol FROM usuario WHERE activo = 1 AND rol IN ('docente','estudiante') ORDER BY apellido");
$lista_usuarios = $usuarios->fetch_all(MYSQLI_ASSOC);

$sancion = [
    'id_usuario'   => (int)($_GET['id_usuario'] ?? 0),
    'motivo'       => '',
    'fecha_inicio' => date('Y-m-d'),
    'fecha_fin'    => date('Y-m-d', strtotime('+7 days')),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sancion['id_usuario']   = (int)($_POST['id_usuario'] ?? 0);
    $sancion['motivo']       = trim($_POST['motivo'] ?? '');
    $sancion['fecha_inicio'] = $_POST['fecha_inicio'] ?? '';
    $sancion['fecha_fin']    = $_POST['fecha_fin'] ?? '';

    if ($sancion['id_usuario'] <= 0) $errores[] = 'Elegí el usuario a sancionar.';
    if ($sancion['motivo'] === '') $errores[] = 'Indicá el motivo de la sanción.';
    if ($sancion['fecha_inicio'] === '' || $sancion['fecha_fin'] === '' || strtotime($sancion['fecha_fin']) < strtotime($sancion['fecha_inicio'])) {
        $errores[] = 'La fecha de fin debe ser igual o posterior a la fecha de inicio.';
    }

    if (empty($errores)) {
        $stmt = $conn->prepare("INSERT INTO sancion (id_usuario, motivo, fecha_inicio, fecha_fin) VALUES (?,?,?,?)");
        $stmt->bind_param('isss', $sancion['id_usuario'], $sancion['motivo'], $sancion['fecha_inicio'], $sancion['fecha_fin']);

        if ($stmt->execute()) {
            $id_sancion = $conn->insert_id;
            crear_notificacion($conn, $sancion['id_usuario'], 'sancion', "Se te aplicó una sanción: {$sancion['motivo']} Vigente hasta el " . date('d/m/Y', strtotime($sancion['fecha_fin'])) . '.');
            registrar_log($conn, $id_admin, 'Aplicó sanción manual', 'sancion', $id_sancion);

            header('Location: dashboard_administrador.php?ok=' . urlencode('Sanción aplicada correctamente.'));
            exit;
        }
        $errores[] = 'No se pudo aplicar la sanción: ' . $conn->error;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>SGPET - Nueva sanción</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="form-page">
        <h2>Aplicar sanción</h2>

        <?php foreach ($errores as $err): ?>
            <div class="error-msg"><?php echo htmlspecialchars($err); ?></div>
        <?php endforeach; ?>

        <form method="POST">
            <div class="input-group">
                <label>Usuario</label>
                <select name="id_usuario" required>
                    <option value="">-- Elegí un usuario --</option>
                    <?php foreach ($lista_usuarios as $u): ?>
                        <option value="<?php echo $u['id_usuario']; ?>" <?php echo $sancion['id_usuario'] == $u['id_usuario'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($u['nombre'] . ' ' . $u['apellido'] . ' (' . $u['rol'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="input-group">
                <label>Motivo</label>
                <textarea name="motivo" rows="3" required placeholder="Ej: atraso reiterado en la devolución de equipos"><?php echo htmlspecialchars($sancion['motivo']); ?></textarea>
            </div>
            <div class="input-group">
                <label>Fecha de inicio</label>
                <input type="date" name="fecha_inicio" required value="<?php echo htmlspecialchars($sancion['fecha_inicio']); ?>">
            </div>
            <div class="input-group">
                <label>Fecha de fin</label>
                <input type="date" name="fecha_fin" required value="<?php echo htmlspecialchars($sancion['fecha_fin']); ?>">
            </div>

            <div class="acciones">
                <button type="submit" class="btn btn-azul">Aplicar sanción</button>
                <a href="dashboard_administrador.php" class="btn btn-editar">Cancelar</a>
            </div>
        </form>
    </div>
</body>
</html>

<?php
require_once 'config.php';
require_once 'includes/auth_check.php';
requerir_rol('administrador');

$editando = isset($_GET['id']);
$equipo = ['id' => '', 'nombre' => '', 'tipo' => '', 'estado' => 'Disponible'];
$errores = [];

if ($editando) {
    $id_editar = (int)$_GET['id'];
    $stmt = $conn->prepare("SELECT id, nombre, tipo, estado FROM equipos WHERE id = ?");
    $stmt->bind_param('i', $id_editar);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 1) {
        $equipo = $res->fetch_assoc();
    } else {
        header('Location: dashboard_administrador.php?error=' . urlencode('Equipo no encontrado.'));
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $equipo['nombre'] = trim($_POST['nombre'] ?? '');
    $equipo['tipo']   = trim($_POST['tipo'] ?? '');
    $equipo['estado'] = $_POST['estado'] ?? '';

    if ($equipo['nombre'] === '' || $equipo['tipo'] === '') {
        $errores[] = 'Nombre y tipo son obligatorios.';
    }
    if (!in_array($equipo['estado'], ['Disponible', 'Prestado', 'En reparación'])) {
        $errores[] = 'Estado inválido.';
    }

    if (empty($errores)) {
        if ($editando) {
            $stmt = $conn->prepare("UPDATE equipos SET nombre=?, tipo=?, estado=? WHERE id=?");
            $stmt->bind_param('sssi', $equipo['nombre'], $equipo['tipo'], $equipo['estado'], $id_editar);
        } else {
            $stmt = $conn->prepare("INSERT INTO equipos (nombre, tipo, estado) VALUES (?,?,?)");
            $stmt->bind_param('sss', $equipo['nombre'], $equipo['tipo'], $equipo['estado']);
        }

        if ($stmt->execute()) {
            $msg = $editando ? 'Equipo actualizado correctamente.' : 'Equipo creado correctamente.';
            header('Location: dashboard_administrador.php?ok=' . urlencode($msg));
            exit;
        }
        $errores[] = 'No se pudo guardar: ' . $conn->error;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>SGPET - <?php echo $editando ? 'Editar equipo' : 'Nuevo equipo'; ?></title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="form-page">
        <h2><?php echo $editando ? 'Editar equipo' : 'Nuevo equipo'; ?></h2>

        <?php foreach ($errores as $err): ?>
            <div class="error-msg"><?php echo htmlspecialchars($err); ?></div>
        <?php endforeach; ?>

        <form method="POST">
            <div class="input-group">
                <label>Nombre / identificador</label>
                <input type="text" name="nombre" required placeholder="Ej: Ceibalita N/S 3746317213"
                       value="<?php echo htmlspecialchars($equipo['nombre']); ?>">
            </div>
            <div class="input-group">
                <label>Tipo</label>
                <input type="text" name="tipo" required placeholder="Ej: Ceibalita, Kit de robótica, Placa Micro:bit..."
                       value="<?php echo htmlspecialchars($equipo['tipo']); ?>">
            </div>
            <div class="input-group">
                <label>Estado</label>
                <select name="estado" required>
                    <?php foreach (['Disponible', 'Prestado', 'En reparación'] as $st): ?>
                        <option value="<?php echo $st; ?>" <?php echo $equipo['estado'] === $st ? 'selected' : ''; ?>>
                            <?php echo $st; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="acciones">
                <button type="submit" class="btn btn-azul">Guardar</button>
                <a href="dashboard_administrador.php" class="btn btn-editar">Cancelar</a>
            </div>
        </form>
    </div>
</body>
</html>

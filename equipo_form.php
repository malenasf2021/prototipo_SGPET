<?php
require_once 'config.php';
require_once 'includes/auth_check.php';
requerir_rol('admin');

$editando = isset($_GET['id']);
$equipo = ['id_equipo' => '', 'numero_serie' => '', 'tipo' => '', 'condicion_tecnica' => 'funcional',
           'disponibilidad' => 'disponible', 'restriccion_uso' => ''];
$errores = [];

$condiciones = ['funcional' => 'Funcional', 'dañado' => 'Dañado', 'en_reparacion' => 'En reparación', 'kit_incompleto' => 'Kit incompleto'];
$disponibilidades = ['disponible' => 'Disponible', 'reservado' => 'Reservado', 'prestado' => 'Prestado', 'en_mantenimiento' => 'En mantenimiento', 'dado_de_baja' => 'Dado de baja'];

if ($editando) {
    $id_editar = (int)$_GET['id'];
    $stmt = $conn->prepare("SELECT id_equipo, numero_serie, tipo, condicion_tecnica, disponibilidad, restriccion_uso FROM equipo WHERE id_equipo = ?");
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
    $equipo['numero_serie']      = trim($_POST['numero_serie'] ?? '');
    $equipo['tipo']              = trim($_POST['tipo'] ?? '');
    $equipo['condicion_tecnica'] = $_POST['condicion_tecnica'] ?? '';
    $equipo['disponibilidad']    = $_POST['disponibilidad'] ?? '';
    $equipo['restriccion_uso']   = trim($_POST['restriccion_uso'] ?? '');

    if ($equipo['numero_serie'] === '' || $equipo['tipo'] === '') {
        $errores[] = 'Número de serie y tipo son obligatorios.';
    }
    if (!array_key_exists($equipo['condicion_tecnica'], $condiciones)) {
        $errores[] = 'Condición técnica inválida.';
    }
    if (!array_key_exists($equipo['disponibilidad'], $disponibilidades)) {
        $errores[] = 'Disponibilidad inválida.';
    }

    $restriccion_final = $equipo['restriccion_uso'] !== '' ? $equipo['restriccion_uso'] : null;

    if (empty($errores)) {
        if ($editando) {
            $stmt = $conn->prepare("UPDATE equipo SET numero_serie=?, tipo=?, condicion_tecnica=?, disponibilidad=?, restriccion_uso=? WHERE id_equipo=?");
            $stmt->bind_param('sssssi', $equipo['numero_serie'], $equipo['tipo'], $equipo['condicion_tecnica'], $equipo['disponibilidad'], $restriccion_final, $id_editar);
        } else {
            $stmt = $conn->prepare("INSERT INTO equipo (numero_serie, tipo, condicion_tecnica, disponibilidad, restriccion_uso) VALUES (?,?,?,?,?)");
            $stmt->bind_param('sssss', $equipo['numero_serie'], $equipo['tipo'], $equipo['condicion_tecnica'], $equipo['disponibilidad'], $restriccion_final);
        }

        if ($stmt->execute()) {
            $msg = $editando ? 'Equipo actualizado correctamente.' : 'Equipo creado correctamente.';
            header('Location: dashboard_administrador.php?ok=' . urlencode($msg));
            exit;
        }
        $errores[] = ($conn->errno === 1062) ? 'Ya existe un equipo con ese número de serie.' : 'No se pudo guardar: ' . $conn->error;
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
                <label>Número de serie</label>
                <input type="text" name="numero_serie" required placeholder="Ej: 3746317213 o ROB-06"
                       value="<?php echo htmlspecialchars($equipo['numero_serie']); ?>">
            </div>
            <div class="input-group">
                <label>Tipo</label>
                <input type="text" name="tipo" required placeholder="Ej: Ceibalita, Kit de robótica, Placa Micro:bit..."
                       value="<?php echo htmlspecialchars($equipo['tipo']); ?>">
            </div>
            <div class="input-group">
                <label>Condición técnica</label>
                <select name="condicion_tecnica" required>
                    <?php foreach ($condiciones as $val => $lbl): ?>
                        <option value="<?php echo $val; ?>" <?php echo $equipo['condicion_tecnica'] === $val ? 'selected' : ''; ?>><?php echo $lbl; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="input-group">
                <label>Disponibilidad</label>
                <select name="disponibilidad" required>
                    <?php foreach ($disponibilidades as $val => $lbl): ?>
                        <option value="<?php echo $val; ?>" <?php echo $equipo['disponibilidad'] === $val ? 'selected' : ''; ?>><?php echo $lbl; ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="nota">Condición técnica y disponibilidad son independientes: un equipo puede estar "Funcional" y "Reservado" a la vez, por ejemplo.</p>
            </div>
            <div class="input-group">
                <label>Restricción de uso (opcional)</label>
                <input type="text" name="restriccion_uso" placeholder="Ej: Uso solo por el día, dentro de la institución"
                       value="<?php echo htmlspecialchars($equipo['restriccion_uso'] ?? ''); ?>">
            </div>

            <div class="acciones">
                <button type="submit" class="btn btn-azul">Guardar</button>
                <a href="dashboard_administrador.php" class="btn btn-editar">Cancelar</a>
            </div>
        </form>
    </div>
</body>
</html>

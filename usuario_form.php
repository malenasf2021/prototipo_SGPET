<?php
require_once 'config.php';
require_once 'includes/auth_check.php';
requerir_rol('admin');

$editando = isset($_GET['id']);
$usuario = ['id_usuario' => '', 'nombre' => '', 'apellido' => '', 'cedula' => '', 'email' => '',
            'telefono' => '', 'rol' => 'estudiante', 'departamento' => '', 'grupo' => ''];
$errores = [];

if ($editando) {
    $id_editar = (int)$_GET['id'];
    $stmt = $conn->prepare("SELECT id_usuario, nombre, apellido, cedula, email, telefono, rol, departamento, grupo FROM usuario WHERE id_usuario = ?");
    $stmt->bind_param('i', $id_editar);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 1) {
        $usuario = $res->fetch_assoc();
    } else {
        header('Location: dashboard_administrador.php?error=' . urlencode('Usuario no encontrado.'));
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario['nombre']       = trim($_POST['nombre'] ?? '');
    $usuario['apellido']     = trim($_POST['apellido'] ?? '');
    $usuario['cedula']       = trim($_POST['cedula'] ?? '');
    $usuario['email']        = trim($_POST['email'] ?? '');
    $usuario['telefono']     = trim($_POST['telefono'] ?? '');
    $usuario['rol']          = $_POST['rol'] ?? '';
    $usuario['departamento'] = trim($_POST['departamento'] ?? '');
    $usuario['grupo']        = trim($_POST['grupo'] ?? '');
    $password                = $_POST['password'] ?? '';

    if ($usuario['nombre'] === '' || $usuario['apellido'] === '') {
        $errores[] = 'Nombre y apellido son obligatorios.';
    }
    if (!preg_match('/^\d{7,9}$/', $usuario['cedula'])) {
        $errores[] = 'La cédula debe tener entre 7 y 9 dígitos.';
    }
    if (!filter_var($usuario['email'], FILTER_VALIDATE_EMAIL)) {
        $errores[] = 'El email no es válido.';
    }
    if (!in_array($usuario['rol'], ['admin', 'docente', 'estudiante'])) {
        $errores[] = 'Rol inválido.';
    }
    if (!$editando && $password === '') {
        $errores[] = 'La contraseña es obligatoria para un usuario nuevo.';
    }

    $departamento_final = ($usuario['rol'] === 'docente' && $usuario['departamento'] !== '') ? $usuario['departamento'] : null;
    $grupo_final = ($usuario['rol'] === 'estudiante' && $usuario['grupo'] !== '') ? $usuario['grupo'] : null;
    $telefono_final = $usuario['telefono'] !== '' ? $usuario['telefono'] : null;

    if (empty($errores)) {
        if ($editando) {
            if ($password !== '') {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE usuario SET nombre=?, apellido=?, cedula=?, email=?, telefono=?, rol=?, departamento=?, grupo=?, contrasena_hash=? WHERE id_usuario=?");
                $stmt->bind_param('sssssssssi', $usuario['nombre'], $usuario['apellido'], $usuario['cedula'], $usuario['email'], $telefono_final, $usuario['rol'], $departamento_final, $grupo_final, $hash, $id_editar);
            } else {
                $stmt = $conn->prepare("UPDATE usuario SET nombre=?, apellido=?, cedula=?, email=?, telefono=?, rol=?, departamento=?, grupo=? WHERE id_usuario=?");
                $stmt->bind_param('ssssssssi', $usuario['nombre'], $usuario['apellido'], $usuario['cedula'], $usuario['email'], $telefono_final, $usuario['rol'], $departamento_final, $grupo_final, $id_editar);
            }
            if ($stmt->execute()) {
                header('Location: dashboard_administrador.php?ok=' . urlencode('Usuario actualizado correctamente.'));
                exit;
            }
            $errores[] = ($conn->errno === 1062) ? 'Ya existe otro usuario con esa cédula o ese email.' : 'No se pudo guardar: ' . $conn->error;
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO usuario (nombre, apellido, cedula, email, telefono, contrasena_hash, rol, departamento, grupo) VALUES (?,?,?,?,?,?,?,?,?)");
            $stmt->bind_param('sssssssss', $usuario['nombre'], $usuario['apellido'], $usuario['cedula'], $usuario['email'], $telefono_final, $hash, $usuario['rol'], $departamento_final, $grupo_final);
            if ($stmt->execute()) {
                header('Location: dashboard_administrador.php?ok=' . urlencode('Usuario creado correctamente. Su ID de acceso es ' . $conn->insert_id . '.'));
                exit;
            }
            $errores[] = ($conn->errno === 1062) ? 'Ya existe otro usuario con esa cédula o ese email.' : 'No se pudo guardar: ' . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>SGPET - <?php echo $editando ? 'Editar usuario' : 'Nuevo usuario'; ?></title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="form-page">
        <h2><?php echo $editando ? 'Editar usuario' : 'Nuevo usuario'; ?></h2>

        <?php foreach ($errores as $err): ?>
            <div class="error-msg"><?php echo htmlspecialchars($err); ?></div>
        <?php endforeach; ?>

        <?php if ($editando): ?>
            <p class="nota" style="margin-bottom:16px;">ID de acceso: <strong><?php echo $usuario['id_usuario']; ?></strong> (no editable, se generó automáticamente).</p>
        <?php endif; ?>

        <form method="POST">
            <div class="input-group">
                <label>Nombre</label>
                <input type="text" name="nombre" required value="<?php echo htmlspecialchars($usuario['nombre']); ?>">
            </div>
            <div class="input-group">
                <label>Apellido</label>
                <input type="text" name="apellido" required value="<?php echo htmlspecialchars($usuario['apellido']); ?>">
            </div>
            <div class="input-group">
                <label>Cédula</label>
                <input type="text" name="cedula" required value="<?php echo htmlspecialchars($usuario['cedula']); ?>">
            </div>
            <div class="input-group">
                <label>Email</label>
                <input type="email" name="email" required value="<?php echo htmlspecialchars($usuario['email']); ?>">
            </div>
            <div class="input-group">
                <label>Teléfono</label>
                <input type="text" name="telefono" value="<?php echo htmlspecialchars($usuario['telefono'] ?? ''); ?>">
            </div>
            <div class="input-group">
                <label>Rol</label>
                <select name="rol" id="rol" required onchange="
                    document.getElementById('depto_wrap').style.display = this.value==='docente' ? 'block' : 'none';
                    document.getElementById('grupo_wrap').style.display = this.value==='estudiante' ? 'block' : 'none';">
                    <?php foreach (['admin' => 'Administrador', 'docente' => 'Docente', 'estudiante' => 'Estudiante'] as $val => $lbl): ?>
                        <option value="<?php echo $val; ?>" <?php echo $usuario['rol'] === $val ? 'selected' : ''; ?>><?php echo $lbl; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="input-group" id="depto_wrap" style="display: <?php echo $usuario['rol'] === 'docente' ? 'block' : 'none'; ?>;">
                <label>Departamento (solo docentes)</label>
                <input type="text" name="departamento" placeholder="Ej: Informática" value="<?php echo htmlspecialchars($usuario['departamento'] ?? ''); ?>">
            </div>
            <div class="input-group" id="grupo_wrap" style="display: <?php echo $usuario['rol'] === 'estudiante' ? 'block' : 'none'; ?>;">
                <label>Grupo (solo estudiantes)</label>
                <input type="text" name="grupo" placeholder="Ej: 2do B" value="<?php echo htmlspecialchars($usuario['grupo'] ?? ''); ?>">
            </div>
            <div class="input-group">
                <label><?php echo $editando ? 'Nueva contraseña (dejar en blanco para no cambiarla)' : 'Contraseña'; ?></label>
                <input type="password" name="password" <?php echo $editando ? '' : 'required'; ?>>
            </div>

            <div class="acciones">
                <button type="submit" class="btn btn-azul">Guardar</button>
                <a href="dashboard_administrador.php" class="btn btn-editar">Cancelar</a>
            </div>
        </form>
    </div>
</body>
</html>

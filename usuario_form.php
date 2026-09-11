<?php
require_once 'config.php';
require_once 'includes/auth_check.php';
requerir_rol('administrador');

$editando = isset($_GET['id']);
$usuario = ['id' => '', 'nombre' => '', 'apellido' => '', 'email' => '', 'rol' => 'estudiante', 'grupo' => ''];
$errores = [];

if ($editando) {
    $id_editar = (int)$_GET['id'];
    $stmt = $conn->prepare("SELECT id, nombre, apellido, email, rol, grupo FROM usuarios WHERE id = ?");
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
    $usuario['id']       = trim($_POST['id'] ?? '');
    $usuario['nombre']   = trim($_POST['nombre'] ?? '');
    $usuario['apellido'] = trim($_POST['apellido'] ?? '');
    $usuario['email']    = trim($_POST['email'] ?? '');
    $usuario['rol']      = $_POST['rol'] ?? '';
    $usuario['grupo']    = trim($_POST['grupo'] ?? '');
    $password            = $_POST['password'] ?? '';

    // --- Validaciones ---
    if (!preg_match('/^\d{8}$/', $usuario['id'])) {
        $errores[] = 'El ID debe ser un número entero de 8 dígitos.';
    }
    if ($usuario['nombre'] === '' || $usuario['apellido'] === '') {
        $errores[] = 'Nombre y apellido son obligatorios.';
    }
    if (!filter_var($usuario['email'], FILTER_VALIDATE_EMAIL)) {
        $errores[] = 'El email no es válido.';
    }
    if (!in_array($usuario['rol'], ['administrador', 'docente', 'estudiante'])) {
        $errores[] = 'Rol inválido.';
    }
    if (!$editando && $password === '') {
        $errores[] = 'La contraseña es obligatoria para un usuario nuevo.';
    }
    $grupo_final = ($usuario['rol'] === 'estudiante' && $usuario['grupo'] !== '') ? $usuario['grupo'] : null;

    if (empty($errores)) {
        if ($editando) {
            // No se permite cambiar el ID (es la clave primaria)
            if ($password !== '') {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE usuarios SET nombre=?, apellido=?, email=?, rol=?, grupo=?, password=? WHERE id=?");
                $stmt->bind_param('ssssssi', $usuario['nombre'], $usuario['apellido'], $usuario['email'], $usuario['rol'], $grupo_final, $hash, $id_editar);
            } else {
                $stmt = $conn->prepare("UPDATE usuarios SET nombre=?, apellido=?, email=?, rol=?, grupo=? WHERE id=?");
                $stmt->bind_param('sssssi', $usuario['nombre'], $usuario['apellido'], $usuario['email'], $usuario['rol'], $grupo_final, $id_editar);
            }
            if ($stmt->execute()) {
                header('Location: dashboard_administrador.php?ok=' . urlencode('Usuario actualizado correctamente.'));
                exit;
            }
            $errores[] = ($conn->errno === 1062) ? 'Ya existe otro usuario con ese email.' : 'No se pudo guardar: ' . $conn->error;
        } else {
            // Verificar que el ID no exista ya
            $stmt = $conn->prepare("SELECT id FROM usuarios WHERE id = ?");
            $stmt->bind_param('i', $usuario['id']);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $errores[] = 'Ya existe un usuario con ese ID.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO usuarios (id, nombre, apellido, email, password, rol, grupo) VALUES (?,?,?,?,?,?,?)");
                $stmt->bind_param('issssss', $usuario['id'], $usuario['nombre'], $usuario['apellido'], $usuario['email'], $hash, $usuario['rol'], $grupo_final);
                if ($stmt->execute()) {
                    header('Location: dashboard_administrador.php?ok=' . urlencode('Usuario creado correctamente.'));
                    exit;
                }
                $errores[] = ($conn->errno === 1062) ? 'Ya existe otro usuario con ese email.' : 'No se pudo guardar: ' . $conn->error;
            }
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

        <form method="POST">
            <div class="input-group">
                <label>ID (8 dígitos)</label>
                <input type="text" name="id" maxlength="8" pattern="\d{8}" required
                       value="<?php echo htmlspecialchars($usuario['id']); ?>"
                       <?php echo $editando ? 'readonly style="background:#f0f0f0;"' : ''; ?>>
            </div>
            <div class="input-group">
                <label>Nombre</label>
                <input type="text" name="nombre" required value="<?php echo htmlspecialchars($usuario['nombre']); ?>">
            </div>
            <div class="input-group">
                <label>Apellido</label>
                <input type="text" name="apellido" required value="<?php echo htmlspecialchars($usuario['apellido']); ?>">
            </div>
            <div class="input-group">
                <label>Email</label>
                <input type="email" name="email" required value="<?php echo htmlspecialchars($usuario['email']); ?>">
            </div>
            <div class="input-group">
                <label>Rol</label>
                <select name="rol" id="rol" required onchange="document.getElementById('grupo_wrap').style.display = this.value==='estudiante' ? 'block' : 'none';">
                    <?php foreach (['administrador', 'docente', 'estudiante'] as $r): ?>
                        <option value="<?php echo $r; ?>" <?php echo $usuario['rol'] === $r ? 'selected' : ''; ?>>
                            <?php echo ucfirst($r); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
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

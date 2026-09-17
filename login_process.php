<?php
require_once 'config.php';
require_once 'includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.html');
    exit;
}

$id_usuario = trim($_POST['id_usuario'] ?? '');
$password = $_POST['password'] ?? '';

if ($id_usuario === '' || $password === '') {
    header('Location: index.html?error=' . urlencode('Completá el ID de usuario y la contraseña.'));
    exit;
}

$stmt = $conn->prepare("
    SELECT id_usuario, nombre, apellido, contrasena_hash, rol, activo
    FROM usuario
    WHERE id_usuario = ?
");
$stmt->bind_param('i', $id_usuario);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $usuario = $result->fetch_assoc();

    if (!$usuario['activo']) {
        header('Location: index.html?error=' . urlencode('Este usuario está inactivo. Consultá con el administrador.'));
        exit;
    }

    if (password_verify($password, $usuario['contrasena_hash'])) {
        // Login correcto: guardamos datos en sesión
        $_SESSION['id_usuario'] = $usuario['id_usuario'];
        $_SESSION['nombre']     = $usuario['nombre'];
        $_SESSION['apellido']   = $usuario['apellido'];
        $_SESSION['rol']        = $usuario['rol']; // admin | docente | estudiante

        header('Location: ' . panel_de_rol($usuario['rol']));
        exit;
    }
}

header('Location: index.html?error=' . urlencode('ID de usuario o contraseña incorrectos.'));
exit;

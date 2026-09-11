<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.html');
    exit;
}

$id = trim($_POST['id'] ?? '');
$password = $_POST['password'] ?? '';

if ($id === '' || $password === '') {
    header('Location: index.html?error=' . urlencode('Completá ID y contraseña.'));
    exit;
}

$stmt = $conn->prepare("SELECT id, nombre, apellido, password, rol FROM usuarios WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $usuario = $result->fetch_assoc();

    if (password_verify($password, $usuario['password'])) {
        // Login correcto: guardamos datos en sesión
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['nombre']     = $usuario['nombre'];
        $_SESSION['apellido']   = $usuario['apellido'];
        $_SESSION['rol']        = $usuario['rol']; // administrador | docente | estudiante

        header('Location: dashboard_' . strtolower($usuario['rol']) . '.php');
        exit;
    }
}

header('Location: index.html?error=' . urlencode('ID o contraseña incorrectos.'));
exit;

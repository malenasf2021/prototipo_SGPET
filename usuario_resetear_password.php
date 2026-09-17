<?php
require_once 'config.php';
require_once 'includes/auth_check.php';
requerir_rol('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard_administrador.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);

$stmt = $conn->prepare("SELECT id_usuario FROM usuario WHERE id_usuario = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
if ($stmt->get_result()->num_rows !== 1) {
    header('Location: dashboard_administrador.php?error=' . urlencode('Usuario no encontrado.'));
    exit;
}

// Contraseña temporal legible (evita caracteres confusos como 0/O, 1/l/I)
$alfabeto = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
$temporal = '';
for ($i = 0; $i < 8; $i++) {
    $temporal .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
}

$hash = password_hash($temporal, PASSWORD_DEFAULT);
$stmt = $conn->prepare("UPDATE usuario SET contrasena_hash = ? WHERE id_usuario = ?");
$stmt->bind_param('si', $hash, $id);
$stmt->execute();

$mensaje = "Contraseña temporal para el ID $id: $temporal";
header('Location: dashboard_administrador.php?ok=' . urlencode($mensaje));
exit;

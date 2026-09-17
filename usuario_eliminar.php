<?php
require_once 'config.php';
require_once 'includes/auth_check.php';
requerir_rol('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard_administrador.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);

if ($id === (int)$_SESSION['id_usuario']) {
    header('Location: dashboard_administrador.php?error=' . urlencode('No podés desactivar tu propio usuario.'));
    exit;
}

$stmt = $conn->prepare("UPDATE usuario SET activo = NOT activo WHERE id_usuario = ?");
$stmt->bind_param('i', $id);

if ($stmt->execute() && $stmt->affected_rows >= 0) {
    header('Location: dashboard_administrador.php?ok=' . urlencode('Estado del usuario actualizado.'));
} else {
    header('Location: dashboard_administrador.php?error=' . urlencode('No se pudo actualizar el usuario.'));
}
exit;

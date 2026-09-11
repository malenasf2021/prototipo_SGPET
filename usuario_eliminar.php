<?php
require_once 'config.php';
require_once 'includes/auth_check.php';
requerir_rol('administrador');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard_administrador.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);

// No permitir que el administrador se elimine a sí mismo
if ($id === (int)$_SESSION['usuario_id']) {
    header('Location: dashboard_administrador.php?error=' . urlencode('No podés eliminar tu propio usuario.'));
    exit;
}

$stmt = $conn->prepare("DELETE FROM usuarios WHERE id = ?");
$stmt->bind_param('i', $id);

if ($stmt->execute()) {
    header('Location: dashboard_administrador.php?ok=' . urlencode('Usuario eliminado.'));
} else {
    // Probablemente tiene préstamos asociados (FK)
    header('Location: dashboard_administrador.php?error=' . urlencode('No se pudo eliminar: el usuario tiene préstamos registrados.'));
}
exit;

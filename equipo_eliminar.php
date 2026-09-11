<?php
require_once 'config.php';
require_once 'includes/auth_check.php';
requerir_rol('administrador');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard_administrador.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);

$stmt = $conn->prepare("DELETE FROM equipos WHERE id = ?");
$stmt->bind_param('i', $id);

if ($stmt->execute()) {
    header('Location: dashboard_administrador.php?ok=' . urlencode('Equipo eliminado.'));
} else {
    header('Location: dashboard_administrador.php?error=' . urlencode('No se pudo eliminar: el equipo tiene préstamos registrados.'));
}
exit;

<?php
require_once 'config.php';
require_once 'includes/auth_check.php';
requerir_sesion();

$panel = panel_de_rol($_SESSION['rol']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $panel);
    exit;
}

$id_notificacion = (int)($_POST['id'] ?? 0);
$id_usuario = (int)$_SESSION['id_usuario'];

// El WHERE por id_usuario asegura que solo se pueda marcar como leída
// una notificación propia, sin necesidad de una consulta previa.
$stmt = $conn->prepare("UPDATE notificacion SET leida = 1 WHERE id_notificacion = ? AND id_usuario = ?");
$stmt->bind_param('ii', $id_notificacion, $id_usuario);
$stmt->execute();

header('Location: ' . $panel);
exit;

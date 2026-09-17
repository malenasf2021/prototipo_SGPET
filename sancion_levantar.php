<?php
require_once 'config.php';
require_once 'includes/auth_check.php';
require_once 'includes/helpers.php';
requerir_rol('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard_administrador.php');
    exit;
}

$id_sancion = (int)($_POST['id'] ?? 0);

$stmt = $conn->prepare("SELECT id_usuario, fecha_fin FROM sancion WHERE id_sancion = ?");
$stmt->bind_param('i', $id_sancion);
$stmt->execute();
$sancion = $stmt->get_result()->fetch_assoc();

if (!$sancion) {
    header('Location: dashboard_administrador.php?error=' . urlencode('Sanción no encontrada.'));
    exit;
}
if (strtotime($sancion['fecha_fin']) < strtotime('today')) {
    header('Location: dashboard_administrador.php?error=' . urlencode('Esa sanción ya venció.'));
    exit;
}

$stmt = $conn->prepare("UPDATE sancion SET fecha_fin = CURDATE() WHERE id_sancion = ?");
$stmt->bind_param('i', $id_sancion);

if ($stmt->execute()) {
    crear_notificacion($conn, $sancion['id_usuario'], 'sancion', 'Tu sanción fue levantada por un administrador.');
    registrar_log($conn, (int)$_SESSION['id_usuario'], 'Levantó sanción', 'sancion', $id_sancion);
    header('Location: dashboard_administrador.php?ok=' . urlencode('Sanción levantada.'));
} else {
    header('Location: dashboard_administrador.php?error=' . urlencode('No se pudo levantar la sanción.'));
}
exit;

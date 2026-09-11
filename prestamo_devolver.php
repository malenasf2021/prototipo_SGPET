<?php
require_once 'config.php';
require_once 'includes/auth_check.php';
requerir_rol('administrador');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard_administrador.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$hoy = date('Y-m-d');

$stmt = $conn->prepare("SELECT equipo_id FROM prestamos WHERE id = ? AND estado IN ('Activo','Atrasado')");
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows !== 1) {
    header('Location: dashboard_administrador.php?error=' . urlencode('Préstamo no encontrado o ya devuelto.'));
    exit;
}
$equipo_id = $res->fetch_assoc()['equipo_id'];

$conn->begin_transaction();
try {
    $stmt = $conn->prepare("UPDATE prestamos SET estado='Devuelto', fecha_devolucion=? WHERE id=?");
    $stmt->bind_param('si', $hoy, $id);
    $stmt->execute();

    $stmt = $conn->prepare("UPDATE equipos SET estado='Disponible' WHERE id=?");
    $stmt->bind_param('i', $equipo_id);
    $stmt->execute();

    $conn->commit();
    header('Location: dashboard_administrador.php?ok=' . urlencode('Devolución registrada. El equipo vuelve a estar disponible.'));
} catch (Exception $e) {
    $conn->rollback();
    header('Location: dashboard_administrador.php?error=' . urlencode('No se pudo registrar la devolución.'));
}
exit;

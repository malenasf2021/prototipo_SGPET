<?php
require_once 'config.php';
require_once 'includes/auth_check.php';
require_once 'includes/helpers.php';
requerir_sesion();

$panel = panel_de_rol($_SESSION['rol']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $panel);
    exit;
}

$id_reserva = (int)($_POST['id'] ?? 0);

$stmt = $conn->prepare("SELECT id_reserva, id_usuario, id_equipo, estado FROM reserva WHERE id_reserva = ?");
$stmt->bind_param('i', $id_reserva);
$stmt->execute();
$reserva = $stmt->get_result()->fetch_assoc();

if (!$reserva) {
    header('Location: ' . $panel . '?error=' . urlencode('Reserva no encontrada.'));
    exit;
}

$es_admin = $_SESSION['rol'] === 'admin';
$es_dueno = $reserva['id_usuario'] === (int)$_SESSION['id_usuario'];

if (!$es_admin && !$es_dueno) {
    header('Location: ' . $panel . '?error=' . urlencode('No podés cancelar la reserva de otro usuario.'));
    exit;
}
if ($reserva['estado'] !== 'pendiente') {
    header('Location: ' . $panel . '?error=' . urlencode('Solo se pueden cancelar reservas pendientes.'));
    exit;
}

$stmt = $conn->prepare("UPDATE reserva SET estado = 'cancelada' WHERE id_reserva = ?");
$stmt->bind_param('i', $id_reserva);

if ($stmt->execute()) {
    // Libera el equipo si seguía marcado como "reservado" por esta reserva.
    $conn->query("UPDATE equipo SET disponibilidad = 'disponible' WHERE id_equipo = " . (int)$reserva['id_equipo'] . " AND disponibilidad = 'reservado'");
    crear_notificacion($conn, $reserva['id_usuario'], 'reserva', 'Tu reserva fue cancelada.');
    registrar_log($conn, (int)$_SESSION['id_usuario'], 'Canceló reserva', 'reserva', $id_reserva);
    header('Location: ' . $panel . '?ok=' . urlencode('Reserva cancelada.'));
} else {
    header('Location: ' . $panel . '?error=' . urlencode('No se pudo cancelar la reserva.'));
}
exit;

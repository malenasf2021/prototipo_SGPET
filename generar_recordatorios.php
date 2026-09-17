<?php
// generar_recordatorios.php
// Botón manual del panel de administración que reemplaza a una tarea
// programada (cron): marca como "atrasado" los préstamos vencidos y
// genera notificaciones de vencimiento (avisa el mismo día y también
// el día anterior). En una implementación con servidor propio esto se
// ejecutaría automáticamente todos los días.
require_once 'config.php';
require_once 'includes/auth_check.php';
require_once 'includes/helpers.php';
requerir_rol('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard_administrador.php');
    exit;
}

$id_admin = (int)$_SESSION['id_usuario'];
$generadas = 0;

// 1) Préstamos vencidos que siguen "activo": pasan a "atrasado" y se notifica.
$vencidos = $conn->query("
    SELECT id_prestamo, id_usuario, id_equipo, fecha_devolucion_esperada
    FROM prestamo
    WHERE estado = 'activo' AND fecha_devolucion_esperada < CURDATE()
");
while ($p = $vencidos->fetch_assoc()) {
    $conn->query("UPDATE prestamo SET estado = 'atrasado' WHERE id_prestamo = " . (int)$p['id_prestamo']);

    if (!ya_notificado_hoy($conn, 'prestamo', $p['id_prestamo'], 'Recordatorio de atraso')) {
        $atraso = dias_atraso($p['fecha_devolucion_esperada']);
        crear_notificacion($conn, $p['id_usuario'], 'recordatorio_vencimiento', "Tu préstamo está atrasado ($atraso día(s)). Por favor devolvé el equipo cuanto antes.");
        registrar_log($conn, $id_admin, 'Recordatorio de atraso', 'prestamo', $p['id_prestamo']);
        $generadas++;
    }
}

// 2) Préstamos que vencen hoy o mañana: recordatorio preventivo.
$por_vencer = $conn->query("
    SELECT id_prestamo, id_usuario, fecha_devolucion_esperada
    FROM prestamo
    WHERE estado IN ('activo','atrasado') AND fecha_devolucion_esperada IN (CURDATE(), CURDATE() + INTERVAL 1 DAY)
");
while ($p = $por_vencer->fetch_assoc()) {
    if (!ya_notificado_hoy($conn, 'prestamo', $p['id_prestamo'], 'Recordatorio de vencimiento')) {
        crear_notificacion($conn, $p['id_usuario'], 'recordatorio_vencimiento', 'Tu préstamo vence el ' . date('d/m/Y', strtotime($p['fecha_devolucion_esperada'])) . '.');
        registrar_log($conn, $id_admin, 'Recordatorio de vencimiento', 'prestamo', $p['id_prestamo']);
        $generadas++;
    }
}

header('Location: dashboard_administrador.php?ok=' . urlencode("Recordatorios generados: $generadas."));
exit;

<?php
// config.php - Conexión a la base de datos SGPET
// Ajustar estos datos si tu XAMPP usa otro usuario/contraseña de MySQL

session_start();

// Desde PHP 8.1, mysqli lanza excepciones (mysqli_sql_exception) en vez de
// devolver false ante un error de la base (por ejemplo, una clave duplicada).
// Todo el código de este proyecto está escrito esperando el comportamiento
// clásico (if ($stmt->execute())), así que lo restauramos acá para que los
// mensajes de validación (email/cédula/N° de serie duplicado, etc.) se
// muestren correctamente en vez de tirar un error 500.
mysqli_report(MYSQLI_REPORT_OFF);

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');       // en XAMPP por defecto está vacío
define('DB_NAME', 'sgpet_db');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Error de conexión a la base de datos: " . $conn->connect_error);
}

$conn->set_charset('utf8mb4');

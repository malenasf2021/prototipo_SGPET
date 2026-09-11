<?php
// config.php - Conexión a la base de datos SGPET
// Ajustar estos datos si tu XAMPP usa otro usuario/contraseña de MySQL

session_start();

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');       // en XAMPP por defecto está vacío
define('DB_NAME', 'sgpet');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Error de conexión a la base de datos: " . $conn->connect_error);
}

$conn->set_charset('utf8mb4');

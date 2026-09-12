SGPET - Login con vistas diferenciadas (Administrador / Docente / Estudiante)
================================================================

CÓMO INSTALARLO EN XAMPP
1. Copiá toda la carpeta "sgpet_prototipo_v2" dentro de: C:\xampp\htdocs\
   (podés renombrarla si querés, por ejemplo a "sgpet").
2. Iniciá Apache y MySQL desde el Panel de Control de XAMPP.
3. Abrí phpMyAdmin (http://localhost/phpmyadmin), pestaña "Importar",
   y subí el archivo: sql/database.sql
   (esto crea la base "sgpet", las tablas y carga los usuarios/equipos de prueba).
4. Revisá config.php: si tu MySQL de XAMPP usa usuario/contraseña distinto
   a root/(vacío), ajustalo ahí.
5. Entrá desde el navegador a: http://localhost/sgpet_prototipo_v2/index.html

USUARIOS DE PRUEBA
Todos comparten la misma contraseña de prueba: Sgpet2026!
(guardada como hash bcrypt en la base, nunca en texto plano)

ADMINISTRADORES
 40111222 - Laura Castro
 40111223 - Analía Sáenz
 40111224 - Malena Sánchez

DOCENTES
 41222333 - Rosana Pereyra
 41222334 - Gustavo Techera
 41222335 - Valeria Núñez
 41222336 - Diego Larrosa
 41222337 - Marcela Ferreira

ESTUDIANTES
 50333444 - Bruno Machado
 50333445 - Camila Rodríguez
 50333446 - Ignacio Silva
 50333447 - Sofía Correa
 50333448 - Mateo Acosta
 50333449 - Valentina Suárez
 50333450 - Joaquín Ramírez
 50333451 - Abril Gómez
 50333452 - Nicolás Fagúndez
 50333453 - Martina Cabrera

CÓMO FUNCIONA
- index.html: formulario de login (pide ID de 8 dígitos + contraseña).
- login_process.php: valida contra la tabla "usuarios" con password_verify()
  y redirige según el campo "rol" guardado en sesión.

- dashboard_administrador.php: ve TODOS los usuarios, TODO el inventario
  y TODOS los préstamos del sistema. Desde acá el administrador puede:
    · Dar de alta, editar o eliminar usuarios (usuario_form.php / usuario_eliminar.php)
    · Generar una contraseña temporal para cualquier usuario (usuario_resetear_password.php)
    · Dar de alta, editar o eliminar equipos (equipo_form.php / equipo_eliminar.php)
    · Registrar la devolución de un préstamo activo (prestamo_devolver.php),
      lo que vuelve a poner el equipo como "Disponible"

- dashboard_docente.php: ve el inventario disponible completo y SOLO
  sus propios préstamos. Puede elegir un equipo puntual del listado y
  una fecha de devolución prevista para solicitarlo (crear_prestamo.php).

- dashboard_estudiante.php: vista más acotada; ve disponibilidad
  resumida por tipo de equipo (no el detalle completo) y SOLO sus
  propios préstamos. Elige un TIPO de equipo (no una unidad puntual) y
  el sistema le asigna automáticamente la primera unidad disponible de
  ese tipo (también vía crear_prestamo.php).

- crear_prestamo.php: valida la fecha, verifica que el equipo/tipo siga
  disponible, crea el registro en "prestamos" y marca el equipo como
  "Prestado", todo dentro de una transacción (evita que dos personas se
  lleven el mismo equipo si solicitan al mismo tiempo).

- includes/auth_check.php: si alguien no logueado intenta entrar a un
  dashboard, lo manda al login; si un usuario intenta entrar al panel
  de otro rol, lo redirige automáticamente al suyo.



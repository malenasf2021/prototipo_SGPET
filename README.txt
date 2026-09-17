SGPET - Login con vistas diferenciadas (Administrador / Docente / Estudiante)
================================================================

CÓMO INSTALARLO EN XAMPP
1. Copiá toda la carpeta "sgpet_prototipo_v2" dentro de: C:\xampp\htdocs\
   (podés renombrarla si querés, por ejemplo a "sgpet").
2. Iniciá Apache y MySQL desde el Panel de Control de XAMPP.
3. Abrí phpMyAdmin (http://localhost/phpmyadmin), pestaña "Importar",
   y subí el archivo: sql/sgpet_schema.sql
   (esto crea la base "sgpet_db" , carga
   los usuarios ficticios y el inventario de equipos de prueba).
4. Revisá config.php: si tu MySQL de XAMPP usa usuario/contraseña distinto
   a root/(vacío), ajustalo ahí.
5. Entrá desde el navegador a: http://localhost/sgpet_prototipo_v2/index.html

IDENTIFICADOR DE LOGIN
  · id_usuario: clave primaria interna, autoincremental, de 5 dígitos.
    ES la que se usa para iniciar sesión.

USUARIOS DE PRUEBA
Todos comparten la misma contraseña de prueba: Sgpet2026!
(guardada cifrada con bcrypt en "contrasena_hash", nunca en texto plano)

ADMINISTRADORES
 10001 - Laura Castro    (cédula 40111222)
 10002 - Analía Sáenz    (cédula 40111223)
 10003 - Malena Sánchez  (cédula 40111224)

DOCENTES
 10004 - Rosana Pereyra  (Informática)
 10005 - Gustavo Techera (Robótica)
 10006 - Valeria Núñez   (Ciencias)
 10007 - Diego Larrosa   (Matemática)
 10008 - Marcela Ferreira (Informática)

ESTUDIANTES
 10009 - Bruno Machado     (2do B)
 10010 - Camila Rodríguez  (2do B)
 10011 - Ignacio Silva     (3ro A)
 10012 - Sofía Correa      (3ro A)
 10013 - Mateo Acosta      (1ro C)
 10014 - Valentina Suárez  (1ro C)
 10015 - Joaquín Ramírez   (2do A)
 10016 - Abril Gómez       (2do A)
 10017 - Nicolás Fagúndez  (3ro B)
 10018 - Martina Cabrera   (3ro B)

INVENTARIO CARGADO (27 equipos, sin préstamos activos)
 · 10 Ceibalitas (identificadas por número de serie de 10 dígitos)
 · 5 Kits de robótica (uno con condición "kit_incompleto" / disponibilidad
   "en_mantenimiento", para mostrar que ambos estados son independientes)
 · 5 Placas Micro:bit
 · 2 Impresoras 3D (con restricción de uso: solo por el día, dentro de
   la institución)
 · 5 Multisensor Datalogger (uno "dañado" / "dado_de_baja", como ejemplo
   de equipo retirado del inventario activo)

CÓMO FUNCIONA
- index.html: login estático (HTML puro). Pide id_usuario (5 dígitos) +
  contraseña. Si login_process.php devuelve un error, esta misma página
  lo lee de la URL (?error=...) y lo muestra con JavaScript, porque al
  ser un .html no puede leer variables de sesión de PHP directamente.

- login_process.php: valida contra la tabla "usuario" (contrasena_hash
  con password_verify(), y activo=1) y redirige a
  dashboard_administrador.php / dashboard_docente.php / dashboard_estudiante.php
  según el "rol" (admin / docente / estudiante).

- dashboard_administrador.php: ve TODOS los usuarios y TODO el
  inventario de equipos. Desde acá el administrador puede:
    · Dar de alta o editar usuarios (usuario_form.php)
    · Desactivar/reactivar un usuario (usuario_eliminar.php) — NO se
      borra el registro físicamente, porque las tablas de préstamos,
      reservas y sanciones de la próxima entrega van a necesitar
      conservar el historial (la base usa ON DELETE RESTRICT a propósito)
    · Generar una contraseña temporal (usuario_resetear_password.php)
    · Dar de alta, editar o eliminar equipos (equipo_form.php / equipo_eliminar.php)

- dashboard_docente.php: ve el inventario completo, equipo por equipo
  (número de serie, tipo, disponibilidad, restricciones).

- dashboard_estudiante.php: vista más acotada, disponibilidad resumida
  por tipo de equipo (sin el detalle unidad por unidad).

- includes/auth_check.php: si alguien no logueado intenta entrar a un
  dashboard, lo manda al login; si un usuario intenta entrar al panel
  de otro rol, lo redirige automáticamente al suyo.

MÓDULO DE RESERVAS, PRÉSTAMOS, FALLAS, SANCIONES, NOTIFICACIONES Y AUDITORÍA
- reserva (reserva_form.php / reserva_cancelar.php): docentes y
  estudiantes reservan equipos con anticipación. Los estudiantes solo
  pueden reservar ceibalitas (regla de negocio del CU01/CU03). El
  propio usuario puede cancelar una reserva pendiente; el admin puede
  cancelar cualquiera o convertirla en préstamo.
- prestamo (prestamo_form.php / prestamo_devolucion.php): solo el
  administrador registra préstamos (directos o convirtiendo una
  reserva pendiente) y procesa la devolución con inspección técnica
  de la condición del equipo.
- falla (se registra desde prestamo_devolucion.php): daños o piezas
  faltantes detectados en la inspección al devolver un equipo.
- sancion (sancion_form.php / sancion_levantar.php): restricción
  temporal a un usuario. Se aplica automáticamente al devolver con
  atraso o con una falla grave, y también puede aplicarla o
  levantarla manualmente el administrador. Un usuario sancionado no
  puede generar nuevas reservas ni recibir préstamos mientras esté
  vigente.
- notificacion (se ve en cada panel, se marca como leída con
  notificacion_marcar_leida.php): avisos de reservas, préstamos,
  sanciones y vencimientos. El botón "Generar recordatorios de
  vencimiento" del panel admin (generar_recordatorios.php) marca como
  atrasados los préstamos vencidos y avisa los que vencen hoy o
  mañana; reemplaza a una tarea programada (cron) que en un servidor
  propio correría todos los días automáticamente.
- log_auditoria (se ve en el panel admin): registra usuario, fecha,
  acción y entidad de cada operación relevante del sistema.

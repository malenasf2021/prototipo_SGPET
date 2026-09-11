-- =====================================================
-- Carga de inventario real de equipos - SGPET
-- Ejecutar DESPUÉS de tener creada la base "sgpet" y la tabla "equipos"
-- (podés pegarlo directo en la pestaña SQL de phpMyAdmin,
--  o agregarlo al final de sql/database.sql)
-- =====================================================

USE sgpet;

-- Si ya habías cargado los equipos de ejemplo anteriores y querés
-- reemplazarlos por este inventario real, primero vaciá la tabla:
-- (Ojo: esto borra también los préstamos relacionados, por la FK)
-- DELETE FROM prestamos;
-- DELETE FROM equipos;
-- ALTER TABLE equipos AUTO_INCREMENT = 1;

-- ---------- 10 CEIBALITAS (identificadas por N° de serie de 10 dígitos) ----------
INSERT INTO equipos (nombre, tipo, estado) VALUES
('Ceibalita N/S 3746317213', 'Ceibalita', 'Disponible'),
('Ceibalita N/S 9697354961', 'Ceibalita', 'Disponible'),
('Ceibalita N/S 2181241943', 'Ceibalita', 'Disponible'),
('Ceibalita N/S 1958682846', 'Ceibalita', 'Disponible'),
('Ceibalita N/S 4163119785', 'Ceibalita', 'Disponible'),
('Ceibalita N/S 9963334018', 'Ceibalita', 'Disponible'),
('Ceibalita N/S 2812140441', 'Ceibalita', 'Disponible'),
('Ceibalita N/S 1127978094', 'Ceibalita', 'Disponible'),
('Ceibalita N/S 1939042955', 'Ceibalita', 'Disponible'),
('Ceibalita N/S 9703905715', 'Ceibalita', 'Disponible');

-- ---------- 5 KITS DE ROBÓTICA (identificados del 1 al 5) ----------
INSERT INTO equipos (nombre, tipo, estado) VALUES
('Kit Robótica 1', 'Kit de robótica', 'Disponible'),
('Kit Robótica 2', 'Kit de robótica', 'Disponible'),
('Kit Robótica 3', 'Kit de robótica', 'Disponible'),
('Kit Robótica 4', 'Kit de robótica', 'Disponible'),
('Kit Robótica 5', 'Kit de robótica', 'Disponible');

-- ---------- 5 PLACAS MICRO:BIT (identificadas del 1 al 5) ----------
INSERT INTO equipos (nombre, tipo, estado) VALUES
('Micro:bit 1', 'Placa Micro:bit', 'Disponible'),
('Micro:bit 2', 'Placa Micro:bit', 'Disponible'),
('Micro:bit 3', 'Placa Micro:bit', 'Disponible'),
('Micro:bit 4', 'Placa Micro:bit', 'Disponible'),
('Micro:bit 5', 'Placa Micro:bit', 'Disponible');

-- ---------- 2 IMPRESORAS 3D (solo por el día, dentro de la institución) ----------
INSERT INTO equipos (nombre, tipo, estado) VALUES
('Impresora 3D 1 (uso diario - solo dentro de la institución)', 'Impresora 3D', 'Disponible'),
('Impresora 3D 2 (uso diario - solo dentro de la institución)', 'Impresora 3D', 'Disponible');

-- ---------- 5 MULTISENSOR DATALOGGER (identificados del 1 al 5) ----------
INSERT INTO equipos (nombre, tipo, estado) VALUES
('Multisensor Datalogger 1', 'Multisensor Datalogger', 'Disponible'),
('Multisensor Datalogger 2', 'Multisensor Datalogger', 'Disponible'),
('Multisensor Datalogger 3', 'Multisensor Datalogger', 'Disponible'),
('Multisensor Datalogger 4', 'Multisensor Datalogger', 'Disponible'),
('Multisensor Datalogger 5', 'Multisensor Datalogger', 'Disponible');

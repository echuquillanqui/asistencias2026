-- Registros de prueba de asistencia para agosto y setiembre de 2026.
-- Requisito: importar primero bk_basededatos.sql para disponer de los empleados 1, 2 y 3.
-- El script es idempotente: elimina únicamente los registros de prueba del mismo
-- período y empleados antes de volver a generarlos.

USE `control_acceso_db`;

START TRANSACTION;

DELETE FROM `attendance_logs`
WHERE `employee_id` IN (1, 2, 3)
  AND `date_log` BETWEEN '2026-08-01' AND '2026-09-30';

INSERT INTO `attendance_logs` (
    `employee_id`,
    `date_log`,
    `check_in_time`,
    `breakfast_time`,
    `breakfast_return_time`,
    `lunch_out_time`,
    `lunch_return_time`,
    `check_out_time`,
    `source_ip`,
    `total_hours`,
    `status`
)
WITH RECURSIVE `fechas` AS (
    SELECT DATE('2026-08-01') AS `fecha`
    UNION ALL
    SELECT DATE_ADD(`fecha`, INTERVAL 1 DAY)
    FROM `fechas`
    WHERE `fecha` < '2026-09-30'
),
`empleados_prueba` AS (
    SELECT 1 AS `employee_id`
    UNION ALL SELECT 2
    UNION ALL SELECT 3
),
`marcaciones` AS (
    SELECT
        `e`.`employee_id`,
        `f`.`fecha`,
        ADDTIME('07:52:00', SEC_TO_TIME(MOD(DAYOFMONTH(`f`.`fecha`) * `e`.`employee_id` * 7, 25) * 60)) AS `entrada`,
        ADDTIME('09:28:00', SEC_TO_TIME(MOD(DAYOFMONTH(`f`.`fecha`) + `e`.`employee_id`, 7) * 60)) AS `desayuno_salida`,
        ADDTIME('12:58:00', SEC_TO_TIME(MOD(DAYOFMONTH(`f`.`fecha`) + (`e`.`employee_id` * 2), 7) * 60)) AS `almuerzo_salida`,
        ADDTIME('17:57:00', SEC_TO_TIME(MOD(DAYOFMONTH(`f`.`fecha`) * 3 + `e`.`employee_id`, 16) * 60)) AS `salida`
    FROM `fechas` AS `f`
    CROSS JOIN `empleados_prueba` AS `e`
    WHERE WEEKDAY(`f`.`fecha`) < 5
)
SELECT
    `employee_id`,
    `fecha`,
    `entrada`,
    `desayuno_salida`,
    ADDTIME(`desayuno_salida`, '00:15:00'),
    `almuerzo_salida`,
    ADDTIME(`almuerzo_salida`, '01:00:00'),
    `salida`,
    CONCAT('192.168.1.', 10 + `employee_id`),
    ROUND((TIME_TO_SEC(`salida`) - TIME_TO_SEC(`entrada`) - 3600) / 3600, 2),
    IF(`entrada` > '08:00:00', 'tarde', 'a_tiempo')
FROM `marcaciones`
ORDER BY `fecha`, `employee_id`;

COMMIT;

-- Resultado esperado: 129 registros (43 días laborables x 3 empleados).
SELECT
    DATE_FORMAT(`date_log`, '%Y-%m') AS `mes`,
    COUNT(*) AS `registros`,
    SUM(`status` = 'a_tiempo') AS `a_tiempo`,
    SUM(`status` = 'tarde') AS `tardanzas`
FROM `attendance_logs`
WHERE `employee_id` IN (1, 2, 3)
  AND `date_log` BETWEEN '2026-08-01' AND '2026-09-30'
GROUP BY DATE_FORMAT(`date_log`, '%Y-%m')
ORDER BY `mes`;

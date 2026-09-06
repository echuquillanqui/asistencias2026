<?php
require_once '../app/models/Employee.php';
// Si tienes Attendance.php, descomenta la siguiente línea:
// require_once '../app/models/Attendance.php';

class AttendanceController {
    private static $breakfastReturnChecked = false;
    private static $hasBreakfastReturnColumn = false;

    private function ensureBreakfastReturnColumn($db) {
        if (self::$breakfastReturnChecked) {
            return;
        }

        $stmt = $db->prepare("SHOW COLUMNS FROM attendance_logs LIKE 'breakfast_return_time'");
        $stmt->execute();
        self::$hasBreakfastReturnColumn = (bool)$stmt->fetch(PDO::FETCH_ASSOC);

        if (!self::$hasBreakfastReturnColumn) {
            $db->exec("ALTER TABLE attendance_logs ADD COLUMN breakfast_return_time TIME NULL AFTER breakfast_time");
            self::$hasBreakfastReturnColumn = true;
        }

        self::$breakfastReturnChecked = true;
    }

    private function getSettingValue($db, $settingName, $fallback = '') {
        $query = "SELECT setting_value FROM settings WHERE setting_name = :settingName LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':settingName', $settingName);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row && !empty($row['setting_value']) ? $row['setting_value'] : $fallback;
    }

    private function getFirstScheduleTime($scheduleValue, $fallback = '08:00:00') {
        $parts = array_filter(array_map('trim', explode(',', (string)$scheduleValue)));
        if (empty($parts)) {
            return $fallback;
        }
        $time = $parts[0];
        return strlen($time) === 5 ? ($time . ':00') : $time;
    }

    private function calculateTotalHours($checkIn, $checkOut, $lunchOut, $lunchReturn) {
        $workSeconds = strtotime($checkOut) - strtotime($checkIn);
        if ($workSeconds < 0) {
            return null;
        }

        if (!empty($lunchOut) && !empty($lunchReturn)) {
            $lunchSeconds = strtotime($lunchReturn) - strtotime($lunchOut);
            if ($lunchSeconds > 0) {
                $workSeconds -= $lunchSeconds;
            }
        }

        if ($workSeconds < 0) {
            $workSeconds = 0;
        }
        return round($workSeconds / 3600, 2);
    }

    private function getClientIp() {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $forwarded = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($forwarded[0]);
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    private function ipIsAllowed($db, $currentIp) {
        $query = "SELECT setting_value FROM settings WHERE setting_name = 'kiosk_allowed_ips' LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result || empty(trim($result['setting_value']))) {
            return true; // Si no hay configuración, no bloquea.
        }

        $allowedIps = array_filter(array_map('trim', explode(',', $result['setting_value'])));
        return in_array($currentIp, $allowedIps, true);
    }

    private function ensureQrTokensTable($db) {
        $db->exec("CREATE TABLE IF NOT EXISTS attendance_qr_tokens (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            token_hash CHAR(64) NOT NULL,
            employee_id INT NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_attendance_qr_token_hash (token_hash),
            KEY idx_attendance_qr_employee (employee_id),
            KEY idx_attendance_qr_expiry (expires_at),
            CONSTRAINT fk_attendance_qr_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    }

    private function consumeQrToken($db, $scannedValue) {
        if (strpos($scannedValue, 'ATT:') !== 0) {
            return null;
        }

        $token = substr($scannedValue, 4);
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }

        $tokenHash = hash('sha256', $token);
        $db->beginTransaction();
        try {
            $update = $db->prepare("UPDATE attendance_qr_tokens
                                    SET used_at = NOW()
                                    WHERE token_hash = :token_hash
                                      AND used_at IS NULL
                                      AND expires_at >= NOW()"
            );
            $update->execute([':token_hash' => $tokenHash]);
            if ($update->rowCount() !== 1) {
                $db->rollBack();
                return null;
            }

            $lookup = $db->prepare("SELECT employee_id FROM attendance_qr_tokens WHERE token_hash = :token_hash LIMIT 1");
            $lookup->execute([':token_hash' => $tokenHash]);
            $employeeId = $lookup->fetchColumn();
            $db->commit();
            return $employeeId ? (int)$employeeId : null;
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    }
    
    // 1. Muestra la vista del Kiosco (Pantalla con cámara)
    public function index() {
        require_once '../app/views/attendance/kiosk.php';
    }

    // 2. Procesa el escaneo del código QR
    public function register() {
        $database = new Database();
        $db = $database->getConnection();
        $this->ensureBreakfastReturnColumn($db);
        $this->ensureQrTokensTable($db);
        
        // Inicializamos mensaje vacio
        $message = "";
        $error = "";
        $currentIp = $this->getClientIp();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$this->ipIsAllowed($db, $currentIp)) {
                $error = "Marcación bloqueada: este dispositivo/red no pertenece a una sede autorizada. IP detectada: {$currentIp}";
                require_once '../app/views/attendance/kiosk.php';
                return;
            }

            // El QR contiene un token temporal; nunca el código permanente del empleado.
            $scannedValue = trim($_POST['employee_code'] ?? '');
            $employeeId = $this->consumeQrToken($db, $scannedValue);

            if ($employeeId !== null) {
                // A. Buscar al empleado asociado al token consumido.
                $queryEmp = "SELECT e.id, e.first_name, s.entry_time as schedule_entry_time
                             FROM employees e
                             LEFT JOIN schedules s ON e.schedule_id = s.id
                             WHERE e.id = :employee_id AND e.status = 'activo' LIMIT 1";
                $stmtEmp = $db->prepare($queryEmp);
                $stmtEmp->bindParam(':employee_id', $employeeId, PDO::PARAM_INT);
                $stmtEmp->execute();
                $empleado = $stmtEmp->fetch(PDO::FETCH_ASSOC);

                if ($empleado) {
                    $empId = $empleado['id'];
                    $fechaHoy = date('Y-m-d');
                    $horaActual = date('H:i:s');

                    // B. Verificar si ya tiene registro HOY
                    $queryCheck = "SELECT id, check_in_time, breakfast_time, breakfast_return_time, lunch_out_time, lunch_return_time, check_out_time FROM attendance_logs 
                                   WHERE employee_id = :empId AND date_log = :fechaHoy";
                    $stmtCheck = $db->prepare($queryCheck);
                    $stmtCheck->bindParam(':empId', $empId);
                    $stmtCheck->bindParam(':fechaHoy', $fechaHoy);
                    $stmtCheck->execute();
                    $registroHoy = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                    if (!$registroHoy) {
                        // CASO 1: No existe registro hoy -> ES UNA ENTRADA
                        $entrySchedule = !empty($empleado['schedule_entry_time'])
                            ? $empleado['schedule_entry_time']
                            : $this->getSettingValue($db, 'entry_time', '08:00');
                        $entryLimit = $this->getFirstScheduleTime($entrySchedule, '08:00:00');
                        $status = (strtotime($horaActual) > strtotime($entryLimit)) ? 'tarde' : 'a_tiempo';

                        $insert = "INSERT INTO attendance_logs (employee_id, date_log, check_in_time, source_ip, status) 
                                   VALUES (:empId, :fechaHoy, :horaActual, :sourceIp, :status)";
                        $stmtInsert = $db->prepare($insert);
                        $stmtInsert->bindParam(':empId', $empId);
                        $stmtInsert->bindParam(':fechaHoy', $fechaHoy);
                        $stmtInsert->bindParam(':horaActual', $horaActual);
                        $stmtInsert->bindParam(':sourceIp', $currentIp);
                        $stmtInsert->bindParam(':status', $status);
                        
                        if($stmtInsert->execute()) {
                            $message = "¡Buenos días " . $empleado['first_name'] . "! Entrada registrada.";
                        } else {
                            $error = "Error al registrar la entrada.";
                        }

                    } else {
                        // CASO 2: Ya existe registro hoy
                        $action = strtolower(trim($_POST['action_type'] ?? 'auto'));

                        if ($action === 'auto') {
                            if ($registroHoy['breakfast_time'] == NULL) {
                                $action = 'breakfast_out';
                            } elseif (($registroHoy['breakfast_return_time'] ?? null) == NULL) {
                                $action = 'breakfast_return';
                            } elseif ($registroHoy['lunch_out_time'] == NULL) {
                                $action = 'lunch_out';
                            } elseif ($registroHoy['lunch_return_time'] == NULL) {
                                $action = 'lunch_return';
                            } elseif ($registroHoy['check_out_time'] == NULL) {
                                $action = 'check_out';
                            } else {
                                $action = 'done';
                            }
                        }

                        if ($action === 'breakfast_out') {
                            if ($registroHoy['breakfast_time'] != NULL) {
                                $error = "La salida a desayuno ya fue registrada.";
                            } else {
                                $update = "UPDATE attendance_logs SET breakfast_time = :horaActual WHERE id = :logId";
                                $stmtUpdate = $db->prepare($update);
                                $stmtUpdate->bindParam(':horaActual', $horaActual);
                                $stmtUpdate->bindParam(':logId', $registroHoy['id']);
                                if($stmtUpdate->execute()) {
                                    $message = "Salida a desayuno registrada para " . $empleado['first_name'] . ".";
                                } else {
                                    $error = "Error al registrar salida a desayuno.";
                                }
                            }
                        } elseif ($action === 'breakfast_return') {
                            if ($registroHoy['breakfast_time'] == NULL) {
                                $error = "Primero debe registrar salida a desayuno.";
                            } elseif (($registroHoy['breakfast_return_time'] ?? null) != NULL) {
                                $error = "El retorno de desayuno ya fue registrado.";
                            } else {
                                $update = "UPDATE attendance_logs SET breakfast_return_time = :horaActual WHERE id = :logId";
                                $stmtUpdate = $db->prepare($update);
                                $stmtUpdate->bindParam(':horaActual', $horaActual);
                                $stmtUpdate->bindParam(':logId', $registroHoy['id']);
                                if($stmtUpdate->execute()) {
                                    $message = "Retorno de desayuno registrado para " . $empleado['first_name'] . ".";
                                } else {
                                    $error = "Error al registrar retorno de desayuno.";
                                }
                            }
                        } elseif ($action === 'lunch_out') {
                            if ($registroHoy['lunch_out_time'] != NULL) {
                                $error = "La salida a almuerzo ya fue registrada.";
                            } else {
                                $update = "UPDATE attendance_logs SET lunch_out_time = :horaActual WHERE id = :logId";
                                $stmtUpdate = $db->prepare($update);
                                $stmtUpdate->bindParam(':horaActual', $horaActual);
                                $stmtUpdate->bindParam(':logId', $registroHoy['id']);
                                if($stmtUpdate->execute()) {
                                    $message = "Salida a almuerzo registrada para " . $empleado['first_name'] . ".";
                                } else {
                                    $error = "Error al registrar salida a almuerzo.";
                                }
                            }
                        } elseif ($action === 'lunch_return') {
                            if ($registroHoy['lunch_out_time'] == NULL) {
                                $error = "Primero debe registrar salida a almuerzo.";
                            } elseif ($registroHoy['lunch_return_time'] != NULL) {
                                $error = "El retorno de almuerzo ya fue registrado.";
                            } else {
                                $update = "UPDATE attendance_logs SET lunch_return_time = :horaActual WHERE id = :logId";
                                $stmtUpdate = $db->prepare($update);
                                $stmtUpdate->bindParam(':horaActual', $horaActual);
                                $stmtUpdate->bindParam(':logId', $registroHoy['id']);
                                if($stmtUpdate->execute()) {
                                    $message = "Retorno de almuerzo registrado para " . $empleado['first_name'] . ".";
                                } else {
                                    $error = "Error al registrar retorno de almuerzo.";
                                }
                            }
                        } elseif ($action === 'check_out') {
                            if ($registroHoy['check_out_time'] != NULL) {
                                $error = "La salida final ya fue registrada.";
                            } else {
                            $totalHours = $this->calculateTotalHours(
                                $registroHoy['check_in_time'],
                                $horaActual,
                                $registroHoy['lunch_out_time'],
                                $registroHoy['lunch_return_time']
                            );

                            $update = "UPDATE attendance_logs 
                                       SET check_out_time = :horaActual, total_hours = :totalHours
                                       WHERE id = :logId";
                            $stmtUpdate = $db->prepare($update);
                            $stmtUpdate->bindParam(':horaActual', $horaActual);
                            $stmtUpdate->bindParam(':totalHours', $totalHours);
                            $stmtUpdate->bindParam(':logId', $registroHoy['id']);
                            
                                if($stmtUpdate->execute()) {
                                    $message = "¡Hasta mañana " . $empleado['first_name'] . "! Salida final registrada.";
                                } else {
                                    $error = "Error al registrar la salida final.";
                                }
                            }
                        } elseif ($action === 'done') {
                            $error = "Ya completaste todas las marcaciones de hoy.";
                        } else {
                            $error = "Tipo de marcación no válido.";
                        }
                    }
                } else {
                    $error = "Código QR no válido o empleado no encontrado.";
                }
            } else {
                $error = "QR vencido, utilizado o no válido. Genera uno nuevo desde tu portal.";
            }
        }

        // Volver a cargar la vista con los mensajes
        require_once '../app/views/attendance/kiosk.php';
    }
}
?>

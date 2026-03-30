<?php
require_once '../app/models/Employee.php';
// Si tienes Attendance.php, descomenta la siguiente línea:
// require_once '../app/models/Attendance.php';

class AttendanceController {
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
    
    // 1. Muestra la vista del Kiosco (Pantalla con cámara)
    public function index() {
        require_once '../app/views/attendance/kiosk.php';
    }

    // 2. Procesa el escaneo del código QR
    public function register() {
        $database = new Database();
        $db = $database->getConnection();
        
        // Inicializamos mensaje vacio
        $message = "";
        $error = "";
        $currentIp = $this->getClientIp();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$this->ipIsAllowed($db, $currentIp)) {
                $error = "Marcación bloqueada: este dispositivo/red no pertenece a una sede autorizada.";
                require_once '../app/views/attendance/kiosk.php';
                return;
            }

            // Recibimos el código del escáner
            $code = isset($_POST['employee_code']) ? $_POST['employee_code'] : '';

            if(!empty($code)) {
                // A. Buscar al empleado por su código
                $queryEmp = "SELECT id, first_name FROM employees WHERE employee_code = :code LIMIT 1";
                $stmtEmp = $db->prepare($queryEmp);
                $stmtEmp->bindParam(':code', $code);
                $stmtEmp->execute();
                $empleado = $stmtEmp->fetch(PDO::FETCH_ASSOC);

                if ($empleado) {
                    $empId = $empleado['id'];
                    $fechaHoy = date('Y-m-d');
                    $horaActual = date('H:i:s');

                    // B. Verificar si ya tiene registro HOY
                    $queryCheck = "SELECT id, check_in_time, breakfast_time, lunch_out_time, lunch_return_time, check_out_time FROM attendance_logs 
                                   WHERE employee_id = :empId AND date_log = :fechaHoy";
                    $stmtCheck = $db->prepare($queryCheck);
                    $stmtCheck->bindParam(':empId', $empId);
                    $stmtCheck->bindParam(':fechaHoy', $fechaHoy);
                    $stmtCheck->execute();
                    $registroHoy = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                    if (!$registroHoy) {
                        // CASO 1: No existe registro hoy -> ES UNA ENTRADA
                        $entrySchedule = $this->getSettingValue($db, 'entry_time', '08:00');
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
                        if ($registroHoy['breakfast_time'] == NULL) {
                            $update = "UPDATE attendance_logs SET breakfast_time = :horaActual WHERE id = :logId";
                            $stmtUpdate = $db->prepare($update);
                            $stmtUpdate->bindParam(':horaActual', $horaActual);
                            $stmtUpdate->bindParam(':logId', $registroHoy['id']);

                            if($stmtUpdate->execute()) {
                                $message = "Desayuno registrado para " . $empleado['first_name'] . ".";
                            } else {
                                $error = "Error al registrar desayuno.";
                            }
                        } elseif ($registroHoy['lunch_out_time'] == NULL) {
                            $update = "UPDATE attendance_logs SET lunch_out_time = :horaActual WHERE id = :logId";
                            $stmtUpdate = $db->prepare($update);
                            $stmtUpdate->bindParam(':horaActual', $horaActual);
                            $stmtUpdate->bindParam(':logId', $registroHoy['id']);

                            if($stmtUpdate->execute()) {
                                $message = "Salida a almuerzo registrada para " . $empleado['first_name'] . ".";
                            } else {
                                $error = "Error al registrar salida a almuerzo.";
                            }
                        } elseif ($registroHoy['lunch_return_time'] == NULL) {
                            $update = "UPDATE attendance_logs SET lunch_return_time = :horaActual WHERE id = :logId";
                            $stmtUpdate = $db->prepare($update);
                            $stmtUpdate->bindParam(':horaActual', $horaActual);
                            $stmtUpdate->bindParam(':logId', $registroHoy['id']);

                            if($stmtUpdate->execute()) {
                                $message = "Retorno de almuerzo registrado para " . $empleado['first_name'] . ".";
                            } else {
                                $error = "Error al registrar retorno de almuerzo.";
                            }
                        } elseif ($registroHoy['check_out_time'] == NULL) {
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
                        } else {
                            $error = "Ya completaste todas las marcaciones de hoy.";
                        }
                    }
                } else {
                    $error = "Código QR no válido o empleado no encontrado.";
                }
            }
        }

        // Volver a cargar la vista con los mensajes
        require_once '../app/views/attendance/kiosk.php';
    }
}
?>

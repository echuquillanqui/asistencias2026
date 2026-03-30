<?php
require_once '../app/models/Employee.php';
// Si tienes Attendance.php, descomenta la siguiente línea:
// require_once '../app/models/Attendance.php';

class AttendanceController {
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
                    $queryCheck = "SELECT id, check_out_time FROM attendance_logs 
                                   WHERE employee_id = :empId AND date_log = :fechaHoy";
                    $stmtCheck = $db->prepare($queryCheck);
                    $stmtCheck->bindParam(':empId', $empId);
                    $stmtCheck->bindParam(':fechaHoy', $fechaHoy);
                    $stmtCheck->execute();
                    $registroHoy = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                    if (!$registroHoy) {
                        // CASO 1: No existe registro hoy -> ES UNA ENTRADA
                        $insert = "INSERT INTO attendance_logs (employee_id, date_log, check_in_time, source_ip) 
                                   VALUES (:empId, :fechaHoy, :horaActual, :sourceIp)";
                        $stmtInsert = $db->prepare($insert);
                        $stmtInsert->bindParam(':empId', $empId);
                        $stmtInsert->bindParam(':fechaHoy', $fechaHoy);
                        $stmtInsert->bindParam(':horaActual', $horaActual);
                        $stmtInsert->bindParam(':sourceIp', $currentIp);
                        
                        if($stmtInsert->execute()) {
                            $message = "¡Buenos días " . $empleado['first_name'] . "! Entrada registrada.";
                        } else {
                            $error = "Error al registrar la entrada.";
                        }

                    } else {
                        // CASO 2: Ya existe registro hoy
                        if ($registroHoy['check_out_time'] == NULL) {
                            // Tiene entrada pero no salida -> ES UNA SALIDA
                            $update = "UPDATE attendance_logs SET check_out_time = :horaActual 
                                       WHERE id = :logId";
                            $stmtUpdate = $db->prepare($update);
                            $stmtUpdate->bindParam(':horaActual', $horaActual);
                            $stmtUpdate->bindParam(':logId', $registroHoy['id']);
                            
                            if($stmtUpdate->execute()) {
                                $message = "¡Hasta mañana " . $empleado['first_name'] . "! Salida registrada.";
                            } else {
                                $error = "Error al registrar la salida.";
                            }
                        } else {
                            // Ya marcó entrada y salida hoy
                            $error = "Ya has registrado tu salida hoy. No puedes marcar de nuevo.";
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

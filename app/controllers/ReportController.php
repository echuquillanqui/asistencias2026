<?php
require_once '../app/config/db.php';
require_once '../app/models/Attendance.php';
require_once '../app/models/Employee.php';
require_once '../app/models/Setting.php'; // 1. IMPORTAMOS EL MODELO DE AJUSTES

class ReportController {
    private $attendanceModel;
    private $employeeModel;
    private $settingModel; // 2. VARIABLE NUEVA
    private $db;
    
    private function getFirstScheduleTime($scheduleValue, $fallback = '08:00:00') {
        $parts = array_filter(array_map('trim', explode(',', (string)$scheduleValue)));
        if (empty($parts)) return $fallback;
        $time = $parts[0];
        return strlen($time) === 5 ? ($time . ':00') : $time;
    }

    public function __construct() {
        if (session_status() == PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['user_id'])) {
            header("Location: ?c=Auth&a=login");
            exit;
        }
        
        if ($_SESSION['role'] != 'admin') {
            header("Location: ?c=Dashboard");
            exit;
        }

        $database = new Database();
        $this->db = $database->getConnection();
        $this->attendanceModel = new Attendance($this->db);
        $this->employeeModel = new Employee($this->db);
        $this->settingModel = new Setting($this->db); // 3. INICIALIZAR
    }

    public function index() { require_once '../app/views/reports/index.php'; }

    public function history() {
        $employees = $this->employeeModel->read();
        $start_date = isset($_GET['start']) ? $_GET['start'] : date('Y-m-01');
        $end_date = isset($_GET['end']) ? $_GET['end'] : date('Y-m-d');
        $employee_id = isset($_GET['employee_id']) ? $_GET['employee_id'] : '';
        
        $logs = $this->attendanceModel->getLogsWithFilters($employee_id, $start_date, $end_date);
        
        // 4. OBTENER LA HORA REAL DE LA BASE DE DATOS
        $entrySchedule = $this->settingModel->get('entry_time');
        $horaEntradaOficial = $this->getFirstScheduleTime($entrySchedule, '08:00:00');

        require_once '../app/views/reports/history.php';
    }

    public function export() {
        if (isset($_POST['start_date'])) {
            $start = $_POST['start_date']; $end = $_POST['end_date'];
            
            // OBTENER HORA DE LA BD TAMBIÉN PARA EL EXCEL
            $horaLimite = $this->getFirstScheduleTime($this->settingModel->get('entry_time'), '08:00:00');

            $data = $this->attendanceModel->getHistoryByDate($start, $end);
            
            $filename = "Reporte_" . date('Ymd') . ".csv";
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=' . $filename);
            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
            
            fputcsv($output, ['Codigo', 'Empleado', 'Depto', 'Fecha', 'Entrada', 'Desayuno', 'Salida Almuerzo', 'Retorno Almuerzo', 'Salida', 'Horas', 'Estado', 'Puntualidad']);
            
            foreach ($data as $row) {
                $horas = "--"; 
                $puntual = "Puntual";
                
                if ($row['check_out_time']) {
                    $horas = number_format((float)($row['total_hours'] ?? 0), 2);
                }
                
                // USAR LA VARIABLE DE LA BD PARA CALCULAR
                $horaLimiteEmpleado = !empty($row['schedule_entry_time'])
                    ? $this->getFirstScheduleTime($row['schedule_entry_time'], $horaLimite)
                    : $horaLimite;

                if ($row['check_in_time'] > $horaLimiteEmpleado) $puntual = "TARDE";
                
                $est = ($row['check_out_time']) ? 'Completado' : 'En Turno';
                fputcsv($output, [
                    $row['employee_code'],
                    $row['first_name'].' '.$row['last_name'],
                    $row['department'],
                    $row['date_log'],
                    $row['check_in_time'],
                    $row['breakfast_time'],
                    $row['lunch_out_time'],
                    $row['lunch_return_time'],
                    $row['check_out_time'],
                    $horas,
                    $est,
                    $puntual
                ]);
            }
            fclose($output); exit;
        }
    }
}
?>

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

    private function minutesLate($checkInTime, $limitTime) {
        if (empty($checkInTime) || empty($limitTime)) {
            return 0;
        }
        $diff = strtotime($checkInTime) - strtotime($limitTime);
        if ($diff <= 0) {
            return 0;
        }
        return (int)ceil($diff / 60);
    }
    
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
            
            $filename = "Reporte_Asistencia_" . date('Ymd') . ".xls";
            header('Content-Type: application/vnd.ms-excel; charset=utf-8');
            header('Content-Disposition: attachment; filename=' . $filename);

            $xmlRowsMain = [];
            $summary = [];

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
                $minutosTarde = $this->minutesLate($row['check_in_time'], $horaLimiteEmpleado);
                
                $est = ($row['check_out_time']) ? 'Completado' : 'En Turno';
                $employeeKey = (string)$row['employee_code'];
                if (!isset($summary[$employeeKey])) {
                    $summary[$employeeKey] = [
                        'codigo' => $row['employee_code'],
                        'empleado' => $row['first_name'].' '.$row['last_name'],
                        'dias_tarde' => 0,
                        'minutos_tarde' => 0
                    ];
                }

                if ($puntual === "TARDE") {
                    $summary[$employeeKey]['dias_tarde']++;
                    $summary[$employeeKey]['minutos_tarde'] += $minutosTarde;
                }

                $entradaStyle = ($puntual === "TARDE") ? ' ss:StyleID="lateCell"' : '';
                $xmlRowsMain[] =
                    '<Row>'
                    . '<Cell><Data ss:Type="String">' . htmlspecialchars((string)$row['employee_code']) . '</Data></Cell>'
                    . '<Cell><Data ss:Type="String">' . htmlspecialchars($row['first_name'].' '.$row['last_name']) . '</Data></Cell>'
                    . '<Cell><Data ss:Type="String">' . htmlspecialchars((string)$row['department']) . '</Data></Cell>'
                    . '<Cell><Data ss:Type="String">' . htmlspecialchars((string)$row['date_log']) . '</Data></Cell>'
                    . '<Cell' . $entradaStyle . '><Data ss:Type="String">' . htmlspecialchars((string)$row['check_in_time']) . '</Data></Cell>'
                    . '<Cell><Data ss:Type="String">' . htmlspecialchars((string)$row['breakfast_time']) . '</Data></Cell>'
                    . '<Cell><Data ss:Type="String">' . htmlspecialchars((string)($row['breakfast_return_time'] ?? '')) . '</Data></Cell>'
                    . '<Cell><Data ss:Type="String">' . htmlspecialchars((string)$row['lunch_out_time']) . '</Data></Cell>'
                    . '<Cell><Data ss:Type="String">' . htmlspecialchars((string)$row['lunch_return_time']) . '</Data></Cell>'
                    . '<Cell><Data ss:Type="String">' . htmlspecialchars((string)$row['check_out_time']) . '</Data></Cell>'
                    . '<Cell><Data ss:Type="String">' . htmlspecialchars((string)$horas) . '</Data></Cell>'
                    . '<Cell><Data ss:Type="String">' . htmlspecialchars((string)$est) . '</Data></Cell>'
                    . '<Cell><Data ss:Type="String">' . htmlspecialchars((string)$puntual) . '</Data></Cell>'
                    . '<Cell><Data ss:Type="Number">' . (int)$minutosTarde . '</Data></Cell>'
                    . '</Row>';
            }

            $xmlRowsSummary = [];
            foreach ($summary as $item) {
                $descuentoDias = intdiv((int)$item['dias_tarde'], 3);
                $sobrantes = (int)$item['dias_tarde'] % 3;
                $minPendientes = ($sobrantes > 0) ? (int)$item['minutos_tarde'] : 0;
                $xmlRowsSummary[] =
                    '<Row>'
                    . '<Cell><Data ss:Type="String">' . htmlspecialchars((string)$item['codigo']) . '</Data></Cell>'
                    . '<Cell><Data ss:Type="String">' . htmlspecialchars((string)$item['empleado']) . '</Data></Cell>'
                    . '<Cell><Data ss:Type="Number">' . (int)$item['dias_tarde'] . '</Data></Cell>'
                    . '<Cell><Data ss:Type="Number">' . (int)$item['minutos_tarde'] . '</Data></Cell>'
                    . '<Cell><Data ss:Type="Number">' . $descuentoDias . '</Data></Cell>'
                    . '<Cell><Data ss:Type="Number">' . $minPendientes . '</Data></Cell>'
                    . '</Row>';
            }

            echo '<?xml version="1.0" encoding="UTF-8"?>';
            echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" '
                . 'xmlns:o="urn:schemas-microsoft-com:office:office" '
                . 'xmlns:x="urn:schemas-microsoft-com:office:excel" '
                . 'xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
            echo '<Styles>'
                . '<Style ss:ID="header"><Font ss:Bold="1"/><Interior ss:Color="#D9E1F2" ss:Pattern="Solid"/></Style>'
                . '<Style ss:ID="lateCell"><Font ss:Color="#FF0000" ss:Bold="1"/></Style>'
                . '</Styles>';

            echo '<Worksheet ss:Name="Asistencia">';
            echo '<Table>';
            echo '<Row>'
                . '<Cell ss:StyleID="header"><Data ss:Type="String">Código</Data></Cell>'
                . '<Cell ss:StyleID="header"><Data ss:Type="String">Empleado</Data></Cell>'
                . '<Cell ss:StyleID="header"><Data ss:Type="String">Depto</Data></Cell>'
                . '<Cell ss:StyleID="header"><Data ss:Type="String">Fecha</Data></Cell>'
                . '<Cell ss:StyleID="header"><Data ss:Type="String">Entrada</Data></Cell>'
                . '<Cell ss:StyleID="header"><Data ss:Type="String">Salida Desayuno</Data></Cell>'
                . '<Cell ss:StyleID="header"><Data ss:Type="String">Retorno Desayuno</Data></Cell>'
                . '<Cell ss:StyleID="header"><Data ss:Type="String">Salida Almuerzo</Data></Cell>'
                . '<Cell ss:StyleID="header"><Data ss:Type="String">Retorno Almuerzo</Data></Cell>'
                . '<Cell ss:StyleID="header"><Data ss:Type="String">Salida</Data></Cell>'
                . '<Cell ss:StyleID="header"><Data ss:Type="String">Horas</Data></Cell>'
                . '<Cell ss:StyleID="header"><Data ss:Type="String">Estado</Data></Cell>'
                . '<Cell ss:StyleID="header"><Data ss:Type="String">Puntualidad</Data></Cell>'
                . '<Cell ss:StyleID="header"><Data ss:Type="String">Minutos Tarde</Data></Cell>'
                . '</Row>';
            echo implode('', $xmlRowsMain);
            echo '</Table>';
            echo '</Worksheet>';

            echo '<Worksheet ss:Name="Resumen Tardanzas">';
            echo '<Table>';
            echo '<Row>'
                . '<Cell ss:StyleID="header"><Data ss:Type="String">Código</Data></Cell>'
                . '<Cell ss:StyleID="header"><Data ss:Type="String">Empleado</Data></Cell>'
                . '<Cell ss:StyleID="header"><Data ss:Type="String">Días de tardanza</Data></Cell>'
                . '<Cell ss:StyleID="header"><Data ss:Type="String">Minutos acumulados</Data></Cell>'
                . '<Cell ss:StyleID="header"><Data ss:Type="String">Días de descuento</Data></Cell>'
                . '<Cell ss:StyleID="header"><Data ss:Type="String">Minutos (si no llega a 3)</Data></Cell>'
                . '</Row>';
            echo implode('', $xmlRowsSummary);
            echo '</Table>';
            echo '</Worksheet>';

            echo '</Workbook>';
            exit;
        }
    }
}
?>

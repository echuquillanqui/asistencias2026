<?php
require_once '../app/config/db.php';
require_once '../app/models/Attendance.php';
require_once '../app/models/Employee.php';
require_once '../app/models/Setting.php'; // 1. IMPORTAMOS EL MODELO DE AJUSTES
require_once '../app/services/SunafilReportService.php';
require_once '../app/services/SimpleXlsxWriter.php';

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

    public function index() {
        $employees = $this->employeeModel->read();
        $sites = $this->employeeModel->getSites();
        require_once '../app/views/reports/index.php';
    }

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
            if (!$this->validDate($start) || !$this->validDate($end) || $start > $end) {
                header('Location: ?c=Report&err=filtro_invalido');
                exit;
            }
            $filterType = $_POST['filter_type'] ?? 'all';
            $siteName = '';
            $employeeId = null;

            if ($filterType === 'site') {
                $requestedSite = trim($_POST['site_name'] ?? '');
                if ($requestedSite !== '' && in_array($requestedSite, $this->employeeModel->getSites(), true)) {
                    $siteName = $requestedSite;
                } else {
                    header('Location: ?c=Report&err=filtro_invalido');
                    exit;
                }
            } elseif ($filterType === 'employee') {
                $requestedEmployeeId = filter_var($_POST['employee_id'] ?? null, FILTER_VALIDATE_INT);
                if ($requestedEmployeeId && $this->employeeModel->getById($requestedEmployeeId)) {
                    $employeeId = (int)$requestedEmployeeId;
                } else {
                    header('Location: ?c=Report&err=filtro_invalido');
                    exit;
                }
            } elseif ($filterType !== 'all') {
                header('Location: ?c=Report&err=filtro_invalido');
                exit;
            }
            
            // OBTENER HORA DE LA BD TAMBIÉN PARA EL EXCEL
            $horaLimite = $this->getFirstScheduleTime($this->settingModel->get('entry_time'), '08:00:00');

            $data = $this->attendanceModel->getHistoryByDate($start, $end, $siteName, $employeeId);
            if (!$data) {
                header('Location: ?c=Report&err=sin_datos');
                exit;
            }
            
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
                    . '<Cell><Data ss:Type="String">' . htmlspecialchars((string)($row['site_name'] ?? '')) . '</Data></Cell>'
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
                . '<Cell ss:StyleID="header"><Data ss:Type="String">Sede</Data></Cell>'
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

    public function exportSunafil() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ?c=Report');
            exit;
        }
        $start = $_POST['start_date'] ?? '';
        $end = $_POST['end_date'] ?? '';
        if (!$this->validDate($start) || !$this->validDate($end) || $start > $end) {
            header('Location: ?c=Report&err=filtro_invalido');
            exit;
        }
        [$siteName, $employeeId] = $this->validatedScope($_POST);
        $source = $this->attendanceModel->getSunafilData($start, $end, $siteName, $employeeId);
        if (!$source['employees']) {
            header('Location: ?c=Report&err=sin_datos');
            exit;
        }
        $rows = (new SunafilReportService())->buildRows($source['employees'], $source['logs'], $start, $end);

        $schedules = array_values(array_unique(array_filter(array_column($source['employees'], 'schedule_name'))));
        $breaks = [];
        foreach ($source['employees'] as $employee) {
            if (!empty($employee['schedule_lunch_out_time']) && !empty($employee['schedule_lunch_return_time'])) {
                $out = strtotime($this->getFirstScheduleTime($employee['schedule_lunch_out_time'], ''));
                $return = strtotime($this->getFirstScheduleTime($employee['schedule_lunch_return_time'], ''));
                if ($out !== false && $return !== false && $return >= $out) $breaks[] = gmdate('H:i', $return - $out);
            }
        }
        $employeeSites = implode(', ', array_values(array_unique(array_filter(array_column($source['employees'], 'site_name')))));
        $siteLabel = $siteName ?: ((string)$this->settingModel->get('workplace_name') ?: $employeeSites);
        $logo = (string)$this->settingModel->get('employer_logo');
        $logoPath = $logo !== '' ? __DIR__ . '/../../public/' . ltrim($logo, '/') : '';
        $metadata = [
            'business_name' => (string)$this->settingModel->get('employer_business_name'),
            'trade_name' => (string)$this->settingModel->get('employer_trade_name'),
            'ruc' => (string)$this->settingModel->get('employer_ruc'),
            'site' => $siteLabel,
            'fiscal_address' => (string)$this->settingModel->get('employer_fiscal_address'),
            'address' => (string)$this->settingModel->get('workplace_address'),
            'period' => date('d/m/Y', strtotime($start)) . ' al ' . date('d/m/Y', strtotime($end)),
            'generated_at' => date('d/m/Y H:i'),
            'schedule' => implode(', ', $schedules),
            'break_time' => implode(', ', array_values(array_unique($breaks))),
            'logo_path' => is_file($logoPath) ? $logoPath : '',
        ];
        $temp = tempnam(sys_get_temp_dir(), 'sunafil_');
        (new SimpleXlsxWriter())->save($rows, $metadata, $temp);
        $filename = 'Reporte_SUNAFIL_' . $start . '_' . $end . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($temp));
        // Evita que avisos o salida accidental previa se antepongan al ZIP y
        // provoquen que Excel intente reparar partes válidas del libro.
        while (ob_get_level() > 0) ob_end_clean();
        readfile($temp);
        unlink($temp);
        exit;
    }

    private function validDate($value) {
        $date = DateTime::createFromFormat('!Y-m-d', (string)$value);
        return $date && $date->format('Y-m-d') === $value;
    }

    private function validatedScope(array $input) {
        $filterType = $input['filter_type'] ?? 'all';
        if ($filterType === 'site') {
            $site = trim($input['site_name'] ?? '');
            if ($site !== '' && in_array($site, $this->employeeModel->getSites(), true)) return [$site, null];
        } elseif ($filterType === 'employee') {
            $id = filter_var($input['employee_id'] ?? null, FILTER_VALIDATE_INT);
            if ($id && $this->employeeModel->getById($id)) return ['', (int)$id];
        } elseif ($filterType === 'all') {
            return ['', null];
        }
        header('Location: ?c=Report&err=filtro_invalido');
        exit;
    }
}
?>

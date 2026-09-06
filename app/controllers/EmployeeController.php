<?php
require_once '../app/config/db.php';
require_once '../app/models/Employee.php';

class EmployeeController {
    private $employeeModel;
    private $db;
    private $availableSites = ['HUANCAYO', 'LIMA', 'PASCO', 'SAN RAMON', 'HUANCAVELICA'];

    private static $scheduleBreakfastReturnChecked = false;

    private function ensurePortalDevicesTable() {
        $this->db->exec("CREATE TABLE IF NOT EXISTS employee_portal_devices (
            employee_id INT NOT NULL,
            device_token_hash CHAR(64) NOT NULL,
            active_session_hash CHAR(64) NULL,
            registered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_access_at DATETIME NULL,
            PRIMARY KEY (employee_id),
            CONSTRAINT fk_portal_device_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    }

    private function ensureScheduleBreakfastReturnColumn() {
        if (self::$scheduleBreakfastReturnChecked) {
            return;
        }
        $this->db->exec("ALTER TABLE schedules ADD COLUMN IF NOT EXISTS breakfast_return_time VARCHAR(255) NULL AFTER breakfast_time");
        $this->db->exec("ALTER TABLE schedules MODIFY breakfast_time VARCHAR(255) NULL");
        self::$scheduleBreakfastReturnChecked = true;
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
        $this->employeeModel = new Employee($this->db);
    }

    public function reset_portal_device() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: ?c=Employee");
            exit;
        }

        $submittedToken = $_POST['csrf_token'] ?? '';
        if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $submittedToken)) {
            header("Location: ?c=Employee&err=sesion_invalida");
            exit;
        }

        $employeeId = filter_input(INPUT_POST, 'employee_id', FILTER_VALIDATE_INT);
        if (!$employeeId) {
            header("Location: ?c=Employee&err=empleado_invalido");
            exit;
        }

        $this->ensurePortalDevicesTable();
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("DELETE FROM employee_portal_devices WHERE employee_id = :employee_id");
            $stmt->execute([':employee_id' => $employeeId]);
            $tokensTable = $this->db->query("SHOW TABLES LIKE 'attendance_qr_tokens'");
            if ($tokensTable->fetchColumn()) {
                $tokens = $this->db->prepare("UPDATE attendance_qr_tokens SET used_at = NOW()
                                              WHERE employee_id = :employee_id AND used_at IS NULL");
                $tokens->execute([':employee_id' => $employeeId]);
            }
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
        header("Location: ?c=Employee&msg=dispositivo_restablecido");
        exit;
    }

    private function normalizeScheduleId($scheduleId) {
        if (empty($scheduleId)) {
            return null;
        }

        $stmt = $this->db->prepare("SELECT id FROM schedules WHERE id = :id LIMIT 1");
        $stmt->bindParam(':id', $scheduleId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC) ? (int)$scheduleId : null;
    }

    public function index() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        $csrfToken = $_SESSION['csrf_token'];
        $search = isset($_GET['q']) ? $_GET['q'] : "";
        $employees = $this->employeeModel->read($search);
        
        $stmt = $this->db->query("SELECT * FROM departments WHERE status = 'activo'");
        $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $schedules = $this->employeeModel->getSchedules();
        $availableSites = $this->availableSites;
        require_once '../app/views/employees/index.php';
    }

    public function store() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $initialPassword = $_POST['initial_password'] ?? '';
            if (strlen($initialPassword) < 8) {
                header("Location: ?c=Employee&err=clave_insegura");
                exit;
            }

            $siteName = strtoupper(trim($_POST['site_name'] ?? ''));
            if (!in_array($siteName, $this->availableSites, true)) {
                header("Location: ?c=Employee&err=sede_invalida");
                exit;
            }

            $data = [
                'employee_code' => $_POST['employee_code'],
                'first_name' => $_POST['first_name'],
                'last_name' => $_POST['last_name'],
                'email' => $_POST['email'],
                'department_id' => $_POST['department_id'],
                'position' => $_POST['position'],
                'site_name' => $siteName,
                'schedule_id' => $this->normalizeScheduleId($_POST['schedule_id'] ?? null),
                'initial_password' => $initialPassword
            ];
            
            if ($this->employeeModel->create($data)) {
                header("Location: ?c=Employee&msg=guardado");
            } else {
                header("Location: ?c=Employee&err=error");
            }
        }
    }

    public function edit() {
        if (isset($_GET['id'])) {
            $emp = $this->employeeModel->getById($_GET['id']);
            $stmt = $this->db->query("SELECT * FROM departments WHERE status = 'activo'");
            $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $schedules = $this->employeeModel->getSchedules();

            if ($emp) {
                $availableSites = $this->availableSites;
                require_once '../app/views/employees/edit.php';
            } else {
                header("Location: ?c=Employee");
            }
        }
    }

    public function update_data() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $siteName = strtoupper(trim($_POST['site_name'] ?? ''));
            if (!in_array($siteName, $this->availableSites, true)) {
                header("Location: ?c=Employee&err=sede_invalida");
                exit;
            }

            $data = [
                'id' => $_POST['id'],
                'employee_code' => $_POST['employee_code'],
                'first_name' => $_POST['first_name'],
                'last_name' => $_POST['last_name'],
                'email' => $_POST['email'],
                'department_id' => $_POST['department_id'],
                'position' => $_POST['position'],
                'site_name' => $siteName,
                'schedule_id' => $this->normalizeScheduleId($_POST['schedule_id'] ?? null)
            ];
            $this->employeeModel->update($data);
            header("Location: ?c=Employee&msg=actualizado");
        }
    }

    public function create_schedule() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: ?c=Employee");
            exit;
        }

        $this->ensureScheduleBreakfastReturnColumn();

        $name = strtoupper(trim($_POST['schedule_name'] ?? ''));
        if ($name === '') {
            header("Location: ?c=Employee&err=horario_invalido");
            exit;
        }

        $data = [
            'name' => $name,
            'entry_time' => strtoupper(trim($_POST['entry_time'] ?? '08:00')),
            'breakfast_time' => strtoupper(trim($_POST['breakfast_time'] ?? '')),
            'breakfast_return_time' => strtoupper(trim($_POST['breakfast_return_time'] ?? '')),
            'lunch_out_time' => strtoupper(trim($_POST['lunch_out_time'] ?? '13:00')),
            'lunch_return_time' => strtoupper(trim($_POST['lunch_return_time'] ?? '14:00')),
            'check_out_time' => strtoupper(trim($_POST['check_out_time'] ?? '18:00')),
        ];

        if ($this->employeeModel->createSchedule($data)) {
            header("Location: ?c=Employee&msg=horario_creado");
        } else {
            header("Location: ?c=Employee&err=horario_duplicado");
        }
    }

    public function assign_schedule() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: ?c=Employee");
            exit;
        }

        $scheduleId = $this->normalizeScheduleId($_POST['schedule_id'] ?? null);
        if (!$scheduleId) {
            header("Location: ?c=Employee&err=horario_invalido");
            exit;
        }

        $mode = $_POST['assign_mode'] ?? 'individual';
        $ok = false;

        if ($mode === 'department') {
            $departmentId = (int)($_POST['department_id'] ?? 0);
            if ($departmentId > 0) {
                $ok = $this->employeeModel->assignScheduleByDepartment($scheduleId, $departmentId);
            }
        } elseif ($mode === 'site') {
            $siteName = strtoupper(trim($_POST['site_name'] ?? ''));
            if (in_array($siteName, $this->availableSites, true)) {
                $ok = $this->employeeModel->assignScheduleBySite($scheduleId, $siteName);
            }
        } else {
            $employeeIds = array_map('intval', $_POST['employee_ids'] ?? []);
            $employeeIds = array_values(array_filter($employeeIds));
            if (!empty($employeeIds)) {
                $ok = $this->employeeModel->assignScheduleToEmployees($scheduleId, $employeeIds);
            }
        }

        if ($ok) {
            header("Location: ?c=Employee&msg=horario_asignado");
        } else {
            header("Location: ?c=Employee&err=asignacion_invalida");
        }
    }

    public function toggle() {
        if (isset($_GET['id']) && isset($_GET['status'])) {
            $id = $_GET['id'];
            $currentStatus = $_GET['status'];
            $newStatus = ($currentStatus == 'activo') ? 'inactivo' : 'activo';
            
            $this->employeeModel->toggleStatus($id, $newStatus);
            header("Location: ?c=Employee&msg=estado_cambiado");
        }
    }

    public function delete() {
        if(isset($_GET['id'])) {
            $this->employeeModel->delete($_GET['id']);
            header("Location: ?c=Employee&msg=eliminado");
        }
    }
}
?>

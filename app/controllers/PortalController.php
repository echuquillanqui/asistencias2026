<?php
require_once '../app/config/db.php';
require_once '../app/models/Employee.php';
require_once '../app/models/Attendance.php';

class PortalController {
    private $db;
    private $employeeModel;
    private $attendanceModel;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->employeeModel = new Employee($this->db);
        $this->attendanceModel = new Attendance($this->db);
        
        if (session_status() == PHP_SESSION_NONE) session_start();
    }

    // 1. MOSTRAR LOGIN (Redirige al Login Unificado)
    public function login() {
        // Si ya está logueado, mandar al dashboard
        if (isset($_SESSION['portal_role']) && $_SESSION['portal_role'] == 'empleado') {
            header("Location: ?c=Portal&a=index");
            exit;
        }
        // CORRECCIÓN: Si intentan entrar aquí, los mandamos al Login Principal
        header("Location: ?c=Auth&a=login");
    }

    // 2. PROCESAR LOGIN (Viene desde el Login Unificado)
    public function authenticate() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = $_POST['email'];
            $password = $_POST['password'];

            $employee = $this->employeeModel->login($email, $password);

            if ($employee) {
                session_regenerate_id(true);
                $_SESSION['portal_id'] = $employee['id'];
                $_SESSION['portal_name'] = $employee['first_name'];
                $_SESSION['portal_code'] = $employee['employee_code'];
                $_SESSION['portal_role'] = 'empleado';

                header("Location: ?c=Portal&a=index");
            } else {
                // Si falla, regresamos al Login Principal con un error
                header("Location: ?c=Auth&a=login&error=Credenciales%20incorrectas");
            }
        }
    }

    // 3. DASHBOARD DEL EMPLEADO
    public function index() {
        // Seguridad: Verificar si es empleado
        if (!isset($_SESSION['portal_role']) || $_SESSION['portal_role'] != 'empleado') {
            // Si no tiene permiso, al login principal
            header("Location: ?c=Auth&a=login");
            exit;
        }

        $empName = $_SESSION['portal_name'];
        $empCode = $_SESSION['portal_code'];

        require_once '../app/views/portal/dashboard.php';
    }

    // Genera una credencial QR de corta duración y de un solo uso.
    public function qr() {
        if (!isset($_SESSION['portal_role']) || $_SESSION['portal_role'] !== 'empleado') {
            http_response_code(401);
            exit;
        }

        $this->ensureQrTokensTable();

        // No conservar tokens caducados indefinidamente.
        $this->db->exec("DELETE FROM attendance_qr_tokens WHERE expires_at < DATE_SUB(NOW(), INTERVAL 1 DAY)");

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $employeeId = (int)$_SESSION['portal_id'];
        $expiresAt = date('Y-m-d H:i:s', time() + 45);

        $query = "INSERT INTO attendance_qr_tokens (token_hash, employee_id, expires_at)
                  VALUES (:token_hash, :employee_id, :expires_at)";
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            ':token_hash' => $tokenHash,
            ':employee_id' => $employeeId,
            ':expires_at' => $expiresAt,
        ]);

        require_once '../app/libs/phpqrcode/qrlib.php';
        header('Content-Type: image/png');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        QRcode::png('ATT:' . $token, false, QR_ECLEVEL_M, 7, 2);
        exit;
    }

    private function ensureQrTokensTable() {
        $this->db->exec("CREATE TABLE IF NOT EXISTS attendance_qr_tokens (
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

    // 4. CAMBIAR CONTRASEÑA
    public function change_password() {
        if (!isset($_SESSION['portal_role'])) { header("Location: ?c=Auth&a=login"); exit; }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $currentPass = $_POST['current_password'];
            $newPass = $_POST['new_password'];
            $confirmPass = $_POST['confirm_password'];
            $empId = $_SESSION['portal_id'];

            $employee = $this->employeeModel->getById($empId);

            if (!password_verify($currentPass, $employee['password'])) {
                header("Location: ?c=Portal&a=index&err=pass_incorrecta");
                exit;
            }

            if ($newPass !== $confirmPass) {
                header("Location: ?c=Portal&a=index&err=no_coinciden");
                exit;
            }

            $newHash = password_hash($newPass, PASSWORD_DEFAULT);
            $this->employeeModel->updatePassword($empId, $newHash);

            header("Location: ?c=Portal&a=index&msg=pass_actualizada");
        }
    }

    // 5. CERRAR SESIÓN (CORREGIDO)
    public function logout() {
        // Limpiamos solo las variables del portal
        unset($_SESSION['portal_id']);
        unset($_SESSION['portal_name']);
        unset($_SESSION['portal_code']);
        unset($_SESSION['portal_role']);
        session_regenerate_id(true);
        
        // REDIRECCIÓN AL LOGIN PRINCIPAL
        header("Location: ?c=Auth&a=login");
    }
}
?>

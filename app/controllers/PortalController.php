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

    private function setDeviceCookie($token) {
        setcookie('portal_device', $token, [
            'expires' => time() + (86400 * 365),
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        $_COOKIE['portal_device'] = $token;
    }

    private function authorizeDevice($employeeId) {
        $this->ensurePortalDevicesTable();
        $stmt = $this->db->prepare("SELECT device_token_hash FROM employee_portal_devices WHERE employee_id = :employee_id LIMIT 1");
        $stmt->execute([':employee_id' => $employeeId]);
        $registeredHash = $stmt->fetchColumn();
        $deviceToken = $_COOKIE['portal_device'] ?? '';

        if ($registeredHash) {
            if ($deviceToken === '' || !hash_equals($registeredHash, hash('sha256', $deviceToken))) {
                return false;
            }
        } else {
            $deviceToken = bin2hex(random_bytes(32));
            $insert = $this->db->prepare("INSERT INTO employee_portal_devices (employee_id, device_token_hash)
                                          VALUES (:employee_id, :device_token_hash)");
            $insert->execute([
                ':employee_id' => $employeeId,
                ':device_token_hash' => hash('sha256', $deviceToken),
            ]);
            $this->setDeviceCookie($deviceToken);
        }

        return true;
    }

    private function sessionIsAuthorized() {
        if (!isset($_SESSION['portal_id'], $_SESSION['portal_session_key'])) {
            return false;
        }

        $deviceToken = $_COOKIE['portal_device'] ?? '';
        if ($deviceToken === '') {
            return false;
        }

        $this->ensurePortalDevicesTable();
        $stmt = $this->db->prepare("SELECT device_token_hash, active_session_hash
                                    FROM employee_portal_devices WHERE employee_id = :employee_id LIMIT 1");
        $stmt->execute([':employee_id' => (int)$_SESSION['portal_id']]);
        $device = $stmt->fetch(PDO::FETCH_ASSOC);

        return $device
            && hash_equals($device['device_token_hash'], hash('sha256', $deviceToken))
            && !empty($device['active_session_hash'])
            && hash_equals($device['active_session_hash'], hash('sha256', $_SESSION['portal_session_key']));
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
                if (!$this->authorizeDevice((int)$employee['id'])) {
                    header("Location: ?c=Auth&a=login&error=" . urlencode('Este usuario está vinculado a otro dispositivo. Solicita a RR. HH. que restablezca el acceso.'));
                    exit;
                }

                session_regenerate_id(true);
                $sessionKey = bin2hex(random_bytes(32));
                $_SESSION['portal_id'] = $employee['id'];
                $_SESSION['portal_name'] = $employee['first_name'];
                $_SESSION['portal_code'] = $employee['employee_code'];
                $_SESSION['portal_role'] = 'empleado';
                $_SESSION['portal_session_key'] = $sessionKey;

                $stmt = $this->db->prepare("UPDATE employee_portal_devices
                                            SET active_session_hash = :session_hash, last_access_at = NOW()
                                            WHERE employee_id = :employee_id");
                $stmt->execute([
                    ':session_hash' => hash('sha256', $sessionKey),
                    ':employee_id' => (int)$employee['id'],
                ]);

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
        if (!isset($_SESSION['portal_role']) || $_SESSION['portal_role'] != 'empleado' || !$this->sessionIsAuthorized()) {
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
        if (!isset($_SESSION['portal_role']) || $_SESSION['portal_role'] !== 'empleado' || !$this->sessionIsAuthorized()) {
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
        if (isset($_SESSION['portal_id'], $_SESSION['portal_session_key'])) {
            $this->ensurePortalDevicesTable();
            $this->ensureQrTokensTable();
            $stmt = $this->db->prepare("UPDATE employee_portal_devices SET active_session_hash = NULL
                                        WHERE employee_id = :employee_id AND active_session_hash = :session_hash");
            $stmt->execute([
                ':employee_id' => (int)$_SESSION['portal_id'],
                ':session_hash' => hash('sha256', $_SESSION['portal_session_key']),
            ]);
            $tokens = $this->db->prepare("UPDATE attendance_qr_tokens SET used_at = NOW()
                                          WHERE employee_id = :employee_id AND used_at IS NULL");
            $tokens->execute([':employee_id' => (int)$_SESSION['portal_id']]);
        }
        // Limpiamos solo las variables del portal
        unset($_SESSION['portal_id']);
        unset($_SESSION['portal_name']);
        unset($_SESSION['portal_code']);
        unset($_SESSION['portal_role']);
        unset($_SESSION['portal_session_key']);
        session_regenerate_id(true);
        
        // REDIRECCIÓN AL LOGIN PRINCIPAL
        header("Location: ?c=Auth&a=login");
    }
}
?>

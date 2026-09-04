<?php
require_once '../app/config/db.php';
require_once '../app/models/Schedule.php';

class ScheduleController {
    private $db;
    private $scheduleModel;

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
        $this->ensureOptionalBreakfastColumns();
        $this->scheduleModel = new Schedule($this->db);
    }

    private function ensureOptionalBreakfastColumns() {
        $returnColumn = $this->db->query("SHOW COLUMNS FROM schedules LIKE 'breakfast_return_time'");
        if (!$returnColumn->fetch(PDO::FETCH_ASSOC)) {
            $this->db->exec("ALTER TABLE schedules ADD COLUMN breakfast_return_time VARCHAR(255) NULL AFTER breakfast_time");
        }

        $breakfastColumn = $this->db->query("SHOW COLUMNS FROM schedules LIKE 'breakfast_time'")->fetch(PDO::FETCH_ASSOC);
        if ($breakfastColumn && strtoupper((string)$breakfastColumn['Null']) !== 'YES') {
            $this->db->exec("ALTER TABLE schedules MODIFY breakfast_time VARCHAR(255) NULL");
        }
    }

    public function index() {
        $schedules = $this->scheduleModel->read();
        require_once '../app/views/schedules/index.php';
    }

    public function store() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: ?c=Schedule");
            exit;
        }

        $data = [
            'name' => strtoupper(trim($_POST['name'] ?? '')),
            'entry_time' => trim($_POST['entry_time'] ?? ''),
            'breakfast_time' => trim($_POST['breakfast_time'] ?? ''),
            'breakfast_return_time' => trim($_POST['breakfast_return_time'] ?? ''),
            'lunch_out_time' => trim($_POST['lunch_out_time'] ?? ''),
            'lunch_return_time' => trim($_POST['lunch_return_time'] ?? ''),
            'check_out_time' => trim($_POST['check_out_time'] ?? ''),
        ];

        if ($data['name'] === '' || $data['entry_time'] === '' || $data['lunch_out_time'] === '' || $data['lunch_return_time'] === '' || $data['check_out_time'] === '') {
            header("Location: ?c=Schedule&err=datos_incompletos");
            exit;
        }

        try {
            $this->scheduleModel->create($data);
            header("Location: ?c=Schedule&msg=creado");
        } catch (PDOException $e) {
            if ((string)$e->getCode() === '23000') {
                header("Location: ?c=Schedule&err=duplicado");
            } else {
                header("Location: ?c=Schedule&err=error");
            }
        }
        exit;
    }
}
?>

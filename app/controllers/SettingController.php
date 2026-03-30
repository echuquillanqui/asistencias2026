<?php
require_once '../app/config/db.php';
require_once '../app/models/Setting.php';

class SettingController {
    private $settingModel;

    public function __construct() {
        if (session_status() == PHP_SESSION_NONE) session_start();
        // Solo Admin
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
            header("Location: ?c=Dashboard");
            exit;
        }

        $database = new Database();
        $this->settingModel = new Setting($database->getConnection());
    }

    public function index() {
        $entry_time = $this->settingModel->get('entry_time');
        $breakfast_time = $this->settingModel->get('breakfast_time');
        $lunch_out_time = $this->settingModel->get('lunch_out_time');
        $lunch_return_time = $this->settingModel->get('lunch_return_time');
        $check_out_time = $this->settingModel->get('check_out_time');
        require_once '../app/views/settings/index.php';
    }

    public function update() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $entryTimes = strtoupper(trim($_POST['entry_time']));
            $breakfastTimes = strtoupper(trim($_POST['breakfast_time']));
            $lunchOutTimes = strtoupper(trim($_POST['lunch_out_time']));
            $lunchReturnTimes = strtoupper(trim($_POST['lunch_return_time']));
            $checkOutTimes = strtoupper(trim($_POST['check_out_time']));

            $this->settingModel->set('entry_time', $entryTimes);
            $this->settingModel->set('breakfast_time', $breakfastTimes);
            $this->settingModel->set('lunch_out_time', $lunchOutTimes);
            $this->settingModel->set('lunch_return_time', $lunchReturnTimes);
            $this->settingModel->set('check_out_time', $checkOutTimes);

            header("Location: ?c=Setting&msg=guardado");
        }
    }
}
?>

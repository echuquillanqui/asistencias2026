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
        $employer_business_name = $this->settingModel->get('employer_business_name');
        $employer_trade_name = $this->settingModel->get('employer_trade_name');
        $employer_ruc = $this->settingModel->get('employer_ruc');
        $employer_fiscal_address = $this->settingModel->get('employer_fiscal_address');
        $workplace_name = $this->settingModel->get('workplace_name');
        $workplace_address = $this->settingModel->get('workplace_address');
        $employer_logo = $this->settingModel->get('employer_logo');
        require_once '../app/views/settings/index.php';
    }

    public function update() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $ruc = preg_replace('/\D+/', '', trim($_POST['employer_ruc'] ?? ''));
            if (strlen($ruc) !== 11) {
                header('Location: ?c=Setting&err=' . urlencode('El RUC debe contener exactamente 11 dígitos.'));
                exit;
            }
            $entryTimes = strtoupper(trim($_POST['entry_time'] ?? ''));
            $breakfastTimes = strtoupper(trim($_POST['breakfast_time'] ?? ''));
            $lunchOutTimes = strtoupper(trim($_POST['lunch_out_time'] ?? ''));
            $lunchReturnTimes = strtoupper(trim($_POST['lunch_return_time'] ?? ''));
            $checkOutTimes = strtoupper(trim($_POST['check_out_time'] ?? ''));

            $this->settingModel->set('entry_time', $entryTimes);
            $this->settingModel->set('breakfast_time', $breakfastTimes);
            $this->settingModel->set('lunch_out_time', $lunchOutTimes);
            $this->settingModel->set('lunch_return_time', $lunchReturnTimes);
            $this->settingModel->set('check_out_time', $checkOutTimes);

            $companyFields = [
                'employer_business_name' => 200,
                'employer_trade_name' => 200,
                'employer_ruc' => 11,
                'employer_fiscal_address' => 255,
                'workplace_name' => 150,
                'workplace_address' => 255,
            ];
            foreach ($companyFields as $key => $maxLength) {
                $value = trim($_POST[$key] ?? '');
                if ($key === 'employer_ruc') $value = $ruc;
                $this->settingModel->set($key, mb_substr($value, 0, $maxLength));
            }

            $logoError = $this->saveLogo($_FILES['employer_logo'] ?? null);
            if ($logoError !== null) {
                header('Location: ?c=Setting&err=' . urlencode($logoError));
                exit;
            }

            header("Location: ?c=Setting&msg=guardado");
            exit;
        }
    }

    private function saveLogo($upload) {
        if (!$upload || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
        if (($upload['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) return 'No se pudo subir el logo.';
        if (($upload['size'] ?? 0) > 2 * 1024 * 1024) return 'El logo no puede superar 2 MB.';

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
        $extensions = ['image/png' => 'png', 'image/jpeg' => 'jpg'];
        if (!isset($extensions[$mime]) || @getimagesize($upload['tmp_name']) === false) {
            return 'El logo debe ser una imagen PNG o JPG válida.';
        }

        $directory = __DIR__ . '/../../public/uploads/company';
        if (!is_dir($directory) && !mkdir($directory, 0755, true)) return 'No se pudo preparar la carpeta del logo.';
        $filename = 'logo_' . bin2hex(random_bytes(8)) . '.' . $extensions[$mime];
        if (!move_uploaded_file($upload['tmp_name'], $directory . '/' . $filename)) return 'No se pudo guardar el logo.';

        $oldLogo = (string)$this->settingModel->get('employer_logo');
        $relativePath = 'uploads/company/' . $filename;
        $this->settingModel->set('employer_logo', $relativePath);
        if ($oldLogo !== '' && strpos($oldLogo, 'uploads/company/') === 0) {
            $oldPath = __DIR__ . '/../../public/' . $oldLogo;
            if (is_file($oldPath)) @unlink($oldPath);
        }
        return null;
    }
}
?>

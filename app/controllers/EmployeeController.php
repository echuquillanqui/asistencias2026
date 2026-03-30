<?php
require_once '../app/config/db.php';
require_once '../app/models/Employee.php';

class EmployeeController {
    private $employeeModel;
    private $db;

    public function __construct() {
        if (session_status() == PHP_SESSION_NONE) session_start();
        
        // 1. Verificar Login
        if (!isset($_SESSION['user_id'])) {
            header("Location: ?c=Auth&a=login");
            exit;
        }
        
        // 2. Bloqueo de Rol (Solo Admin)
        if ($_SESSION['role'] != 'admin') {
            header("Location: ?c=Dashboard");
            exit;
        }

        $database = new Database();
        $this->db = $database->getConnection();
        $this->employeeModel = new Employee($this->db);
    }

    // 1. LISTAR EMPLEADOS (Y CARGAR DATOS PARA EL MODAL)
    public function index() {
        // Capturar búsqueda
        $search = isset($_GET['q']) ? $_GET['q'] : "";
        
        // A. Obtener lista de empleados
        $employees = $this->employeeModel->read($search);
        
        // B. Obtener departamentos (Necesario para el Modal de Crear)
        $stmt = $this->db->query("SELECT * FROM departments WHERE status = 'activo'");
        $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        require_once '../app/views/employees/index.php';
    }

    // 2. GUARDAR NUEVO EMPLEADO (Desde el Modal)
    public function store() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'employee_code' => $_POST['employee_code'],
                'first_name' => $_POST['first_name'],
                'last_name' => $_POST['last_name'],
                'email' => $_POST['email'],
                'department_id' => $_POST['department_id'],
                'position' => $_POST['position']
            ];
            
            // El modelo se encarga de asignar la contraseña por defecto '123456'
            if ($this->employeeModel->create($data)) {
                header("Location: ?c=Employee&msg=guardado");
            } else {
                // Manejo básico de error (opcional)
                header("Location: ?c=Employee&err=error");
            }
        }
    }

    // 3. MOSTRAR FORMULARIO DE EDICIÓN (Página aparte)
    public function edit() {
        if (isset($_GET['id'])) {
            $emp = $this->employeeModel->getById($_GET['id']);
            
            // También necesitamos departamentos aquí para el select
            $stmt = $this->db->query("SELECT * FROM departments WHERE status = 'activo'");
            $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($emp) {
                require_once '../app/views/employees/edit.php';
            } else {
                header("Location: ?c=Employee");
            }
        }
    }

    // 4. GUARDAR CAMBIOS DE EDICIÓN
    public function update_data() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'id' => $_POST['id'],
                'employee_code' => $_POST['employee_code'],
                'first_name' => $_POST['first_name'],
                'last_name' => $_POST['last_name'],
                'email' => $_POST['email'],
                'department_id' => $_POST['department_id'],
                'position' => $_POST['position']
            ];
            $this->employeeModel->update($data);
            header("Location: ?c=Employee&msg=actualizado");
        }
    }

    // 5. ACTIVAR / DESACTIVAR
    public function toggle() {
        if (isset($_GET['id']) && isset($_GET['status'])) {
            $id = $_GET['id'];
            $currentStatus = $_GET['status'];
            
            // Invertir estado
            $newStatus = ($currentStatus == 'activo') ? 'inactivo' : 'activo';
            
            $this->employeeModel->toggleStatus($id, $newStatus);
            header("Location: ?c=Employee&msg=estado_cambiado");
        }
    }

    // 6. ELIMINAR (Opcional, ya que usamos desactivar)
    public function delete() {
        if(isset($_GET['id'])) {
            $this->employeeModel->delete($_GET['id']);
            header("Location: ?c=Employee&msg=eliminado");
        }
    }
}
?>
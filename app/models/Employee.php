<?php
class Employee {
    private $conn;
    private $table = "employees";

    public function __construct($db) {
        $this->conn = $db;
    }

    // 1. LEER TODOS (Para Admin)
    public function read($search = "") {
        $query = "SELECT e.*, d.name as department_name, s.name as schedule_name
                  FROM " . $this->table . " e
                  LEFT JOIN departments d ON e.department_id = d.id
                  LEFT JOIN schedules s ON e.schedule_id = s.id";
        
        if (!empty($search)) {
            $query .= " WHERE e.first_name LIKE :search 
                        OR e.last_name LIKE :search 
                        OR e.employee_code LIKE :search 
                        OR e.position LIKE :search
                        OR s.name LIKE :search";
        }
        $query .= " ORDER BY e.id DESC";
        $stmt = $this->conn->prepare($query);
        if (!empty($search)) {
            $searchTerm = "%" . $search . "%";
            $stmt->bindParam(':search', $searchTerm);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // 2. OBTENER UNO
    public function getById($id) {
        $query = "SELECT * FROM " . $this->table . " WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // 3. CREAR (Con contraseña por defecto '123456')
    public function create($data) {
        $defaultPass = password_hash("123456", PASSWORD_DEFAULT);
        $query = "INSERT INTO " . $this->table . " 
                 (employee_code, first_name, last_name, email, password, department_id, position, site_name, schedule_id, status) 
                 VALUES (:code, :fname, :lname, :email, :pass, :dept, :pos, :site, :schedule_id, 'activo')";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':code', $data['employee_code']);
        $stmt->bindParam(':fname', $data['first_name']);
        $stmt->bindParam(':lname', $data['last_name']);
        $stmt->bindParam(':email', $data['email']);
        $stmt->bindParam(':pass', $defaultPass);
        $stmt->bindParam(':dept', $data['department_id']);
        $stmt->bindParam(':pos', $data['position']);
        $stmt->bindParam(':site', $data['site_name']);
        $stmt->bindParam(':schedule_id', $data['schedule_id']);
        return $stmt->execute();
    }

    // 4. ACTUALIZAR DATOS
    public function update($data) {
        $query = "UPDATE " . $this->table . " 
                  SET first_name = :fname, last_name = :lname, 
                      email = :email, department_id = :dept, position = :pos, employee_code = :code, site_name = :site, schedule_id = :schedule_id
                  WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':fname', $data['first_name']);
        $stmt->bindParam(':lname', $data['last_name']);
        $stmt->bindParam(':email', $data['email']);
        $stmt->bindParam(':dept', $data['department_id']);
        $stmt->bindParam(':pos', $data['position']);
        $stmt->bindParam(':code', $data['employee_code']);
        $stmt->bindParam(':site', $data['site_name']);
        $stmt->bindParam(':schedule_id', $data['schedule_id']);
        $stmt->bindParam(':id', $data['id']);
        return $stmt->execute();
    }

    // 5. CAMBIAR ESTADO
    public function toggleStatus($id, $newStatus) {
        $query = "UPDATE " . $this->table . " SET status = :status WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':status', $newStatus);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    // 6. ELIMINAR
    public function delete($id) {
        $query = "DELETE FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    // 7. LOGIN EMPLEADO
    public function login($email, $password) {
        $query = "SELECT * FROM " . $this->table . " WHERE email = :email AND status = 'activo' LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Verificar si tiene password y si coincide
            if (!empty($row['password']) && password_verify($password, $row['password'])) {
                return $row;
            }
        }
        return false;
    }

    // 8. ACTUALIZAR SOLO CONTRASEÑA
    public function updatePassword($id, $newHash) {
        $query = "UPDATE " . $this->table . " SET password = :pass WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':pass', $newHash);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    // 9. HORARIOS DISPONIBLES
    public function getSchedules() {
        $stmt = $this->conn->query("SELECT * FROM schedules ORDER BY name ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // 10. CREAR HORARIO
    public function createSchedule($data) {
        $query = "INSERT INTO schedules (name, entry_time, breakfast_time, lunch_out_time, lunch_return_time, check_out_time)
                  VALUES (:name, :entry, :breakfast, :lunch_out, :lunch_return, :check_out)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':name', $data['name']);
        $stmt->bindParam(':entry', $data['entry_time']);
        $stmt->bindParam(':breakfast', $data['breakfast_time']);
        $stmt->bindParam(':lunch_out', $data['lunch_out_time']);
        $stmt->bindParam(':lunch_return', $data['lunch_return_time']);
        $stmt->bindParam(':check_out', $data['check_out_time']);
        return $stmt->execute();
    }

    // 11. ASIGNAR HORARIO A MÚLTIPLES EMPLEADOS
    public function assignScheduleToEmployees($scheduleId, $employeeIds) {
        if (empty($employeeIds)) {
            return false;
        }

        $placeholders = implode(',', array_fill(0, count($employeeIds), '?'));
        $query = "UPDATE " . $this->table . " SET schedule_id = ? WHERE id IN ($placeholders)";
        $stmt = $this->conn->prepare($query);

        $params = array_merge([$scheduleId], $employeeIds);
        return $stmt->execute($params);
    }

    // 12. ASIGNAR HORARIO POR DEPARTAMENTO
    public function assignScheduleByDepartment($scheduleId, $departmentId) {
        $query = "UPDATE " . $this->table . " SET schedule_id = :schedule_id WHERE department_id = :department_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':schedule_id', $scheduleId);
        $stmt->bindParam(':department_id', $departmentId);
        return $stmt->execute();
    }

    // 13. ASIGNAR HORARIO POR SEDE
    public function assignScheduleBySite($scheduleId, $siteName) {
        $query = "UPDATE " . $this->table . " SET schedule_id = :schedule_id WHERE site_name = :site_name";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':schedule_id', $scheduleId);
        $stmt->bindParam(':site_name', $siteName);
        return $stmt->execute();
    }
}
?>

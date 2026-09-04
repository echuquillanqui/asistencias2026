<?php
class Schedule {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function read() {
        $query = "SELECT s.*,
                         (SELECT COUNT(*) FROM employees e WHERE e.schedule_id = s.id) AS employee_count
                  FROM schedules s
                  ORDER BY s.name ASC";
        $stmt = $this->conn->query($query);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create($data) {
        $query = "INSERT INTO schedules
                    (name, entry_time, breakfast_time, breakfast_return_time, lunch_out_time, lunch_return_time, check_out_time)
                  VALUES
                    (:name, :entry_time, :breakfast_time, :breakfast_return_time, :lunch_out_time, :lunch_return_time, :check_out_time)";
        $stmt = $this->conn->prepare($query);

        foreach ($data as $field => $value) {
            $stmt->bindValue(':' . $field, $value === '' ? null : $value, $value === '' ? PDO::PARAM_NULL : PDO::PARAM_STR);
        }

        return $stmt->execute();
    }
}
?>

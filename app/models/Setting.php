<?php
class Setting {
    private $conn;
    private $table = "settings";

    public function __construct($db) {
        $this->conn = $db;
    }

    // Obtener un valor por su nombre (Ej: 'entry_time')
    public function get($name) {
        $query = "SELECT setting_value FROM " . $this->table . " WHERE setting_name = :name LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':name', $name);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['setting_value'] : null;
    }

    // Guardar un valor
    public function set($name, $value) {
        $query = "INSERT INTO " . $this->table . " (setting_name, setting_value)
                  VALUES (:name, :val)
                  ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':val', $value);
        $stmt->bindParam(':name', $name);
        return $stmt->execute();
    }
}
?>

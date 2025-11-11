<?php
// sci/src/Core/Models/Department.php
namespace SCI\Chat\Core\Models;

use mysqli;

class Department {
    private $conn;

    public function __construct(mysqli $db) {
        $this->conn = $db;
    }

    public function getDepartments(bool $getAll = false): array {
        $sql = "SELECT id, name, description, is_public, created_at FROM departments";
        if (!$getAll) {
            $sql .= " WHERE is_public = 1";
        }
        $sql .= " ORDER BY name ASC";
        $result = $this->conn->query($sql);
        if ($result) {
            return $result->fetch_all(MYSQLI_ASSOC);
        } else {
            error_log("Error en la consulta de departamentos (Modelo): " . $this->conn->error);
            return [];
        }
    }
}
?>
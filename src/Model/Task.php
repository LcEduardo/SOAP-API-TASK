<?php

namespace App\Model;

use Exception;
use PDO;

class Task extends Model{

    protected function getTable(): string {
        return 'tasks';
    }

    public function getById($id) {
        // Unico select com JOIN pois é apenas uma consulta
        $stmt = $this->conn->prepare(
            "SELECT t.id, t.title, t.description, t.status, t.created, t.modified, w.name as \"worker\", b.name as \"boss\" FROM tasks t
                LEFT JOIN workers w on w.id = t.worker_id
                LEFT JOIN bosses b on b.id = t.boss_id
             WHERE t.id = :id;"
            );
        $stmt->execute(['id' => $id]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
            
        if ($task === false) {
            throw new Exception("Task with id $id not found", 404);
        }

        return $task;

    }
}
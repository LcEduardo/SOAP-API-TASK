<?php

namespace App\Controller;

use App\Model\Task;
use Exception;
use PDOException;

class ControllerTask 
{
    private Task $task;

    public function __construct(Task $task) {
        $this->task = $task;
    }

    public function getTask($id) {
        try {
            $task = $this->task->getById($id);

        }catch(PDOException $e) {
            return [
                "status" => 500,
                "body" => ['error' => 'Database error'],
            ];
        }catch(Exception $e) {
            return [
                "status" => $e->getCode(),
                "body" => ['error' => $e->getMessage()],
            ];
        }

        return [
            "status" => 200,
            "body" => $task,
        ];
    }

    public function createTask($worker, $boss, $description, $title) {
        try {

            // title[]=a chega como array; trim() em array lança TypeError (não é Exception)
            if (($title !== null && !is_string($title)) || ($description !== null && !is_string($description))) {
                throw new Exception("The title and description must be strings", 400);
            }

            // title=" " vira "" depois do trim, então conta como não enviado
            $title = trim($title ?? '');

            if ($title === '') {
                throw new Exception("The title is required", 400);
            }

            $taskId = $this->task->create([
                'title'       => $title,
                'description' => $description,
                'status'      => 'pending',   //default
                'worker_id'   => $worker['id'] ?? null,
                'boss_id'     => $boss['id'],
            ]);

            $task = $this->task->getById($taskId);

        }catch(PDOException $e) {
            return [
                "status" => 500,
                "body" => ['error' => 'Database error'],
            ];
        }catch(Exception $e) {
            return [
                "status" => $e->getCode(),
                "body" => ['error' => $e->getMessage()],
            ];
        }

        return [
            "status" => 201,
            "body" => $task,
        ];
    }
}
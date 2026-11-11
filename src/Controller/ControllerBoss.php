<?php

namespace App\Controller;

use App\Model\Boss;
use Exception;
use PDOException;

class ControllerBoss
{
    private Boss $boss;

    public function __construct(Boss $boss) {
        $this->boss = $boss;
    }

    public function getBoss($id) {
        try {

            $id = filter_var($id, FILTER_VALIDATE_INT);

            if ($id === false) {
                 throw new Exception("The boss_id isn't interger", 400);
            }

            $boss = $this->boss->getById($id);

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
            "body" => $boss,
        ];
    }

    public function createBoss($name, $email) {
        try {

            // name[]=a chega como array; trim() em array lança TypeError (não é Exception)
            if (($name !== null && !is_string($name)) || ($email !== null && !is_string($email))) {
                throw new Exception("The name and email must be strings", 400);
            }

            $name = trim($name ?? '');
            // email é salvo em minúsculo para o UNIQUE não aceitar Ana@x.com e ana@x.com como diferentes
            $email = strtolower(trim($email ?? ''));

            if ($name === '') {
                throw new Exception("The name is required", 400);
            }

            // mb_strlen conta caracteres como o VARCHAR do Postgres ("João" = 4, strlen daria 5)
            if (mb_strlen($name) > 100) {
                throw new Exception("The name must have at most 100 characters", 400);
            }

            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw new Exception("The email isn't valid", 400);
            }

            if (mb_strlen($email) > 150) {
                throw new Exception("The email must have at most 150 characters", 400);
            }

            $bossId = $this->boss->create([
                'name'  => $name,
                'email' => $email,
            ]);

            $boss = $this->boss->getById($bossId);

        }catch(PDOException $e) {
            // 23505 = unique_violation (email já cadastrado)
            if ($e->getCode() === '23505') {
                return [
                    "status" => 409,
                    "body" => ['error' => 'Email already registered'],
                ];
            }

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
            "body" => $boss,
        ];
    }

}

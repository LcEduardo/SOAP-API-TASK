<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

class InitialSeeder extends AbstractSeed
{
    public function run(): void
    {
        $this->table('workers')->insert([
            ['name' => 'Ana Silva',   'email' => 'ana.silva@example.com'],
            ['name' => 'Bruno Costa', 'email' => 'bruno.costa@example.com'],
            ['name' => 'Carla Souza', 'email' => 'carla.souza@example.com'],
            ['name' => 'Diego Lima',  'email' => 'diego.lima@example.com'],
            ['name' => 'Elisa Rocha', 'email' => 'elisa.rocha@example.com'],
        ])->saveData();

        $this->table('bosses')->insert([
            ['name' => 'Fernando Alves',  'email' => 'fernando.alves@example.com'],
            ['name' => 'Gabriela Nunes',  'email' => 'gabriela.nunes@example.com'],
            ['name' => 'Henrique Dias',   'email' => 'henrique.dias@example.com'],
            ['name' => 'Isabela Martins', 'email' => 'isabela.martins@example.com'],
            ['name' => 'João Pereira',    'email' => 'joao.pereira@example.com'],
        ])->saveData();

        $this->table('tasks')->insert([
            ['title' => 'Create login page', 'description' => 'Build the login form with validation.',   'status' => 'pending',     'worker_id' => 1, 'boss_id' => 1],
            ['title' => 'Fix database bug',  'description' => 'Connection fails on the staging server.', 'status' => 'in_progress', 'worker_id' => 2, 'boss_id' => 1],
            ['title' => 'Write API docs',    'description' => 'Document all the API endpoints.',         'status' => 'pending',     'worker_id' => 3, 'boss_id' => 2],
            ['title' => 'Design dashboard',  'description' => 'Create the layout for the main screen.',  'status' => 'done',        'worker_id' => 4, 'boss_id' => 3],
            ['title' => 'Set up backups',    'description' => 'Schedule daily database backups.',        'status' => 'in_progress', 'worker_id' => 5, 'boss_id' => 4],
        ])->saveData();
    }
}

<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateInitialTables extends AbstractMigration
{
    public function change(): void
    {
        $this->table('workers')
            ->addColumn('name', 'string', ['limit' => 100])
            ->addColumn('email', 'string', ['limit' => 150])
            ->addColumn('created', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['email'], ['unique' => true])
            ->create();

        $this->table('bosses')
            ->addColumn('name', 'string', ['limit' => 100])
            ->addColumn('email', 'string', ['limit' => 150])
            ->addColumn('created', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['email'], ['unique' => true])
            ->create();

        $this->table('tasks')
            ->addColumn('title', 'string', ['limit' => 150])
            ->addColumn('description', 'text', ['null' => true])
            ->addColumn('status', 'string', ['limit' => 20, 'default' => 'pending'])
            ->addColumn('created', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('modified', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('worker_id', 'integer', ['null' => true])
            ->addColumn('boss_id', 'integer', ['null' => true])
            ->addForeignKey('worker_id', 'workers', 'id')
            ->addForeignKey('boss_id', 'bosses', 'id')
            ->create();
    }
}

<?php


use Phinx\Migration\AbstractMigration;

class CreateSoireesTable extends AbstractMigration
{
    /**
     * The dates of the soirées, planned in the admin (/admin/events) before their event, its theme and its sign-up:
     * App\Main\Calendar adds each one's event of the same day. They used to be in src/Main/config.php.
     * The same as SQL for phpMyAdmin: src/Events/db/create-soirees-table.sql
     */
    public function change()
    {
        $this->table('soirees')
            ->addColumn('date', 'date')
            ->addIndex('date', ['unique' => true])
            // The week of the quadrimestre ("S9"), if any
            ->addColumn('week', 'string', ['limit' => 10, 'null' => true])
            // Kept rather than removed, so that the calendars that have it show it cancelled
            ->addColumn('cancelled', 'boolean', ['default' => 0])
            ->create();

        if ($this->isMigratingUp()) {
            $this->table('soirees')->insert([
                ['date' => '2026-09-30', 'week' => 'S3'],
                ['date' => '2026-10-14', 'week' => 'S5'],
                ['date' => '2026-11-11', 'week' => 'S9'],
                ['date' => '2026-11-18', 'week' => 'S10'],
            ])->saveData();
        }
    }
}

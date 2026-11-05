<?php


use Phinx\Migration\AbstractMigration;

class CreateDancerMatchesTable extends AbstractMigration
{
    /**
     * Which names are one dancer in the statistics, chosen on /admin/statistics/names (App\Events\DancerNames).
     * The same as SQL for phpMyAdmin: src/Events/db/create-dancer-matches-table.sql
     */
    public function change()
    {
        // The keys are compared byte for byte, and can be as long as a first and a last name together: no index, the
        // page reads the whole table, which holds a few hundred rows at most
        $key = ['limit' => 520, 'encoding' => 'utf8mb4', 'collation' => 'utf8mb4_bin'];
        $this->table('dancer_matches')
            ->addColumn('name_key', 'string', $key)
            ->addColumn('match_key', 'string', $key)
            // 1: name_key is another spelling of match_key. 0: they are two different people
            ->addColumn('same', 'boolean')
            ->create();
    }
}

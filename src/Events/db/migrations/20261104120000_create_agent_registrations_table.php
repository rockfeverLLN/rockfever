<?php


use Phinx\Migration\AbstractMigration;

class CreateAgentRegistrationsTable extends AbstractMigration
{
    /**
     * The sign-ups made through an AI assistant (/mcp): the log of what was done, and what lets the person who holds a
     * registration's reference see or cancel it. The same as SQL for phpMyAdmin: src/Events/db/create-agent-registrations-table.sql
     */
    public function change()
    {
        $this->table('agent_registrations')
            // Secret: whoever has it can cancel this sign-up. Unique, so sending a confirmation twice signs up once
            ->addColumn('reference', 'char', ['limit' => 32])
            ->addIndex('reference', ['unique' => true])
            ->addColumn('event_id', 'integer')
            ->addForeignKey('event_id', 'events', ['id'], ['delete' => 'CASCADE'])
            // Whether this sign-up put the name on the list. If it was already there, it isn't this sign-up's to cancel
            ->addColumn('added', 'boolean', ['default' => 0])
            // The dancer it added: empty once the team removes them
            ->addColumn('dancer_id', 'integer', ['null' => true])
            ->addForeignKey('dancer_id', 'dancers', ['id'], ['delete' => 'SET_NULL'])
            ->addColumn('firstname', 'string')
            ->addColumn('lastname', 'string')
            // The User-Agent of the assistant
            ->addColumn('client', 'string', ['null' => true])
            ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('cancelled_at', 'datetime', ['null' => true])
            ->create();
    }
}

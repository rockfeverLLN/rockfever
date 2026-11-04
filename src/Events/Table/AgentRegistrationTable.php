<?php
namespace App\Events\Table;

use Framework\Database\NoRecordException;
use Framework\Database\Table;

/**
 * The sign-ups made through an AI assistant (App\Main\McpTools)
 */
class AgentRegistrationTable extends Table
{
    protected $table = 'agent_registrations';

    public function findByReference(string $reference) : ?object
    {
        try {
            return $this->findBy('reference', $reference);
        } catch (NoRecordException $e) {
            return null;
        }
    }
}

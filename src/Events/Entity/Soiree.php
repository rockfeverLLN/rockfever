<?php
namespace App\Events\Entity;

/**
 * The date of a soirée, planned before its event (theme, description, sign-up) is created in the admin
 */
class Soiree
{
    public $id;

    // Y-m-d
    public $date;

    // The week of the quadrimestre ("S9"), or null
    public $week;

    public $cancelled;
}

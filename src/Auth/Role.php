<?php
/**.
 * User: Nicolas van de Walle
 * Date: 25-Feb-18
 * Time: 13:24
 */

namespace App\Auth;

class Role implements \Framework\Auth\Role
{
    public $id;

    public $label;

    public $role;

    public $password;


    /**
     * @return string
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * @return string
     */
    public function getRole(): string
    {
        return $this->role;
    }
}

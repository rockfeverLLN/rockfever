<?php
/**.
 * User: Nicolas van de Walle
 * Date: 25-Feb-18
 * Time: 13:26
 */

namespace App\Auth;

use App\Auth\Table\RoleTable;
use Framework\Auth;
use Framework\Auth\Role;
use Framework\Database\NoRecordException;
use Framework\Session\SessionInterface;

class DatabaseAuth implements Auth
{

    /**
     * @var RoleTable
     */
    private $roleTable;
    /**
     * @var SessionInterface
     */
    private $session;

    /**
     * @var Role|null
     */
    private $user;

    public function __construct(RoleTable $roleTable, SessionInterface $session)
    {
        $this->roleTable = $roleTable;
        $this->session = $session;
    }

    /**
     * @param string $role
     * @param string $password
     * @return Role|null
     */
    public function login(string $role, string $password) : ?Role
    {
        if (empty($role) || empty($password)) {
            return null;
        }

        // An unknown role is a failed login like a wrong password, not an error page (and it counts in the throttle)
        try {
            /** @var \App\Auth\Role $user */
            $user = $this->roleTable->findBy('role', $role);
        } catch (NoRecordException $e) {
            return null;
        }
        if (password_verify($password, $user->password)) {
            $this->session->regenerate();
            $this->session->set('auth.user', $user->id);
            return $user;
        }
        return null;
    }

    public function logout() : void
    {
        $this->session->delete('auth.user');
    }

    /**
     * @return Role|null
     */
    public function getUser(): ?Role
    {
        if ($this->user) {
            return $this->user;
        }

        $roleId = $this->session->get('auth.user');
        if ($roleId) {
            try {
                $this->user =  $this->roleTable->find($roleId);
                return $this->user;
            } catch (NoRecordException $exception) {
                $this->session->delete('auth.user');
                return null;
            }
        }
        return null;
    }
}

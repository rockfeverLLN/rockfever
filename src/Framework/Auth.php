<?php

namespace Framework;

use Framework\Auth\Role;

interface Auth
{

    /**
     * @return Role|null
     */
    public function getUser() : ?Role;
}

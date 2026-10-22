<?php
namespace Framework\Auth;

interface Role
{

    /**
     * @return string
     */
    public function getLabel() : string;

    /**
     * @return string
     */
    public function getRole() : string;
}

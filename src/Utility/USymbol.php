<?php

namespace Deployer\Utility;

class USymbol
{
    public static function create($name)
    {
        return new self($name);
    }

    private $name;
    private $magic;
    private function __construct($name)
    {
        $this->name = $name;
        $this->magic = openssl_random_pseudo_bytes(64);
    }

    private function __clone()
    {
    }

    public function __toString()
    {
        return $this->name;
    }
}

<?php

namespace Tests;

class FilterClass
{
    protected $dependency;

    public function __construct(Dependency $dependency)
    {
        $this->dependency = $dependency;
    }

    public function filter($value)
    {
        return $this->dependency instanceof Dependency;
    }
}

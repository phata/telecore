<?php

namespace myTest\Dummy;

/**
 * A dummy variable wrapper for test.
 */
class DummyVar
{
    private $value;

    public function __construct($value)
    {
        $this->value = $value;
    }

    public function get()
    {
        return $this->value;
    }
}

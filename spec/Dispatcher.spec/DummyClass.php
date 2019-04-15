<?php

namespace myTest\Dummy;

class DummyClass
{
    public static function method($foo, $bar, DummyVar $dummy)
    {
        $vars['foo'] = $foo;
        $vars['bar'] = $bar;
        $vars['dummy'] = $dummy;
    }
}

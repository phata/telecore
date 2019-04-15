<?php

namespace myTest\Dummy;

use \Psr\Container\ContainerInterface;
use \Psr\Container\NotFoundExceptionInterface;

/**
 * A dummy PSR-11 ContainerInterface implementation.
 */
class Container implements ContainerInterface
{

    private $definitions;

    public function __construct(?array $definitions = null)
    {
        $this->definitions = $definitions ?? [];
    }

    public function set($id, $value)
    {
        $this->definitions[$id] = $value;
    }

    public function has($id)
    {
        return @isset($this->definitions[$id]);
    }

    public function get($id)
    {
        if (!$this->has($id)) {
            throw new class("{$id} not found in container") extends \Exception implements NotFoundExceptionInterface {
                // no body
            };
        }
        return $this->definitions[$id];
    }
}

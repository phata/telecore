<?php

namespace Phata\TeleCore\Session;

use \Predis\Client;

/**
 * Class for bot session with specific
 * chat / user combinition.
 *
 * @category Class
 * @package  Phata\TeleCore
 */
class RedisSession implements SessionInterface
{

    private $_client;
    private $_namespace;

    /**
     * Class constructor.
     *
     * @param Client $client  Predis client.
     * @param string $namespace The namespace for the storage keys.
     */
    public function __construct(Client $client, string $namespace)
    {
        $this->_client = $client;
        $this->_namespace = $namespace;
    }

    /**
     * Get the storage namespace for the specific session.
     *
     * @return string
     */
    public function getNamespace(): string
    {
        return $this->_namespace;
    }

    /**
     * {@inheritDoc}
     */
    public function set(string $key, $value, ?int $expires = null)
    {
        // TODO: need to lock (with redis setnx)
        $expires = $expires ?? strtotime('+1 week');
        $response = $this->_client
            ->transaction()
            ->set($this->getNamespace() . $key, serialize($value))
            ->expireat($this->getNamespace() . $key, $expires)
            ->get($this->getNamespace() . $key)
            ->execute();
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $key, $defaultValue = null)
    {
        // TODO: check if this is the proper way to handle empty
        // TODO: need to lock (with redis setnx)
        $response = $this->_client
            ->get($this->getNamespace() . $key);
        return !empty($response) ? unserialize($response) : $defaultValue;
    }
}

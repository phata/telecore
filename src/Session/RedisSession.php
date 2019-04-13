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
class RedisSession implements Session
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
     * Undocumented function
     *
     * @param Client   $client  Predis client.
     * @param object   $message Message object.
     * @param callable $hasher  A callable that takes string and hash it.
     *
     * @return Session
     */
    public static function fromMessage(
        Client $client,
        $message,
        ?callable $hasher = null
    ): Session {
        $chatID = \str_replace('/', '_', \password_hash($message->chat->id, PASSWORD_DEFAULT));
        $userID = \str_replace('/', '_', \password_hash($message->from->id, PASSWORD_DEFAULT));
        $namespace = "session://chat-{$chatID}/user-{$userID}/";
        return new RedisSession($client, $namespace);
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
     * Set a key value pair in the session
     *
     * @param string   $key     Storage key.
     * @param mixed    $value   Value to be stored.
     * @param int|null $expires Unix timestamp for expiration.
     *                          Set to null will fallback to
     *                          implementation default.
     *                          Default null.
     */
    public function set(string $key, $value, ?int $expires = null)
    {
        $expires = $expires ?? strtotime('+1 week');

        // TODO: implement _client
        $response = $this->_client
            ->transaction()
            ->set($this->getNamespace() . $key, serialize($value))
            ->expireat($this->getNamespace() . $key, $expires)
            ->get($this->getNamespace() . $key)
            ->execute();
    }

    /**
     * Get a value from the session
     *
     * @param string $key          Storage key
     * @param mixed  $defaultValue Value to use if not set.
     *                             Default null.
     *
     * @return mixed Value stored.
     */
    public function get(string $key, $defaultValue = null)
    {
        // TODO: implement _client
        // TODO: check if this is the proper way to handle empty
        $response = $this->_client
            ->get($this->getNamespace() . $key);
        return !empty($response) ? unserialize($response) : $defaultValue;
    }
}

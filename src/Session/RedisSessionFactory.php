<?php

namespace Phata\TeleCore\Session;

use \Predis\Client as RedisClient;

/**
 * Class for bot to produce session.
 *
 * @category Class
 * @package  Phata\TeleCore
 */
class RedisSessionFactory implements Factory
{

    private $_client;

    /**
     * Class constructor
     *
     * @param Predis\Client $client Redis client.
     */
    public function __construct(RedisClient $client)
    {
        $this->_client = $client;
    }

    /**
     * Create session from given message.
     * Session produced will be specific to the
     * chat-user combinition.
     *
     * @param object $message Message to produce session with.
     *
     * @return Session
     */
    public function fromMessage($message): Session
    {
        return RedisSession::fromMessage(
            $this->_client,
            $message
        );
    }
}

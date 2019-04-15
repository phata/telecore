<?php

namespace Phata\TeleCore\Session;

use \Predis\Client as RedisClient;

/**
 * Class for bot to produce session.
 *
 * @category Class
 * @package  Phata\TeleCore
 */
class RedisSessionFactory implements FactoryInterface
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
     * {@inheritDoc}
     */
    public function getChatSession($chat, ?callable $hasher = null): SessionInterface
    {
        $hasher = $hasher ?? 'sha1';
        $chatID = \str_replace('/', '_', $chat->id);
        $namespace = "session://chat-{$chatID}/";
        return new RedisSession($this->_client, $namespace);
    }

    /**
     * {@inheritDoc}
     */
    public function getChatUserSession($chat, $user, ?callable $hasher = null): SessionInterface
    {
        $hasher = $hasher ?? 'sha1';
        $chatID = \str_replace('/', '_', $hasher($chat->id));
        $userID = \str_replace('/', '_', $hasher($user->id));
        $namespace = "session://chat-{$chatID}/user-{$userID}/";
        return new RedisSession($this->_client, $namespace);
    }
}

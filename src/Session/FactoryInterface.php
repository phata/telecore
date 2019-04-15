<?php

namespace Phata\TeleCore\Session;

/**
 * Class for bot to produce session.
 *
 * @category Interface
 * @package  Phata\TeleCore
 */
interface FactoryInterface
{
    /**
     * Get session access of given chat scope.
     *
     * @param object        $chat
     *     Chat object to produce session with.
     * @param callable|null $hasher
     *     Hasher callback to hash a string with.
     *
     * @return SessionInterface
     */
    public function getChatSession($chat, ?callable $hasher = null): SessionInterface;

    /**
     * Get session access of given chat-user scope.
     *
     * @param object $chat
     *     Chat object to produce session with.
     * @param object $user
     *     User object to produce session with in the interaction.
     * @param callable|null $hasher
     *     Hasher callback to hash a string with.
     *
     * @return SessionInterface
     */
    public function getChatUserSession($chat, $user, ?callable $hasher = null): SessionInterface;
}

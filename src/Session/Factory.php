<?php

namespace Phata\TeleCore\Session;

/**
 * Class for bot to produce session.
 *
 * @category Interface
 * @package  Phata\TeleCore
 */
interface Factory
{
    /**
     * Create session from given message.
     * Session produced will be specific to the
     * chat-user combinition.
     *
     * @param object $message Message to produce session with.
     *
     * @return Session
     */
    public function fromMessage($message): Session;
}

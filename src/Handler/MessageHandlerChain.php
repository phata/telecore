<?php

namespace Phata\TeleCore\Handler;

/**
 * A class to chain multiple message handlers to implement
 * message handler.
 */
class MessageHandlerChain implements MessageHandlerInterface
{
    private $handlers = [];

    /**
     * Class constructor
     *
     * @param MessageHandlerInterface[] ...$handlers
     *     Message handler instances to be in the chain.
     */
    public function __construct(MessageHandlerInterface ...$handlers)
    {
        foreach ($handlers as $handler) {
            $this->addHandler($handler);
        }
    }

    /**
     * Add message handler to the chain.
     *
     * @param MessageHandlerInterface $handler
     *     Message handler.
     *
     * @return void
     */
    public function addHandler(MessageHandlerInterface $handler)
    {
        $this->handlers[] = $handler;
    }

    /**
     * {@inheritDoc}
     */
    public function handleMessage($request)
    {
        foreach ($this->handlers as $handler) {
            if ($handler($request) !== false) {
                return true;
            }
        }
        return false;
    }
}

<?php

namespace Phata\TeleCore\Handler;

interface MessageHandlerInterface
{
    /**
     * Handle message requests.
     *
     * @param object $request
     *     Request objeect.
     *
     * @return bool
     *     True if a handler is found. False if not.
     */
    public function handleMessage($request): bool;
}

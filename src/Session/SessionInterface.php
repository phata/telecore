<?php

namespace Phata\TeleCore\Session;

/**
 * Class for bot session of a specific scope.
 *
 * @category Interface
 * @package  Phata\TeleCore
 */
interface SessionInterface
{
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
    public function set(string $key, $value, ?int $expires = null);

    /**
     * Get a value from the session
     *
     * @param string $key          Storage key
     * @param mixed  $defaultValue Value to use if not set.
     *                             Default null.
     *
     * @return mixed Value stored.
     */
    public function get(string $key, $defaultValue = null);
}

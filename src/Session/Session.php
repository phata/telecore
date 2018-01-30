<?php

namespace Phata\TeleCore\Session;

/**
 * Class for bot session with specific
 * chat / user combinition.
 *
 * @category Interface
 * @package  Phata\TeleCore
 */
interface Session
{
    /**
     * Set a key value pair in the namespace
     *
     * @param string $key     Storage key.
     * @param mixed  $value   Value to be stored.
     * @param int    $expires Unix timestamp for expiration.
     *                        Set to null will fallback to
     *                        implementation default.
     *                        Default null.
     */
    public function set(string $key, $value, ?int $expires=null);

    /**
     * Get a value from the namespace
     *
     * @param string $key          Storage key
     * @param mixed  $defaultValue Value to use if not set.
     *                             Default null.
     *
     * @return mixed Value stored.
     */
    public function get(string $key, $defaultValue=null);
}

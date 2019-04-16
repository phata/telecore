<?php

namespace Phata\TeleCore;

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use TelegramBot\Api\BotApi;
use Phata\TeleCore\Session\FactoryInterface as SessionFactoryInterface;
use \Exception;
use \ReflectionClass;
use \ReflectionFunction;
use \ReflectionParameter;

class Dispatcher
{
    private $_cmds = [];
    private $_handlers = [];
    private $_container = null;
    private $_sessionFactory = null;
    private $_logger = null;

    /**
     * Constructor function.
     *
     * @param ContainerInterface $container
     *     Container for dependency injection.
     * @param LoggerInterface $logger
     *     Logger for logging dispatch.
     * @param Phata\TeleCore\Session\FactoryInterface $sessionFactory
     *     Session factory for accessing session.
     */
    public function __construct(
        ContainerInterface $container,
        LoggerInterface $logger,
        SessionFactoryInterface $sessionFactory
    ) {
        $this->_container = $container;
        $this->_logger = $logger;
        $this->_sessionFactory = $sessionFactory;
    }

    /**
     * Reflect dependencies of a callable.
     *
     * Build a parameter array specific to the given callable.
     *
     * @param ContainerInterface $container
     *     Container to get parameters from.
     * @param callable $callable
     *     Callable to be called with the parameters.
     * @param array $overrides
     *     Key-value pairs for override variables.
     *
     * @return array An array of mixed type parameters.
     */
    public static function reflectDependencies(
        ContainerInterface $container,
        callable $callable,
        array $overrides = []
    ): array {

        // reflect the callable function or method for arguments
        $ref = !is_array($callable)
            ? new ReflectionFunction($callable)
            : (new ReflectionClass($callable[0]))->getMethod($callable[1]);

        // map the dependencies
        return array_map(function (ReflectionParameter $paramDef) use ($container, $overrides) {
            $name = $paramDef->getName();
            $type = $paramDef->getType();
            $typeStr = ($type !== null) ? $type->getName() : null;

            // return type overrides, if match.
            if (isset($overrides[$typeStr])) {
                return $overrides[$typeStr];
            }

            // return name overrides, if match.
            if (isset($overrides[$name])) {
                return $overrides[$name];
            }

            // has type, try getting variable by type
            if ($typeStr !== null) {
                return $container->get($typeStr);
            }

            // no type, or the type not found, find by name
            return $container->has($name)
                ? $container->get($name)
                : null;
        }, $ref->getParameters());
    }

    /**
     * Add an update handler to the bot.
     *
     * @param string $type The type of update. Possible values:
     * message, edited_message, channel_post, edited_channel_post,
     * inline_query, chosen_inline_result, callback_query,
     * shipping_query, pre_checkout_query.

     * @param callable $handler Handler function for the command.
     *                 a function signature of:
     *                 function (object $request)
     *
     * @return array Array of argument to be dispatched with.
     * @throw Exception
     */
    public function addHandler(string $type, callable $handler): void
    {
        $valid_types = [
            "message",
            "edited_message",
            "channel_post",
            "edited_channel_post",
            "inline_query",
            "chosen_inline_result",
            "callback_query",
            "shipping_query",
            "pre_checkout_query",
        ];
        if (!in_array($type, $valid_types)) {
            throw new \Exception(sprintf('unknown update type "%s"', $type));
            return;
        }
        if (isset($this->_handlers[$type])) {
            throw new \Exception(sprintf('handler for type "%s" already exists.', $type));
            return;
        }
        $this->_handlers[$type] = $handler;
    }

    /**
     * List the key to type of handler registered.
     *
     * @return array
     *     Array of type strings of update types that has
     *     a handler registered.
     */
    public function listHandlers(): array
    {
        return array_keys($this->_handlers);
    }

    /**
     * Dispatches the given stream to the given routes.
     *
     * @param object $request Decoded json object of request.
     *
     * @return array Array of argument to be dispatched with.
     */
    public function dispatch($request): ?array
    {

        // TODO: use middleware for logging (only for debug).

        // route different type of updates:
        //
        // * Message message
        // * Message edited_message
        // * Message channel_post
        // * Message edited_channel_post
        // * InlineQuery inline_query
        // * ChosenInlineResult chosen_inline_result
        // * CallbackQuery callback_query
        // * ShippingQuery shipping_query
        // * PreCheckoutQuery pre_checkout_query
        //
        foreach ($this->_handlers as $type => $handlers) {
            if (isset($request->$type)) {
                $session = null;

                switch ($type) {
                    case 'message':
                        $message = $request->message;
                        $this->_logger->info("message: getting sessionFactory: " . var_export(isset($this->_sessionFactory), true));
                        $this->_logger->info("message: getting message: " . json_encode($message));
                        $session = $this->_sessionFactory->getChatUserSession($message->chat, $message->from);
                        break;
                    case 'callback_query':
                        $message = $request->$type->message;
                        $this->_logger->info("callback_query: getting message: " . json_encode($message));
                        $session = $this->_sessionFactory->getChatUserSession($message->chat, $message->from);
                        break;
                }

                // set container request $type and $session.
                $this->_container->set('type', $type);
                $this->_container->set(Session::class, $session);

                // reflect the dependencies of the handler.
                $params = static::reflectDependencies(
                    $this->_container,
                    $this->_handlers[$type],
                    ['request' => $request]
                );
                return [
                    $this->_handlers[$type],
                    $params,
                ];
            }
        }
        return null;
    }
}

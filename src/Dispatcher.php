<?php

namespace Phata\TeleCore;

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use TelegramBot\Api\BotApi;
use Phata\TeleCore\Session\Factory as SessionFactory;
use Phata\TeleCore\Session\Session;
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
     * @param SessionFactory $sessionFactory
     *     Session factory for accessing session.
     */
    public function __construct(
        ContainerInterface $container,
        LoggerInterface $logger,
        SessionFactory $sessionFactory
    ) {
        $this->_handlers = [
            'message' => [$this, 'handleCommandMessage'],
        ];
        $this->_container = $container;
        $this->_logger = $logger;
        $this->_sessionFactory = $sessionFactory;
    }

    /**
     * Add a handler specific for certain command in the "message" type
     * update.
     *
     * @param string $commandStr The command, with or without slash prefix,
     *                        for routing.
     * @param callable $handler Handler function for the command. With
     *                 a function signature of:
     *                 function (object $command, object $request)
     */
    public function addCommand(string $commandStr, callable $handler): void
    {
        $commandStr = '/' . ltrim($commandStr, '/');
        if (isset($this->_cmds[$commandStr])) {
            throw new \Exception(sprintf('handler for command "%s" already exists.', $commandStr));
            return;
        }
        $this->_cmds[$commandStr] = $handler;
    }

    /**
     * Get message command
     *
     * @param object $message The message object.
     *
     * @return ?array Array of command entities, or null if there is none.
     */
    public static function getMessageCommand($message): ?array
    {
        if (!isset($message->entities) || !is_array($message->entities)) {
            return null;
        }
        return array_reduce(
            $message->entities,
            function ($carry, $item) {
                if ($item->type === 'bot_command' && ($item->offset == 0)) {
                    if ($carry === null) {
                        $carry = [];
                    }
                    $carry[] = $item;
                }
                return $carry;
            },
            null
        );
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
     *
     * @return array An array of mixed type parameters.
     */
    public static function reflectDependencies(ContainerInterface $container, callable $callable): array
    {
        // reflect the callable function or method for arguments
        $ref = !is_array($callable)
            ? new ReflectionFunction($callable)
            : (new ReflectionClass($callable[0]))->getMethod($callable[1]);

        // map the dependencies
        return array_map(function (ReflectionParameter $paramDef) use ($container) {
            $name = $paramDef->getName();
            $type = $paramDef->getType();

            // has type, try getting variable by type
            if ($type !== null && $container->has($type->getName())) {
                return $container->get($type->getName());
            }

            // no type, or the type not found, find by name
            return $container->has($name)
                ? $container->get($name)
                : null;
        }, $ref->getParameters());
    }

    /**
     * Dispatch commands stored in this dispatcher.
     * Meant to be used by handleCommandMessage.
     *
     * @param object $request
     *
     * @return array The command handler and the command information
     */
    public function dispatchCommand($request): array
    {
        // parse command
        $commands = static::getMessageCommand($request->message);
        if ($commands === null) {
            // do not handle message without command
            return [null, null];
        }

        // if there is a command in the message
        $commandStr = substr(
            $request->message->text,
            $commands[0]->offset,
            $commands[0]->length
        );

        // fill command and request to container, if exists
        if ($this->_container === null) {
            // should throw new exception
            throw new Exception("Container not found.");
        }
        $session = $this->_sessionFactory->fromMessage($request->message);

        // Fill the container with variables about the
        // request.
        //
        // TODO: might separate this into some sort of
        // "container driver" for not all container has
        // a `set` method.
        $container = $this->_container;
        $container->set('command', $commands[0]);

        // if command handler found for the given command string,
        // dispatch the handler.
        if (isset($this->_cmds[$commandStr])) {
            $params = static::reflectDependencies(
                $container,
                $this->_cmds[$commandStr]
            );
            $this->_logger->debug("command found");
            return [
                $this->_cmds[$commandStr],
                $params,
            ];
        }

        // no command handler found for the given command string.
        $this->_logger->debug('command handler not found: ' . $commandStr);
        throw new Exception('command handler not found: ' . $commandStr);
        return [null, []]; // return an array for list to extract anyway
    }

    /**
     * Handle message commands.
     *
     * @param string $type Type of update, expecting "message".
     * @param object $request Request objeect.
     */
    public function handleCommandMessage(string $type, $request): void
    {
        list($commandHandler, $params) = $this->dispatchCommand($request);
        if ($commandHandler === null) {
            // do nothing
            return;
        }
        $commandHandler(...$params);
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

    public function listHandlers()
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

        // set request to container
        $this->_container->set('request', $request);

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
                        $this->_logger->info("message: getting sessionFactory: " . var_export(isset($this->_sessionFactory), true));
                        $this->_logger->info("message: getting message: " . json_encode($request->$type));
                        $session = $this->_sessionFactory->fromMessage($request->$type);
                        break;
                    case 'callback_query':
                        $this->_logger->info("callback_query: getting message: " . json_encode($request->$type->message));
                        $session = $this->_sessionFactory->fromMessage($request->$type->message);
                        break;
                }

                // set container request $type and $session.
                $this->_container->set('type', $type);
                $this->_container->set(Session::class, $session);

                // reflect the dependencies of the handler.
                $params = static::reflectDependencies(
                    $this->_container,
                    $this->_handlers[$type]
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

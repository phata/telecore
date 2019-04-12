<?php

namespace Phata\TeleCore;

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use TelegramBot\Api\BotApi;
use Phata\TeleCore\Session\Factory as SessionFactory;
use \Exception;
use \ReflectionFunction;
use \ReflectionParameter;

class Dispatcher
{
    private $_cmds = [];
    private $_handlers = [];
    private $_container = null;
    private $_sessionFactory = null;
    private $_logger = null;

    public function __construct()
    {
        $this->_handlers = [
            'message' => [$this, 'handleCommandMessage'],
        ];
    }

    /**
     * Set the session factory to dispatcher.
     *
     * @param SessionFactory $sessionFactory
     * @return void
     */
    public function setSessionFactory(SessionFactory $sessionFactory)
    {
        $this->_sessionFactory = $sessionFactory;
    }

    /**
     * Set the session factory to dispatcher.
     *
     * @param LoggerInterface $sessionFactory
     *
     * @return void
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->_logger = $logger;
    }

    public function setContainer(ContainerInterface $container)
    {
        $this->_container = $container;
    }

    /**
     * Add a handler specific for certain command in the "message" type
     * update.
     *
     * @param string $command The command, with or without slash prefix,
     *                        for routing.
     * @param callable $handler Handler function for the command. With
     *                 a function signature of:
     *                 function (object $command, object $request)
     */
    public function addCommand(string $command, callable $handler): void
    {
        $command = '/' . ltrim($command, '/');
        if (isset($this->_cmds[$command])) {
            throw new \Exception(sprintf('handler for command "%s" already exists.', $command));
            return;
        }
        $this->_cmds[$command] = $handler;
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
                    if ($carry === null) $carry = [];
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
        // Note: assume it is a function first.
        // will array type callables (i.e. instance
        // method, static method) later.
        $ref = new ReflectionFunction($callable);
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
        $command = substr(
            $request->message->text,
            $commands[0]->offset,
            $commands[0]->length
        );
        if (isset($this->_cmds[$command])) {
            return [
                $this->_cmds[$command],
                [
                    $commands[0],
                    $request,
                ],
            ];
        }
    }

    /**
     * Handle message commands.
     *
     * @param string $type Type of update, expecting "message".
     * @param object $request Request objeect.
     */
    public function handleCommandMessage(string $type, $request): void
    {
        list($commandHandler, $args) = $this->dispatchCommand($request);
        if ($commandHandler === null){
            // do nothing
            return;
        }
        $commandHandler(...$args);
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

    public function listHandlers() {
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
                switch ($type) {
                case 'message':
                    $this->_logger->info("message: getting sessionFactory: " . var_export(isset($this->_sessionFactory), true));
                    $this->_logger->info("message: getting message: " . json_encode($request->$type));
                    $request->session = $this->_sessionFactory->fromMessage($request->$type);
                    break;
                case 'callback_query':
                    $this->_logger->info("callback_query: getting message: " . json_encode($request->$type->message));
                    $request->session = $this->_sessionFactory->fromMessage($request->$type->message);
                    break;
                }
                return [
                    $this->_handlers[$type],
                    [$type, $request],
                ];
            }
        }
        return null;
    }
}
